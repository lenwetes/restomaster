<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\VipInvitacion;
use App\Services\ClubVipService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class RegistroVipPublicoController extends Controller
{
    /**
     * Muestra el formulario público para completar el registro del Club VIP.
     */
    public function mostrar(string $token)
    {
        $tokenHash = hash('sha256', $token);
        $invitacion = VipInvitacion::where('token_hash', $tokenHash)->with('cliente')->first();

        if (! $invitacion || ! $invitacion->esVigente()) {
            return view('cliente.vip-invitacion-invalida', [
                'motivo' => ! $invitacion ? 'Invitación no encontrada' : 'Esta invitación ha expirado o ya fue utilizada.',
            ]);
        }

        return view('cliente.vip-registro-publico', [
            'invitacion' => $invitacion,
            'cliente' => $invitacion->cliente,
            'token' => $token,
        ]);
    }

    /**
     * Procesa y valida el formulario completado por el comensal.
     */
    public function procesar(Request $request, string $token)
    {
        $tokenHash = hash('sha256', $token);
        $invitacion = VipInvitacion::where('token_hash', $tokenHash)->first();

        if (! $invitacion || ! $invitacion->esVigente()) {
            return redirect()->route('vip.registro', $token)->with('error', 'La invitación ya no es válida.');
        }

        $validated = $request->validate([
            'nombre' => 'required|string|min:3|max:100',
            'telefono' => 'required|string|max:20',
            'email' => 'required|email|max:100',
            'fecha_nacimiento' => 'required|date|before:-18 years',
            'password' => ['required', 'confirmed', Password::min(8)],
            'acepta_tratamiento_datos' => 'accepted',
            'autoriza_whatsapp' => 'nullable|boolean',
            'autoriza_email' => 'nullable|boolean',
        ], [
            'fecha_nacimiento.before' => 'Debes ser mayor de 18 años para ingresar al Club VIP.',
            'acepta_tratamiento_datos.accepted' => 'Es requisito aceptar la política de tratamiento de datos personales.',
        ]);

        $completado = app(ClubVipService::class)->registrarFormularioVip($invitacion, $validated);

        if (! $completado) {
            return back()->with('error', 'No fue posible procesar tu postulación.');
        }

        return view('cliente.vip-registro-completado', [
            'cliente' => $invitacion->cliente,
        ]);
    }
}
