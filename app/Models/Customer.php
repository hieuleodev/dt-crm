<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\Customer\CompanyType;
use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerTier;
use App\Enums\Customer\Industry;
use App\Enums\Customer\LifecycleStage;
use App\Enums\Customer\ProcurementMethod;
use App\Enums\Customer\SourceChannel;
use App\Services\Customer\FindDuplicateCustomers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = ['id', 'name_key', 'data_completeness', 'created_at', 'updated_at', 'deleted_at'];

    protected array $auditExclude = ['name_key', 'data_completeness', 'last_contacted_at'];

    protected static function booted(): void
    {
        static::saving(function (Customer $c) {
            $c->name_key = FindDuplicateCustomers::nameKey($c->name);
            $c->phone = self::normalizePhone($c->phone);
            $c->email = $c->email ? mb_strtolower(trim($c->email)) : null;
        });
    }

    protected function casts(): array
    {
        return [
            'company_type' => CompanyType::class,
            'industry' => Industry::class,
            'source_channel' => SourceChannel::class,
            'tier' => CustomerTier::class,
            'lifecycle_stage' => LifecycleStage::class,
            'status' => CustomerStatus::class,
            'procurement_method' => ProcurementMethod::class,
            'required_certificates' => 'array',
            'consent_channels' => 'array',
            'vendor_registered' => 'boolean',
            'marketing_consent' => 'boolean',
            'vendor_registered_at' => 'date',
            'lifecycle_stage_at' => 'datetime',
            'consent_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'employee_count' => 'integer',
            'ppe_budget_estimate' => 'integer',
            'credit_limit' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function plants(): HasMany
    {
        return $this->hasMany(Plant::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Lead::class)->where('kind', 'opportunity');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function ownerHistories(): HasMany
    {
        return $this->hasMany(CustomerOwnerHistory::class)->latest('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')->whereIn('auditable_type', AuditLog::typesFor(self::class));
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isManager() ? $query : $query->where('owner_id', $user->id);
    }

    public static function normalizePhone(?string $value): ?string
    {
        $value = preg_replace('/(?!^\+)[^0-9]/', '', trim((string) $value));

        return $value === '' ? null : $value;
    }

    public static function normalizeTaxCode(?string $value): ?string
    {
        $value = preg_replace('/[^0-9-]/', '', (string) $value);

        return $value === '' ? null : $value;
    }
}
