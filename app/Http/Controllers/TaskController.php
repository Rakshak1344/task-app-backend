<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[Authorize('viewAny', Task::class)]
    public function index(IndexTaskRequest $request)
    {
        $pageCount = $request->validated('per_page', 10);
        $tasks = $request->user()->tasks()
            ->latest()->latest('id')
            ->paginate($pageCount);

        return TaskResource::collection($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[Authorize('create', Task::class)]
    public function store(StoreTaskRequest $request)
    {
        $task = $request->user()->tasks()->create($request->validated());
        return TaskResource::make($task);
    }

    /**
     * Display the specified resource.
     */
    #[Authorize('view', 'task')]
    public function show(Task $task)
    {
        return TaskResource::make($task);
    }

    /**
     * Update the specified resource in storage.
     */
    #[Authorize('update', 'task')]
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task->update($request->validated());
        return TaskResource::make($task);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[Authorize('delete', 'task')]
    public function destroy(Task $task)
    {
        $task->delete();
        return response()->json(['message' => 'Deleted Successfully']);
    }
}
