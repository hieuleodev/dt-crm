<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @return list<string> */
    public static function typesFor(string $model): array
    {
        $legacy = match ($model) {
            Customer::class, Contact::class, Plant::class, CustomerNote::class => 'App\\Domain\\Customer\\Models\\'.class_basename($model),
            Task::class => 'App\\Domain\\Care\\Models\\Task',
            default => null,
        };

        return $legacy === null ? [$model] : [$model, $legacy];
    }

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
