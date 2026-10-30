<?php

namespace App\Models;

use App\Enums\DisbursementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_cycle_id',
        'savings_turn_id',
        'beneficiary_person_id',
        'expected_amount',
        'collected_amount',
        'delivered_amount',
        'delivered_at',
        'status',
        'registered_by',
        'annul_reason',
        'annulled_at',
        'annulled_by',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'collected_amount' => 'decimal:2',
            'delivered_amount' => 'decimal:2',
            'delivered_at' => 'datetime',
            'annulled_at' => 'datetime',
            'status' => DisbursementStatus::class,
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SavingsCycle::class, 'savings_cycle_id');
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(SavingsTurn::class, 'savings_turn_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'beneficiary_person_id');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function annuller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }
}
