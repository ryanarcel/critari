<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Criterion;
use App\Models\Demo;
use App\Models\Question;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreateAssignmentTest extends TestCase
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

    public function test_guests_cannot_view_the_create_assignment_page(): void
    {
        $this->get($this->tenantUrl('/assignments/create'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_teachers_can_view_the_create_assignment_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get($this->tenantUrl('/assignments/create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Assignments/Create'));
    }

    public function test_guests_cannot_store_a_teacher_assignment(): void
    {
        $this->postJson($this->tenantUrl('/assignments'), $this->assignmentPayload())
            ->assertUnauthorized();
    }

    public function test_authenticated_teachers_can_create_an_assignment_without_a_demo(): void
    {
        $tenant = $this->initializeTenant();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson($this->tenantUrl('/assignments'), $this->assignmentPayload([
                'title' => 'Teacher Owned Essay',
            ]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.demo_id', null);

        $assignmentId = $response->json('data.assignment_id');

        $tenant->run(function () use ($user, $assignmentId) {
            $assignment = Assignment::query()->find($assignmentId);

            $this->assertNotNull($assignment);
            $this->assertSame($user->id, $assignment->created_by);
            $this->assertNull($assignment->demo_id);
            $this->assertSame(10, $assignment->max_score);
            $this->assertSame(
                'Explain the causes of the Trojan War.',
                $assignment->formattedPrompts()
            );
            $this->assertEquals(
                ['Explain the causes of the Trojan War.'],
                $assignment->questions->pluck('prompt')->all()
            );
            $this->assertEquals(1, Criterion::where('assignment_id', $assignment->id)->count());
            $this->assertNotNull($assignment->join_code);
            $this->assertSame(6, strlen($assignment->join_code));
        });
    }

    public function test_demo_saves_still_create_a_demo_backed_assignment(): void
    {
        $tenant = $this->initializeTenant();
        $sessionId = 'demo-session-'.uniqid();

        $response = $this->postJson($this->tenantUrl('/assignments'), $this->assignmentPayload([
            'title' => 'Demo Essay',
            'session_id' => $sessionId,
        ]))
            ->assertCreated()
            ->assertJsonPath('success', true);

        $assignmentId = $response->json('data.assignment_id');

        $tenant->run(function () use ($sessionId, $assignmentId) {
            $demo = Demo::query()->where('session_id', $sessionId)->first();
            $assignment = Assignment::query()->find($assignmentId);

            $this->assertNotNull($demo);
            $this->assertNotNull($assignment);
            $this->assertSame($demo->id, $assignment->demo_id);
            $this->assertNull($assignment->created_by);
            $this->assertNull($assignment->join_code);
        });
    }

    public function test_created_assignments_appear_on_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson($this->tenantUrl('/assignments'), $this->assignmentPayload([
                'title' => 'Dashboard Essay',
            ]))
            ->assertCreated();

        $this->actingAs($user)
            ->get($this->tenantUrl('/dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('assignments', 1)
                ->where('assignments.0.title', 'Dashboard Essay')
                ->where('assignments.0.status', 'Draft')
            );
    }

    public function test_teachers_can_create_an_assignment_with_multiple_questions(): void
    {
        $tenant = $this->initializeTenant();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson($this->tenantUrl('/assignments'), $this->assignmentPayload([
                'title' => 'Two Prompt Essay',
                'question' => null,
                'questions' => [
                    ['prompt' => 'Explain the causes of the Trojan War.'],
                    ['prompt' => "What does Hector's death reveal about honor?"],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('success', true);

        $assignmentId = $response->json('data.assignment_id');

        $tenant->run(function () use ($assignmentId) {
            $assignment = Assignment::query()->find($assignmentId);

            $this->assertNotNull($assignment);
            $this->assertEquals(2, $assignment->questions->count());
            $this->assertEquals(
                [
                    'Explain the causes of the Trojan War.',
                    "What does Hector's death reveal about honor?",
                ],
                $assignment->questions->pluck('prompt')->all()
            );
            $this->assertTrue(
                $assignment->questions->every(fn (Question $question): bool => $question->assignment_id === $assignment->id)
            );
            $this->assertSame(
                "1. Explain the causes of the Trojan War.\n\n2. What does Hector's death reveal about honor?",
                $assignment->formattedPrompts()
            );
        });
    }
}
