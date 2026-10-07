<?php

namespace App\Livewire\Concerns;

use App\Models\Insumo;
use App\Models\Proveedor;
use App\Services\ReposicionRapidaService;
use Throwable;

trait ManejaReposicionRapida
{
    public bool $modalReponer = false;

    public ?int $reponerInsumoId = null;

    public ?array $reponerDatos = null;

    public string $reponerPestana = 'solicitar'; // 'solicitar' | 'ingresar'

    public ?int $reponerProveedorId = null;

    public float $reponerCantidad = 0.0;

    public float $reponerCostoUnitario = 0.0;

    public string $reponerFuentePago = 'externo'; // 'externo' | 'caja_menor' | 'credito'

    public string $reponerNumeroDocumento = '';

    public bool $reponerMostrarCrearProveedor = false;

    public array $reponerNuevoProveedor = [
        'nombre' => '',
        'telefono' => '',
        'email' => '',
        'nit' => '',
        'contacto' => '',
    ];

    public ?array $reponerEnlacesGenerados = null;

    public ?string $reponerMensajeExito = null;

    public ?string $reponerMensajeError = null;

    /**
     * Abre el modal de reposición rápida precargando datos y cálculo de stock de seguridad.
     */
    public function abrirModalReponer(int $insumoId): void
    {
        $this->reset([
            'reponerMensajeExito',
            'reponerMensajeError',
            'reponerMostrarCrearProveedor',
            'reponerEnlacesGenerados',
            'reponerNumeroDocumento',
        ]);

        $this->reponerNuevoProveedor = [
            'nombre' => '',
            'telefono' => '',
            'email' => '',
            'nit' => '',
            'contacto' => '',
        ];

        try {
            $service = app(ReposicionRapidaService::class);
            $datos = $service->prepararDatosInsumo($insumoId);

            $this->reponerInsumoId = $insumoId;
            $this->reponerDatos = $datos;
            $this->reponerPestana = 'solicitar';

            $this->reponerProveedorId = $datos['proveedor_habitual_id']
                ?? ($datos['proveedores_disponibles'][0]['id'] ?? null);

            $this->reponerCantidad = (float) $datos['cantidad_sugerida'];
            $this->reponerCostoUnitario = (float) $datos['costo_unitario_estimado'];
            $this->reponerFuentePago = $datos['hay_turno_caja_abierto'] ? 'caja_menor' : 'externo';

            $this->actualizarEnlacesPedido();
            $this->modalReponer = true;
        } catch (Throwable $e) {
            $this->reponerMensajeError = 'No se pudo cargar el insumo: '.$e->getMessage();
        }
    }

    /**
     * Cambia de pestaña (solicitar pedido vs ingresar a stock).
     */
    public function setReponerPestana(string $pestana): void
    {
        if (in_array($pestana, ['solicitar', 'ingresar'], true)) {
            $this->reponerPestana = $pestana;
            $this->reponerMensajeExito = null;
            $this->reponerMensajeError = null;
        }
    }

    /**
     * Actualiza el proveedor seleccionado y recalcula enlaces de WhatsApp y correo.
     */
    public function updatedReponerProveedorId(): void
    {
        $this->actualizarEnlacesPedido();
    }

    /**
     * Recalcula los enlaces cuando cambia la cantidad solicitada.
     */
    public function updatedReponerCantidad(): void
    {
        $this->actualizarEnlacesPedido();
    }

    /**
     * Genera dinámicamente los enlaces y mensajes según el proveedor y cantidad actual.
     */
    public function actualizarEnlacesPedido(): void
    {
        if (! $this->reponerInsumoId || ! $this->reponerProveedorId) {
            $this->reponerEnlacesGenerados = null;

            return;
        }

        $insumo = Insumo::find($this->reponerInsumoId);
        $proveedor = Proveedor::find($this->reponerProveedorId);

        if ($insumo && $proveedor) {
            $service = app(ReposicionRapidaService::class);
            $this->reponerEnlacesGenerados = $service->generarEnlacesPedido(
                $insumo,
                $proveedor,
                max(0.1, (float) $this->reponerCantidad)
            );
        } else {
            $this->reponerEnlacesGenerados = null;
        }
    }

    /**
     * Guarda un nuevo proveedor creado en caliente y lo asocia automáticamente.
     */
    public function guardarNuevoProveedorReponer(): void
    {
        $this->validate([
            'reponerNuevoProveedor.nombre' => 'required|string|min:2|max:120',
            'reponerNuevoProveedor.telefono' => 'nullable|string|max:30',
            'reponerNuevoProveedor.email' => 'nullable|email|max:120',
            'reponerNuevoProveedor.nit' => 'nullable|string|max:30',
        ], [
            'reponerNuevoProveedor.nombre.required' => 'El nombre de la empresa/proveedor es obligatorio.',
            'reponerNuevoProveedor.email.email' => 'El correo electrónico no tiene un formato válido.',
        ]);

        try {
            $service = app(ReposicionRapidaService::class);
            $proveedor = $service->crearProveedorRapido($this->reponerInsumoId, $this->reponerNuevoProveedor);

            // Refrescar lista de proveedores y seleccionar el nuevo
            $this->reponerDatos = $service->prepararDatosInsumo($this->reponerInsumoId);
            $this->reponerProveedorId = $proveedor->id;
            $this->reponerMostrarCrearProveedor = false;
            $this->actualizarEnlacesPedido();

            $this->reponerMensajeExito = "Proveedor '{$proveedor->nombre}' guardado y vinculado con éxito.";
        } catch (Throwable $e) {
            $this->reponerMensajeError = 'Error al crear proveedor: '.$e->getMessage();
        }
    }

    /**
     * Registra formalmente la orden de pedido solicitada al proveedor.
     */
    public function solicitarPedidoProveedor(): void
    {
        if (! $this->reponerInsumoId || ! $this->reponerProveedorId) {
            $this->reponerMensajeError = 'Selecciona un proveedor válido para el pedido.';

            return;
        }

        if ((float) $this->reponerCantidad <= 0) {
            $this->reponerMensajeError = 'Ingresa una cantidad mayor a cero.';

            return;
        }

        try {
            $service = app(ReposicionRapidaService::class);
            $insumo = Insumo::findOrFail($this->reponerInsumoId);

            $compra = $service->registrarOrdenPedidoPendiente(
                insumo: $insumo,
                proveedorId: $this->reponerProveedorId,
                cantidad: (float) $this->reponerCantidad,
                costoEstimado: (float) $this->reponerCostoUnitario,
                usuario: auth()->user()
            );

            $this->reponerMensajeExito = "Orden {$compra->numero_factura} registrada como 'Solicitada'. Puedes notificar al proveedor mediante WhatsApp o Correo.";
        } catch (Throwable $e) {
            $this->reponerMensajeError = 'Error al registrar pedido: '.$e->getMessage();
        }
    }

    /**
     * Procesa el ingreso físico inmediato del insumo al Kardex e Inventario.
     */
    public function confirmarIngresoDirectoStock(): void
    {
        if (! $this->reponerInsumoId) {
            $this->reponerMensajeError = 'No hay insumo seleccionado.';

            return;
        }

        if ((float) $this->reponerCantidad <= 0) {
            $this->reponerMensajeError = 'La cantidad a ingresar debe ser mayor a cero.';

            return;
        }

        if ((float) $this->reponerCostoUnitario < 0) {
            $this->reponerMensajeError = 'El costo unitario no puede ser negativo.';

            return;
        }

        try {
            $service = app(ReposicionRapidaService::class);
            $resultado = $service->ingresarStockDirecto(
                insumoId: $this->reponerInsumoId,
                cantidad: (float) $this->reponerCantidad,
                costoUnitario: (float) $this->reponerCostoUnitario,
                fuentePago: $this->reponerFuentePago,
                proveedorId: $this->reponerProveedorId,
                numeroDocumento: $this->reponerNumeroDocumento,
                usuario: auth()->user()
            );

            $this->reponerMensajeExito = $resultado['mensaje'];

            // Refrescar datos en el componente padre si existen métodos
            if (method_exists($this, 'dispatch')) {
                $this->dispatch('inventario-actualizado');
                $this->dispatch('notificar', [
                    'tipo' => 'exito',
                    'mensaje' => $resultado['mensaje'],
                ]);
            }

            // Auto-cerrar tras breve confirmación visual
            $this->modalReponer = false;
        } catch (Throwable $e) {
            $this->reponerMensajeError = 'Error al ingresar stock: '.$e->getMessage();
        }
    }

    /**
     * Cierra el modal de reposición rápida.
     */
    public function cerrarModalReponer(): void
    {
        $this->modalReponer = false;
        $this->reponerInsumoId = null;
        $this->reponerDatos = null;
        $this->reponerEnlacesGenerados = null;
        $this->reponerMensajeExito = null;
        $this->reponerMensajeError = null;
    }
}
