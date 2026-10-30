<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'person_id',
        'role_id',
        'email',
        'password',
        'is_active',
    ];

    protected $with = [
        'role',
        'person',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function createdCycles(): HasMany
    {
        return $this->hasMany(SavingsCycle::class, 'created_by');
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === RoleName::Admin;
    }

    public function isSocio(): bool
    {
        return $this->role?->name === RoleName::Socio;
    }

    public function hasRole(RoleName|string $role): bool
    {
        $value = $role instanceof RoleName ? $role : RoleName::from($role);

        return $this->role?->name === $value;
    }

    public function canAccessCycle(SavingsCycle $cycle): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->person_id) {
            return false;
        }

        return $cycle->members()->where('person_id', $this->person_id)->exists();
    }
}
