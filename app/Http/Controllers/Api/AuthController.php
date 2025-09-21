<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            // 'email' => ['required', 'string', 'email', 'max:255', 'unique:users'], // If you add email
            'password' => ['required', 'confirmed', Password::defaults()],
            // Add validation for other user fields if they are part of registration
            // 'user_money_collector_type' => ['required', Rule::in(['lab','company','clinic','all'])],
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            // 'email' => $request->email,
            'password' => Hash::make($request->password),
            // 'user_money_collector_type' => $request->user_money_collector_type, // example
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user, // You might want to use an API Resource here
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'], // or 'email' if using email for login
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'], // Optional device name for token
        ]);

        $user = User::where('username', $request->username)->first();
        if ($user->is_active == false) {
            throw ValidationException::withMessages([
                'message'=> 'الحساب غير مفعل',
            ]);
        }
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' // or 'email'
                 => [trans('auth.failed')],
            ]);
        }

        //load roles and permissions
        $user->load('roles.permissions');
        $deviceName = $request->input('device_name', $request->userAgent());

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $user, // You might want to use an API Resource here
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        // To delete all tokens for the user:
        // $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        $user = $request->user()->load('roles.permissions');
        return response()->json($user); // You might want to use an API Resource here
    }
}