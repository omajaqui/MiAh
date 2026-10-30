<?php

namespace App\Models;

use App\Enums\ContributionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_cycle_id',
        'savings_turn_id',
        'person_id',
        'expected_amount',
        'received_amount',
        'expected_date',
        'received_at',
        'status',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'expected_date' => 'date',
            'received_at' => 'datetime',
            'status' => ContributionStatus::class,
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SavingsContributionPayment::class);
    }

    public function paymentReportItems(): HasMany
    {
        return $this->hasMany(MemberPaymentReportItem::class);
    }
}
