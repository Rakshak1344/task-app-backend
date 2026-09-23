<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Validation failures return 422 with a field-keyed errors object
|--------------------------------------------------------------------------
*/

it('rejects a login payload missing required fields', function (array $payload, string $field) {
    $this->postJson('/api/v1/login', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field)
        ->assertJsonStructure(['message', 'errors']);
})->with([
    'no email' => [['password' => 'password'], 'email'],
    'no password' => [['email' => 'ada@example.com'], 'password'],
    'empty body' => [[], 'email'],
    'null email' => [['email' => null, 'password' => 'password'], 'email'],
    'empty password' => [['email' => 'ada@example.com', 'password' => ''], 'password'],
]);

it('rejects a malformed email address', function (string $email) {
    $this->postJson('/api/v1/login', [
        'email' => $email,
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
    // Note: "user@localhost" style addresses (no dot in the domain) are valid under the
    // `email` rule, so they are deliberately absent here — they reach the credential
    // check and correctly return 401, not 422.
})->with(['not-an-email', '@example.com', 'spaces in@example.com', 'ada@']);

it('reports every invalid field at once rather than stopping at the first', function () {
    $this->postJson('/api/v1/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('validates before touching credentials, so a malformed email is 422 not 401', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    // 401 here would mean the request reached the credential check with junk input.
    $this->postJson('/api/v1/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertStatus(422);
});
