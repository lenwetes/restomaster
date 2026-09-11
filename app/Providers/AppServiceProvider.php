<?php

namespace App\Providers;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\CuentaPorPagar;
use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Policies\CajaPolicy;
use App\Policies\ClientePolicy;
use App\Policies\CuentaPorPagarPolicy;
use App\Policies\InsumoPolicy;
use App\Policies\MesaPolicy;
use App\Policies\PedidoPolicy;
use App\Policies\ReservaPolicy;
use App\Policies\TurnoCajaPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'es'));

        Volt::mount([
            resource_path('views/livewire'),
        ]);

        // Super-admin bypass: administradores tienen acceso irrestricto
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
        });

        // Registro de Policies de Dominio
        Gate::policy(Pedido::class, PedidoPolicy::class);
        Gate::policy(Caja::class, CajaPolicy::class);
        Gate::policy(TurnoCaja::class, TurnoCajaPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Insumo::class, InsumoPolicy::class);
        Gate::policy(CuentaPorPagar::class, CuentaPorPagarPolicy::class);
        Gate::policy(Reserva::class, ReservaPolicy::class);
        Gate::policy(Mesa::class, MesaPolicy::class);

        // Gates para acciones del sistema
        Gate::define('administrar-configuracion', function (User $user) {
            return $user->hasRole('admin');
        });
    }
}
