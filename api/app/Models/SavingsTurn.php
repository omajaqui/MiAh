<?php

namespace App\Models;

use App\Enums\TurnStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SavingsTurn extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_cycle_id',
        'turn_number',
        'beneficiary_person_id',
        'savings_cycle_member_id',
        'expected_contribution_date',
        'expected_disbursement_date',
        'weekly_quota',
        'contributors_count',
        'expected_gross_amount',
        'collected_amount',
        'status',
        'actual_disbursement_date',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'expected_contribution_date' => 'date',
            'expected_disbursement_date' => 'date',
            'actual_disbursement_date' => 'date',
            'weekly_quota' => 'decimal:2',
            'expected_gross_amount' => 'decimal:2',
            'collected_amount' => 'decimal:2',
            'status' => TurnStatus::class,
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SavingsCycle::class, 'savings_cycle_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'beneficiary_person_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(SavingsCycleMember::class, 'savings_cycle_member_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(SavingsContribution::class);
    }

    public function disbursement(): HasOne
    {
        return $this->hasOne(SavingsDisbursement::class);
    }

    public function activeDisbursement(): HasOne
    {
        return $this->hasOne(SavingsDisbursement::class)
            ->where('status', \App\Enums\DisbursementStatus::Completado);
    }
}
