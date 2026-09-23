<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Successful signup
|--------------------------------------------------------------------------
*/

it('creates a user and returns a token with 201', function () {
    $response = $this->postJson('/api/v1/signup', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
    ])->assertCreated();

    $response->assertJsonStructure([
        'data' => ['access_token', 'user' => ['id', 'name', 'email']],
        'message',
    ]);

    $this->assertDatabaseHas('users', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
});

it('stores the password hashed, never in plain text', function () {
    $this->postJson('/api/v1/signup', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
    ])->assertCreated();

    $user = User::where('email', 'ada@example.com')->firstOrFail();

    expect($user->password)->not->toBe('secret-password')
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('issues a signup token that authenticates immediately', function () {
    $token = $this->postJson('/api/v1/signup', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
    ])->assertCreated()->json('data.access_token');

    $this->withHeaders(bearer($token))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@example.com');
});

/*
|--------------------------------------------------------------------------
| Validation failures
|--------------------------------------------------------------------------
*/

it('rejects a duplicate email with 422 rather than a database error', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/signup', [
        'name' => 'Impostor',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    expect(User::where('email', 'ada@example.com')->count())->toBe(1);
});

it('rejects a duplicate email even when the existing user is soft deleted', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    $user->delete();

    // The unique index still holds the soft-deleted row, so this must fail validation
    // rather than blowing up as a raw integrity-constraint violation.
    $this->postJson('/api/v1/signup', [
        'name' => 'Ada Again',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('rejects a short password', function () {
    $this->postJson('/api/v1/signup', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'short',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
});

it('rejects signup payloads missing required fields', function (array $payload, string $field) {
    $this->postJson('/api/v1/signup', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'no name' => [['email' => 'a@example.com', 'password' => 'secret-password'], 'name'],
    'no email' => [['name' => 'Ada', 'password' => 'secret-password'], 'email'],
    'no password' => [['name' => 'Ada', 'email' => 'a@example.com'], 'password'],
    'bad email' => [['name' => 'Ada', 'email' => 'nope', 'password' => 'secret-password'], 'email'],
]);
