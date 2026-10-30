<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsContributionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_contribution_id',
        'amount',
        'paid_at',
        'status',
        'registered_by',
        'annul_reason',
        'annulled_at',
        'annulled_by',
        'observations',
        'member_payment_report_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'annulled_at' => 'datetime',
            'status' => PaymentStatus::class,
        ];
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(SavingsContribution::class, 'savings_contribution_id');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function annuller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MemberPaymentReport::class, 'member_payment_report_id');
    }
}
