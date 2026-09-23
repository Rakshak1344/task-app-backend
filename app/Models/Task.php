<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Policies\TaskPolicy;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(TaskPolicy::class)]
#[Fillable(['title', 'description', 'status', 'priority', 'due_date'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Mirrors the column defaults in the tasks migration.
     *
     * The database default alone is not enough: Eloquent does not read it back after an
     * insert, so a task created without a status would be returned to the client with
     * "status": null and only appear as "pending" on the next request.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TaskStatus::PENDING->value,
        'priority' => TaskPriority::MEDIUM->value,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
        ];
    }

    /**
     * Case-insensitive partial match on the title.
     *
     * LOWER(title) LIKE lower(term) rather than ILIKE: Postgres LIKE is case-sensitive
     * and SQLite has no ILIKE, so lowering both sides is the one form that behaves
     * identically on either driver.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term) {
            // Escape LIKE metacharacters so searching for "50%" or "a_b" matches literally.
            $escaped = addcslashes(mb_strtolower(trim($term)), '%_\\');

            $query->whereRaw('LOWER(title) LIKE ?', ["%{$escaped}%"]);
        });
    }

    #[Scope]
    protected function withStatus(Builder $query, TaskStatus|string|null $status): void
    {
        $query->when(filled($status), fn (Builder $query) => $query->where(
            'status',
            $status instanceof TaskStatus ? $status->value : $status,
        ));
    }

    #[Scope]
    protected function withPriority(Builder $query, TaskPriority|string|null $priority): void
    {
        $query->when(filled($priority), fn (Builder $query) => $query->where(
            'priority',
            $priority instanceof TaskPriority ? $priority->value : $priority,
        ));
    }
}
