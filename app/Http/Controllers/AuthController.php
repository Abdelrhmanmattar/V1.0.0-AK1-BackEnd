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
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return $this->respondWithToken($token);
    }

    // Get current user
    public function me()
    {
        return response()->json(Auth::user());
    }

    // Logout (invalidate token)
    public function logout()
    {
        Auth::logout();
        return response()->json(['message' => 'Logged out']);
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
