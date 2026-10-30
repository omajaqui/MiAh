<?php

namespace App\Models;

use App\Enums\CycleStatus;
use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'contribution_weekday',
        'first_contribution_date',
        'first_disbursement_date',
        'weekly_quota',
        'currency',
        'members_count',
        'turns_count',
        'status',
        'observations',
        'created_by',
        'closed_at',
        'closed_by',
        'close_reason',
        'finalized_at',
        'finalized_by',
        'finalize_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'first_contribution_date' => 'date',
            'first_disbursement_date' => 'date',
            'weekly_quota' => 'decimal:2',
            'status' => CycleStatus::class,
            'closed_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SavingsCycleMember::class)->orderBy('delivery_position');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', MemberStatus::Activo);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(SavingsTurn::class)->orderBy('turn_number');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(SavingsContribution::class);
    }

    public function paymentReports(): HasMany
    {
        return $this->hasMany(MemberPaymentReport::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(SavingsDisbursement::class);
    }

    public function isOpen(): bool
    {
        return $this->status === CycleStatus::Abierto;
    }

    public function isInProgress(): bool
    {
        return $this->status === CycleStatus::EnProceso;
    }

    public function isClosed(): bool
    {
        return $this->status === CycleStatus::Cerrado;
    }

    public function isFinalized(): bool
    {
        return $this->status === CycleStatus::Finalizado;
    }
}
