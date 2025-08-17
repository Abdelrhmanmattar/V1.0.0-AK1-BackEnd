<?php

// app/Http/Controllers/AuthController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth; // 👈 add this

class AuthController extends Controller
{
    // Register new user
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'role' => 'required|in:furniture_manager,devices_manager',
        ]);

        $user = User::create($data); // password auto-hashed via cast
        return response()->json(['user' => $user], 201);
    }

    // Login and return JWT
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        return response()->json([
            "success" => true,
            "data" => [
                "user" => [
                    "id" => (string) $user->id,
                    "email" => $user->email,
                    "name" => $user->name,
                    "role" => $user->role, // assuming your User model has a `role` field
                ],
                "token" => $token, // JWT or bearer token
            ]
        ]);
    }

    // Get current user
    public function me()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                "success" => false,
                "error" => "Unauthorized"
            ], 401);
        }

        return response()->json([
            "success" => true,
            "data" => [
                "id" => (string) $user->id,
                "email" => $user->email,
                "name" => $user->name,
                "role" => $user->role, // must be either "furniture_manager" or "devices_manager"
            ]
        ], 200);
    }

    // Logout (invalidate token)
    public function logout()
    {
        try {
            Auth::logout();
            return response()->json(['success' => true, 'data' => null], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
    }

    protected function respondWithToken(string $token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in'   => JWTAuth::factory()->getTTL() * 60, // 👈 use the Facade
        ]);
    }
}
