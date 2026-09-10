<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FixInteractivosEnRootTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $rolGerente = Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);

        $this->gerente = User::factory()->create([
            'role_id' => $rolGerente->id,
            'email_verified_at' => now(),
        ]);
    }

    public static function paginasConAccionesEnHeader(): array
    {
        return [
            'reservas' => ['reservas', 'reservas.index', 'wire:click="abrirCrear"'],
            'cxp' => ['cxp', 'cxp.index', 'wire:click="abrirCrear"'],
            'cocina kds' => ['cocina', 'areaSeleccionada', "wire:click=\"\$set('areaSeleccionada'"],
            'caja control' => ['caja', 'abrirModalNuevaCaja', 'wire:click="abrirModalNuevaCaja"'],
        ];
    }

    #[DataProvider('paginasConAccionesEnHeader')]
    public function test_acciones_interactivas_quedan_dentro_del_root(string $ruta, string $componente, string $directiva): void
    {
        $html = $this->actingAs($this->gerente)->get($ruta)->assertOk()->getContent();

        $posMain = strpos($html, '<main');
        $this->assertNotFalse($posMain);
        $contenido = substr($html, $posMain);

        $posRaiz = strpos($contenido, 'wire:id=');
        $this->assertNotFalse($posRaiz, "No se encontró el root del componente {$componente} en la ruta {$ruta}.");

        $posAccion = strpos($contenido, $directiva);
        $this->assertNotFalse($posAccion, "No se encontró {$directiva} en la ruta {$ruta}.");
        $this->assertGreaterThan(
            $posRaiz,
            $posAccion,
            "{$directiva} quedó fuera del root Livewire (en el x-slot header) y su click no funciona en {$ruta}."
        );
    }
}
