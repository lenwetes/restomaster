<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Events\ComandaEnviada;
use App\Events\ItemListoParaServir;
use App\Events\MesaActualizada;
use App\Events\PedidoQrSolicitado;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\MesaService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificacionesTiempoRealTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $mesero;

    protected User $cocinero;

    protected Mesa $mesa;

    protected Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sushixpress Central']);

        $roleMesero = Role::firstOrCreate(['slug' => 'mesero'], ['nombre' => 'Mesero']);
        $roleCocina = Role::firstOrCreate(['slug' => 'cocina'], ['nombre' => 'Cocinero']);

        $this->mesero = User::create([
            'name' => 'Carlos Mesero',
            'email' => 'mesero@sushixpress.com',
            'password' => bcrypt('password'),
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->cocinero = User::create([
            'name' => 'Kenji Sushi Chef',
            'email' => 'chef@sushixpress.com',
            'password' => bcrypt('password'),
            'role_id' => $roleCocina->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => '12',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => MesaEstado::LIBRE->value,
            'mesero_id' => $this->mesero->id,
        ]);

        $this->producto = Producto::create([
            'nombre' => 'Philadelphia Especial Roll',
            'slug' => 'philadelphia-especial-roll',
            'precio' => 38000,
            'activo' => true,
            'sucursal_id' => $this->sucursal->id,
        ]);
    }

    public function test_enviar_a_cocina_emite_evento_comanda_enviada(): void
    {
        Event::fake([ComandaEnviada::class]);

        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedido([
            'mesa_id' => $this->mesa->id,
            'sucursal_id' => $this->sucursal->id,
            'mesero_id' => $this->mesero->id,
        ], [
            ['producto_id' => $this->producto->id, 'cantidad' => 2],
        ], $this->mesero);

        $pedidoService->enviarACocina($pedido);

        Event::assertDispatched(ComandaEnviada::class, function ($event) use ($pedido) {
            return $event->pedidoId === $pedido->id &&
                   $event->mesaNumero === '12' &&
                   $event->broadcastOn()[0]->name === "private-cocina.{$this->sucursal->id}";
        });
    }

    public function test_marcar_item_listo_emite_evento_item_listo_para_servir_al_mesero(): void
    {
        Event::fake([ItemListoParaServir::class]);

        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedido([
            'mesa_id' => $this->mesa->id,
            'sucursal_id' => $this->sucursal->id,
            'mesero_id' => $this->mesero->id,
        ], [
            ['producto_id' => $this->producto->id, 'cantidad' => 1],
        ], $this->mesero);

        $pedidoService->enviarACocina($pedido);
        $item = $pedido->items()->first();

        $pedidoService->marcarItemListo($item);

        Event::assertDispatched(ItemListoParaServir::class, function ($event) use ($item) {
            return $event->itemId === $item->id &&
                   $event->meseroId === $this->mesero->id &&
                   $event->mesaNumero === '12' &&
                   $event->broadcastOn()[0]->name === "private-mesero.{$this->mesero->id}";
        });
    }

    public function test_crear_pedido_desde_qr_emite_pedido_qr_solicitado(): void
    {
        Event::fake([PedidoQrSolicitado::class]);

        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->crearPedidoDesdeQr($this->mesa, [
            ['producto_id' => $this->producto->id, 'cantidad' => 1],
        ], 'Cliente Mesa 12');

        Event::assertDispatched(PedidoQrSolicitado::class, function ($event) use ($pedido) {
            return $event->pedidoId === $pedido->id &&
                   $event->mesaNumero === '12' &&
                   $event->broadcastOn()[0]->name === "private-sucursal.{$this->sucursal->id}";
        });
    }

    public function test_cambiar_estado_mesa_emite_mesa_actualizada(): void
    {
        Event::fake([MesaActualizada::class]);

        $mesaService = app(MesaService::class);
        $mesaService->cambiarEstado($this->mesa, MesaEstado::OCUPADA);

        Event::assertDispatched(MesaActualizada::class, function ($event) {
            return $event->mesaId === $this->mesa->id &&
                   $event->numero === '12' &&
                   $event->estado === MesaEstado::OCUPADA->value &&
                   $event->broadcastOn()[0]->name === "private-sucursal.{$this->sucursal->id}";
        });
    }
}
