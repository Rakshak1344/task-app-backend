<?php

use App\Models\Task;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Unauthenticated access
|--------------------------------------------------------------------------
*/

it('rejects unauthenticated access to every task route', function () {
    $task = Task::factory()->for(User::factory())->create();

    $this->getJson('/api/v1/tasks')->assertUnauthorized();
    $this->postJson('/api/v1/tasks', ['title' => 'Nope'])->assertUnauthorized();
    $this->getJson("/api/v1/tasks/{$task->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertUnauthorized();
    $this->deleteJson("/api/v1/tasks/{$task->id}")->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| The owner has full access to their own task
|--------------------------------------------------------------------------
*/

it('lets the owner run the full crud cycle on their own task', function () {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    $this->actingAs($owner, 'sanctum');

    $this->getJson('/api/v1/tasks')->assertOk();
    $this->postJson('/api/v1/tasks', ['title' => 'Mine'])->assertCreated();
    $this->getJson("/api/v1/tasks/{$task->id}")->assertOk();
    $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertOk();
    $this->deleteJson("/api/v1/tasks/{$task->id}")->assertOk();
});

/*
|--------------------------------------------------------------------------
| A non-owner is refused with 403 and cannot mutate anything
|--------------------------------------------------------------------------
*/

it('forbids a non-owner from viewing another users task', function () {
    $task = Task::factory()->for(User::factory())->create();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertForbidden();
});

it('forbids a non-owner from updating another users task and leaves it unchanged', function () {
    $task = Task::factory()->for(User::factory())->create(['title' => 'Untouched']);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Hacked', 'status' => 'completed'])
        ->assertForbidden();

    expect($task->fresh()->title)->toBe('Untouched');
});

it('forbids a non-owner from deleting another users task and leaves it present', function () {
    $task = Task::factory()->for(User::factory())->create();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->deleteJson("/api/v1/tasks/{$task->id}")
        ->assertForbidden();

    expect($task->fresh())->not->toBeNull()
        ->and($task->fresh()->deleted_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The index leaks nothing belonging to anyone else
|--------------------------------------------------------------------------
*/

it('only lists tasks belonging to the authenticated user', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $mine = Task::factory(3)->for($owner)->create();
    $theirs = Task::factory(2)->for($stranger)->create();

    $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/tasks')->assertOk();

    $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

    expect($returnedIds)->toBe($mine->pluck('id')->sort()->values()->all());

    foreach ($theirs as $foreign) {
        expect($returnedIds)->not->toContain($foreign->id);
    }
});

/*
|--------------------------------------------------------------------------
| Ownership cannot be forged on create or reassigned on update
|--------------------------------------------------------------------------
*/

it('assigns the authenticated user as owner even when user_id is forged in the payload', function () {
    $actor = User::factory()->create();
    $victim = User::factory()->create();

    $response = $this->actingAs($actor, 'sanctum')
        ->postJson('/api/v1/tasks', [
            'title' => 'Forged',
            'user_id' => $victim->id,
        ])
        ->assertCreated();

    $task = Task::findOrFail($response->json('data.id'));

    expect($task->user_id)->toBe($actor->id)
        ->and($task->user_id)->not->toBe($victim->id);
});

it('does not let an owner give their task away by sending user_id', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/tasks/{$task->id}", [
            'title' => 'Still mine',
            'user_id' => $stranger->id,
        ])
        ->assertOk();

    expect($task->fresh()->user_id)->toBe($owner->id);
});
