<?php

use App\Models\Task;
use App\Models\User;

it('returns a paginated envelope', function () {
    $owner = User::factory()->create();
    Task::factory(25)->for($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/tasks')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'title', 'status', 'priority']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('defaults to 10 per page when per_page is not supplied', function () {
    $owner = User::factory()->create();
    Task::factory(25)->for($owner)->create();

    $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/tasks')->assertOk();

    expect($response->json('data'))->toHaveCount(10)
        ->and($response->json('meta.per_page'))->toBe(10)
        ->and($response->json('meta.total'))->toBe(25)
        ->and($response->json('meta.last_page'))->toBe(3);
});

it('honors a per_page supplied in the query', function () {
    $owner = User::factory()->create();
    Task::factory(25)->for($owner)->create();

    $this->actingAs($owner, 'sanctum');

    $small = $this->getJson('/api/v1/tasks?per_page=5')->assertOk();
    expect($small->json('data'))->toHaveCount(5)
        ->and($small->json('meta.per_page'))->toBe(5)
        ->and($small->json('meta.last_page'))->toBe(5);

    $big = $this->getJson('/api/v1/tasks?per_page=25')->assertOk();
    expect($big->json('data'))->toHaveCount(25)
        ->and($big->json('meta.last_page'))->toBe(1);
});

it('rejects an out of range or non numeric per_page', function (string $value) {
    $owner = User::factory()->create();
    Task::factory(3)->for($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/tasks?per_page={$value}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
})->with(['0', '101', 'abc', '-5']);

it('returns the remainder on the last page', function () {
    $owner = User::factory()->create();
    Task::factory(25)->for($owner)->create();

    $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/tasks?page=3')->assertOk();

    expect($response->json('data'))->toHaveCount(5)
        ->and($response->json('meta.current_page'))->toBe(3);
});

it('pages are disjoint and together cover every task the user owns', function () {
    $owner = User::factory()->create();
    $expected = Task::factory(25)->for($owner)->create()->pluck('id')->sort()->values()->all();

    $this->actingAs($owner, 'sanctum');

    $seen = collect();

    foreach ([1, 2, 3] as $page) {
        $seen = $seen->merge(
            collect($this->getJson("/api/v1/tasks?page={$page}")->assertOk()->json('data'))->pluck('id')
        );
    }

    expect($seen->duplicates())->toBeEmpty()
        ->and($seen->sort()->values()->all())->toBe($expected);
});

it('never leaks another users tasks on any page', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    Task::factory(25)->for($owner)->create();
    $foreignIds = Task::factory(25)->for($stranger)->create()->pluck('id');

    $this->actingAs($owner, 'sanctum');

    $first = $this->getJson('/api/v1/tasks')->assertOk();
    expect($first->json('meta.total'))->toBe(25);

    foreach ([1, 2, 3] as $page) {
        $ids = collect($this->getJson("/api/v1/tasks?page={$page}")->assertOk()->json('data'))->pluck('id');

        foreach ($foreignIds as $foreignId) {
            expect($ids)->not->toContain($foreignId);
        }
    }
});

it('returns an empty page rather than an error when the user has no tasks', function () {
    $response = $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/tasks')
        ->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->json('meta.total'))->toBe(0);
});
