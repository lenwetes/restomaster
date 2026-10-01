<?php

namespace App\Livewire\Concerns;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Services\ClienteService;
use App\Services\FidelizacionService;

/**
 * Trait para la gestión de clientes, fidelización y Habeas Data en el POS.
 *
 * @property float $subtotal
 * @property float $descuento
 * @property int $puntosDisponibles
 * @property int $puntosCanjeados
 * @property float $descuentoPuntos
 * @property ?int $clienteId
 * @property ?int $direccionId
 * @property string $nombreCliente
 * @property string $telefonoCliente
 * @property string $direccionDelivery
 * @property array $sugerenciasClientes
 * @property bool $mostrarSugerencias
 * @property bool $mostrarModalHabeasData
 * @property ?Cliente $clienteParaAutorizar
 * @property string $habeasNombre
 * @property string $habeasTelefono
 * @property string $habeasEmail
 * @property string $habeasDireccion
 * @property string $habeasCanal
 * @property string $habeasNotas
 * @property mixed $autorizacionHabeasDataDoc
 *
 * @method float getSubtotalProperty()
 */
trait ManejaClientePos
{
    public function seleccionarCliente(int $id): void
    {
        $cliente = Cliente::with('direcciones')->find($id);
        if ($cliente) {
            $this->clienteId = $cliente->id;
            $this->nombreCliente = $cliente->nombre;
            $this->telefonoCliente = $cliente->telefono ?? '';
            $this->puntosDisponibles = $cliente->puntos_fidelidad ?? 0;

            $dir = $cliente->direccionPredeterminada ?? $cliente->direcciones->first();
            if ($dir) {
                $this->direccionId = $dir->id;
                $this->direccionDelivery = $dir->direccion_completa;
            }
        }
    }

    public function desvincularCliente(): void
    {
        $this->clienteId = null;
        $this->direccionId = null;
        $this->nombreCliente = '';
        $this->telefonoCliente = '';
        $this->direccionDelivery = '';
        $this->puntosDisponibles = 0;
        $this->puntosCanjeados = 0;
        $this->descuentoPuntos = 0.0;
        $this->sugerenciasClientes = [];
        $this->mostrarSugerencias = false;
    }

    public function updatedNombreCliente(string $valor): void
    {
        $termino = trim($valor);
        if (mb_strlen($termino) >= 4) {
            $clienteService = app(ClienteService::class);
            $this->sugerenciasClientes = $clienteService->buscarPredictivo($termino, 6)
                ->map(fn (Cliente $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'telefono' => $c->telefono,
                    'email' => $c->email,
                    'tier' => $c->tier,
                    'badge_class' => $c->badgeTier()['color'] ?? '',
                    'badge_label' => $c->badgeTier()['label'] ?? strtoupper($c->tier ?? 'OCASIONAL'),
                    'puntos' => $c->puntos_fidelidad,
                ])
                ->all();
            $this->mostrarSugerencias = count($this->sugerenciasClientes) > 0;
        } else {
            $this->sugerenciasClientes = [];
            $this->mostrarSugerencias = false;
        }
    }

    public function seleccionarClientePredictivo(int $id): void
    {
        $this->seleccionarCliente($id);
        $this->mostrarSugerencias = false;
        $this->sugerenciasClientes = [];
    }

    public function cerrarSugerencias(): void
    {
        $this->mostrarSugerencias = false;
        $this->sugerenciasClientes = [];
    }

    public function abrirModalHabeasData(): void
    {
        if ($this->clienteId) {
            $cliente = Cliente::find($this->clienteId);
            if ($cliente) {
                $this->habeasNombre = $cliente->nombre;
                $this->habeasTelefono = $cliente->telefono ?? '';
                $this->habeasEmail = $cliente->email ?? '';
                $this->habeasAcepta = (bool) $cliente->acepta_tratamiento_datos;
                $this->habeasWhatsapp = $cliente->autoriza_whatsapp ?? true;
                $this->habeasEmailPromos = $cliente->autoriza_email ?? true;
                $this->habeasDireccion = $this->direccionDelivery;
            }
        } else {
            $this->habeasNombre = $this->nombreCliente;
            $this->habeasTelefono = $this->telefonoCliente;
            $this->habeasEmail = '';
            $this->habeasAcepta = false;
            $this->habeasWhatsapp = true;
            $this->habeasEmailPromos = true;
            $this->habeasDireccion = $this->direccionDelivery;
        }

        $this->mostrarModalHabeasData = true;
    }

    public function guardarHabeasData(): void
    {
        $this->validate([
            'habeasNombre' => 'required|string|min:3|max:100',
            'habeasAcepta' => 'accepted',
            'habeasTelefono' => 'nullable|string|max:20',
            'habeasEmail' => 'nullable|email|max:100',
            'habeasDireccion' => 'nullable|string|max:255',
        ], [
            'habeasNombre.required' => 'El nombre del cliente es obligatorio.',
            'habeasNombre.min' => 'El nombre debe tener al menos 3 letras.',
            'habeasAcepta.accepted' => 'Debe aceptar la política de tratamiento de datos personales (Habeas Data).',
            'habeasEmail.email' => 'Ingrese un correo electrónico válido.',
        ]);

        $clienteService = app(ClienteService::class);

        if (! $this->clienteId) {
            $cliente = $clienteService->buscarOcrearOcasional($this->habeasNombre);
            $this->clienteId = $cliente->id;
            $this->nombreCliente = $cliente->nombre;
        } else {
            $cliente = Cliente::findOrFail($this->clienteId);
            $cliente->update(['nombre' => trim($this->habeasNombre)]);
            $this->nombreCliente = $cliente->nombre;
        }

        $clienteService->registrarConsentimientoHabeasData($cliente, [
            'telefono' => $this->habeasTelefono ?: null,
            'email' => $this->habeasEmail ?: null,
            'direccion' => $this->habeasDireccion ?: null,
            'acepta_tratamiento_datos' => $this->habeasAcepta,
            'canal_autorizacion_datos' => 'pos_terminal',
            'autoriza_whatsapp' => $this->habeasWhatsapp,
            'autoriza_email' => $this->habeasEmailPromos,
        ]);

        $this->telefonoCliente = $cliente->fresh()->telefono ?? '';
        if (! empty($this->habeasDireccion)) {
            $this->direccionDelivery = $this->habeasDireccion;
        }

        $this->mostrarModalHabeasData = false;
        session()->flash('notificacion', "¡Cliente {$cliente->nombre} registrado y autorizado con éxito!");
    }

    public function canjearPuntos(int $puntos): void
    {
        $this->authorize('canjearPuntos', Pedido::class);

        if ($puntos > $this->puntosDisponibles) {
            $puntos = $this->puntosDisponibles;
        }

        $subtotal = method_exists($this, 'getSubtotalProperty') ? (float) $this->getSubtotalProperty() : (float) ($this->subtotal ?? 0.0);
        $descuentoActual = (float) ($this->descuento ?? 0.0);
        $remanente = max(0.0, $subtotal - $descuentoActual);
        $descuentoCalculado = app(FidelizacionService::class)->calcularDescuentoPorPuntos($puntos);
        if ($descuentoCalculado > $remanente) {
            $descuento = $remanente;
            $puntos = (int) ceil($descuento / 10);
        } else {
            $descuento = $descuentoCalculado;
        }

        $this->puntosCanjeados = $puntos;
        $this->descuentoPuntos = $descuento;
    }

    public function limpiarCanje(): void
    {
        $this->puntosCanjeados = 0;
        $this->descuentoPuntos = 0.0;
    }

    public function updatedDescuentoPuntos(): void
    {
        if ($this->puntosCanjeados > 0 && $this->clienteId) {
            $this->descuentoPuntos = min($this->descuentoPuntos, app(FidelizacionService::class)->calcularDescuentoPorPuntos($this->puntosCanjeados));
        } else {
            $this->descuentoPuntos = 0.0;
        }
    }

    public function updatedPuntosCanjeados(): void
    {
        if ($this->puntosCanjeados > 0 && $this->clienteId) {
            $this->canjearPuntos($this->puntosCanjeados);
        } else {
            $this->limpiarCanje();
        }
    }
}
