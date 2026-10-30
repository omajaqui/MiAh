<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPaymentReportItem extends Model
{
    protected $fillable = [
        'member_payment_report_id',
        'savings_contribution_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MemberPaymentReport::class, 'member_payment_report_id');
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(SavingsContribution::class, 'savings_contribution_id');
    }
}
