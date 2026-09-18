<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['role_id', 'sucursal_id', 'name', 'email', 'telefono', 'activo', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected ?array $permisosMemo = null;

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

    public function pedidosRepartidor(): HasMany
    {
        return $this->hasMany(Pedido::class, 'repartidor_id');
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
        return in_array($this->role?->slug, ['delivery', 'repartidor'], true);
    }

    /**
     * Mesas actualmente asignadas a este mesero.
     */
    public function mesasAsignadas(): HasMany
    {
        return $this->hasMany(Mesa::class, 'mesero_id');
    }

    /**
     * Pedidos atendidos por este mesero.
     */
    public function pedidosAtendidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'mesero_id');
    }

    public function permisoExplicito(string $key): ?bool
    {
        try {
            $this->permisosMemo ??= DB::table('permission_user')
                ->where('user_id', $this->id)
                ->pluck('tipo', 'permission')
                ->all();
        } catch (\Throwable) {
            return null;
        }

        if (! array_key_exists($key, $this->permisosMemo)) {
            return null;
        }

        return $this->permisosMemo[$key] === 'grant';
    }

    public function olvidarPermisosMemo(): void
    {
        $this->permisosMemo = null;
    }
}
