<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

/**
 * @return Collection<int, string>
 */
function searchTitles(string $query): Collection
{
    return collect(
        test()->getJson("/api/v1/tasks?{$query}")->assertOk()->json('data')
    )->pluck('title');
}

/*
|--------------------------------------------------------------------------
| Matching
|--------------------------------------------------------------------------
*/

it('matches a partial title', function () {
    Task::factory()->for($this->user)->create(['title' => 'Write the quarterly report']);
    Task::factory()->for($this->user)->create(['title' => 'Buy milk']);

    expect(searchTitles('search=quarterly'))->toEqual(collect(['Write the quarterly report']));
});

it('matches regardless of case in either direction', function (string $term) {
    Task::factory()->for($this->user)->create(['title' => 'Write the Quarterly Report']);

    expect(searchTitles("search={$term}"))->toHaveCount(1);
})->with(['quarterly', 'QUARTERLY', 'QuArTeRlY', 'Quarterly']);

it('matches a term in the middle and at the end of a title', function () {
    Task::factory()->for($this->user)->create(['title' => 'Alpha beta gamma']);

    expect(searchTitles('search=beta'))->toHaveCount(1)
        ->and(searchTitles('search=gamma'))->toHaveCount(1)
        ->and(searchTitles('search=Alpha'))->toHaveCount(1);
});

it('returns every task whose title matches', function () {
    Task::factory()->for($this->user)->create(['title' => 'Report one']);
    Task::factory()->for($this->user)->create(['title' => 'Report two']);
    Task::factory()->for($this->user)->create(['title' => 'Unrelated']);

    expect(searchTitles('search=report'))->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| Non-matching
|--------------------------------------------------------------------------
*/

it('searches the title only, not the description', function () {
    Task::factory()->for($this->user)->create([
        'title' => 'Unrelated title',
        'description' => 'This description mentions quarterly explicitly.',
    ]);

    expect(searchTitles('search=quarterly'))->toBeEmpty();
});

it('returns an empty page with 200 when nothing matches', function () {
    Task::factory(3)->for($this->user)->create(['title' => 'Something else']);

    $response = $this->getJson('/api/v1/tasks?search=nomatchwhatsoever')->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->json('meta.total'))->toBe(0);
});

it('ignores an empty or whitespace-only search term and returns everything', function (string $term) {
    Task::factory(3)->for($this->user)->create();

    $response = $this->getJson('/api/v1/tasks?search='.urlencode($term))->assertOk();

    expect($response->json('meta.total'))->toBe(3);
})->with(['', '   ']);

/*
|--------------------------------------------------------------------------
| LIKE metacharacters must be literal, not wildcards
|--------------------------------------------------------------------------
*/

it('treats a percent sign as a literal character', function () {
    Task::factory()->for($this->user)->create(['title' => 'Grew 50% this year']);
    Task::factory()->for($this->user)->create(['title' => 'No numbers here']);

    // An unescaped "%" would wildcard and match both rows.
    expect(searchTitles('search='.urlencode('50%')))->toEqual(collect(['Grew 50% this year']));
});

it('treats an underscore as a literal character', function () {
    Task::factory()->for($this->user)->create(['title' => 'snake_case naming']);
    Task::factory()->for($this->user)->create(['title' => 'snakeXcase naming']);

    // An unescaped "_" matches any single character, which would return both.
    expect(searchTitles('search='.urlencode('snake_case')))->toEqual(collect(['snake_case naming']));
});

it('treats a lone percent sign as a literal rather than matching everything', function () {
    Task::factory(3)->for($this->user)->create(['title' => 'Plain title']);
    Task::factory()->for($this->user)->create(['title' => '100% done']);

    expect(searchTitles('search='.urlencode('%')))->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Search must never widen ownership scoping
|--------------------------------------------------------------------------
*/

it('never returns another users task even when the title matches', function () {
    $stranger = User::factory()->create();

    Task::factory()->for($this->user)->create(['title' => 'My report']);
    Task::factory()->for($stranger)->create(['title' => 'Their report']);

    $titles = searchTitles('search=report');

    expect($titles)->toEqual(collect(['My report']))
        ->and($titles)->not->toContain('Their report');
});

it('returns nothing when only another users task matches', function () {
    $stranger = User::factory()->create();
    Task::factory()->for($stranger)->create(['title' => 'Confidential merger plan']);

    expect(searchTitles('search=merger'))->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Combining with filters and pagination
|--------------------------------------------------------------------------
*/

it('combines search with a status filter', function () {
    Task::factory()->for($this->user)->create(['title' => 'Report A', 'status' => 'pending']);
    Task::factory()->for($this->user)->create(['title' => 'Report B', 'status' => 'completed']);
    Task::factory()->for($this->user)->create(['title' => 'Other', 'status' => 'pending']);

    expect(searchTitles('search=report&status=pending'))->toEqual(collect(['Report A']));
});

it('paginates the filtered result set rather than the whole table', function () {
    Task::factory(15)->for($this->user)->create(['title' => 'Report item']);
    Task::factory(10)->for($this->user)->create(['title' => 'Unrelated']);

    $response = $this->getJson('/api/v1/tasks?search=report&per_page=10')->assertOk();

    expect($response->json('meta.total'))->toBe(15)
        ->and($response->json('meta.last_page'))->toBe(2)
        ->and($response->json('data'))->toHaveCount(10);
});

it('keeps the search term in the pagination links', function () {
    Task::factory(15)->for($this->user)->create(['title' => 'Report item']);

    $next = $this->getJson('/api/v1/tasks?search=report&per_page=10')->assertOk()->json('links.next');

    // Without withQueryString() the filters silently vanish on page two.
    expect($next)->toContain('search=report')->toContain('per_page=10');
});

it('keeps the filters applied on page two', function () {
    Task::factory(15)->for($this->user)->create(['title' => 'Report item']);
    Task::factory(10)->for($this->user)->create(['title' => 'Unrelated']);

    $response = $this->getJson('/api/v1/tasks?search=report&per_page=10&page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(5)
        ->and(collect($response->json('data'))->pluck('title')->unique()->all())
        ->toBe(['Report item']);
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

it('rejects a search term longer than 255 characters', function () {
    $this->getJson('/api/v1/tasks?search='.str_repeat('a', 256))
        ->assertStatus(422)
        ->assertJsonValidationErrors('search');
});
