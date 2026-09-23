<?php

use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

it('rejects a create payload with no title', function () {
    $this->postJson('/api/v1/tasks', ['description' => 'Body but no title'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title')
        ->assertJsonStructure(['message', 'errors']);

    expect(Task::count())->toBe(0);
});

it('rejects a blank or whitespace-only title', function (mixed $title) {
    $this->postJson('/api/v1/tasks', ['title' => $title])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');

    expect(Task::count())->toBe(0);
})->with([
    'empty string' => [''],
    'null' => [null],
]);

it('rejects a title longer than 255 characters', function () {
    $this->postJson('/api/v1/tasks', ['title' => str_repeat('a', 256)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');

    expect(Task::count())->toBe(0);
});

it('accepts a title of exactly 255 characters', function () {

    $this->postJson('/api/v1/tasks', ['title' => str_repeat('a', 255)])
        ->assertCreated();
});

it('rejects a status outside the enum', function (string $status) {
    $this->postJson('/api/v1/tasks', ['title' => 'Valid', 'status' => $status])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect(Task::count())->toBe(0);
})->with(['done', 'PENDING', 'archived', '1', 'in progress']);

it('rejects a priority outside the enum', function (string $priority) {
    $this->postJson('/api/v1/tasks', ['title' => 'Valid', 'priority' => $priority])
        ->assertStatus(422)
        ->assertJsonValidationErrors('priority');

    expect(Task::count())->toBe(0);
})->with(['urgent', 'HIGH', 'critical', '2']);

it('rejects an unparseable due date', function (string $dueDate) {
    $this->postJson('/api/v1/tasks', ['title' => 'Valid', 'due_date' => $dueDate])
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');

    expect(Task::count())->toBe(0);
})->with(['not-a-date', 'yesterday-ish', '2026-13-45']);

it('reports every invalid field in one response', function () {
    $this->postJson('/api/v1/tasks', [
        'status' => 'nope',
        'priority' => 'nope',
        'due_date' => 'nope',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'status', 'priority', 'due_date']);
});

it('rejects an invalid update and leaves the task untouched', function (array $payload, string $field) {
    $task = Task::factory()->for($this->user)->create([
        'title' => 'Original title',
        'status' => 'pending',
    ]);

    $this->patchJson("/api/v1/tasks/{$task->id}", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect($task->fresh()->title)->toBe('Original title')
        ->and($task->fresh()->status->value)->toBe('pending');
})->with([
    'bad status' => [['status' => 'archived'], 'status'],
    'bad priority' => [['priority' => 'urgent'], 'priority'],
    'bad due date' => [['due_date' => 'not-a-date'], 'due_date'],
    'empty title' => [['title' => ''], 'title'],
    'long title' => [['title' => str_repeat('a', 256)], 'title'],
]);

it('allows a partial update without requiring every field', function () {
    $task = Task::factory()->for($this->user)->create([
        'title' => 'Original title',
        'status' => 'pending',
    ]);

    $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.title', 'Original title');
});

it('accepts an empty update payload as a no-op', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Unchanged']);

    $this->patchJson("/api/v1/tasks/{$task->id}", [])
        ->assertOk()
        ->assertJsonPath('data.title', 'Unchanged');
});

it('returns 404 for a task id that does not exist', function () {
    $this->getJson('/api/v1/tasks/999999')->assertNotFound();
    $this->patchJson('/api/v1/tasks/999999', ['title' => 'x'])->assertNotFound();
    $this->deleteJson('/api/v1/tasks/999999')->assertNotFound();
});

it('returns 404 rather than 500 for a soft deleted task', function () {
    $task = Task::factory()->for($this->user)->create();
    $task->delete();

    $this->getJson("/api/v1/tasks/{$task->id}")->assertNotFound();
});
