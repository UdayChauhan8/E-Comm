<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VerifyOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_otp_returns_only_id_and_bearer_token(): void
    {
        $this->artisan('passport:keys', ['--force' => true]);
        $this->artisan('passport:client', [
            '--personal' => true,
            '--name' => 'Testing Personal Access Client',
            '--provider' => 'users',
        ]);

        $otp = '123456';
        $user = User::factory()->unverified()->create([
            'otp' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ]);

        $response->assertOk();
        $this->assertSame(['id', 'token'], array_keys($response->json()));
        $this->assertSame($user->id, $response->json('id'));
        $this->assertIsString($response->json('token'));
        $this->assertNotSame('', $response->json('token'));
    }
}
