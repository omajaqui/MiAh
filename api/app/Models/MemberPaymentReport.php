<?php

namespace App\Models;

use App\Enums\MemberPaymentReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberPaymentReport extends Model
{
    protected $fillable = [
        'savings_cycle_id',
        'person_id',
        'reported_by',
        'amount',
        'receipt_path',
        'receipt_original_name',
        'status',
        'observations',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => MemberPaymentReportStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SavingsCycle::class, 'savings_cycle_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MemberPaymentReportItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SavingsContributionPayment::class);
    }
}
