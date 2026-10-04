<?php

namespace App\Services;

use App\Events\ClienteElegibleVip;
use App\Events\ClienteVipAprobado;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\User;
use App\Models\VipInvitacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClubVipService
{
    /**
     * Obtiene el consumo mínimo configurado (default: 500.000 COP).
     */
    public function obtenerConsumoMinimo(): float
    {
        $val = Configuracion::where('clave', 'vip_consumo_minimo')->value('valor');
        if (is_array($val)) {
            $val = $val['valor'] ?? reset($val);
        }

        return is_numeric($val) ? (float) $val : 500000.0;
    }

    /**
     * Obtiene los días de la ventana de análisis (default: 60 días).
     */
    public function obtenerVentanaDias(): int
    {
        $val = Configuracion::where('clave', 'vip_ventana_dias')->value('valor');
        if (is_array($val)) {
            $val = $val['valor'] ?? reset($val);
        }

        return is_numeric($val) ? (int) $val : 60;
    }

    /**
     * Calcula el consumo acumulado de un cliente en pedidos pagados dentro de la ventana de días.
     */
    public function calcularConsumoVentana(Cliente $cliente, ?int $dias = null): float
    {
        $dias = $dias ?? $this->obtenerVentanaDias();
        $desde = now()->subDays($dias);

        return (float) Pedido::where('cliente_id', $cliente->id)
            ->where('estado', 'pagado')
            ->where('created_at', '>=', $desde)
            ->sum('total');
    }

    /**
     * Evalúa si un cliente califica para ser VIP y actualiza su estado a elegible si no lo era.
     */
    public function evaluarElegibilidad(Cliente $cliente): bool
    {
        // Si ya es VIP activo o tiene invitación en curso, mantener su estado
        if (in_array($cliente->vip_estado, [Cliente::VIP_ESTADO_ACTIVO, Cliente::VIP_ESTADO_INVITADO, Cliente::VIP_ESTADO_PENDIENTE], true)) {
            return false;
        }

        $consumo = $this->calcularConsumoVentana($cliente);
        $minimo = $this->obtenerConsumoMinimo();

        if ($consumo >= $minimo) {
            if ($cliente->vip_estado !== Cliente::VIP_ESTADO_ELEGIBLE) {
                $cliente->update([
                    'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE,
                    'vip_elegible_at' => now(),
                ]);

                event(new ClienteElegibleVip($cliente, $consumo));
            }

            return true;
        }

        // Si ya no cumple el consumo y era elegible pero nunca fue invitado, vuelve a ninguno
        if ($cliente->vip_estado === Cliente::VIP_ESTADO_ELEGIBLE) {
            $cliente->update([
                'vip_estado' => Cliente::VIP_ESTADO_NINGUNO,
                'vip_elegible_at' => null,
            ]);
        }

        return false;
    }

    /**
     * Genera una invitación al Club VIP para un cliente elegible.
     */
    public function generarInvitacion(Cliente $cliente, User|int $usuario, string $canal = 'whatsapp'): VipInvitacion
    {
        $userId = $usuario instanceof User ? $usuario->id : (int) $usuario;

        return DB::transaction(function () use ($cliente, $userId, $canal) {
            $clienteLocked = Cliente::where('id', $cliente->id)->lockForUpdate()->firstOrFail();

            // Invalidar invitaciones previas vigentes
            VipInvitacion::where('cliente_id', $clienteLocked->id)
                ->where('estado', VipInvitacion::ESTADO_VIGENTE)
                ->update(['estado' => VipInvitacion::ESTADO_CANCELADA]);

            $diasVigenciaVal = Configuracion::where('clave', 'vip_invitacion_dias')->value('valor');
            if (is_array($diasVigenciaVal)) {
                $diasVigenciaVal = $diasVigenciaVal['valor'] ?? reset($diasVigenciaVal);
            }
            $diasVigencia = is_numeric($diasVigenciaVal) ? (int) $diasVigenciaVal : 7;

            $tokenRaw = Str::random(40);
            $tokenHash = hash('sha256', $tokenRaw);

            $invitacion = VipInvitacion::create([
                'cliente_id' => $clienteLocked->id,
                'token_hash' => $tokenHash,
                'canal' => $canal,
                'enviada_por' => $userId,
                'expira_at' => now()->addDays($diasVigencia),
                'estado' => VipInvitacion::ESTADO_VIGENTE,
            ]);

            $clienteLocked->update([
                'vip_estado' => Cliente::VIP_ESTADO_INVITADO,
            ]);

            // Guardar token plano en propiedad temporal para envío
            $invitacion->token_plano = $tokenRaw;
            $invitacion->url = route('vip.registro', ['token' => $tokenRaw]);

            return $invitacion;
        });
    }

    /**
     * Procesa el formulario público de registro VIP completado por el cliente.
     */
    public function registrarFormularioVip(VipInvitacion $invitacion, array $datos): bool
    {
        if (! $invitacion->esVigente()) {
            return false;
        }

        return DB::transaction(function () use ($invitacion, $datos) {
            $cliente = $invitacion->cliente()->lockForUpdate()->firstOrFail();

            $cliente->update([
                'nombre' => $datos['nombre'] ?? $cliente->nombre,
                'telefono' => $datos['telefono'] ?? $cliente->telefono,
                'email' => $datos['email'] ?? $cliente->email,
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'password' => bcrypt($datos['password']),
                'vip_estado' => Cliente::VIP_ESTADO_PENDIENTE,
                'acepta_tratamiento_datos' => true,
                'fecha_autorizacion_datos' => now(),
                'canal_autorizacion_datos' => 'formulario_vip',
                'autoriza_whatsapp' => ! empty($datos['autoriza_whatsapp']),
                'autoriza_email' => ! empty($datos['autoriza_email']),
            ]);

            $invitacion->update([
                'estado' => VipInvitacion::ESTADO_COMPLETADA,
                'usada_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Aprueba la membresía VIP de un cliente pendiente (Solo Admin / Gerente).
     */
    public function aprobarVip(Cliente $cliente, User|int $usuario): bool
    {
        $userId = $usuario instanceof User ? $usuario->id : (int) $usuario;
        $usuarioModel = $usuario instanceof User ? $usuario : (User::find($userId) ?? auth()->user());

        return DB::transaction(function () use ($cliente, $userId, $usuarioModel) {
            $clienteLocked = Cliente::where('id', $cliente->id)->lockForUpdate()->firstOrFail();

            $puntosBienvenidaVal = Configuracion::where('clave', 'vip_puntos_bienvenida')->value('valor');
            if (is_array($puntosBienvenidaVal)) {
                $puntosBienvenidaVal = $puntosBienvenidaVal['valor'] ?? reset($puntosBienvenidaVal);
            }
            $puntosBienvenida = is_numeric($puntosBienvenidaVal) ? (int) $puntosBienvenidaVal : 100;

            $clienteLocked->update([
                'vip_estado' => Cliente::VIP_ESTADO_ACTIVO,
                'tier' => Cliente::TIER_VIP,
                'vip_desde' => now(),
                'vip_aprobado_por' => $userId,
                'puntos_fidelidad' => $clienteLocked->puntos_fidelidad + $puntosBienvenida,
            ]);

            if ($usuarioModel) {
                event(new ClienteVipAprobado($clienteLocked, $usuarioModel));
            }

            return true;
        });
    }

    /**
     * Alias compatible con tests para aprobar solicitud VIP.
     */
    public function aprobarSolicitud(Cliente $cliente, User|int $usuario, ?string $notas = null): Cliente
    {
        $this->aprobarVip($cliente, $usuario);

        return $cliente->fresh();
    }

    /**
     * Rechaza la postulación VIP con un motivo documentado.
     */
    public function rechazarVip(Cliente $cliente, User|int $usuario, string $motivo): bool
    {
        $userId = $usuario instanceof User ? $usuario->id : (int) $usuario;

        return DB::transaction(function () use ($cliente, $userId, $motivo) {
            $clienteLocked = Cliente::where('id', $cliente->id)->lockForUpdate()->firstOrFail();

            $clienteLocked->update([
                'vip_estado' => Cliente::VIP_ESTADO_ELEGIBLE, // Vuelve a elegible si cumple
            ]);

            // Registrar motivo en la última invitación
            VipInvitacion::where('cliente_id', $clienteLocked->id)
                ->latest()
                ->first()?->update([
                    'motivo_rechazo' => $motivo,
                    'revisada_por' => $userId,
                ]);

            return true;
        });
    }

    /**
     * Suspende la membresía VIP de un cliente.
     */
    public function suspenderVip(Cliente $cliente, User|int $usuario, ?string $motivo = null): Cliente
    {
        return DB::transaction(function () use ($cliente) {
            $clienteLocked = Cliente::where('id', $cliente->id)->lockForUpdate()->firstOrFail();

            $clienteLocked->update([
                'vip_estado' => Cliente::VIP_ESTADO_SUSPENDIDO,
                'tier' => Cliente::TIER_FRECUENTE,
            ]);

            return $clienteLocked;
        });
    }

    /**
     * Reactiva la membresía VIP de un cliente suspendido.
     */
    public function reactivarVip(Cliente $cliente, User|int $usuario): bool
    {
        return DB::transaction(function () use ($cliente) {
            $clienteLocked = Cliente::where('id', $cliente->id)->lockForUpdate()->firstOrFail();

            $clienteLocked->update([
                'vip_estado' => Cliente::VIP_ESTADO_ACTIVO,
                'tier' => Cliente::TIER_VIP,
            ]);

            return true;
        });
    }
}
