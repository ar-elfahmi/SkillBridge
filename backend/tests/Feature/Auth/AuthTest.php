<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Auth\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $spa = ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mahasiswa Uji',
            'email' => 'mhs@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'student',
        ], $overrides);
    }

    public function test_csrf_cookie_endpoint_sets_xsrf_token(): void
    {
        $this->get('/sanctum/csrf-cookie', $this->spa)
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_register_creates_user_and_logs_in(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['role' => 'study_program']), $this->spa)
            ->assertCreated()
            ->assertJsonPath('data.email', 'mhs@example.test')
            ->assertJsonPath('data.role', 'study_program')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'mhs@example.test')->firstOrFail();
        $this->assertSame(UserRole::StudyProgram, $user->role);
        $this->assertNotSame('rahasia123', $user->getAuthPassword());
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_register_rejects_invalid_role(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['role' => 'admin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_duplicate_email_and_bad_confirmation(): void
    {
        User::factory()->create(['email' => 'mhs@example.test']);

        $this->postJson('/api/auth/register', $this->payload(['password_confirmation' => 'lain12345']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonPath('errors.email.0', 'Email sudah terdaftar.');
    }

    public function test_new_user_defaults_to_student_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Student, $user->fresh()?->role);
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'mhs@example.test', 'password' => 'rahasia123']);

        $this->postJson('/api/auth/login', ['email' => 'MHS@example.test', 'password' => 'rahasia123'], $this->spa)
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'student');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        User::factory()->create(['email' => 'mhs@example.test', 'password' => 'rahasia123']);

        $this->postJson('/api/auth/login', ['email' => 'mhs@example.test', 'password' => 'salah'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email atau kata sandi salah.');

        $this->assertGuest('web');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Silakan masuk terlebih dahulu.']);
    }

    public function test_me_returns_profile_and_role(): void
    {
        $user = User::factory()->studyProgram()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'study_program',
                'created_at' => $user->created_at?->toIso8601String(),
            ]]);
    }

    public function test_session_flow_login_me_logout(): void
    {
        User::factory()->create(['email' => 'mhs@example.test', 'password' => 'rahasia123']);

        $this->postJson('/api/auth/login', ['email' => 'mhs@example.test', 'password' => 'rahasia123'], $this->spa)
            ->assertOk();
        $this->getJson('/api/auth/me', $this->spa)->assertOk();

        $this->postJson('/api/auth/logout', [], $this->spa)->assertNoContent();
        $this->assertGuest('web');

        Auth::forgetGuards();
        $this->getJson('/api/auth/me', $this->spa)->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        config(['skillbridge.rate_limits.auth_per_minute' => 2]);
        User::factory()->create(['email' => 'mhs@example.test', 'password' => 'rahasia123']);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'mhs@example.test', 'password' => 'salah'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'mhs@example.test', 'password' => 'salah'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Terlalu banyak percobaan. Coba lagi dalam satu menit.');
    }
}
