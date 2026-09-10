<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role_id', 'sucursal_id', 'name', 'email', 'telefono', 'activo', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Get the role associated with the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the branch (sucursal) associated with the user.
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Determine if the user has a specific role by slug.
     */
    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    /**
     * Helper methods for specific roles.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isGerente(): bool
    {
        return $this->hasRole('gerente');
    }

    public function isCajero(): bool
    {
        return $this->hasRole('cajero');
    }

    public function isMesero(): bool
    {
        return $this->hasRole('mesero');
    }

    public function isCocina(): bool
    {
        return $this->hasRole('cocina');
    }

    public function isBarra(): bool
    {
        return $this->hasRole('barra');
    }

    public function isDelivery(): bool
    {
        return $this->hasRole('delivery');
    }
}
