<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => $m->writeAudit('created', [], $m->auditableValues($m->getAttributes())));

        static::updated(function (Model $m) {
            $changes = $m->auditableValues($m->getChanges());
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($m->getOriginal(), $changes);
            $m->writeAudit('updated', $m->auditableValues($old), $changes);
        });

        static::deleted(fn (Model $m) => $m->writeAudit('deleted', $m->auditableValues($m->getOriginal()), []));
    }

    protected function auditableValues(array $values): array
    {
        $exclude = array_merge(['updated_at', 'created_at', 'password', 'remember_token'], $this->auditExclude ?? []);

        return array_map(
            fn ($v) => $v instanceof \BackedEnum ? $v->value : $v,
            array_diff_key($values, array_flip($exclude)),
        );
    }

    protected function writeAudit(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
