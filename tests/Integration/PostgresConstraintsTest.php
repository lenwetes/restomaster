<?php

namespace Tests\Integration;

use App\Models\Configuracion;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Sucursal;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostgresConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create([
            'nombre' => 'SushiXpress Envigado',
            'codigo' => 'ENV-01',
            'activa' => true,
        ]);
    }

    /**
     * Test de restricción de unicidad compuesta (sucursal_id, numero) en mesas.
     */
    public function test_unicidad_compuesta_sucursal_y_numero_en_mesas(): void
    {
        Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'Mesa-10',
            'capacidad' => 4,
            'zona' => 'salon',
            'estado' => 'libre',
            'activo' => true,
        ]);

        $this->expectException(QueryException::class);

        // Intentar crear otra mesa con el mismo número en la misma sucursal debe lanzar QueryException
        Mesa::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => 'Mesa-10',
            'capacidad' => 2,
            'zona' => 'terraza',
            'estado' => 'libre',
            'activo' => true,
        ]);
    }

    /**
     * Test de unicidad compuesta (grupo, clave) en configuraciones del sistema.
     */
    public function test_unicidad_compuesta_grupo_y_clave_en_configuraciones(): void
    {
        Configuracion::create([
            'grupo' => 'sistema',
            'clave' => 'moneda_defecto',
            'valor' => 'COP',
        ]);

        $this->expectException(QueryException::class);

        // Clave duplicada en el mismo grupo debe fallar a nivel de BD
        Configuracion::create([
            'grupo' => 'sistema',
            'clave' => 'moneda_defecto',
            'valor' => 'USD',
        ]);
    }

    /**
     * Test de recreación de slug en Producto tras soft-delete.
     */
    public function test_recreacion_de_producto_con_mismo_slug_tras_soft_delete(): void
    {
        $producto1 = Producto::create([
            'nombre' => 'Maki Tempura Audit',
            'slug' => 'maki-tempura-audit',
            'precio' => 25000,
            'activo' => true,
        ]);

        $producto1->delete();
        $this->assertSoftDeleted('productos', ['id' => $producto1->id]);

        $producto2 = Producto::create([
            'nombre' => 'Maki Tempura Nuevo Audit',
            'slug' => 'maki-tempura-audit',
            'precio' => 28000,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('productos', ['id' => $producto2->id, 'slug' => 'maki-tempura-audit']);
    }
}
