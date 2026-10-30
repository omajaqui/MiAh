<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SavingsCycleMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_cycle_id',
        'person_id',
        'joined_at',
        'delivery_position',
        'status',
        'weekly_quota',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'weekly_quota' => 'decimal:2',
            'status' => MemberStatus::class,
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

    public function turn(): HasOne
    {
        return $this->hasOne(SavingsTurn::class);
    }
}
