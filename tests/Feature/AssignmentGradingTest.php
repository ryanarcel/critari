<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\CriterionScore;
use App\Models\Demo;
use App\Models\Submission;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class AssignmentGradingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->initializeTenant();
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Midterm Essay',
            'question' => 'Explain the causes of the Trojan War.',
            'levels' => [
                ['name' => 'Needs Imp.', 'range' => '0-4'],
                ['name' => 'Excellent', 'range' => '9-10'],
            ],
            'criteria' => [
                [
                    'name' => 'Idea explanation',
                    'cells' => ['Little explanation', 'Thorough explanation'],
                ],
            ],
        ], $overrides);
    }

    private function createAssignmentFor(User $user, array $overrides = []): int
    {
        $response = $this->actingAs($user)
            ->postJson($this->tenantUrl('/assignments'), $this->assignmentPayload($overrides))
            ->assertCreated();

        return (int) $response->json('data.assignment_id');
    }

    /**
     * @param  array<int, array{criterion_name?: string, score: int, feedback?: string, overall_feedback?: string}>  $papers
     */
    private function fakeAssessmentResponses(array $papers): void
    {
        OpenAI::fake(array_map(fn (array $paper) => CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'scores' => [
                                [
                                    'criterion_name' => $paper['criterion_name'] ?? 'Idea explanation',
                                    'score' => $paper['score'],
                                    'feedback' => $paper['feedback'] ?? 'Clear argument.',
                                ],
                            ],
                            'overall_feedback' => $paper['overall_feedback'] ?? 'Strong paper.',
                        ]),
                    ],
                ],
            ],
        ]), $papers));
    }

    private function fakeAssessmentResponse(string $criterionName = 'Idea explanation'): void
    {
        $this->fakeAssessmentResponses([
            [
                'criterion_name' => $criterionName,
                'score' => 8,
                'feedback' => 'Clear argument.',
                'overall_feedback' => 'Strong paper.',
            ],
        ]);
    }

    /**
     * @param  string|list<string>  $responses
     */
    private function joinAndSubmit(User $student, int $assignmentId, string|array $responses): int
    {
        $tenant = $this->initializeTenant();
        $code = null;
        $answers = [];

        $tenant->run(function () use ($assignmentId, $responses, &$code, &$answers) {
            $assignment = Assignment::query()->with('questions')->find($assignmentId);
            $code = $assignment?->join_code;
            $responseList = array_values(is_array($responses) ? $responses : [$responses]);

            $answers = $assignment?->questions->values()->map(function ($question, int $index) use ($responseList) {
                return [
                    'question_id' => $question->id,
                    'response' => $responseList[$index] ?? $responseList[0],
                ];
            })->all() ?? [];
        });

        $this->actingAs($student)
            ->from($this->tenantUrl('/student'))
            ->post($this->tenantUrl('/student/join'), ['code' => $code])
            ->assertRedirect($this->tenantUrl('/student/assignments/'.$assignmentId));

        $this->actingAs($student)
            ->post($this->tenantUrl('/student/assignments/'.$assignmentId), [
                'answers' => $answers,
            ])
            ->assertRedirect($this->tenantUrl('/student/assignments/'.$assignmentId));

        return (int) $tenant->run(fn () => Submission::query()
            ->where('assignment_id', $assignmentId)
            ->where('user_id', $student->id)
            ->value('id'));
    }

    public function test_guests_cannot_view_an_assignment(): void
    {
        $user = User::factory()->create();
        $assignmentId = $this->createAssignmentFor($user);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get($this->tenantUrl('/assignments/'.$assignmentId))
            ->assertRedirect(route('login'));
    }

    public function test_students_cannot_view_a_teacher_assignment(): void
    {
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create();
        $assignmentId = $this->createAssignmentFor($teacher);

        $this->actingAs($student)
            ->get($this->tenantUrl('/assignments/'.$assignmentId))
            ->assertNotFound();
    }

    public function test_other_teachers_cannot_view_an_assignment(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assignmentId = $this->createAssignmentFor($owner);

        $this->actingAs($other)
            ->get($this->tenantUrl('/assignments/'.$assignmentId))
            ->assertNotFound();
    }

    public function test_owners_can_view_the_assignment_page(): void
    {
        $user = User::factory()->create();
        $assignmentId = $this->createAssignmentFor($user, [
            'title' => 'Grading Essay',
            'question' => null,
            'questions' => [
                ['prompt' => 'Explain the causes of the Trojan War.'],
                ['prompt' => "What does Hector's death reveal about honor?"],
            ],
        ]);

        $this->actingAs($user)
            ->get($this->tenantUrl('/assignments/'.$assignmentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assignments/Show')
                ->where('assignment.id', $assignmentId)
                ->where('assignment.title', 'Grading Essay')
                ->has('assignment.questions', 2)
                ->where('assignment.questions.0.prompt', 'Explain the causes of the Trojan War.')
                ->has('assignment.criteria', 1)
                ->where('assignment.criteria.0.name', 'Idea explanation')
                ->has('assignment.join_code')
                ->where('assignment.max_score', 10)
                ->where('assignment.overall_max_score', 20)
                ->has('assignment.submissions', 0)
            );
    }

    public function test_teachers_cannot_paste_papers_onto_their_assignment(): void
    {
        $user = User::factory()->create();
        $assignmentId = $this->createAssignmentFor($user);

        $this->actingAs($user)
            ->postJson($this->tenantUrl('/submissions'), [
                'assignment_id' => $assignmentId,
                'student_name' => 'Jordan Lee',
                'student_response' => 'The war began with the abduction of Helen.',
            ])
            ->assertForbidden();
    }

    public function test_student_submissions_appear_on_the_teacher_page(): void
    {
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create(['name' => 'Jordan Lee']);
        $assignmentId = $this->createAssignmentFor($teacher);

        $this->joinAndSubmit(
            $student,
            $assignmentId,
            'The war began with the abduction of Helen.'
        );

        $this->actingAs($teacher)
            ->get($this->tenantUrl('/assignments/'.$assignmentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assignments/Show')
                ->has('assignment.submissions', 1)
                ->where('assignment.submissions.0.student_name', 'Jordan Lee')
                ->where('assignment.submissions.0.status', 'pending')
            );
    }

    public function test_teacher_page_hides_papers_without_a_student(): void
    {
        $tenant = $this->initializeTenant();
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create(['name' => 'Jordan Lee']);
        $assignmentId = $this->createAssignmentFor($teacher);

        $this->joinAndSubmit(
            $student,
            $assignmentId,
            'The war began with the abduction of Helen.'
        );

        $tenant->run(function () use ($assignmentId) {
            Submission::query()->create([
                'assignment_id' => $assignmentId,
                'user_id' => null,
                'payload' => [
                    'student_name' => 'Pasted paper',
                    'student_response' => 'An old teacher-pasted answer.',
                ],
                'status' => 'graded',
                'score' => 22,
                'submitted_at' => now(),
                'graded_at' => now(),
            ]);
        });

        $this->actingAs($teacher)
            ->get($this->tenantUrl('/assignments/'.$assignmentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assignments/Show')
                ->has('assignment.submissions', 1)
                ->where('assignment.submissions.0.student_name', 'Jordan Lee')
            );
    }

    public function test_other_teachers_cannot_add_a_paper(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assignmentId = $this->createAssignmentFor($owner);

        $this->actingAs($other)
            ->postJson($this->tenantUrl('/submissions'), [
                'assignment_id' => $assignmentId,
                'student_name' => 'Alex Kim',
                'student_response' => 'An unrelated essay.',
            ])
            ->assertForbidden();
    }

    public function test_owners_can_grade_a_paper_with_ai(): void
    {
        $tenant = $this->initializeTenant();
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create(['name' => 'Jordan Lee']);
        $assignmentId = $this->createAssignmentFor($teacher);
        $submissionId = $this->joinAndSubmit(
            $student,
            $assignmentId,
            'The war began with the abduction of Helen.'
        );

        $this->fakeAssessmentResponse();

        $this->actingAs($teacher)
            ->postJson($this->tenantUrl('/submissions/'.$submissionId.'/assess'), [
                'submission_id' => $submissionId,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_score', 8)
            ->assertJsonPath('max_score', 10);

        $tenant->run(function () use ($submissionId) {
            $submission = Submission::query()->with('scores.criterion')->find($submissionId);

            $this->assertNotNull($submission);
            $this->assertSame('graded', $submission->status);
            $this->assertEquals(8, $submission->score);
            $this->assertSame('Strong paper.', $submission->payload['overall_feedback']);
            $this->assertSame('Jordan Lee', $submission->payload['student_name']);
            $this->assertEquals(1, $submission->scores->count());
            $this->assertNotNull($submission->scores->first()->question_id);
            $this->assertSame('Idea explanation', $submission->scores->first()->criterion->name);
            $this->assertEquals(8, $submission->scores->first()->score);
            $this->assertSame('Clear argument.', $submission->scores->first()->feedback);
        });
    }

    public function test_regrading_replaces_previous_criterion_scores(): void
    {
        $tenant = $this->initializeTenant();
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create(['name' => 'Jordan Lee']);
        $assignmentId = $this->createAssignmentFor($teacher);
        $submissionId = $this->joinAndSubmit(
            $student,
            $assignmentId,
            'The war began with the abduction of Helen.'
        );

        $this->fakeAssessmentResponse();

        $this->actingAs($teacher)
            ->postJson($this->tenantUrl('/submissions/'.$submissionId.'/assess'), [
                'submission_id' => $submissionId,
            ])
            ->assertCreated();

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'scores' => [
                                    [
                                        'criterion_name' => 'Idea explanation',
                                        'score' => 4,
                                        'feedback' => 'Needs more evidence.',
                                    ],
                                ],
                                'overall_feedback' => 'Revised assessment.',
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($teacher)
            ->postJson($this->tenantUrl('/submissions/'.$submissionId.'/assess'), [
                'submission_id' => $submissionId,
            ])
            ->assertCreated()
            ->assertJsonPath('total_score', 4);

        $tenant->run(function () use ($submissionId) {
            $submission = Submission::query()->with('scores')->find($submissionId);

            $this->assertNotNull($submission);
            $this->assertEquals(1, CriterionScore::query()->where('submission_id', $submissionId)->count());
            $this->assertEquals(4, $submission->score);
            $this->assertSame('Revised assessment.', $submission->payload['overall_feedback']);
            $this->assertSame('Needs more evidence.', $submission->scores->first()->feedback);
        });
    }

    public function test_other_teachers_cannot_grade_a_paper(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $student = User::factory()->student()->create(['name' => 'Jordan Lee']);
        $assignmentId = $this->createAssignmentFor($owner);
        $submissionId = $this->joinAndSubmit(
            $student,
            $assignmentId,
            'The war began with the abduction of Helen.'
        );

        $this->actingAs($other)
            ->postJson($this->tenantUrl('/submissions/'.$submissionId.'/assess'), [
                'submission_id' => $submissionId,
            ])
            ->assertForbidden();
    }

    public function test_demo_submissions_can_still_be_assessed_without_auth(): void
    {
        $tenant = $this->initializeTenant();
        $sessionId = 'demo-session-'.uniqid();

        $assignmentId = $this->postJson($this->tenantUrl('/assignments'), $this->assignmentPayload([
            'title' => 'Demo Essay',
            'session_id' => $sessionId,
        ]))
            ->assertCreated()
            ->json('data.assignment_id');

        $demoId = $tenant->run(fn () => Demo::query()->where('session_id', $sessionId)->value('id'));

        $submissionId = $this->postJson($this->tenantUrl('/submissions'), [
            'assignment_id' => $assignmentId,
            'demo_id' => $demoId,
            'student_response' => 'The war began with the abduction of Helen.',
        ])
            ->assertCreated()
            ->json('submission_id');

        $this->fakeAssessmentResponse();

        $this->postJson($this->tenantUrl('/submissions/'.$submissionId.'/assess'), [
            'submission_id' => $submissionId,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $tenant->run(function () use ($assignmentId, $submissionId) {
            $assignment = Assignment::query()->find($assignmentId);
            $submission = Submission::query()->find($submissionId);

            $this->assertNotNull($assignment);
            $this->assertNull($assignment->created_by);
            $this->assertNotNull($submission);
            $this->assertSame('graded', $submission->status);
        });
    }

    public function test_each_question_is_graded_against_the_full_rubric(): void
    {
        $tenant = $this->initializeTenant();
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create(['name' => 'Jordan Lee']);
        $assignmentId = $this->createAssignmentFor($teacher, [
            'question' => null,
            'questions' => [
                ['prompt' => 'Explain the causes of the Trojan War.'],
                ['prompt' => "What does Hector's death reveal about honor?"],
            ],
        ]);
        $submissionId = $this->joinAndSubmit($student, $assignmentId, [
            'The war began with the abduction of Helen.',
            'Hector dies defending Troy.',
        ]);

        $this->fakeAssessmentResponses([
            [
                'score' => 8,
                'feedback' => 'Clear argument.',
                'overall_feedback' => 'Strong first answer.',
            ],
            [
                'score' => 4,
                'feedback' => 'Needs more evidence.',
                'overall_feedback' => 'Partial second answer.',
            ],
        ]);

        $this->actingAs($teacher)
            ->postJson($this->tenantUrl('/submissions/'.$submissionId.'/assess'), [
                'submission_id' => $submissionId,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_score', 12)
            ->assertJsonPath('max_score', 20);

        $tenant->run(function () use ($submissionId) {
            $submission = Submission::query()->with('scores')->find($submissionId);

            $this->assertNotNull($submission);
            $this->assertEquals(12, $submission->score);
            $this->assertCount(2, $submission->scores);
            $this->assertCount(2, $submission->scores->pluck('question_id')->filter()->unique());
            $this->assertEqualsCanonicalizing([8, 4], $submission->scores->pluck('score')->map(fn ($score) => (int) $score)->all());
            $this->assertEqualsCanonicalizing(
                ['Strong first answer.', 'Partial second answer.'],
                collect($submission->payload['question_feedback'] ?? [])->values()->all()
            );
        });

        $this->actingAs($teacher)
            ->get($this->tenantUrl('/assignments/'.$assignmentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assignments/Show')
                ->where('assignment.overall_max_score', 20)
                ->has('assignment.submissions', 1)
                ->has('assignment.submissions.0.question_grades', 2)
                ->where('assignment.submissions.0.question_grades.0.max_score', 10)
                ->where('assignment.submissions.0.question_grades.1.max_score', 10)
                ->where('assignment.submissions.0.question_grades.0.score', 8)
                ->where('assignment.submissions.0.question_grades.1.score', 4)
            );

        $this->actingAs($student)
            ->get($this->tenantUrl('/student/assignments/'.$assignmentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Assignment')
                ->where('assignment.overall_max_score', 20)
                ->has('submission.question_grades', 2)
                ->where('submission.question_grades.0.score', 8)
                ->where('submission.question_grades.1.score', 4)
            );
    }
}
