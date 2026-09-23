<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

it('filters by each status', function (TaskStatus $status) {

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

it('filters by each priority', function (TaskPriority $priority) {
    foreach (TaskPriority::cases() as $case) {
        Task::factory(2)->for($this->user)->create(['priority' => $case]);
    }

    $response = $this->getJson("/api/v1/tasks?priority={$priority->value}")->assertOk();

    expect($response->json('meta.total'))->toBe(2)
        ->and(collect($response->json('data'))->pluck('priority')->unique()->all())
        ->toBe([$priority->value]);
})->with(TaskPriority::cases());

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

it('never returns another users task through a filter', function () {
    $stranger = User::factory()->create();

    Task::factory(2)->for($this->user)->create(['status' => TaskStatus::PENDING]);
    Task::factory(5)->for($stranger)->create(['status' => TaskStatus::PENDING]);

    expect($this->getJson('/api/v1/tasks?status=pending')->assertOk()->json('meta.total'))->toBe(2);
});

it('returns the newest tasks first', function () {
    $oldest = Task::factory()->for($this->user)->create(['created_at' => now()->subWeek()]);
    $older = Task::factory()->for($this->user)->create(['created_at' => now()->subDay()]);
    $newest = Task::factory()->for($this->user)->create(['created_at' => now()]);

    $ids = collect($this->getJson('/api/v1/tasks')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$newest->id, $older->id, $oldest->id]);
});

it('keeps newest first when a filter is applied', function () {
    $older = Task::factory()->for($this->user)
        ->create(['status' => TaskStatus::PENDING, 'created_at' => now()->subDay()]);
    $newer = Task::factory()->for($this->user)
        ->create(['status' => TaskStatus::PENDING, 'created_at' => now()]);
    Task::factory()->for($this->user)->create(['status' => TaskStatus::COMPLETED]);

    $ids = collect($this->getJson('/api/v1/tasks?status=pending')->assertOk()->json('data'))
        ->pluck('id')->all();

    expect($ids)->toBe([$newer->id, $older->id]);
});

it('breaks ties on id so rows created in the same instant still page cleanly', function () {
    $sameMoment = now();

    $first = Task::factory()->for($this->user)->create(['created_at' => $sameMoment]);
    $second = Task::factory()->for($this->user)->create(['created_at' => $sameMoment]);

    $ids = collect($this->getJson('/api/v1/tasks')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$second->id, $first->id]);
});

it('ignores sort and direction parameters now that ordering is fixed', function () {
    $older = Task::factory()->for($this->user)->create(['created_at' => now()->subDay()]);
    $newer = Task::factory()->for($this->user)->create(['created_at' => now()]);

    $ids = collect(
        $this->getJson('/api/v1/tasks?sort=title&direction=asc')->assertOk()->json('data')
    )->pluck('id')->all();

    expect($ids)->toBe([$newer->id, $older->id]);
});

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
