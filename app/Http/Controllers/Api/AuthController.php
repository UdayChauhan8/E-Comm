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

class AuthController extends Controller
{
    
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth-token')->accessToken;

        return response()->json([
            'message' => 'Registration successful. Please verify your email with OTP.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

   
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('auth-token')->accessToken;

        return response()->json([
            'message'     => 'Login successful',
            'user'        => $user,
            'is_verified' => $user->is_verified,
            'token'       => $token,
        ]);
    }

    /**
     * Send OTP to user's email.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->is_verified) {
            return response()->json([
                'message' => 'Email already verified',
            ]);
        }

        // Generate a random 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save OTP and expiry (10 minutes)
        $user->update([
            'otp'            => Hash::make($otp),  // Store hashed (secure)
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP email (queued)
        Mail::to($user->email)->send(new OtpMail($otp, $user->name));

        return response()->json([
            'message' => 'OTP sent to ' . $user->email,
        ]);
    }

    /**
     * Verify OTP and mark user as verified.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->is_verified) {
            return response()->json([
                'message' => 'Email already verified',
            ]);
        }

        // Check if OTP exists
        if (!$user->otp) {
            return response()->json([
                'message' => 'No OTP found. Please request a new one.',
            ], 400);
        }

        // Check if OTP has expired
        if (now()->greaterThan($user->otp_expires_at)) {
            $user->update(['otp' => null, 'otp_expires_at' => null]);

            return response()->json([
                'message' => 'OTP has expired. Please request a new one.',
            ], 400);
        }

        // Check if OTP matches
        if (!Hash::check($request->otp, $user->otp)) {
            return response()->json([
                'message' => 'Invalid OTP',
            ], 400);
        }

        // Mark user as verified and clear OTP
        $user->update([
            'is_verified'    => true,
            'otp'            => null,
            'otp_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Email verified successfully! You can now make purchases.',
        ]);
    }

    /**
     * Logout — revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

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