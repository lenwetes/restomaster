<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Sucursal $sucursal;

    protected User $mesero;

    protected Mesa $mesa;

    protected Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sucursal Offline Test']);
        $roleMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);

        $this->mesero = User::factory()->create([
            'role_id' => $roleMesero->id,
            'sucursal_id' => $this->sucursal->id,
            'name' => 'Mesero Offline',
            'activo' => true,
        ]);

        $this->mesa = Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 4,
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'libre',
        ]);

        $this->producto = Producto::create([
            'nombre' => 'Nigiri Salmon Test',
            'slug' => 'nigiri-salmon-test',
            'precio' => 32000,
            'costo' => 12000,
            'area_cocina' => 'sushi',
            'activo' => true,
        ]);
    }

    public function test_sincroniza_comanda_offline_y_crea_pedido_en_cocina(): void
    {
        $this->actingAs($this->mesero);
        $uuid = (string) Str::uuid();

        $payload = [
            'comandas' => [
                [
                    'uuid' => $uuid,
                    'mesa_id' => $this->mesa->id,
                    'sucursal_id' => $this->sucursal->id,
                    'nombre_cliente' => 'Cliente Offline',
                    'subtotal' => 64000,
                    'total' => 64000,
                    'items' => [
                        [
                            'producto_id' => $this->producto->id,
                            'nombre' => $this->producto->nombre,
                            'cantidad' => 2,
                            'precio' => 32000,
                            'area_cocina' => 'sushi',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/pos/sincronizar-offline', $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'total_procesados' => 1,
            ]);

        $pedido = Pedido::where('idempotencia_uuid', $uuid)->first();
        $this->assertNotNull($pedido);
        $this->assertSame('en_cocina', $pedido->estado);
        $this->assertSame(64000.0, (float) $pedido->total);
        $this->assertSame('Cliente Offline', $pedido->nombre_cliente);
        $this->assertSame($this->mesa->id, $pedido->mesa_id);
        $this->assertCount(1, $pedido->items);
        $this->assertSame(2, $pedido->items->first()->cantidad);

        $this->mesa->refresh();
        $this->assertSame('ocupada', $this->mesa->estado);
    }

    public function test_sincronizacion_offline_es_estrictamente_idempotente(): void
    {
        $this->actingAs($this->mesero);
        $uuid = (string) Str::uuid();

        $payload = [
            'comandas' => [
                [
                    'uuid' => $uuid,
                    'mesa_id' => $this->mesa->id,
                    'sucursal_id' => $this->sucursal->id,
                    'subtotal' => 32000,
                    'total' => 32000,
                    'items' => [
                        [
                            'producto_id' => $this->producto->id,
                            'nombre' => $this->producto->nombre,
                            'cantidad' => 1,
                            'precio' => 32000,
                        ],
                    ],
                ],
            ],
        ];

        // Primera llamada
        $res1 = $this->postJson('/api/pos/sincronizar-offline', $payload);
        $res1->assertOk();

        // Segunda llamada con el mismo UUID
        $res2 = $this->postJson('/api/pos/sincronizar-offline', $payload);
        $res2->assertOk()
            ->assertJsonPath('sincronizados.0.estado', 'ya_existia');

        $this->assertSame(1, Pedido::where('idempotencia_uuid', $uuid)->count());
    }
}
