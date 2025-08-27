<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
       
        try {
            // Validate login request
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            $credentials = $request->only('email', 'password');

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid credentials. Please check your email or password.'
                ], 401);
            }

            $user  = Auth::user();
            $token = $user->createToken('API Token')->accessToken;

            return response()->json([
                'status' => 'success',
                'token'  => $token,
                'user'   => $user
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Login failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }


    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->token()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No active session found.'
                ], 401);
            }

            // Revoke token
            $user->token()->revoke();

            return response()->json([
                'status'  => 'success',
                'message' => 'Logged out successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'user_id' => $request->user()->id ?? null,
                'error'   => $e->getMessage()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong while logging out. Please try again.'
            ], 500);
        }
    }
}
