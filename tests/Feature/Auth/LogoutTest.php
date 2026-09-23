<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Unauthenticated access
|--------------------------------------------------------------------------
*/

it('rejects unauthenticated logout', function () {
    $this->postJson('/api/v1/logout')->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Logout revokes the current token
|--------------------------------------------------------------------------
*/

it('revokes the current access token on logout', function () {
    $user = User::factory()->create();
    $token = loginToken($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout')
        ->assertOk()
        ->assertJson(['message' => 'Logged out successfully']);

    expect($user->tokens()->count())->toBe(0);
});

it('rejects a revoked token on protected routes', function () {
    $user = User::factory()->create();
    $token = loginToken($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout')
        ->assertOk();

    // The auth manager memoizes the resolved guard across requests in a single
    // test, so the token has to be re-resolved for the assertion to mean anything.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Other sessions stay signed in
|--------------------------------------------------------------------------
*/

it('leaves the other tokens of the same user intact', function () {
    $user = User::factory()->create();
    $phoneToken = loginToken($user);
    $laptopToken = loginToken($user);

    expect($user->tokens()->count())->toBe(2);

    $this->withHeader('Authorization', "Bearer {$phoneToken}")
        ->postJson('/api/v1/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(1);

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$laptopToken}")
        ->getJson('/api/v1/me')
        ->assertOk();
});
