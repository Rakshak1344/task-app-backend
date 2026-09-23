<?php

use App\Models\User;

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

})->with(['not-an-email', '@example.com', 'spaces in@example.com', 'ada@']);

it('reports every invalid field at once rather than stopping at the first', function () {
    $this->postJson('/api/v1/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('validates before touching credentials, so a malformed email is 422 not 401', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertStatus(422);
});
