<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Log the user in through the real endpoint and return the plain text token, so tests
 * exercise an actual PersonalAccessToken rather than the TransientToken that actingAs()
 * would give them.
 *
 * Lives here rather than in a test file because a global function declared inside one
 * test file fatals the moment a second file needs it.
 */
function loginToken(User $user, string $password = 'password'): string
{
    return test()->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk()->json('data.access_token');
}

/**
 * Headers for an authenticated request using a real bearer token.
 *
 * @return array<string, string>
 */
function bearer(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}
