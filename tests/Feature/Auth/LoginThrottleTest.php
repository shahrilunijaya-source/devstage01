<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failures_lock_out_the_email(): void
    {
        $email = 'victim@ursb.test';
        User::factory()->create(['email' => $email, 'password' => bcrypt('correct-password')]);

        // Five wrong-password attempts are merely rejected.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        // The sixth is locked out — even the correct password is refused.
        $response = $this->post('/login', ['email' => $email, 'password' => 'correct-password']);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'Too many login attempts',
            session('errors')->first('email'),
        );
    }

    public function test_successful_login_clears_the_throttle(): void
    {
        $email = 'user@ursb.test';
        User::factory()->create(['email' => $email, 'password' => bcrypt('correct-password')]);

        // A few failures, then a success — the success must not be throttled.
        $this->post('/login', ['email' => $email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $email, 'password' => 'correct-password']);

        $this->assertAuthenticated();
    }
}
