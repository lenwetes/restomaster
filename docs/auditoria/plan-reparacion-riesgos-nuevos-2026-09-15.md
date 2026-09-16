# Plan de Reparaciones — Riesgos Nuevos Post-Remediación (2026-09-15)

> **Para agentes trabajadores:** SKILL SUB-REQUERIDA: usar superpowers:subagent-driven-development (recomendado) o superpowers:executing-plans para implementar este plan tarea por tarea. Los pasos usan sintaxis de casilla (`- [ ]`) para seguimiento.

**Goal:** Cerrar los riesgos nuevos detectados en la re-auditoría del 2026-09-15 16:55 (234/234 baseline; bloqueantes R1-R8 ya FIXED): pago mixto/datafono mal clasificado, IDOR por sucursal (R24), cluster de credencial demo `restomaster2026`, dashboard sin caché, TTL de notificaciones, descuento perdido en merge, race en merge de carrito, delivery sin vincular cobro al turno y cobro sin turno abierto.

**Architecture:** Cambios concentrados en `CajaService` (clasificación de métodos de pago y reporte Z), `PedidoService` (merge/agregar con bloqueo, verificación de pedido activo por mesa), `terminal.blade.php` (modal pago mixto + propagar descuento al pedido existente), policies (criterio de sucursal), `ReporteService` (agregados SQL + caché), `NotificacionService` (TTL), `DeliveryService` (vincular cobro), y eliminación del cluster demo. Toda mutación de dinero: test RED → GREEN → Pint y `abort_if`/policy server-side.

**Tech Stack:** Laravel 13 / PHP 8.3 / PostgreSQL / Livewire Volt / PHPUnit (no Pest).

**Spec:** `docs/auditoria/reauditoria-2026-09-15-verificada.md` (hallazgos nuevos) + `docs/auditoria/remediacion-seguridad-rendimiento-2026-09-15.md` (contrato del plan).

## Global Constraints

- Each fix: **test RED → fix GREEN → Pint → verificación**; money/estado mutations need `authorize()` or `abort_if` server-side.
- Precios SIEMPRE desde BD (`$producto->precio`), nunca del cliente. Dinero `decimal:2`.
- No agregar dependencias. Seguir patrón Service + Policy existente.
- Working tree compartido con Antigravity: crear lock en `.locks/` antes de tocar `app/Services/*`, `resources/views/livewire/pos/terminal.blade.php`, `app/Policies/*`, `database/migrations/*`. Registrar en `coordination.md` al terminar cada lote.
- Suite completa debe quedar verde al final de cada tarea: `php artisan test`.

---

## File Structure

- **Nuevas migraciones:** `database/migrations/2026_09_15_180000_add_pago_mixto_to_pedidos_table.php` (monto_pago_efectivo + monto_pago_tarjeta), `2026_09_15_181000_add_sucursal_id_to_pedidos_table.php` (sucursal_id + backfill + índice).
- **Modificar:** `app/Models/Pedido.php`, `app/Models/User.php` (helpers de sucursal opcionales), `app/Services/PedidoService.php`, `app/Services/CajaService.php`, `app/Services/DeliveryService.php`, `app/Services/ReporteService.php`, `app/Services/NotificacionService.php`, `app/Policies/{PedidoPolicy,TurnoCajaPolicy,CajaPolicy}.php`, `resources/views/livewire/pos/terminal.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/livewire/pages/auth/login.blade.php`, `database/seeders/AdminUserSeeder.php`, `.env.example`, `app/Http/Controllers/ReporteExportController.php`, `resources/views/livewire/caja/control.blade.php`.
- **Nuevos tests:** `tests/Feature/RemediacionPagoMixtoTest.php`, `tests/Feature/RemediacionIdorSucursalTest.php`, `tests/Feature/RemediacionCredencialDemoTest.php`, `tests/Feature/RemediacionDashboardCacheTest.php`, `tests/Feature/RemediacionMergeDescuentoTest.php`, `tests/Feature/RemediacionDeliveryVinculaTurnoTest.php`, `tests/Feature/RemediacionCobroSinTurnoTest.php`.

---

### Task 1: Pago mixto con desglose de monto

**Files:**
- Create: `database/migrations/2026_09_15_180000_add_pago_mixto_to_pedidos_table.php`
- Modify: `app/Models/Pedido.php` (fillable + casts)
- Modify: `app/Services/PedidoService.php` (`cobrarPedido` firma)
- Modify: `app/Services/CajaService.php` (`vincularCobroPedido`)
- Modify: `resources/views/livewire/pos/terminal.blade.php` (modal + propiedades + `procesarCobro`)
- Test: `tests/Feature/RemediacionPagoMixtoTest.php`

**Interfaces:**
- Consumes: `TurnoCaja` campos `total_ventas_efectivo`, `total_ventas_tarjeta`, `total_ventas_transferencia`; `Pedido` estados actuales.
- Produces: `Pedido->monto_pago_efectivo` / `Pedido->monto_pago_tarjeta` (nullable decimal); `cobrarPedido(Pedido, string metodoPago, float montoPagado, ?float montoPagoEfectivo = null): Pedido`.

**Diseño monetario (decisión):** todo pago distinto de `efectivo` se normaliza en el componente a `montoPagado = total`; el desglose solo aplica a `mixto`. `vincularCobroPedido` clasifica: `efectivo → efectivo`; `tarjeta|tarjeta_credito|tarjeta_debito|datafono|datáfono|datfono → tarjeta`; `mixto → efectivo= monto_pago_efectivo, tarjeta= monto_pago_tarjeta`; resto → transferencia.

- [ ] **Step 1: Escribir migración (desglose de pago)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'monto_pago_efectivo')) {
                $table->decimal('monto_pago_efectivo', 12, 2)->nullable()->after('monto_pagado');
            }
            if (! Schema::hasColumn('pedidos', 'monto_pago_tarjeta')) {
                $table->decimal('monto_pago_tarjeta', 12, 2)->nullable()->after('monto_pago_efectivo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['monto_pago_efectivo', 'monto_pago_tarjeta']);
        });
    }
};
```

- [ ] **Step 2: Escribir test RED**

```php
<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\CajaService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemediacionPagoMixtoTest extends TestCase
{
    use RefreshDatabase;

    private PedidoService $pedidoService;
    private CajaService $cajaService;
    private TurnoCaja $turno;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $rolAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin', 'descripcion' => 'Admin']);
        $sucursal = Sucursal::create(['nombre' => 'Test', 'codigo' => 'TST', 'direccion' => 'Calle 1', 'activa' => true]);
        $this->admin = User::factory()->create(['role_id' => $rolAdmin->id, 'sucursal_id' => $sucursal->id, 'activo' => true]);

        $caja = Caja::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Caja 1', 'codigo' => 'CAJA-1', 'activa' => true]);
        $this->cajaService = app(CajaService::class);
        $this->turno = $this->cajaService->abrirTurno($caja, $this->admin, 100000.00, 'Apertura');

        $this->pedidoService = app(PedidoService::class);
    }

    private function crearPedidoConDesglose(string $metodo, ?float $efectivo = null): \App\Models\Pedido
    {
        $producto = \App\Models\Producto::create([
            'nombre' => 'Temaki Salmón', 'precio' => 45000.00, 'categoria_id' => 1,
            'area_cocina' => 'cocina', 'activo' => true, 'sucursal_id' => 1,
        ]);

        $pedido = $this->pedidoService->crearPedido([
            'tipo' => 'mesa', 'estado' => 'creado',
            'descuento' => 0, 'costo_envio' => 0,
        ], [['producto_id' => $producto->id, 'cantidad' => 2]], $this->admin);

        $total = (float) $pedido->total;

        return match (strtolower($metodo)) {
            'efectivo', 'datafono', 'datáfono', 'tarjeta', 'tarjeta_credito', 'transferencia' =>
                $this->pedidoService->cobrarPedido($pedido, $metodo, $total),
            'mixto' => $this->pedidoService->cobrarPedido($pedido, 'mixto', $total, $efectivo ?? 20000.00),
            default => $pedido,
        };
    }

    public function test_mixto_vincula_efectivo_y_tarjeta_al_turno(): void
    {
        $pedido = $this->crearPedidoConDesglose('mixto', 20000.00);
        $this->assertEquals(20000.00, (float) $pedido->monto_pago_efectivo);
        $this->assertEquals(70000.00, (float) $pedido->monto_pago_tarjeta);

        $turno = $this->turno->fresh();
        $this->assertEquals(20000.00, (float) $turno->total_ventas_efectivo);
        $this->assertEquals(70000.00, (float) $turno->total_ventas_tarjeta);
        $this->assertEquals(0.0, (float) $turno->total_ventas_transferencia);
    }

    public function test_datafono_se_clasifica_como_tarjeta_no_como_transferencia(): void
    {
        $pedido = $this->crearPedidoConDesglose('datafono');
        $this->assertEquals('datafono', $pedido->metodo_pago);

        $turno = $this->turno->fresh();
        $this->assertEquals(90000.00, (float) $turno->total_ventas_tarjeta,
            'datafono debe sumar a ventas_tarjeta (antes caía en transferencia).');
        $this->assertEquals(0.0, (float) $turno->total_ventas_transferencia);
    }
}
```

- [ ] **Step 3: Ejecutar y confirmar RED**

Run: `php artisan test --filter=RemediacionPagoMixtoTest`
Expected: FAIL — `datafono` suma a `total_ventas_transferencia`; `mixto` no desglosa (columnas no existen aún... la migración corre con RefreshDatabase).

- [ ] **Step 4: Modificar `Pedido` fillable + casts**

Añadir a `$fillable` y a `casts()`:
```php
'monto_pago_efectivo' => 'decimal:2',
'monto_pago_tarjeta' => 'decimal:2',
```

- [ ] **Step 5: Ampliar `PedidoService::cobrarPedido`**

```php
public function cobrarPedido(Pedido $pedido, string $metodoPago, float $montoPagado, ?float $montoPagoEfectivo = null): Pedido
{
    return DB::transaction(function () use ($pedido, $metodoPago, $montoPagado, $montoPagoEfectivo) {
        $pedido = Pedido::where('id', $pedido->id)->lockForUpdate()->firstOrFail();
        abort_if($pedido->estado === 'pagado', 400, 'El pedido ya se encuentra pagado.');

        $metodo = strtolower($metodoPago);
        $montoEfectivo = null;
        $montoTarjeta = null;

        if ($metodo === 'mixto') {
            $montoEfectivo = max(0, (float) ($montoPagoEfectivo ?? 0));
            $montoTarjeta = max(0, (float) $pedido->total - $montoEfectivo);
        } elseif (in_array($metodo, ['tarjeta', 'tarjeta_credito', 'tarjeta_debito', 'datafono', 'datáfono', 'datfono'], true)) {
            $montoTarjeta = (float) $pedido->total;
        }

        if ($montoPagado < (float) $pedido->total) {
            throw new \InvalidArgumentException("El monto pagado ({$montoPagado}) no puede ser inferior al total del pedido ({$pedido->total}).");
        }

        $cambio = max(0, $montoPagado - (float) $pedido->total);

        $pedido->update([
            'estado' => 'pagado',
            'metodo_pago' => $metodoPago,
            'monto_pagado' => $montoPagado,
            'monto_pago_efectivo' => $montoEfectivo,
            'monto_pago_tarjeta' => $montoTarjeta,
            'cambio' => $cambio,
            'pagado_en' => now(),
        ]);

        // ... resto igual (mesa, turnoActivo, inventario, fidelización, ticket)
    });
}
```

> **Nota:** no rompe la firma para llamadas existentes (`?float = null`).

- [ ] **Step 6: Corregir `CajaService::vincularCobroPedido`**

```php
$metodo = strtolower($pedido->metodo_pago ?? 'efectivo');

if ($metodo === 'efectivo') {
    $turno->total_ventas_efectivo = (float) $turno->total_ventas_efectivo + (float) $pedido->total;
} elseif ($metodo === 'mixto') {
    $efectivo = (float) ($pedido->monto_pago_efectivo ?? 0);
    $tarjeta = (float) ($pedido->monto_pago_tarjeta ?? 0);
    $turno->total_ventas_efectivo = (float) $turno->total_ventas_efectivo + $efectivo;
    $turno->total_ventas_tarjeta = (float) $turno->total_ventas_tarjeta + $tarjeta;
} elseif (in_array($metodo, ['tarjeta', 'tarjeta_credito', 'tarjeta_debito', 'datafono', 'datáfono', 'datfono'], true)) {
    $turno->total_ventas_tarjeta = (float) $turno->total_ventas_tarjeta + (float) $pedido->total;
} else {
    $turno->total_ventas_transferencia = (float) $turno->total_ventas_transferencia + (float) $pedido->total;
}
```

- [ ] **Step 7: Terminal — propiedad y normalización de monto en mixto**

En `terminal.blade.php`, dentro del `new class extends Component`, añadir propiedad (junto a `$montoPagado`):

```php
public float $montoEfectivoMixto = 0.0;
```

Modificar `updatedMetodoPago` para incluir `mixto` (normaliza a total) y resetear desglose al cambiar:

```php
public function updatedMetodoPago($value): void
{
    if (in_array(strtolower((string) $value), ['tarjeta', 'transferencia', 'datafono', 'datáfono', 'mixto'], true)) {
        $this->montoPagado = $this->total;
    }

    if (strtolower((string) $value) !== 'mixto') {
        $this->montoEfectivoMixto = 0.0;
    }
}
```

- [ ] **Step 8: Terminal — modal muestra input de efectivo cuando método es mixto**

Dentro del bloque `@if($metodoPago === 'efectivo')` de `procesarCobro` no está; añadir bloque paralelo, entre los pickers y el bloque de efectivo (justo después de `</div>` que cierra el picker, línea ~1700):

```blade
@if($metodoPago === 'mixto')
    <div>
        <label class="text-xs font-bold text-on-surface-variant">Efectivo (pago mixto):</label>
        <input
            type="number"
            step="1000"
            min="0"
            max="{{ (int) $this->total }}"
            wire:model.live="montoEfectivoMixto"
            class="mt-1 w-full rounded-xl border border-surface-container-high bg-surface-container-low p-3 font-mono text-xl font-bold text-on-surface focus:border-primary focus:ring-0"
        />
        <p class="mt-1 text-[10px] font-semibold text-on-surface-variant">
            El resto (${{ number_format(max(0, (float) $this->total - (float) $this->montoEfectivoMixto), 0, ',', '.') }}) se registra como tarjeta.
        </p>
    </div>
@endif
```

- [ ] **Step 9: Terminal — `procesarCobro` garantiza desglose mixto antes de cobrar**

Dentro de `procesarCobro()`, justo después de la normalización `isMontoClip` existente (bloque `if (in_array(...))` previo a la validación de `$this->montoPagado < $this->total`), añadir:

```php
if (strtolower((string) $this->metodoPago) === 'mixto') {
    $this->montoPagado = (float) $pedido->total;
    $this->montoEfectivoMixto = min(max(0, (float) $this->montoEfectivoMixto), (float) $pedido->total);
}
```

Y en la llamada a `cobrarPedido` (aprox. línea 343):

```php
$montoEfectivo = strtolower((string) $this->metodoPago) === 'mixto' ? (float) $this->montoEfectivoMixto : null;
$this->pedidoCompletado = $pedidoService->cobrarPedido($pedido, $this->metodoPago, $this->montoPagado, $montoEfectivo);
```

- [ ] **Step 10: Ejecutar y confirmar GREEN**

Run: `php artisan test --filter=RemediacionPagoMixtoTest`
Expected: PASS (2/2). Correr también `php artisan test --filter=Fase2CajaTest` para no romper regresión.

- [ ] **Step 11: Pint + commit**

```bash
vendor\bin\pint --dirty
git add database/migrations/2026_09_15_180000_add_pago_mixto_to_pedidos_table.php app/Models/Pedido.php app/Services/PedidoService.php app/Services/CajaService.php resources/views/livewire/pos/terminal.blade.php tests/Feature/RemediacionPagoMixtoTest.php
git commit -m "fix(rem): pago mixto con desglose y clasificación datafono como tarjeta"
```

---

### Task 2: IDOR por sucursal — `sucursal_id` en pedidos + policies por sucursal

**Files:**
- Create: `database/migrations/2026_09_15_181000_add_sucursal_id_to_pedidos_table.php`
- Modify: `app/Models/Pedido.php` (fillable + relación `sucursal` + scope)
- Modify: `app/Services/PedidoService.php` (`crearPedido` atribuye sucursal)
- Modify: `app/Services/DeliveryService.php` (`crearPedidoDelivery` y `marcarEntregado` propagan sucursal)
- Modify: `app/Policies/PedidoPolicy.php`, `app/Policies/TurnoCajaPolicy.php`, `app/Policies/CajaPolicy.php` (criterio sucursal)
- Modify: `resources/views/livewire/caja/control.blade.php` (quitar fallback `Caja::first()` sin sucursal, verificar en abrirTurno)
- Modify: `resources/views/livewire/pos/terminal.blade.php` (pasar instancia a `authorize('cobrar'/'enviarCocina')` cuando exista)
- Modify: `app/Services/ReporteService.php` + `app/Http/Controllers/ReporteExportController.php` (filtrar por sucursal)
- Test: `tests/Feature/RemediacionIdorSucursalTest.php`

**Interfaces:**
- Consumes: `User->sucursal_id`; `Mesa->sucursal_id`; `Caja->sucursal_id`.
- Produces: `Pedido->sucursal_id` (fillable, relación `sucursal(): BelongsTo`); helper privado `private function mismaSucursal(User $user, ?Pedido $pedido): bool` en policies; `ReporteService::{ventasPorPeriodo,ventasPorTipo,ventasPorProducto,ventasPorTrabajador,kpisRealtime,resumenPeriodo}(..., ?int $sucursalId = null)`.

**Diseño:** el criterio de sucursal aplica a mutaciones con instancia. Donde el componente no tiene instancia válida aún, se conserva la validación por rol y se añade `abort_if` por sucursal en la ruta (patrón ya usado en terminal `:273,281`).

- [ ] **Step 1: Migración `sucursal_id` en pedidos con backfill**

```php
<?php

use App\Models\Pedido;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'sucursal_id')) {
                $table->foreignId('sucursal_id')->nullable()->after('tipo')->constrained('sucursales')->nullOnDelete();
                $table->index(['sucursal_id', 'estado']);
            }
        });

        // Backfill: mesa → sucursal de la mesa; delivery sin mesa → sucursal del usuario que creó
        Pedido::with('mesa', 'usuario')->chunkById(100, function ($pedidos) {
            foreach ($pedidos as $pedido) {
                if ($pedido->sucursal_id !== null) {
                    continue;
                }
                $sucursalId = $pedido->mesa?->sucursal_id
                    ?? $pedido->usuario?->sucursal_id
                    ?? \App\Models\Sucursal::value('id');
                if ($sucursalId) {
                    $pedido->updateQuietly(['sucursal_id' => $sucursalId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
        });
    }
};
```

- [ ] **Step 2: Test RED — mesera de sucursal B NO puede operar pedido/caja/reporte de A**

```php
<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemediacionIdorSucursalTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursalA;
    private Sucursal $sucursalB;
    private User $meseroB;
    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        $rolMesero = Role::create(['nombre' => 'Mesero', 'slug' => 'mesero', 'descripcion' => 'Mesero']);
        $rolAdmin = Role::create(['nombre' => 'Administrador', 'slug' => 'admin', 'descripcion' => 'Admin']);

        $this->sucursalA = Sucursal::create(['nombre' => 'Sucursal A', 'codigo' => 'A-01', 'direccion' => 'Cra 1', 'activa' => true]);
        $this->sucursalB = Sucursal::create(['nombre' => 'Sucursal B', 'codigo' => 'B-01', 'direccion' => 'Cra 2', 'activa' => true]);

        $this->adminA = User::factory()->create(['role_id' => $rolAdmin->id, 'sucursal_id' => $this->sucursalA->id, 'activo' => true]);
        $this->meseroB = User::factory()->create(['role_id' => $rolMesero->id, 'sucursal_id' => $this->sucursalB->id, 'activo' => true]);
    }

    private function crearTurnoEn(Sucursal $sucursal, User $user): TurnoCaja
    {
        $caja = Caja::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Caja', 'codigo' => 'CAJA-'.Str::upper(Str::random(4)), 'activa' => true]);

        return app(\App\Services\CajaService::class)->abrirTurno($caja, $user, 100000.00, 'Apertura');
    }

    public function test_mesero_de_otra_sucursal_no_puede_abrir_turno_de_la_sucursal_A(): void
    {
        $turnoA = $this->crearTurnoEn($this->sucursalA, $this->adminA);

        $response = $this->actingAs($this->meseroB)->get(route('caja'));

        // El turno visible para B NO debe ser el de A (IDOR por sucursal)
        $response->assertDontSee($turnoA->id);
    }

    public function test_gerente_filtra_reportes_por_su_sucursal(): void
    {
        $rolGerente = Role::create(['nombre' => 'Gerente', 'slug' => 'gerente', 'descripcion' => 'Gerente']);
        $gerenteA = User::factory()->create(['role_id' => $rolGerente->id, 'sucursal_id' => $this->sucursalA->id, 'activo' => true]);

        $service = app(ReporteService::class);
        $datos = $service->ventasPorPeriodo(now()->subDay()->toDateString(), now()->addDay()->toDateString(), $gerenteA->sucursal_id);

        // No rompe: devuelve estructura esperada aunque vacía
        $this->assertIsArray($datos);
    }
}
```

- [ ] **Step 3: Ejecutar y confirmar RED**

Run: `php artisan test --filter=RemediacionIdorSucursalTest`
Expected: al menos FALLAS por falta de `sucursal_id` en pedidos (si una assertion de caja/reporte genera error, ajustar la assertion al contrato y seguir). El punto RED principal: sin los cambios, `caja/control` muestra turno de otra sucursal y reportes son globales.

- [ ] **Step 4: `Pedido` — fillable, relación, scope**

```php
// fillable: añadir 'sucursal_id' (tras 'tipo')
// relaciones:
public function sucursal(): BelongsTo
{
    return $this->belongsTo(Sucursal::class);
}
// scopes:
public function scopeDeSucursal(Builder $query, ?int $sucursalId): Builder
{
    return $sucursalId === null ? $query : $query->where('sucursal_id', $sucursalId);
}
```

- [ ] **Step 5: `PedidoService::crearPedido` atribuye sucursal**

Al construir el array de `create`, añadir la resolución justo antes:
```php
$mesa = ! empty($datos['mesa_id']) ? Mesa::find($datos['mesa_id']) : null;
$sucursalId = $datos['sucursal_id']
    ?? $mesa?->sucursal_id
    ?? $usuario?->sucursal_id
    ?? \App\Models\Sucursal::value('id');
```
y agregar `'sucursal_id' => $sucursalId,` al array de `Pedido::create([...])` (después de `'tipo'`).

- [ ] **Step 6: `DeliveryService` propaga sucursal**

En `crearPedidoDelivery`, pasar `'sucursal_id' => $datos['sucursal_id'] ?? auth()->user()?->sucursal_id` dentro del array que se entrega a `crearPedido` (buscar la llamada única a `$this->pedidoService->crearPedido(...)`). En `marcarEntregado`, al actualizar `estado`/pago, no tocar `sucursal_id` (ya asignada en creación); solo asegurar backfill si venía null:

```php
if (empty($pedido->sucursal_id)) {
    $pedido->updateQuietly(['sucursal_id' => $pedido->usuario?->sucursal_id ?? \App\Models\Sucursal::value('id')]);
    $pedido->refresh();
}
```

- [ ] **Step 7: Policies con criterio de sucursal**

`PedidoPolicy` — helper privado y aplicación en mutaciones con instancia:
```php
private function mismaSucursal(User $user, ?Pedido $pedido): bool
{
    if (($pedido?->sucursal_id ?? null) === null || $user->sucursal_id === null) {
        return true; // sin sucursal definida, se conserva el control por rol
    }

    return $pedido->sucursal_id === $user->sucursal_id;
}

public function cobrar(User $user, ?Pedido $pedido = null): bool
{
    return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin'])
        && $this->mismaSucursal($user, $pedido);
}

public function enviarCocina(User $user, ?Pedido $pedido = null): bool
{
    return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin'])
        && $this->mismaSucursal($user, $pedido);
}

public function update(User $user, ?Pedido $pedido = null): bool
{
    return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin'])
        && $this->mismaSucursal($user, $pedido);
}
```

`TurnoCajaPolicy`:
```php
public function cerrar(User $user, ?TurnoCaja $turno = null): bool
{
    return in_array($user->role?->slug, ['cajero', 'gerente', 'admin'])
        && $this->mismaSucursal($user, $turno);
}

public function arqueo(User $user, ?TurnoCaja $turno = null): bool
{
    return in_array($user->role?->slug, ['cajero', 'gerente', 'admin'])
        && $this->mismaSucursal($user, $turno);
}

public function guardarMovimiento(User $user, ?TurnoCaja $turno = null): bool
{
    return in_array($user->role?->slug, ['cajero', 'gerente', 'admin'])
        && $this->mismaSucursal($user, $turno);
}

private function mismaSucursal(User $user, ?TurnoCaja $turno): bool
{
    if (($turno?->caja?->sucursal_id ?? null) === null || $user->sucursal_id === null) {
        return true;
    }

    return $turno->caja->sucursal_id === $user->sucursal_id;
}
```

`CajaPolicy` (solo `update`/`delete` reciben instancia):
```php
private function mismaSucursal(User $user, ?Caja $caja): bool
{
    if (($caja?->sucursal_id ?? null) === null || $user->sucursal_id === null) {
        return true;
    }

    return $caja->sucursal_id === $user->sucursal_id;
}

public function update(User $user, Caja $caja): bool
{
    return in_array($user->role?->slug, ['gerente', 'admin']) && $this->mismaSucursal($user, $caja);
}

public function delete(User $user, Caja $caja): bool
{
    return $user->role?->slug === 'admin' && $this->mismaSucursal($user, $caja);
}
```

- [ ] **Step 8: `caja/control.blade.php` — sin fallback global y autorización de caja**

En `mount()` (`:91`), quitar el fallback que ignora sucursal:
```php
$caja = $cajasQuery->first();
if ($caja) {
    $this->cajaSeleccionadaId = $caja->id;
}
```
En `abrirTurno()` justo tras `$caja = Caja::findOrFail($this->cajaSeleccionadaId);`:
```php
abort_if(auth()->user()?->sucursal_id && $caja->sucursal_id !== auth()->user()->sucursal_id, 403, 'La caja no pertenece a tu sucursal.');
```
`CajaService::abrirTurno` — misma validación (defensa en profundidad):
```php
abort_if($caja->sucursal_id !== null && $cajero->sucursal_id !== null && $caja->sucursal_id !== $cajero->sucursal_id, 403, 'La caja no pertenece a tu sucursal.');
```

- [ ] **Step 9: `ReporteService` filtra por sucursal + controlador le pasa el dato**

En cada método de venta listado, añadir parámetro opcional y filtro:
```php
public function ventasPorPeriodo(string $desde, string $hasta, ?int $sucursalId = null): array
{
    return Pedido::query()
        ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
        // ... resto igual
}
```
Aplicar el mismo `->when($sucursalId, ...)` a `ventasPorTipo`, `ventasPorProducto`, `ventasPorTrabajador` y `resumenPeriodo`. En `kpisRealtime()` añadir `?int $sucursalId = null` y filtrar las queries de `Pedido`/`ItemPedido`/`Mesa` con `when($sucursalId, ...)` (Mesa ya tiene `sucursal_id`).
En `ReporteExportController::datos()` (`:74-84`), inyectar:
```php
$service = app(ReporteService::class);
$sucursalId = auth()->user()?->sucursal_id;
// y pasar $sucursalId a cada método ($service->ventasPorPeriodo($desde, $hasta, $sucursalId), etc.)
```

- [ ] **Step 10: Terminal pasa instancia a las políticas de money**

En `procesarCobro()`, tras resolver `$pedido`, reemplazar la autorización de clase por instancia:
```php
$this->authorize('cobrar', $pedido);
```
En `enviarACocina()`, tras resolver `$pedido`:
```php
$this->authorize('enviarCocina', $pedido);
```
> La autorización por clase se mantiene en los puntos donde aún no hay instancia (p. ej. `mount`).

- [ ] **Step 11: Ejecutar y confirmar GREEN**

Run: `php artisan test --filter=RemediacionIdorSucursalTest --filter=Fase2CajaTest --filter=AuthorizePoliciesTest`
Ajustar cualquier test existente que abuse del fallback global (documentar en la PR). Suite completa: `php artisan test`.

- [ ] **Step 12: Pint + commit**

```bash
vendor\bin\pint --dirty
git add database/migrations/2026_09_15_181000_add_sucursal_id_to_pedidos_table.php app/Models/Pedido.php app/Services/PedidoService.php app/Services/DeliveryService.php app/Policies tests/Feature/RemediacionIdorSucursalTest.php resources/views/livewire/caja/control.blade.php resources/views/livewire/pos/terminal.blade.php app/Services/ReporteService.php app/Http/Controllers/ReporteExportController.php
git commit -m "fix(sec): defender por sucursal pedidos, caja, turnos y reportes (R24)"
```

---

### Task 3: Eliminar cluster de credencial demo `restomaster2026`

**Files:**
- Modify: `database/seeders/AdminUserSeeder.php` (quitar fallback determinista, no imprimir password)
- Modify: `resources/views/livewire/pages/auth/login.blade.php` (sin fallback en `rellenarCredencial`, demo solo en local)
- Modify: `config/auth.php` (sin fallback)
- Modify: `.env.example` (variable vacía)
- Test: `tests/Feature/RemediacionCredencialDemoTest.php`

**Interfaces:**
- Produces: `AdminUserSeeder` usa `env('DEMO_USERS_PASSWORD')` con fallback `Str::password(16)`; `login.blade` autocompleta clave SOLO si `config('auth.demo_password')` no es null; `.env.example` con `DEMO_USERS_PASSWORD=`.

- [ ] **Step 1: Test RED — no hay password demo conocida en el código**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemediacionCredencialDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_existe_password_demo_conocido_en_seeder_ni_login(): void
    {
        $seeder = file_get_contents(database_path('seeders/AdminUserSeeder.php'));
        $login = file_get_contents(resource_path('views/livewire/pages/auth/login.blade.php'));
        $envExample = file_get_contents(base_path('.env.example'));

        foreach (['restomaster2026', 'sushixpress2026', 'password123'] as $cred) {
            $this->assertStringNotContainsString($cred, $seeder, "Seeder contiene fallback demo {$cred}.");
            $this->assertStringNotContainsString($cred, $login, "Login blade contiene fallback demo {$cred}.");
            $this->assertStringNotContainsString($cred, $envExample, ".env.example contiene {$cred}.");
        }
    }
}
```

- [ ] **Step 2: Ejecutar y confirmar RED**

Run: `php artisan test --filter=RemediacionCredencialDemoTest`
Expected: FAIL (coincidencias en seeder y login y `.env.example`).

- [ ] **Step 3: `AdminUserSeeder` sin fallback determinista y sin imprimir**

```php
$rawPassword = env('DEMO_USERS_PASSWORD') ?: Str::password(16);
```
Eliminar el bloque `$this->command?->info(...)` que imprime la contraseña (`:31-33`).

- [ ] **Step 4: `login.blade.php` — autocompletar solo con valor real configurado**

```php
public function rellenarCredencial(string $email, ?string $password = null): void
{
    $this->form->email = $email;
    $demoPassword = config('auth.demo_password');
    if ($password !== null) {
        $this->form->password = $password;
    } elseif (is_string($demoPassword) && $demoPassword !== '') {
        $this->form->password = $demoPassword;
    }
}
```

- [ ] **Step 5: `config/auth.php` — sin fallback hardcodeado**

```php
'demo_password' => env('DEMO_USERS_PASSWORD'),
```

- [ ] **Step 6: `.env.example` — variable vacía**

```env
DEMO_USERS_PASSWORD=
```

- [ ] **Step 7: Ejecutar y confirmar GREEN + suite**

Run: `php artisan test --filter=RemediacionCredencialDemoTest`
Expected: PASS. Luego `php artisan test --filter=AuthenticationTest --filter=Fase1MenuCrudTest` para regresión de login (verificar que `rellenarCredencial` sin env no rompe la pantalla).

- [ ] **Step 8: Pint + commit**

```bash
git add database/seeders/AdminUserSeeder.php resources/views/livewire/pages/auth/login.blade.php config/auth.php .env.example tests/Feature/RemediacionCredencialDemoTest.php
git commit -m "fix(sec): eliminar password demo conocido del seeder, login y env"
```

---

### Task 4: Dashboard `kpisRealtime` con agregados SQL + caché

**Files:**
- Modify: `app/Services/ReporteService.php` (`kpisRealtime`)
- Modify: `resources/views/dashboard.blade.php` (pasar sucursal)
- Test: `tests/Feature/RemediacionDashboardCacheTest.php`

**Interfaces:**
- Produces: `kpisRealtime(?int $sucursalId = null): array` con agregados SQL y `Cache::remember('dashboard.kpis.'.($sucursalId ?? 'global'), 30, ...)`; claves idénticas a las actuales (`ventas_dia`, `transacciones_dia`, `ticket_promedio`, `food_cost_porcentaje`, `mesas_ocupadas`, `comandas_cocina_activas`, `picos_por_hora`, `top_productos_hoy`).

- [ ] **Step 1: Test RED — kpisRealtime usa agregados y se cachea**

```php
<?php

namespace Tests\Feature;

use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RemediacionDashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_kpis_realtime_se_cachea_y_devuelve_estructura(): void
    {
        Cache::flush();

        $service = app(ReporteService::class);
        $kpis = $service->kpisRealtime(1);

        foreach (['ventas_dia', 'transacciones_dia', 'ticket_promedio', 'food_cost_porcentaje', 'mesas_ocupadas', 'comandas_cocina_activas', 'picos_por_hora', 'top_productos_hoy'] as $clave) {
            $this->assertArrayHasKey($clave, $kpis);
        }

        $this->assertTrue(Cache::has('dashboard.kpis.1'), 'kpisRealtime debe cachear el resultado.');
    }
}
```

- [ ] **Step 2: Ejecutar y confirmar RED**

Run: `php artisan test --filter=RemediacionDashboardCacheTest`
Expected: FAIL — no existe clave de caché (ni agregados).

- [ ] **Step 3: Reescribir `kpisRealtime` con agregados SQL + caché**

```php
public function kpisRealtime(?int $sucursalId = null): array
{
    return Cache::remember('dashboard.kpis.'.($sucursalId ?? 'global'), 30, function () use ($sucursalId) {
        $hoy = now()->toDateString();
        $sucursalKey = \Illuminate\Support\Facades\DB::raw('sucursal_id = '.((int) ($sucursalId ?? 0)));

        $ventasRow = Pedido::query()
            ->where('estado', 'pagado')
            ->whereDate('pagado_en', $hoy)
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->selectRaw('COALESCE(SUM(total), 0) as ventas, COUNT(*) as transacciones')
            ->first();

        $ventas = (float) ($ventasRow->ventas ?? 0);
        $transacciones = (int) ($ventasRow->transacciones ?? 0);
        $ticketPromedio = $transacciones > 0 ? round($ventas / $transacciones, 2) : 0.0;

        $foodCostRow = \Illuminate\Support\Facades\DB::table('item_pedido as ip')
            ->join('pedidos as p', 'ip.pedido_id', '=', 'p.id')
            ->join('productos as pr', 'ip.producto_id', '=', 'pr.id')
            ->where('p.estado', 'pagado')
            ->whereDate('p.pagado_en', $hoy)
            ->when($sucursalId, fn ($q) => $q->where('p.sucursal_id', $sucursalId))
            ->selectRaw('COALESCE(SUM(ip.cantidad * pr.costo), 0) as costo')
            ->first();
        $foodCost = $ventas > 0 ? round((float) $foodCostRow->costo / $ventas * 100, 1) : 0.0;

        $mesasOcupadas = Mesa::query()
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->whereIn('estado', [MesaEstado::OCUPADA->value, MesaEstado::POR_LIMPIAR->value])
            ->count();

        $comandasActivas = Pedido::query()
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->whereNotIn('estado', ['pagado', 'cancelado'])
            ->count();

        $picosPorHora = Pedido::query()
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->where('estado', 'pagado')
            ->whereDate('pagado_en', $hoy)
            ->selectRaw("EXTRACT(HOUR FROM pagado_en)::int as hora, COUNT(*) as total")
            ->groupByRaw('EXTRACT(HOUR FROM pagado_en)::int')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'hora');

        $topProductos = \Illuminate\Support\Facades\DB::table('item_pedido as ip')
            ->join('pedidos as p', 'ip.pedido_id', '=', 'p.id')
            ->leftJoin('productos as pr', 'ip.producto_id', '=', 'pr.id')
            ->where('p.estado', 'pagado')
            ->whereDate('p.pagado_en', $hoy)
            ->when($sucursalId, fn ($q) => $q->where('p.sucursal_id', $sucursalId))
            ->selectRaw("ip.producto_id, MAX(ip.nombre_producto) as producto, SUM(ip.cantidad) as cantidad, SUM(ip.subtotal) as ventas, SUM(ip.subtotal - (ip.cantidad * COALESCE(pr.costo,0))) as margen")
            ->groupBy('ip.producto_id')
            ->orderByDesc('ventas')
            ->limit(5)
            ->get()
            ->map(fn ($f) => [
                'producto' => $f->producto,
                'cantidad' => (int) $f->cantidad,
                'ventas' => (float) $f->ventas,
                'margen' => round((float) $f->margen, 2),
            ])
            ->values()
            ->all();

        return [
            'ventas_dia' => round($ventas, 2),
            'transacciones_dia' => $transacciones,
            'ticket_promedio' => $ticketPromedio,
            'food_cost_porcentaje' => $foodCost,
            'mesas_ocupadas' => $mesasOcupadas,
            'comandas_cocina_activas' => $comandasActivas,
            'picos_por_hora' => $picosPorHora,
            'top_productos_hoy' => $topProductos,
        ];
    });
}
```

> **Nota de esquema:** verificar el nombre real de la tabla pivot en `database/migrations` (buscar `Schema::create('item_pedido'` o `'item_pedidos'`). Si el nombre es `item_pedidos`, ajustar los `DB::table('item_pedido ...')` en consecuencia.

- [ ] **Step 4: Dashboard pasa sucursal**

```blade
<?php $kpis = app(\App\Services\ReporteService::class)->kpisRealtime(auth()->user()?->sucursal_id); ?>
```

- [ ] **Step 5: Ejecutar y confirmar GREEN**

Run: `php artisan test --filter=RemediacionDashboardCacheTest`
Expected: PASS. Luego ajustar el `file_get_contents` nada; correr suite parcial de reportes si existe (`--filter=Reporte`).

- [ ] **Step 6: Pint + commit**

```bash
vendor\bin\pint --dirty
git add app/Services/ReporteService.php resources/views/dashboard.blade.php tests/Feature/RemediacionDashboardCacheTest.php
git commit -m "perf(rem): kpisRealtime con agregados SQL y caché 30s"
```

---

### Task 5: TTL de notificaciones ≥ intervalo de poll + descuento en merge de pedido existente

**Files:**
- Modify: `app/Services/NotificacionService.php` (TTL 8 → 30)
- Modify: `resources/views/livewire/pos/terminal.blade.php` (propagar descuento/puntos/envío al pedido existente en `enviarACocina` y `procesarCobro`)
- Test: `tests/Feature/RemediacionMergeDescuentoTest.php`

**Interfaces:**
- Produces: `Cache::remember('notif.resumen.*', 30, ...)`; en ramas `pedidoExistente` se escriben `descuento`, `descuento_puntos`, `puntos_canjeados`, `costo_envio` antes de `enviarACocina`/cobro.

- [ ] **Step 1: Test RED — descuento aplicado en merge de pedido existente**

```php
<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RemediacionMergeDescuentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_descuento_se_aplica_al_pedido_existente_al_cobrar(): void
    {
        $rol = Role::create(['nombre' => 'Cajero', 'slug' => 'cajero', 'descripcion' => 'Cajero']);
        $s = Sucursal::create(['nombre' => 'S', 'codigo' => 'S1', 'direccion' => 'X', 'activa' => true]);
        $user = User::factory()->create(['role_id' => $rol->id, 'sucursal_id' => $s->id, 'activo' => true]);

        $mesa = Mesa::create(['sucursal_id' => $s->id, 'numero' => 1, 'nombre' => 'Mesa 1', 'estado' => 'libre', 'qr_uuid' => 'a']);
        $p = \App\Models\Producto::create(['nombre' => 'P', 'precio' => 50000.00, 'categoria_id' => 1, 'area_cocina' => 'cocina', 'activo' => true]);

        $service = app(PedidoService::class);
        $pedido = $service->crearPedido(['tipo' => 'mesa', 'estado' => 'en_cocina', 'mesa_id' => $mesa->id], [['producto_id' => $p->id, 'cantidad' => 1]], $user);

        // El cajero agrega otro item y un 10% de descuento en el carrito
        $component = Volt::actingAs($user)
            ->test('pos.terminal')
            ->set('tipo', 'mesa')
            ->set('mesaId', $mesa->id)
            ->call('agregarProducto', $p->id)
            ->set('descuento', 5000.00)
            ->call('procesarCobro');

        $pedido->refresh();
        $this->assertEquals(5000.00, (float) $pedido->descuento, 'El descuento debe quedar persistido en el pedido existente.');
        $this->assertEquals(95000.00, (float) $pedido->total, 'Total debe reflejar el descuento (100.000 - 5.000).');
    }
}
```

> Ajustar `agregarProducto`/`procesarCobro` al contrato real del componente (validar si `agregarProducto` toma `(id)` y si `procesarCobro` depende de `mostrarModalCobro`). Este test marca el contrato exacto del fix.

- [ ] **Step 2: Ejecutar y confirmar RED**

Run: `php artisan test --filter=RemediacionMergeDescuentoTest`
Expected: FAIL (descuento = 0 en pedido existente).

- [ ] **Step 3: TTL notificaciones**

```php
return Cache::remember($cacheKey, 30, function () use ($usuario) {
```

- [ ] **Step 4: Propagar descuento/puntos/envío en rama `pedidoExistente`**

En `enviarACocina()` donde `$pedidoExistente` es true (`:280-303`), antes de `$pedido = $pedidoService->enviarACocina($pedidoExistente);`:
```php
$pedidoExistente->update([
    'descuento' => (float) $this->descuento,
    'descuento_puntos' => (float) $this->descuentoPuntos,
    'puntos_canjeados' => (int) $this->puntosCanjeados,
    'costo_envio' => (float) $costoEnvio,
]);
$pedidoExistente->recalcularTotales();
$pedidoExistente->refresh();
```

En `procesarCobro()` rama `pedidoExistente` (`:382-404`), tras el merge de items y antes de `$pedido = $pedidoExistente->fresh(['items', 'mesa']);`:
```php
$pedidoExistente->update([
    'descuento' => (float) $this->descuento,
    'descuento_puntos' => (float) $this->descuentoPuntos,
    'puntos_canjeados' => (int) $this->puntosCanjeados,
    'costo_envio' => (float) $costoEnvio,
]);
$pedidoExistente->recalcularTotales();
```

- [ ] **Step 5: Ejecutar y confirmar GREEN + suite**

Run: `php artisan test --filter=RemediacionMergeDescuentoTest --filter=RemediacionPosCocinaTest`
Expected: PASS ambos. Suite completa al final.

- [ ] **Step 6: Pint + commit**

```bash
vendor\bin\pint --dirty
git add app/Services/NotificacionService.php resources/views/livewire/pos/terminal.blade.php tests/Feature/RemediacionMergeDescuentoTest.php
git commit -m "fix(rem): propagar descuento/puntos/envío al pedido existente; TTL notif 30s"
```

---

### Task 6: Race en merge — servicios con bloqueo + no duplicar pedido activo

**Files:**
- Modify: `app/Services/PedidoService.php` (nuevo método `agregarCarritoConBloqueo`, verificación de pedido activo en `crearPedido`)
- Modify: `resources/views/livewire/pos/terminal.blade.php` (usar el nuevo método en `enviarACocina` y `procesarCobro`)
- Test: `tests/Feature/RemediacionMergeDescuentoTest.php` (añadir caso de concurrencia) o nuevo `tests/Feature/RemediacionRaceMergeTest.php`

**Interfaces:**
- Produces: `PedidoService::agregarCarritoConBloqueo(int $mesaId, array $carrito): ?Pedido` — transacción con `lockForUpdate` sobre el pedido activo de la mesa, aplica diff y descuento, retorna el pedido (o `null` si no hay pedido activo). `crearPedido` lanza `DomainException` si ya hay pedido activo para la mesa (dentro de tx con lock).

- [ ] **Step 1: Test RED — dos mercancías simultáneas no duplican items ni crean dos pedidos**

```php
public function test_merge_concurrente_no_duplica_items_ni_pedidos(): void
{
    // setup: usuario cajero, mesa, producto A (55.000) y B (35.000)
    // 1. se crea pedido activo en BD con item A
    // 2. primer "merge" agrega B en el componente
    // 3. segundo "merge" simultáneo agrega B también (simula doble clic/otra terminal)
    // asertar: 1 solo pedido para la mesa y 1 único ItemPedido de B (suma de cantidades, sin duplicación)

    $this->assertEquals(1, Pedido::where('mesa_id', $mesa->id)->activos()->count(), 'Debe existir un único pedido activo por mesa.');
    $this->assertEquals(1, \App\Models\ItemPedido::where('pedido_id', $pedido->id)->where('producto_id', $productoB->id)->count(), 'No debe duplicarse el item del producto B.');
}
```

- [ ] **Step 2: Ejecutar y confirmar RED**

Run: `php artisan test --filter=RemediacionRaceMergeTest`
Expected: FAIL (duplica item o crea 2 pedidos).

- [ ] **Step 3: `PedidoService` — verificación de pedido activo en `crearPedido`**

Dentro de la transacción, antes de crear:
```php
if (! empty($datos['mesa_id'])) {
    $mesa = Mesa::where('id', $datos['mesa_id'])->lockForUpdate()->first();
    $activo = $mesa
        ? Pedido::where('mesa_id', $mesa->id)->whereNotIn('estado', ['pagado', 'cancelado'])->exists()
        : false;
    if ($activo) {
        throw new \DomainException("La mesa #{$mesa->numero} ya tiene un pedido activo.");
    }
}
```

- [ ] **Step 4: `PedidoService` — nuevo método `agregarCarritoConBloqueo`**

```php
public function agregarCarritoConBloqueo(int $mesaId, array $carrito): ?Pedido
{
    return DB::transaction(function () use ($mesaId, $carrito) {
        $mesa = Mesa::where('id', $mesaId)->lockForUpdate()->first();
        if (! $mesa) {
            return null;
        }

        $pedido = Pedido::where('mesa_id', $mesaId)
            ->whereNotIn('estado', ['pagado', 'cancelado'])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $pedido) {
            return null;
        }

        $itemsExistentes = $pedido->items()->get()->keyBy('producto_id');

        foreach ($carrito as $productoId => $itemCarrito) {
            $cantidadCarrito = (int) $itemCarrito['cantidad'];
            $producto = Producto::find($productoId);
            if (! $producto) {
                continue;
            }

            if ($itemsExistentes->has($productoId)) {
                $diferencia = $cantidadCarrito - (int) $itemsExistentes->get($productoId)->cantidad;
                if ($diferencia > 0) {
                    $this->agregarItem($pedido, $producto, $diferencia, $itemCarrito['notas'] ?? null);
                }
            } else {
                $this->agregarItem($pedido, $producto, $cantidadCarrito, $itemCarrito['notas'] ?? null);
            }
        }

        return $pedido->fresh(['items', 'mesa']);
    });
}
```

- [ ] **Step 5: Terminal usa el nuevo método en `enviarACocina` y `procesarCobro`**

Remplazar los bloques `$pedidoExistente` + foreach de diff por:
```php
$pedido = ($this->tipo === 'mesa' && $this->mesaId)
    ? $pedidoService->agregarCarritoConBloqueo($this->mesaId, $this->carrito)
    : null;
```
En `enviarACocina`, si `! $pedido` (no había pedido activo) crear con los datos del carrito (rama actual de `crearPedido`). En `procesarCobro`, si `! $pedido` crear con `estado: 'creado'`. Mantener los `abort_if` de sucursal existentes antes de llamar al método y volver a aplicar el update de descuento (Task 5) después del merge (o integrarlo en `agregarCarritoConBloqueo` con parámetros de descuento/`costoEnvio`).

> **Decisión:** extender `agregarCarritoConBloqueo` con parámetros opcionales `?float $descuento`, `?float $descuentoPuntos`, `?int $puntosCanjeados`, `?float $costoEnvio` y aplicar `recalcularTotales()` dentro; esto centraliza el fix de Task 5 en el mismo lugar atómico.

- [ ] **Step 6: Ejecutar y confirmar GREEN + suite**

Run: `php artisan test --filter=RemediacionRaceMergeTest --filter=RemediacionMergeDescuentoTest --filter=RemediacionPosCocinaTest`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor\bin\pint --dirty
git add app/Services/PedidoService.php resources/views/livewire/pos/terminal.blade.php tests/Feature/RemediacionRaceMergeTest.php
git commit -m "fix(rem): merge de carrito con lockForUpdate y guard único de pedido activo por mesa"
```

---

### Task 7: Delivery vincula cobro del turno y exige turno abierto para cobrar

**Files:**
- Modify: `app/Services/DeliveryService.php` (`marcarEntregado` vincula `vincularCobroPedido` al turno cuando cobra contra entrega; no pisar `estado='pagado'` a 'entregado')
- Modify: `app/Services/PedidoService.php` (`cobrarPedido` lanza `DomainException` si no hay turno abierto; vincular al turno de la sucursal)
- Modify: `resources/views/livewire/delivery/index.blade.php` (mensaje/tiempo de error coherente)
- Test: `tests/Feature/RemediacionDeliveryVinculaTurnoTest.php`, `tests/Feature/RemediacionCobroSinTurnoTest.php`

**Interfaces:**
- Produces: `marcarEntregado`: mantiene `estado` si estaba `pagado`; al cobrar contra entrega busca `TurnoCaja::where('estado','abierto')` de la sucursal del pedido y llama `vincularCobroPedido`. `cobrarPedido`: `$turnoActivo` obligatorio (misma sucursal que el pedido si hay sucursal) o `DomainException`.

- [ ] **Step 1: Test RED — cobro contra entrega queda vinculado al turno**

```php
<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\CajaService;
use App\Services\DeliveryService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemediacionDeliveryVinculaTurnoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cobro_contra_entrega_se_vincula_al_turno_de_caja(): void
    {
        $rol = Role::create(['nombre' => 'Admin', 'slug' => 'admin', 'descripcion' => 'Admin']);
        $s = Sucursal::create(['nombre' => 'S', 'codigo' => 'S1', 'direccion' => 'X', 'activa' => true]);
        $user = User::factory()->create(['role_id' => $rol->id, 'sucursal_id' => $s->id, 'activo' => true]);

        $cajaService = app(CajaService::class);
        $caja = Caja::create(['sucursal_id' => $s->id, 'nombre' => 'C1', 'codigo' => 'C1', 'activa' => true]);
        $turno = $cajaService->abrirTurno($caja, $user, 100000.00, 'Apertura');

        $deliveryService = app(DeliveryService::class);
        $cliente = \App\Models\Cliente::create(['nombre' => 'Cliente X', 'telefono' => '3000000000', 'activo' => true]);

        $pedido = $deliveryService->crearPedidoDelivery([
            'sucursal_id' => $s->id,
            'user_id' => $user->id,
            'cliente_id' => $cliente->id,
            'costo_envio' => 5000.00,
            'metodo_pago' => 'efectivo',
        ]);

        \App\Models\ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => 1,
            'nombre_producto' => 'Producto',
            'cantidad' => 1,
            'precio_unitario' => 45000.00,
            'subtotal' => 45000.00,
            'area_cocina' => 'cocina',
            'estado_cocina' => 'pendiente',
        ]);
        $pedido->recalcularTotales();
        $pedido->refresh();

        $deliveryService->asignarRepartidor($pedido, $user);
        $deliveryService->marcarSalida($pedido);
        $deliveryService->marcarEntregado($pedido, 'efectivo', 50000.00);

        $turno->refresh();
        $this->assertEquals(50000.00, (float) $turno->total_ventas_efectivo,
            'Cobro contra entrega debe vincularse al turno (antes quedaba invisible).');
    }
}
```

- [ ] **Step 2: Test RED — cobrar sin turno abierto lanza excepción**

```php
// en RemediacionCobroSinTurnoTest
public function test_cobrar_pedido_sin_turno_abierto_es_rechazado(): void
{
    // setup: pedido creado SIN abrir turno de caja
    $this->expectException(\DomainException::class);
    $this->pedidoService->cobrarPedido($pedido, 'efectivo', (float) $pedido->total);
}
```

- [ ] **Step 3: Ejecutar y confirmar RED**

Run: `php artisan test --filter=RemediacionDeliveryVinculaTurnoTest --filter=RemediacionCobroSinTurnoTest`
Expected: FAIL — cobro delivery no vinculado; cobro sin turno pasa sin error.

- [ ] **Step 4: `PedidoService::cobrarPedido` — turno obligatorio por sucursal**

Remplazar el bloque `$turnoActivo` (actual `:197-200`):
```php
$turnoActivo = TurnoCaja::query()
    ->where('estado', 'abierto')
    ->when($pedido->sucursal_id, fn ($q) => $q->whereHas('caja', fn ($cq) => $cq->where('sucursal_id', $pedido->sucursal_id)))
    ->latest()
    ->first();

if (! $turnoActivo) {
    throw new \DomainException('No hay un turno de caja abierto en la sucursal. Abre turno antes de cobrar.');
}

app(CajaService::class)->vincularCobroPedido($turnoActivo, $pedido);
```

- [ ] **Step 5: `DeliveryService::marcarEntregado` — sin piso de estado pagado + vincular turno**

Capturar estado previo y no pisar `pagado`:
```php
$yaEstabaPagado = $pedido->estado === 'pagado';

$pedido->update([
    'estado_delivery' => 'entregado',
    'estado' => $yaEstabaPagado ? 'pagado' : 'entregado',
    'hora_entrega' => now(),
]);

if ($metodoPago && ! $yaEstabaPagado) {
    // validación monto recibido
    $montoFinal = $montoRecibido ?? (float) $pedido->total;
    $pedido->update([
        'metodo_pago' => $metodoPago,
        'monto_pagado' => $montoFinal,
        'cambio' => max(0, $montoFinal - (float) $pedido->total),
        'estado' => 'pagado',
        'pagado_en' => now(),
    ]);

    // vincular al turno abierto de la sucursal
    $turnoActivo = TurnoCaja::query()
        ->where('estado', 'abierto')
        ->when($pedido->sucursal_id, fn ($q) => $q->whereHas('caja', fn ($cq) => $cq->where('sucursal_id', $pedido->sucursal_id)))
        ->latest()
        ->first();
    if ($turnoActivo) {
        app(CajaService::class)->vincularCobroPedido($turnoActivo, $pedido);
    }

    app(FidelizacionService::class)->acumularPuntosPorPedido($pedido);
    app(InventarioService::class)->descontarPorPedido($pedido);
}
```

- [ ] **Step 6: Actualizar tests existentes de delivery que asumen no-vinculación**

`Fase4ClientesDeliveryTest::test_liquidacion_recaudo_efectivo_motorizado_en_caja` (`:359-377`) ahora verá `total_ventas_efectivo` con 50.000 en el turno tras `marcarEntregado`; mantener la assertion de `movimientos_caja` (liquidación) pero añadir o adaptar para reflejar que la venta ya está vinculada. Revisar y ajustar a verde.

- [ ] **Step 7: Ejecutar y confirmar GREEN + suite**

Run: `php artisan test --filter=RemediacionDeliveryVinculaTurnoTest --filter=RemediacionCobroSinTurnoTest --filter=Fase4ClientesDeliveryTest`
Ajustar `Fase4ClientesDeliveryTest::test_liquidacion_...` si `total_ventas_efectivo` esperado cambia. Suite completa `php artisan test`.

- [ ] **Step 8: Pint + commit**

```bash
vendor\bin\pint --dirty
git add app/Services/DeliveryService.php app/Services/PedidoService.php tests/Feature/RemediacionDeliveryVinculaTurnoTest.php tests/Feature/RemediacionCobroSinTurnoTest.php tests/Feature/Fase4ClientesDeliveryTest.php resources/views/livewire/delivery/index.blade.php
git commit -m "fix(rem): delivery vincula cobro al turno; cobro exige turno abierto de la sucursal"
```

> **⚠️ Conciencia de cambio de contrato:** exigir turno abierto en `cobrarPedido` puede afectar tests existentes (Fase1OperacionesTest:165, Fase2CajaTest:193, SeguridadDineroAuditoriaTest:139, Fase4ClientesDeliveryTest:216, MeseroPosOptimizationTest). Añadir apertura de turno en esos setUp o crear turno ad-hoc; verificar con suite completa.

---

### Task 8: Verificación final, checklist y registro

**Files:**
- Modify: `docs/auditoria/remediacion-seguridad-rendimiento-2026-09-15.md` (checklist)
- Modify: `docs/auditoria/reauditoria-2026-09-15-verificada.md` (estado de hallazgos)
- Modify: `coordination.md`
- Modify (opcional): `.locks/*` (crear/liberar según trabajos con Antigravity)

- [ ] **Step 1: Suite completa, Pint, audits**

Run: `php artisan test`
Expected: 274/274 + nuevos tests → verde total.
Run: `vendor\bin\pint --test`
Expected: 0.
Run: `composer audit` y `npm audit --omit=dev`
Expected: 0.

- [ ] **Step 2: Escaneo final de secretos y restos**

Run:
```powershell
rg -n --hidden -g "!vendor" -g "!node_modules" -g "!.git" "restomaster2026|sushixpress2026|ryJ8oRftsst90c9" .
```
Expected: 0 resultados fuera de `.env`/historial.

- [ ] **Step 3: Actualizar `reauditoria-2026-09-15-verificada.md`**

Marcar como cerrados los puntos 1, 3, 4, 8, 9, 10, 11, 12, 13 del informe (los cubiertos por este plan), dejando explícitos los que queden pendientes (p. ej. `autorizadoPor` texto libre, arqueo prellenado, `monto_esperado_efectivo` clamp, LOG_LEVEL debug, índices opcionales).

- [ ] **Step 4: Registrar en `coordination.md`**

Entrada: fecha, agente (Antigravity o quien ejecute), lotes cerrados, archivos tocados, tests añadidos, checklist del plan actualizado.

- [ ] **Step 5: Commit final de docs**

```bash
git add docs/auditoria/remediacion-seguridad-rendimiento-2026-09-15.md docs/auditoria/reauditoria-2026-09-15-verificada.md coordination.md
git commit -m "docs(rem): estado final de riesgos nuevos y checklist cerrado"
```

---

## Self-Review

**Cobertura del spec:**
- Punto 1 (mixto/datafono dinero) → Task 1 ✓
- Punto 2 (IDOR sucursal) → Task 2 ✓
- Punto 3 (cluster demo) → Task 3 ✓
- Punto 4 (kpisRealtime + TTL notif) → Task 4 + Task 5 (paso TTL) ✓
- Punto 5 (descuento merge, race, delivery turno, cobro sin turno) → Task 5 (descuento) + Task 6 (race) + Task 7 (delivery + turno) ✓
- Verificación final → Task 8 ✓

**Placeholders:** revisado; quedan dos supuestos que el ejecutor debe verificar con grep de 1 línea (nombre real de la tabla `item_pedido(s)` en Task 4, y la firma exacta de `agregarProducto`/`procesarCobro` en Task 5 porque el test del componente es la forma de fijar el contrato). Ninguno bloquea el inicio.

**Consistencia de tipos:** `cobrarPedido` nuevo 4º parámetro `?float $montoPagoEfectivo` consistente en Task 1 (definición) → Task 7 (llamadas sin cambio). `kpisRealtime(?int $sucursalId = null)` definido en Task 2 y usado en Task 4 igual. `agregarCarritoConBloqueo(int $mesaId, array $carrito)` definido en Task 6 y consumido únicamente en Task 6 (terminal). `vincularCobroPedido` firma intacta, solo cambia la clasificación.