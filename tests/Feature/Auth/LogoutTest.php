<?php

use App\Models\User;

it('rejects unauthenticated logout', function () {
    $this->postJson('/api/v1/logout')->assertUnauthorized();
});

it('revokes the current access token on logout', function () {
    $user = User::factory()->create();
    $token = loginToken($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout')
        ->assertOk()
        ->assertExactJson(['message' => 'Logged out successfully']);

    expect($user->tokens()->count())->toBe(0);
});

it('rejects a revoked token on protected routes', function () {
    $user = User::factory()->create();
    $token = loginToken($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout')
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

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
