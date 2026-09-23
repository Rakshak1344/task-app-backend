<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

/*
|--------------------------------------------------------------------------
| Filter by status
|--------------------------------------------------------------------------
*/

it('filters by each status', function (TaskStatus $status) {
    // TaskFactory randomises status, so every row here is set explicitly.
    foreach (TaskStatus::cases() as $case) {
        Task::factory(2)->for($this->user)->create(['status' => $case]);
    }

    $response = $this->getJson("/api/v1/tasks?status={$status->value}")->assertOk();

    expect($response->json('meta.total'))->toBe(2)
        ->and(collect($response->json('data'))->pluck('status')->unique()->all())
        ->toBe([$status->value]);
})->with(TaskStatus::cases());

it('returns an empty set when no task has the requested status', function () {
    Task::factory(3)->for($this->user)->create(['status' => TaskStatus::PENDING]);

    $response = $this->getJson('/api/v1/tasks?status=completed')->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->json('meta.total'))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Filter by priority
|--------------------------------------------------------------------------
*/

it('filters by each priority', function (TaskPriority $priority) {
    foreach (TaskPriority::cases() as $case) {
        Task::factory(2)->for($this->user)->create(['priority' => $case]);
    }

    $response = $this->getJson("/api/v1/tasks?priority={$priority->value}")->assertOk();

    expect($response->json('meta.total'))->toBe(2)
        ->and(collect($response->json('data'))->pluck('priority')->unique()->all())
        ->toBe([$priority->value]);
})->with(TaskPriority::cases());

/*
|--------------------------------------------------------------------------
| Combined filters
|--------------------------------------------------------------------------
*/

it('applies status and priority together as an AND', function () {
    Task::factory()->for($this->user)->create([
        'title' => 'Wanted',
        'status' => TaskStatus::PENDING,
        'priority' => TaskPriority::HIGH,
    ]);
    Task::factory()->for($this->user)->create([
        'status' => TaskStatus::PENDING,
        'priority' => TaskPriority::LOW,
    ]);
    Task::factory()->for($this->user)->create([
        'status' => TaskStatus::COMPLETED,
        'priority' => TaskPriority::HIGH,
    ]);

    $response = $this->getJson('/api/v1/tasks?status=pending&priority=high')->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.title'))->toBe('Wanted');
});

/*
|--------------------------------------------------------------------------
| Filters must not widen ownership scoping
|--------------------------------------------------------------------------
*/

it('never returns another users task through a filter', function () {
    $stranger = User::factory()->create();

    Task::factory(2)->for($this->user)->create(['status' => TaskStatus::PENDING]);
    Task::factory(5)->for($stranger)->create(['status' => TaskStatus::PENDING]);

    expect($this->getJson('/api/v1/tasks?status=pending')->assertOk()->json('meta.total'))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

it('sorts by due date in the requested direction', function () {
    Task::factory()->for($this->user)->create(['title' => 'Later', 'due_date' => '2026-12-01']);
    Task::factory()->for($this->user)->create(['title' => 'Sooner', 'due_date' => '2026-01-01']);

    $asc = collect($this->getJson('/api/v1/tasks?sort=due_date&direction=asc')->assertOk()->json('data'))
        ->pluck('title')->all();

    $desc = collect($this->getJson('/api/v1/tasks?sort=due_date&direction=desc')->assertOk()->json('data'))
        ->pluck('title')->all();

    expect($asc)->toBe(['Sooner', 'Later'])
        ->and($desc)->toBe(['Later', 'Sooner']);
});

it('defaults to newest first', function () {
    $older = Task::factory()->for($this->user)->create(['created_at' => now()->subDay()]);
    $newer = Task::factory()->for($this->user)->create(['created_at' => now()]);

    $ids = collect($this->getJson('/api/v1/tasks')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$newer->id, $older->id]);
});

/*
|--------------------------------------------------------------------------
| Validation — an unknown filter value must be rejected, not ignored
|--------------------------------------------------------------------------
*/

it('rejects an invalid status with 422 instead of silently listing everything', function () {
    Task::factory(3)->for($this->user)->create();

    $this->getJson('/api/v1/tasks?status=archived')
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('rejects an invalid priority with 422', function () {
    $this->getJson('/api/v1/tasks?priority=urgent')
        ->assertStatus(422)
        ->assertJsonValidationErrors('priority');
});

it('rejects an unsortable column', function () {
    // Prevents sort from becoming an arbitrary-column disclosure vector.
    $this->getJson('/api/v1/tasks?sort=password')
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort');
});

it('rejects an invalid sort direction', function () {
    $this->getJson('/api/v1/tasks?sort=title&direction=sideways')
        ->assertStatus(422)
        ->assertJsonValidationErrors('direction');
});
