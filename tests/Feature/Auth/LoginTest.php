<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Successful login
|--------------------------------------------------------------------------
*/

it('logs a user in with valid credentials', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'ada@example.com',
        'password' => 'password',
    ])->assertOk();

    $response->assertJsonStructure([
        'data' => [
            'access_token',
            'user' => ['id', 'name', 'email', 'created_at'],
        ],
        'message',
    ]);

    expect($response->json('data.user.id'))->toBe($user->id)
        ->and($response->json('data.user.email'))->toBe('ada@example.com')
        ->and($response->json('data.access_token'))->toBeString()->not->toBeEmpty();
});

it('issues a token that actually authenticates subsequent requests', function () {
    $user = User::factory()->create();

    $token = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->json('data.access_token');

    // A token is only meaningful if it opens a protected route.
    $this->withHeaders(bearer($token))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('persists the issued token against the user', function () {
    $user = User::factory()->create();

    expect($user->tokens()->count())->toBe(0);

    loginToken($user);

    expect($user->refresh()->tokens()->count())->toBe(1);
});

it('never returns the password or its hash', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $body = $response->getContent();

    expect($body)->not->toContain('password')
        ->and($body)->not->toContain($user->getAuthPassword());
});

/*
|--------------------------------------------------------------------------
| Failed login
|--------------------------------------------------------------------------
*/

it('rejects a wrong password with 401', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertUnauthorized();
});

it('rejects an unknown email with 401', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ])->assertUnauthorized();
});

it('does not let the response distinguish a wrong password from an unknown user', function () {
    $user = User::factory()->create();

    $wrongPassword = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertUnauthorized();

    $unknownEmail = $this->postJson('/api/v1/login', [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ])->assertUnauthorized();

    // Differing bodies would turn the endpoint into a user-enumeration oracle.
    expect($wrongPassword->json())->toBe($unknownEmail->json());
});

it('issues no token when the credentials are wrong', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertUnauthorized();

    expect($user->tokens()->count())->toBe(0);
});
