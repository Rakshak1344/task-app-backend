<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Creating a task
|--------------------------------------------------------------------------
*/

it('creates a task with every field and returns 201', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tasks', [
            'title' => 'Write the quarterly report',
            'description' => 'Cover Q1 through Q3.',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => '2026-12-01T09:00:00+00:00',
        ])
        ->assertCreated();

    $response->assertJsonStructure([
        'data' => ['id', 'title', 'description', 'status', 'priority', 'due_date', 'created_at', 'updated_at'],
        'message',
    ]);

    $response
        ->assertJsonPath('data.title', 'Write the quarterly report')
        ->assertJsonPath('data.description', 'Cover Q1 through Q3.')
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.priority', 'high');
});

it('persists the task against the authenticated user', function () {
    $user = User::factory()->create();

    $id = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tasks', ['title' => 'Mine alone'])
        ->assertCreated()
        ->json('data.id');

    $task = Task::findOrFail($id);

    expect($task->title)->toBe('Mine alone')
        ->and($task->user_id)->toBe($user->id);

    $this->assertDatabaseHas('tasks', [
        'id' => $id,
        'title' => 'Mine alone',
        'user_id' => $user->id,
    ]);
});

it('falls back to the pending and medium defaults when status and priority are omitted', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tasks', ['title' => 'No status given'])
        ->assertCreated();

    $response
        ->assertJsonPath('data.status', TaskStatus::PENDING->value)
        ->assertJsonPath('data.priority', TaskPriority::MEDIUM->value);

    $task = Task::findOrFail($response->json('data.id'));

    expect($task->status)->toBe(TaskStatus::PENDING)
        ->and($task->priority)->toBe(TaskPriority::MEDIUM);
});

it('accepts a null description and returns it as null', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tasks', ['title' => 'Bare', 'description' => null])
        ->assertCreated()
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.due_date', null);
});

it('round trips the due date', function () {
    $user = User::factory()->create();

    $id = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tasks', [
            'title' => 'Due soon',
            'due_date' => '2026-12-01 09:30:00',
        ])
        ->assertCreated()
        ->json('data.id');

    expect(Task::findOrFail($id)->due_date->format('Y-m-d H:i:s'))->toBe('2026-12-01 09:30:00');
});

it('casts status and priority back to enums on the model', function () {
    $user = User::factory()->create();

    $id = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tasks', [
            'title' => 'Typed',
            'status' => 'completed',
            'priority' => 'low',
        ])
        ->assertCreated()
        ->json('data.id');

    $task = Task::findOrFail($id);

    expect($task->status)->toBe(TaskStatus::COMPLETED)
        ->and($task->priority)->toBe(TaskPriority::LOW);
});

it('creates a task through a real bearer token, not just actingAs', function () {
    $user = User::factory()->create();
    $token = loginToken($user);

    $this->withHeaders(bearer($token))
        ->postJson('/api/v1/tasks', ['title' => 'Via token'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Via token');

    $this->assertDatabaseHas('tasks', ['title' => 'Via token', 'user_id' => $user->id]);
});

it('makes the new task immediately visible in that users list', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tasks', ['title' => 'Fresh'])
        ->assertCreated();

    $titles = collect(
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks')->assertOk()->json('data')
    )->pluck('title');

    expect($titles)->toContain('Fresh');
});
