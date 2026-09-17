<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Question;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssignmentJoinTest extends TestCase
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

    private function createAssignmentFor(User $user): int
    {
        $response = $this->actingAs($user)
            ->postJson($this->tenantUrl('/assignments'), $this->assignmentPayload())
            ->assertCreated();

        return (int) $response->json('data.assignment_id');
    }

    public function test_guests_cannot_join_an_assignment(): void
    {
        $teacher = User::factory()->create();
        $this->createAssignmentFor($teacher);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->post($this->tenantUrl('/student/join'), ['code' => 'ABC123'])
            ->assertRedirect(route('login'));
    }

    public function test_students_can_join_with_a_valid_code(): void
    {
        $tenant = $this->initializeTenant();
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create();
        $assignmentId = $this->createAssignmentFor($teacher);
        $code = $tenant->run(fn () => Assignment::query()->find($assignmentId)?->join_code);

        $this->actingAs($student)
            ->from($this->tenantUrl('/student'))
            ->post($this->tenantUrl('/student/join'), ['code' => strtolower((string) $code)])
            ->assertRedirect($this->tenantUrl('/student/assignments/'.$assignmentId));

        $tenant->run(function () use ($assignmentId, $student) {
            $assignment = Assignment::query()->find($assignmentId);

            $this->assertTrue($assignment?->joinedBy($student));
        });

        $this->actingAs($student)
            ->get($this->tenantUrl('/student'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->has('assignments', 1)
                ->where('assignments.0.title', 'Midterm Essay')
                ->where('assignments.0.status', 'not_started')
            );
    }

    public function test_invalid_codes_are_rejected(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->from($this->tenantUrl('/student'))
            ->post($this->tenantUrl('/student/join'), ['code' => 'NOPE00'])
            ->assertRedirect($this->tenantUrl('/student'))
            ->assertSessionHasErrors('code');
    }

    public function test_students_cannot_open_an_assignment_they_have_not_joined(): void
    {
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create();
        $assignmentId = $this->createAssignmentFor($teacher);

        $this->actingAs($student)
            ->get($this->tenantUrl('/student/assignments/'.$assignmentId))
            ->assertNotFound();
    }

    public function test_joined_students_can_answer_the_questions(): void
    {
        $tenant = $this->initializeTenant();
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create(['name' => 'Jordan Lee']);
        $assignmentId = $this->createAssignmentFor($teacher);
        $code = null;
        $questionId = null;

        $tenant->run(function () use ($assignmentId, &$code, &$questionId) {
            $assignment = Assignment::query()->with('questions')->find($assignmentId);
            $code = $assignment?->join_code;
            $questionId = $assignment?->questions->first()?->id;
        });

        $this->actingAs($student)
            ->post($this->tenantUrl('/student/join'), ['code' => $code])
            ->assertRedirect();

        $this->actingAs($student)
            ->get($this->tenantUrl('/student/assignments/'.$assignmentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Assignment')
                ->where('assignment.title', 'Midterm Essay')
                ->has('assignment.questions', 1)
                ->where('assignment.questions.0.response', '')
                ->where('submission', null)
            );

        $this->actingAs($student)
            ->post($this->tenantUrl('/student/assignments/'.$assignmentId), [
                'answers' => [
                    ['question_id' => $questionId, 'response' => 'Paris abducted Helen.'],
                ],
            ])
            ->assertRedirect($this->tenantUrl('/student/assignments/'.$assignmentId));

        $this->actingAs($student)
            ->get($this->tenantUrl('/student/assignments/'.$assignmentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Assignment')
                ->where('submission.status', 'pending')
                ->where('assignment.questions.0.response', 'Paris abducted Helen.')
            );

        $tenant->run(function () use ($assignmentId, $student, $questionId) {
            $question = Question::query()->find($questionId);
            $this->assertNotNull($question);

            $assignment = Assignment::query()->find($assignmentId);
            $submission = $assignment?->submissions()->where('user_id', $student->id)->first();

            $this->assertNotNull($submission);
            $this->assertSame($student->id, $submission->user_id);
            $this->assertSame('Paris abducted Helen.', $submission->payload['student_response']);
        });
    }

    public function test_a_new_student_does_not_see_another_students_answers(): void
    {
        $tenant = $this->initializeTenant();
        $teacher = User::factory()->create();
        $firstStudent = User::factory()->student()->create(['name' => 'Jordan Lee']);
        $secondStudent = User::factory()->student()->create(['name' => 'Alex Kim']);
        $assignmentId = $this->createAssignmentFor($teacher);
        $code = null;
        $questionId = null;

        $tenant->run(function () use ($assignmentId, &$code, &$questionId) {
            $assignment = Assignment::query()->with('questions')->find($assignmentId);
            $code = $assignment?->join_code;
            $questionId = $assignment?->questions->first()?->id;
        });

        $this->actingAs($firstStudent)
            ->post($this->tenantUrl('/student/join'), ['code' => $code])
            ->assertRedirect();

        $this->actingAs($firstStudent)
            ->post($this->tenantUrl('/student/assignments/'.$assignmentId), [
                'answers' => [
                    ['question_id' => $questionId, 'response' => 'Paris abducted Helen.'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($secondStudent)
            ->from($this->tenantUrl('/student'))
            ->post($this->tenantUrl('/student/join'), ['code' => $code])
            ->assertRedirect($this->tenantUrl('/student/assignments/'.$assignmentId));

        $this->actingAs($secondStudent)
            ->get($this->tenantUrl('/student/assignments/'.$assignmentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Assignment')
                ->where('assignment.questions.0.response', '')
                ->where('submission', null)
            );
    }

    public function test_rejoining_keeps_the_students_own_answers(): void
    {
        $tenant = $this->initializeTenant();
        $teacher = User::factory()->create();
        $student = User::factory()->student()->create();
        $assignmentId = $this->createAssignmentFor($teacher);
        $code = null;
        $questionId = null;

        $tenant->run(function () use ($assignmentId, &$code, &$questionId) {
            $assignment = Assignment::query()->with('questions')->find($assignmentId);
            $code = $assignment?->join_code;
            $questionId = $assignment?->questions->first()?->id;
        });

        $this->actingAs($student)
            ->post($this->tenantUrl('/student/join'), ['code' => $code])
            ->assertRedirect($this->tenantUrl('/student/assignments/'.$assignmentId));

        $this->actingAs($student)
            ->post($this->tenantUrl('/student/assignments/'.$assignmentId), [
                'answers' => [
                    ['question_id' => $questionId, 'response' => 'Paris abducted Helen.'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($student)
            ->post($this->tenantUrl('/student/join'), ['code' => $code])
            ->assertRedirect($this->tenantUrl('/student/assignments/'.$assignmentId));

        $this->actingAs($student)
            ->get($this->tenantUrl('/student/assignments/'.$assignmentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Assignment')
                ->where('assignment.questions.0.response', 'Paris abducted Helen.')
            );
    }

    public function test_teachers_are_redirected_away_from_student_home(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->get($this->tenantUrl('/student'))
            ->assertRedirect($this->tenantUrl('/dashboard'));
    }
}
