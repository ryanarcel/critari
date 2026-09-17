<?php

namespace Tests\Feature;

use App\Models\OAuthState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->initializeTenant();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function mockGoogleRedirect(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('with')->once()->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function mockGoogleUser(string $email, string $name): void
    {
        $googleUser = Mockery::mock(GoogleUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_login_page_shows_teacher_and_student_google_buttons(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_google_redirect_requires_a_valid_role(): void
    {
        $this->get('/auth/google')->assertSessionHasErrors('role');
        $this->get('/auth/google?role=admin')->assertSessionHasErrors('role');
    }

    public function test_teacher_google_button_stores_teacher_intent(): void
    {
        $this->mockGoogleRedirect();

        $this->get('/auth/google?role=teacher')
            ->assertRedirect('https://accounts.google.com');

        $this->assertDatabaseHas('o_auth_states', [
            'intended_role' => 'teacher',
        ], 'landlord');
    }

    public function test_new_google_student_is_created_with_the_student_role(): void
    {
        $tenant = $this->initializeTenant();
        $stateToken = 'student-state-'.uniqid();
        $email = 'student-'.uniqid().'@example.com';

        OAuthState::create([
            'token' => $stateToken,
            'tenant_host' => $this->tenantId.'.localhost',
            'intended_role' => 'student',
            'expires_at' => now()->addHour(),
        ]);

        $this->mockGoogleUser($email, 'Ada Student');

        $response = $this->get('/auth/google/callback?state='.$stateToken)
            ->assertRedirect();

        $this->assertStringContainsString('/auth/oauth/verify?token=', $response->headers->get('Location'));

        $tenant->run(function () use ($email) {
            $user = User::query()->where('email', $email)->first();

            $this->assertNotNull($user);
            $this->assertSame('student', $user->role);
        });
    }

    public function test_student_verify_redirects_to_student_home(): void
    {
        $tenant = $this->initializeTenant();
        $user = null;

        $tenant->run(function () use (&$user) {
            $user = User::factory()->student()->create();
        });

        $token = 'verify-student-'.uniqid();
        DB::connection('landlord')->table('o_auth_states')->insert([
            'token' => $token,
            'tenant_host' => json_encode([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'intended_role' => 'student',
            ]),
            'intended_role' => 'student',
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get($this->tenantUrl('/auth/oauth/verify?token='.$token))
            ->assertRedirect('/student');
    }

    public function test_existing_teacher_keeps_teacher_role_after_student_google_sso(): void
    {
        $tenant = $this->initializeTenant();
        $email = 'teacher-'.uniqid().'@example.com';

        $tenant->run(function () use ($email) {
            User::factory()->teacher()->create([
                'email' => $email,
                'name' => 'Original Teacher',
            ]);
        });

        $stateToken = 'teacher-as-student-'.uniqid();
        OAuthState::create([
            'token' => $stateToken,
            'tenant_host' => $this->tenantId.'.localhost',
            'intended_role' => 'student',
            'expires_at' => now()->addHour(),
        ]);

        $this->mockGoogleUser($email, 'Original Teacher');

        $response = $this->get('/auth/google/callback?state='.$stateToken)
            ->assertRedirect();

        $tenant->run(function () use ($email) {
            $user = User::query()->where('email', $email)->first();

            $this->assertNotNull($user);
            $this->assertSame('teacher', $user->role);
        });

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);

        $this->get($location)
            ->assertRedirect('/dashboard');
    }

    public function test_students_cannot_open_the_teacher_dashboard(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->get($this->tenantUrl('/dashboard'))
            ->assertRedirect('/student');
    }

    public function test_authenticated_students_can_view_student_home(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->get($this->tenantUrl('/student'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('user.name', $user->name)
            );
    }
}
