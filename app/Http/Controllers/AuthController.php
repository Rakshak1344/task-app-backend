<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\SignupRequest;
use App\Http\Resources\AuthResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

    public function signUp(SignupRequest $request)
    {
        $user = User::firstOrCreate($request->validated());
        $token = $user->createToken('auth_token');
        return AuthResource::make($user, $token->plainTextToken);
    }

    public function login(LoginRequest $request)
    {

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw new AuthenticationException('Invalid credentials');
        }

        $token = $user->createToken('auth_token');
        return AuthResource::make($user, $token->plainTextToken);
    }
}
