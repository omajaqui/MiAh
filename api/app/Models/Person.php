<?php

namespace App\Models;

use App\Enums\IdentificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'identification_type',
        'identification_number',
        'phone',
        'email',
        'address',
        'bank_type_id',
        'account_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'identification_type' => IdentificationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function bankType(): BelongsTo
    {
        return $this->belongsTo(BankType::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SavingsCycleMember::class);
    }
}
