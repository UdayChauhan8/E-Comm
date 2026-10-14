<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Token;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Send OTP directly upon registration
        $this->generateAndSendOtp($user);

        return response()->json([
            'message' => 'Registration successful. An OTP has been sent to your email to complete verification.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_verified) {
            $this->generateAndSendOtp($user);

            return response()->json([
                'message' => 'Account not verified. A new OTP has been sent to your email.',
                'is_verified' => false,
            ], 403);
        }

        $token = $user->createToken('auth-token')->accessToken;

        return response()->json([
            'message' => 'Login successful',
            'name'    => $user->name,
            'id'      => $user->id,
            'token'   => $token,
        ]);
    }

    /**
     * Send OTP to a user's email.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->is_verified) {
            return response()->json([
                'message' => 'Email already verified. Please login.',
            ], 400);
        }

        $this->generateAndSendOtp($user);

        return response()->json([
            'message' => 'OTP sent to '.$user->email,
        ]);
    }

    /**
     * Verify OTP and issue token.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->is_verified) {
            return response()->json([
                'message' => 'Email already verified. Please login.',
            ], 400);
        }

        if (! $user->otp) {
            return response()->json([
                'message' => 'No OTP found. Please request a new one.',
            ], 400);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            $user->update(['otp' => null, 'otp_expires_at' => null]);

            return response()->json([
                'message' => 'OTP has expired. Please request a new one.',
            ], 400);
        }

        if (! Hash::check($request->otp, $user->otp)) {
            return response()->json([
                'message' => 'Invalid OTP',
            ], 400);
        }

        // Mark user as verified and clear OTP
        $user->update([
            'is_verified' => true,
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        // Issue token upon successful verification
        $token = $user->createToken('auth-token')->accessToken;

        return response()->json([
            'id' => $user->id,
            'token' => $token,
        ]);
    }

    /**
     * Helper method to generate and send OTP.
     */
    private function generateAndSendOtp(User $user): void
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'otp' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($user->email)->send(new OtpMail($otp, $user->name));
    }

    /**
     * Logout — revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var Token $token */
        $token = $request->user()->token();
        $token->revoke();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
