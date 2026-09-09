# Fase 5 — Reservas, Reportes y Configuración — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar la Fase 5 completa: subsistema de Reservas (internas + públicas + webhook), tablero KPI real en Dashboard, centro de Reportes avanzados con exportación PDF/CSV, y página de Configuración (incl. parametrización DIAN y token webhook).

**Architecture:** Sigue los patrones existentes del proyecto: migración → modelo Eloquent → serviço (`App\Services\*`) → vista Volt en `resources/views/livewire/*` → rutas con middleware `role:`. Los reportes se calculan en `ReporteService` (extendido), el dashboard blade llama al servicio para KPIs reales, y las exportaciones usan `barryvdh/laravel-dompdf` (PDF servidor) + CSV generado en memoria. Webhook y formulario público viven en controladores HTTP fuera del grupo auth.

**Tech Stack:** Laravel 13 / PHP 8.3, Livewire 4 + Volt, PostgreSQL (tests SQLite :memory:), blade/Tailwind (design system Aura Gastro), `barryvdh/laravel-dompdf`.

**Spec:** `docs/superpowers/specs/2026-09-09-fase5-reservas-reportes-design.md`

## Global Constraints

- TDD obligatorio: test RED primero, implementación mínima, GREEN, commit (ver trabajos anteriores).
- Volt: NO exponer métodos privados ni `#[Computed]` a la vista — pasar datos por `with()`.
- `constrained('tabla')` SIEMPRE explícito cuando el nombre plural inferido no existe (ej. `constrained('cuentas_por_pagar')`).
- RBAC vía middleware `role:`; admin pasa siempre (`EnsureUserHasRole`). Roles reales: admin, gerente, cajero, mesero, cocina, barra, delivery, repartidor.
- `bootstrap/app.php` NO registra `routes/api.php` → el webhook se define en `routes/web.php` fuera del grupo auth.
- El enum de mesas existe: `App\Enums\MesaEstado` (LIBRE/OCUPADA/POR_LIMPIAR/RESERVADA).
- `Pedido` usa columna `usuario_id` (relación `usuario()`) y `pagado_en` (date). `ItemPedido` tiene `nombre_producto`, `cantidad`, `precio_unitario`, `subtotal` denormalizados.
- Design tokens Aura Gastro: `bg-surface-container-lowest`, `rounded-3xl`, `border-outline-variant/20`, `material-symbols-outlined`, badges `RES-01`, `CFG-01`, `REP-01`.
- No commits de git salvo instrucción explícita (regla del repo).
- `dashboard.blade.php` y `layout/navigation.blade.php` son de Antigravity (DASH-01) → editar SOLO con lock `fase5-reservas-reportes` activo y registro en `coordination.md`.

---

### Task 0: Preparación (lock + coordinación)

**Files:**
- Create: `.locks/fase5-reservas-reportes.lock`
- Modify: `coordination.md`

**Interfaces:** — ninguna (preparación).

- [ ] **Step 1: Crear el lock file**

```bash
echo "Agente: OpenCode | Fase 5: Reservas/Reportes/Config (23:00) | Modulos en uso: dashboard, reportes, navigation, web.php, ReporteService, configuraciones, reservas" > .locks/fase5-reservas-reportes.lock
```

- [ ] **Step 2: Registrar "en progreso" en coordination.md** (sección Trabajo en Progreso)

```markdown
## Trabajo en Progreso
**Fase 5 (OpenCode, en curso):** Reservas (RES-01), Configuraciones/DIAN (CFG-01), Reportes avanzados + exportaciones (REP-01), KPIs Dashboard (DASH-01). Lock: `.locks/fase5-reservas-reportes.lock`.
```

---

### Task 1: Configuraciones backend (migración, modelo, servicio, seeder + tests)

**Files:**
- Create: `database/migrations/2026_09_09_200000_create_configuraciones_table.php`
- Create: `app/Models/Configuracion.php`
- Create: `app/Services/ConfiguracionService.php`
- Create: `database/seeders/ConfiguracionSeeder.php`
- Test: `tests/Feature/Fase5ConfiguracionTest.php`

**Interfaces:**
- Produces (consumidas por Task 2, 5 y 8):
  - `ConfiguracionService::obtener(string $grupo, string $clave, mixed $default = null): mixed`
  - `ConfiguracionService::guardar(string $grupo, string $clave, mixed $valor): void`
  - `ConfiguracionService::regenerarWebhookToken(): string`
  - Claves sembradas: `general.{razon_social,nit,direccion,telefono,regimen}`, `dian.{envio_activo,ambiente,tipo_documento,resolucion_numero,resolucion_fecha,prefijo,desde,hasta,vigente}`, `reservas.{webhook_token,webhook_activo}`, `impresion.{pie_ticket}`.

- [ ] **Step 1: Escribir el test (RED)**

```php
<?php

namespace Tests\Feature;

use App\Services\ConfiguracionService;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase5ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    private ConfiguracionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConfiguracionSeeder::class);
        $this->service = app(ConfiguracionService::class);
    }

    public function test_seeder_crea_configuraciones_base(): void
    {
        $this->assertSame('SushiXpress S.A.S.', $this->service->obtener('general', 'razon_social'));
        $this->assertSame('habilitacion', $this->service->obtener('dian', 'ambiente', ''));
        $this->assertFalse($this->service->obtener('reservas', 'webhook_activo', true));
        $this->assertNotNull($this->service->obtener('reservas', 'webhook_token'));
    }

    public function test_seeder_es_idempotente(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->assertDatabaseCount('configuraciones', \App\Models\Configuracion::count());
    }

    public function test_obtener_devuelve_default_si_no_existe(): void
    {
        $this->assertSame('valor-default', $this->service->obtener('nada', 'nada', 'valor-default'));
    }

    public function test_guardar_y_obtener_roundtrip(): void
    {
        $this->service->guardar('dian', 'tipo_documento', '02');
        $this->assertSame('02', $this->service->obtener('dian', 'tipo_documento'));
    }

    public function test_guardar_acepta_arrays(): void
    {
        $this->service->guardar('test', 'datos', ['a' => 1, 'b' => 'x']);
        $this->assertSame(['a' => 1, 'b' => 'x'], $this->service->obtener('test', 'datos'));
    }

    public function test_regenerar_webhook_token_cambia_valor(): void
    {
        $anterior = $this->service->obtener('reservas', 'webhook_token');
        $nuevo = $this->service->regenerarWebhookToken();

        $this->assertNotSame($anterior, $nuevo);
        $this->assertSame($nuevo, $this->service->obtener('reservas', 'webhook_token'));
        $this->assertGreaterThanOrEqual(40, strlen($nuevo));
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter Fase5ConfiguracionTest`
Expected: FAIL — clase/dependencias no existen.

- [ ] **Step 3: Migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('grupo')->index();
            $table->string('clave')->unique();
            $table->json('valor')->nullable();
            $table->timestamps();
            $table->index(['grupo', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
```

- [ ] **Step 4: Modelo**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuraciones';

    protected $fillable = ['grupo', 'clave', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'array'];
    }
}
```

- [ ] **Step 5: Servicio**

```php
<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Str;

class ConfiguracionService
{
    public function obtener(string $grupo, string $clave, mixed $default = null): mixed
    {
        $config = Configuracion::where('grupo', $grupo)->where('clave', $clave)->first();

        return $config?->valor ?? $default;
    }

    public function guardar(string $grupo, string $clave, mixed $valor): void
    {
        Configuracion::updateOrCreate(
            ['grupo' => $grupo, 'clave' => $clave],
            ['valor' => $valor],
        );
    }

    public function regenerarWebhookToken(): string
    {
        $token = Str::random(48);

        $this->guardar('reservas', 'webhook_token', $token);

        return $token;
    }
}
```

- [ ] **Step 6: Seeder**

```php
<?php

namespace Database\Seeders;

use App\Services\ConfiguracionService;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(ConfiguracionService::class);
        $defaults = [
            'general' => [
                'razon_social' => 'SushiXpress S.A.S.',
                'nit' => '',
                'direccion' => '',
                'telefono' => '',
                'regimen' => 'Común',
            ],
            'dian' => [
                'envio_activo' => false,
                'ambiente' => 'habilitacion',
                'tipo_documento' => '01',
                'resolucion_numero' => '',
                'resolucion_fecha' => null,
                'prefijo' => 'MP',
                'desde' => null,
                'hasta' => null,
                'vigente' => false,
            ],
            'reservas' => [
                'webhook_token' => \Illuminate\Support\Str::random(48),
                'webhook_activo' => false,
            ],
            'impresion' => [
                'pie_ticket' => '¡Gracias por preferir SushiXpress!',
            ],
        ];

        foreach ($defaults as $grupo => $claves) {
            foreach ($claves as $clave => $valor) {
                $svc->obtener($grupo, $clave, 'no-existe') === 'no-existe'
                    ? $svc->guardar($grupo, $clave, $valor)
                    : null;
            }
        }
    }
}
```

- [ ] **Step 7: Registrar el seeder en `DatabaseSeeder`** (añadir `$this->call(ConfiguracionSeeder::class);`; comprobar si usa `call()` o arreglo y seguir el patrón existente).

- [ ] **Step 8: Ejecutar y verificar GREEN**

Run: `php artisan test --filter Fase5ConfiguracionTest`
Expected: PASS (6 tests).

---

### Task 2: CFG-01 — vista Configuraciones + ruta RBAC

**Files:**
- Create: `resources/views/livewire/configuracion/index.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Middleware/EnsureUserHasRole.php` — NO (no se toca)
- Test: `tests/Feature/Fase5ConfiguracionTest.php` (ampliar)

**Interfaces:**
- Consumes: `ConfiguracionService` (Task 1).
- Produces: ruta `route('configuracion')`, componente Volt `configuracion.index` (métodos públicos `guardarDian()`, `guardarEmpresa()`, `guardarImpresion()`, `regenerarToken()`, `toggleWebhook()`).

- [ ] **Step 1: Escribir el test (RED) — ampliar Fase5ConfiguracionTest**

```php
    public function test_pantalla_configuracion_solo_admin(): void
    {
        $mesero = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'mesero')->value('id')]);
        $this->actingAs($mesero)->get(route('configuracion'))->assertForbidden();

        $admin = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'admin')->value('id')]);
        $this->actingAs($admin)->get(route('configuracion'))->assertOk();
        $this->actingAs($admin)->get(route('configuracion'))->assertSeeVolt('configuracion.index');
    }

    public function test_guardar_config_dian_desde_ui(): void
    {
        $admin = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'admin')->value('id')]);

        \Livewire\Volt\Volt::actingAs($admin)
            ->test('configuracion.index')
            ->set('dianForm.razon_social', 'SushiXpress S.A.S.')
            ->set('dianForm.nit', '9011234567')
            ->set('dianForm.regimen', 'Simplificado')
            ->set('dianForm.envio_activo', true)
            ->call('guardarDian')
            ->assertHasNoErrors();

        $svc = app(\App\Services\ConfiguracionService::class);
        $this->assertSame('9011234567', $svc->obtener('general', 'nit'));
        $this->assertTrue($svc->obtener('dian', 'envio_activo'));
    }

    public function test_regenerar_token_desde_ui(): void
    {
        $admin = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'admin')->value('id')]);
        $antes = app(\App\Services\ConfiguracionService::class)->obtener('reservas', 'webhook_token');

        \Livewire\Volt\Volt::actingAs($admin)
            ->test('configuracion.index')
            ->call('regenerarToken');

        $despues = app(\App\Services\ConfiguracionService::class)->obtener('reservas', 'webhook_token');
        $this->assertNotSame($antes, $despues);
    }
```
Nota: si el TestCase base no siembra roles, crear los roles `admin` y `mesero` en `setUp` (como en otros tests de Fase).

- [ ] **Step 2: Ejecutar y verificar FAIL**

Run: `php artisan test --filter Fase5ConfiguracionTest`
Expected: FAIL — ruta y componente no existen.

- [ ] **Step 3: Ruta en `routes/web.php`** (dentro del grupo `auth`)

```php
    \Livewire\Volt\Volt::route('configuracion', 'configuracion.index')->middleware('role:admin')->name('configuracion');
```

- [ ] **Step 4: Componente Volt `resources/views/livewire/configuracion/index.blade.php`** (estilo REP-01, tokens Aura Gastro; pestañas DIAN / Empresa / Reservas-Webhook)

```blade
<?php

use App\Services\ConfiguracionService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $tabActiva = 'dian';

    public array $dianForm = [];
    public array $empresaForm = [];
    public array $reservasForm = [];
    public string $webhookToken = '';

    public function mount(): void
    {
        $svc = app(ConfiguracionService::class);
        $this->dianForm = [
            'razon_social' => $svc->obtener('general', 'razon_social', ''),
            'nit' => $svc->obtener('general', 'nit', ''),
            'regimen' => $svc->obtener('general', 'regimen', 'Común'),
            'ambiente' => $svc->obtener('dian', 'ambiente', 'habilitacion'),
            'tipo_documento' => $svc->obtener('dian', 'tipo_documento', '01'),
            'resolucion_numero' => $svc->obtener('dian', 'resolucion_numero', ''),
            'resolucion_fecha' => $svc->obtener('dian', 'resolucion_fecha'),
            'prefijo' => $svc->obtener('dian', 'prefijo', 'MP'),
            'desde' => $svc->obtener('dian', 'desde'),
            'hasta' => $svc->obtener('dian', 'hasta'),
            'vigente' => (bool) $svc->obtener('dian', 'vigente', false),
            'envio_activo' => (bool) $svc->obtener('dian', 'envio_activo', false),
        ];
        $this->empresaForm = [
            'direccion' => $svc->obtener('general', 'direccion', ''),
            'telefono' => $svc->obtener('general', 'telefono', ''),
        ];
        $this->reservasForm = [
            'webhook_activo' => (bool) $svc->obtener('reservas', 'webhook_activo', false),
        ];
        $this->webhookToken = $svc->obtener('reservas', 'webhook_token', '');
    }

    public function guardarDian(): void
    {
        $validated = $this->validate([
            'dianForm.razon_social' => ['required', 'string', 'max:255'],
            'dianForm.nit' => ['nullable', 'string', 'max:30'],
            'dianForm.regimen' => ['required', 'string', 'max:60'],
            'dianForm.ambiente' => ['required', 'in:habilitacion,produccion'],
            'dianForm.tipo_documento' => ['required', 'string', 'max:4'],
            'dianForm.resolucion_numero' => ['nullable', 'string', 'max:30'],
            'dianForm.prefijo' => ['nullable', 'string', 'max:10'],
        ]);

        $svc = app(ConfiguracionService::class);
        foreach (['razon_social', 'nit', 'regimen'] as $k) {
            $svc->guardar('general', $k, $validated['dianForm'][$k]);
        }
        foreach (['ambiente', 'tipo_documento', 'resolucion_numero', 'resolucion_fecha', 'prefijo', 'desde', 'hasta', 'vigente', 'envio_activo'] as $k) {
            $svc->guardar('dian', $k, $validated['dianForm'][$k] ?? $this->dianForm[$k]);
        }
        session()->flash('status', 'Configuración DIAN guardada.');
    }

    public function guardarEmpresa(): void
    {
        $validated = $this->validate([
            'empresaForm.direccion' => ['nullable', 'string', 'max:255'],
            'empresaForm.telefono' => ['nullable', 'string', 'max:30'],
        ]);
        $svc = app(ConfiguracionService::class);
        $svc->guardar('general', 'direccion', $validated['empresaForm']['direccion']);
        $svc->guardar('general', 'telefono', $validated['empresaForm']['telefono']);
        session()->flash('status', 'Datos de empresa guardados.');
    }

    public function toggleWebhook(): void
    {
        $this->validate(['reservasForm.webhook_activo' => ['boolean']]);
        app(ConfiguracionService::class)->guardar('reservas', 'webhook_activo', (bool) $this->reservasForm['webhook_activo']);
        session()->flash('status', 'Estado del webhook actualizado.');
    }

    public function regenerarToken(): void
    {
        $this->webhookToken = app(ConfiguracionService::class)->regenerarWebhookToken();
        session()->flash('status', 'Token de webhook regenerado.');
    }

    public function with(): array
    {
        return ['pieTicket' => app(ConfiguracionService::class)->obtener('impresion', 'pie_ticket', '')];
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-[24px] text-primary">settings</span>
        <h1 class="text-xl font-extrabold tracking-tight text-on-surface">Configuración del Sistema</h1>
        <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">CFG-01</span>
    </div>
</x-slot>

<div class="space-y-6">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    @if (session('status'))
        <div class="rounded-xl border border-secondary/40 bg-secondary/10 px-4 py-2 text-xs font-bold text-secondary">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach (['dian' => 'DIAN / Facturación', 'empresa' => 'Empresa', 'reservas' => 'Reservas & Webhook'] as $tab => $label)
            <button wire:click="$set('tabActiva', '{{ $tab }}')"
                class="rounded-xl px-4 py-2 text-xs font-bold {{ $tabActiva === $tab ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface-variant border border-outline-variant/20' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tabActiva === 'dian')
        <form wire:submit="guardarDian" class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 space-y-4">
            <p class="text-[11px] text-on-surface-variant">Parametrización del emisor y resolución. La integración con el proveedor DIAN se conectará en una fase posterior.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="text-xs font-bold text-on-surface-variant">Razón social</label>
                    <input type="text" wire:model="dianForm.razon_social" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">NIT</label>
                    <input type="text" wire:model="dianForm.nit" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Régimen</label>
                    <input type="text" wire:model="dianForm.regimen" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Ambiente</label>
                    <select wire:model="dianForm.ambiente" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0">
                        <option value="habilitacion">Habilitación</option>
                        <option value="produccion">Producción</option>
                    </select></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Tipo documento</label>
                    <input type="text" wire:model="dianForm.tipo_documento" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">N° resolución</label>
                    <input type="text" wire:model="dianForm.resolucion_numero" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Prefijo</label>
                    <input type="text" wire:model="dianForm.prefijo" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Fecha resolución</label>
                    <input type="date" wire:model="dianForm.resolucion_fecha" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Rango desde</label>
                    <input type="text" wire:model="dianForm.desde" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Rango hasta</label>
                    <input type="text" wire:model="dianForm.hasta" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="dianForm.vigente" class="rounded" />
                <label class="text-xs font-bold text-on-surface-variant">Resolución vigente</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="dianForm.envio_activo" class="rounded" />
                <label class="text-xs font-bold text-on-surface-variant">Facturación electrónica activa</label>
            </div>
            <button type="submit" class="rounded-xl bg-primary px-5 py-3 text-sm font-black text-on-primary">Guardar DIAN</button>
        </form>
    @endif

    @if ($tabActiva === 'empresa')
        <form wire:submit="guardarEmpresa" class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="text-xs font-bold text-on-surface-variant">Dirección</label>
                    <input type="text" wire:model="empresaForm.direccion" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <div><label class="text-xs font-bold text-on-surface-variant">Teléfono</label>
                    <input type="text" wire:model="empresaForm.telefono" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
            </div>
            <p class="text-[11px] text-on-surface-variant">Pie de ticket actual: <code class="font-mono">{{ $pieTicket }}</code></p>
            <button type="submit" class="rounded-xl bg-primary px-5 py-3 text-sm font-black text-on-primary">Guardar Empresa</button>
        </form>
    @endif

    @if ($tabActiva === 'reservas')
        <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-5 space-y-4">
            <div>
                <label class="text-xs font-bold text-on-surface-variant">Token del webhook de reservas (n8n / WhatsApp)</label>
                <div class="mt-1 flex items-center gap-2">
                    <code class="flex-1 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-mono break-all">{{ $webhookToken }}</code>
                    <button type="button" wire:click="regenerarToken" class="rounded-xl bg-secondary px-4 py-2 text-xs font-bold text-white">Regenerar</button>
                </div>
                <p class="mt-1 text-[11px] text-on-surface-variant">Endpoint: <code class="font-mono">POST /api/reservas</code> con header <code class="font-mono">X-Webhook-Token</code>.</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="reservasForm.webhook_activo" wire:change="toggleWebhook" class="rounded" />
                <label class="text-xs font-bold text-on-surface-variant">Webhook de reservas activo</label>
            </div>
        </div>
    @endif
</div>
```

- [ ] **Step 5: Ejecutar y verificar GREEN**

Run: `php artisan test --filter Fase5ConfiguracionTest`
Expected: PASS (9 tests).

> Nota convenience para tests: si `User::factory()->create(['role_id' => ...])` requiere roles sembrados, añadir `$this->seed(\Database\Seeders\RoleSeeder::class);` en `setUp` de `Fase5ConfiguracionTest` (los demás tests de Fase crean roles manualmente; seguir el patrón de `Fase0TrabajadoresTest`).

---

### Task 3: Reservas backend (migraciones, modelo, servicio + tests)

**Files:**
- Create: `database/migrations/2026_09_09_200010_create_reservas_table.php`
- Create: `database/migrations/2026_09_09_200020_create_reserva_mesa_table.php`
- Create: `app/Models/Reserva.php`
- Modify: `app/Models/Mesa.php` (relación `reservas()`)
- Create: `app/Services/ReservaService.php`
- Test: `tests/Feature/Fase5ReservasTest.php`

**Interfaces:**
- Produces:
  - `ReservaService::verificarDisponibilidad(string $fecha, string $horaInicio, int $personas, int $duracionMin = 120): \Illuminate\Support\Collection` → Collection de `Mesa` libres con `capacidad >= personas` y sin solapamiento.
  - `ReservaService::crear(array $datos, string $origen = 'sistema'): Reserva`
  - `ReservaService::confirmar(Reserva $reserva, ?\App\Models\User $usuario = null, ?array $mesaIds = null): Reserva`
  - `ReservaService::marcarLlego(Reserva $reserva): Reserva`
  - `ReservaService::finalizar(Reserva $reserva): Reserva`
  - `ReservaService::cancelar(Reserva $reserva): Reserva`
  - `ReservaService::marcarNoShow(Reserva $reserva): Reserva`
  - `ReservaService::reservasDelDia(string $fecha): \Illuminate\Database\Eloquent\Collection`
  - Modelo `Reserva` con relación `mesas()` (BelongsToMany), `cliente()`, `sucursal()`, `confirmadoPor()`, `creadoPor()`.

- [ ] **Step 1: Escribir el test (RED)**

```php
<?php

namespace Tests\Feature;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class Fase5ReservasTest extends TestCase
{
    use RefreshDatabase;

    private ReservaService $service;
    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        Role::create(['nombre' => 'Cocina', 'slug' => 'cocina']);

        $this->sucursal = Sucursal::create(['nombre' => 'Sede Medellín', 'codigo' => 'MDE-01', 'direccion' => 'Calle 10', 'activa' => true]);
        $this->service = app(ReservaService::class);
    }

    private function crearMesas(): array
    {
        $m1 = Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 1, 'zona' => 'salon', 'capacidad' => 2, 'estado' => MesaEstado::LIBRE->value, 'activa' => true]);
        $m4 = Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 2, 'zona' => 'salon', 'capacidad' => 6, 'estado' => MesaEstado::LIBRE->value, 'activa' => true]);

        return [$m1, $m4];
    }

    public function test_crear_reserva_interna(): void
    {
        [$m1, $m4] = $this->crearMesas();

        $reserva = $this->service->crear([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Ana Torres',
            'telefono_contacto' => '3005551122',
            'fecha' => '2026-09-20',
            'hora_llegada' => '13:00',
            'personas' => 4,
            'mesa_ids' => [$m4->id],
            'notas' => 'Aniversario',
        ], 'sistema');

        $this->assertSame('solicitada', $reserva->estado);
        $this->assertSame('sistema', $reserva->origen);
        $this->assertNotNull($reserva->token_publico);
        $this->assertCount(1, $reserva->mesas);
        $this->assertSame('Ana Torres', $reserva->nombre_contacto);
    }

    public function test_crear_rechaza_personas_cero_y_fecha_pasada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->crear([
            'nombre_contacto' => 'X', 'telefono_contacto' => '300',
            'fecha' => '2020-01-01', 'hora_llegada' => '13:00', 'personas' => 0,
        ]);
    }

    public function test_disponibilidad_devuelve_mesas_con_capacidad_sin_ocupadas(): void
    {
        [$m1, $m4] = $this->crearMesas();
        $ocupada = Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 3, 'zona' => 'barra', 'capacidad' => 4, 'estado' => MesaEstado::OCUPADA->value, 'activa' => true]);

        $disponibles = $this->service->verificarDisponibilidad('2026-09-20', '13:00', 2);

        $this->assertTrue($disponibles->contains('id', $m1->id));
        $this->assertTrue($disponibles->contains('id', $m4->id));
        $this->assertFalse($disponibles->contains('id', $ocupada->id));
    }

    public function test_confirmar_bloquea_mesa_y_rechaza_solapamiento(): void
    {
        [$m1, $m4] = $this->crearMesas();
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        $reserva = $this->service->crear([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Ana', 'telefono_contacto' => '300',
            'fecha' => '2026-09-20', 'hora_llegada' => '13:00', 'personas' => 2,
            'mesa_ids' => [$m1->id],
        ]);

        $this->service->confirmar($reserva, $admin);

        $this->assertSame('confirmada', $reserva->fresh()->estado);
        $this->assertSame(MesaEstado::RESERVADA->value, $m1->fresh()->estado);

        $otra = $this->service->crear([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Luis', 'telefono_contacto' => '301',
            'fecha' => '2026-09-20', 'hora_llegada' => '13:30', 'personas' => 2,
            'mesa_ids' => [$m1->id],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->confirmar($otra, $admin);
    }

    public function test_ciclo_vida_completo_bloquea_y_libera_mesa(): void
    {
        [$m1] = $this->crearMesas();
        $reserva = $this->service->crear([
            'sucursal_id' => $this->sucursal->id,
            'nombre_contacto' => 'Ana', 'telefono_contacto' => '300',
            'fecha' => '2026-09-20', 'hora_llegada' => '13:00', 'personas' => 2,
            'mesa_ids' => [$m1->id],
        ]);

        $this->service->confirmar($reserva);
        $this->service->marcarLlego($reserva);
        $this->assertSame('llego', $reserva->fresh()->estado);
        $this->assertSame(MesaEstado::OCUPADA->value, $m1->fresh()->estado);

        $this->service->finalizar($reserva);
        $this->assertSame('finalizada', $reserva->fresh()->estado);
        $this->assertSame(MesaEstado::LIBRE->value, $m1->fresh()->estado);
    }

    public function test_cancelar_y_no_show_liberan_mesa(): void
    {
        [$m1] = $this->crearMesas();
        $r1 = $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'A', 'telefono_contacto' => '1', 'fecha' => '2026-09-21', 'hora_llegada' => '20:00', 'personas' => 2, 'mesa_ids' => [$m1->id]]);
        $this->service->confirmar($r1);
        $this->service->cancelar($r1);
        $this->assertSame('cancelada', $r1->fresh()->estado);
        $this->assertSame(MesaEstado::LIBRE->value, $m1->fresh()->estado);

        $r2 = $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'B', 'telefono_contacto' => '2', 'fecha' => '2026-09-22', 'hora_llegada' => '20:00', 'personas' => 2, 'mesa_ids' => [$m1->id]]);
        $this->service->confirmar($r2);
        $this->service->marcarNoShow($r2);
        $this->assertSame('no_mostro', $r2->fresh()->estado);
        $this->assertSame(MesaEstado::LIBRE->value, $m1->fresh()->estado);
    }

    public function test_reservas_del_dia_filtra_fecha(): void
    {
        $this->crearMesas();
        $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'A', 'telefono_contacto' => '1', 'fecha' => '2026-09-20', 'hora_llegada' => '13:00', 'personas' => 2]);
        $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'B', 'telefono_contacto' => '2', 'fecha' => '2026-09-21', 'hora_llegada' => '13:00', 'personas' => 2]);

        $dia = $this->service->reservasDelDia('2026-09-20');
        $this->assertCount(1, $dia);
        $this->assertSame('A', $dia->first()->nombre_contacto);
    }

    public function test_auditoria_registra_reserva_creada(): void
    {
        $this->crearMesas();
        $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'A', 'telefono_contacto' => '1', 'fecha' => '2026-09-20', 'hora_llegada' => '13:00', 'personas' => 2]);

        $this->assertDatabaseHas('auditorias', ['accion' => 'reserva.creada', 'entidad' => 'reserva']);
    }

    public function test_rbac_ruta_reservas(): void
    {
        $mesero = User::create(['name' => 'M', 'email' => 'm@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'mesero')->value('id'), 'activo' => true]);
        $cocina = User::create(['name' => 'C', 'email' => 'c@test.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'cocina')->value('id'), 'activo' => true]);

        $this->actingAs($mesero)->get(route('reservas'))->assertOk();
        $this->actingAs($cocina)->get(route('reservas'))->assertForbidden();
    }
}
```

- [ ] **Step 2: Ejecutar y verificar FAIL**

Run: `php artisan test --filter Fase5ReservasTest`
Expected: FAIL — Tabla `reservas` no existe.

- [ ] **Step 3: Migración de reservas**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('nombre_contacto');
            $table->string('telefono_contacto', 30);
            $table->string('email_contacto', 120)->nullable();
            $table->date('fecha');
            $table->time('hora_llegada');
            $table->unsignedSmallInteger('duracion_min')->default(120);
            $table->unsignedSmallInteger('personas');
            $table->string('estado')->default('solicitada');
            $table->string('origen')->default('sistema');
            $table->text('notas')->nullable();
            $table->decimal('anticipo', 12, 2)->nullable();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_publico', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha', 'estado']);
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
```

- [ ] **Step 4: Migración pivote**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva_mesa', function (Blueprint $table) {
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $table->primary(['reserva_id', 'mesa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_mesa');
    }
};
```

- [ ] **Step 5: Modelo Reserva**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'sucursal_id', 'cliente_id', 'nombre_contacto', 'telefono_contacto', 'email_contacto',
        'fecha', 'hora_llegada', 'duracion_min', 'personas', 'estado', 'origen',
        'notas', 'anticipo', 'confirmado_por', 'token_publico', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'anticipo' => 'decimal:2',
            'duracion_min' => 'integer',
            'personas' => 'integer',
        ];
    }

    public function mesas(): BelongsToMany
    {
        return $this->belongsToMany(Mesa::class, 'reserva_mesa');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 6: Relación en `Mesa`** (app/Models/Mesa.php)

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// dentro de la clase:
public function reservas(): BelongsToMany
{
    return $this->belongsToMany(Reserva::class, 'reserva_mesa');
}
```

- [ ] **Step 7: Servicio**

```php
<?php

namespace App\Services;

use App\Enums\MesaEstado;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReservaService
{
    public function verificarDisponibilidad(string $fecha, string $horaInicio, int $personas, int $duracionMin = 120): Collection
    {
        $horaFin = $this->sumarMinutos($horaInicio, $duracionMin);

        $bloqueadas = Reserva::query()
            ->whereIn('estado', ['confirmada', 'llego'])
            ->where('fecha', $fecha)
            ->get()
            ->filter(fn (Reserva $r) => $this->seSolapan($horaInicio, $horaFin, $r->hora_llegada, $this->sumarMinutos($r->hora_llegada, $r->duracion_min)))
            ->pluck('id');

        $mesasBloqueadas = \DB::table('reserva_mesa')
            ->whereIn('reserva_id', $bloqueadas)
            ->pluck('mesa_id');

        return Mesa::query()
            ->where('activa', true)
            ->where('capacidad', '>=', $personas)
            ->where('estado', MesaEstado::LIBRE->value)
            ->whereNotIn('id', $mesasBloqueadas)
            ->orderBy('zona')
            ->orderBy('numero')
            ->get();
    }

    public function crear(array $datos, string $origen = 'sistema'): Reserva
    {
        $personas = (int) ($datos['personas'] ?? 0);
        $fecha = $datos['fecha'] ?? null;

        if ($personas < 1) {
            throw new InvalidArgumentException('La reserva debe ser para al menos una persona.');
        }
        if (!$fecha || $fecha < now()->toDateString()) {
            throw new InvalidArgumentException('Debe elegirse una fecha igual o posterior a hoy.');
        }

        $reserva = Reserva::create([
            'sucursal_id' => $datos['sucursal_id'] ?? null,
            'cliente_id' => $datos['cliente_id'] ?? null,
            'nombre_contacto' => $datos['nombre_contacto'],
            'telefono_contacto' => $datos['telefono_contacto'],
            'email_contacto' => $datos['email_contacto'] ?? null,
            'fecha' => $fecha,
            'hora_llegada' => $datos['hora_llegada'],
            'duracion_min' => (int) ($datos['duracion_min'] ?? 120),
            'personas' => $personas,
            'estado' => 'solicitada',
            'origen' => $origen,
            'notas' => $datos['notas'] ?? null,
            'anticipo' => $datos['anticipo'] ?? null,
            'token_publico' => Str::random(32),
            'created_by' => $datos['created_by'] ?? auth()->id(),
        ]);

        if (!empty($datos['mesa_ids'])) {
            $reserva->mesas()->sync($datos['mesa_ids']);
        }

        app(AuditoriaService::class)->registrar(
            accion: 'reserva.creada',
            entidad: 'reserva',
            entidadId: $reserva->id,
            descripcion: "Reserva {$reserva->fecha->toDateString()} {$reserva->hora_llegada} para {$personas} personas",
            datos: ['origen' => $origen, 'personas' => $personas],
        );

        return $reserva;
    }

    public function confirmar(Reserva $reserva, ?User $usuario = null, ?array $mesaIds = null): Reserva
    {
        if ($mesaIds !== null) {
            $reserva->mesas()->sync($mesaIds);
        }

        if ($reserva->mesas->isEmpty()) {
            $disponibles = $this->verificarDisponibilidad($reserva->fecha->toDateString(), $reserva->hora_llegada, $reserva->personas, $reserva->duracion_min);

            if ($disponibles->isEmpty()) {
                throw new InvalidArgumentException('No hay mesas disponibles para esta reserva.');
            }

            $reserva->mesas()->attach($disponibles->sortByDesc('capacidad')->first()->id);
            $reserva->refresh();
        }

        $reserva->mesas->each(function (Mesa $mesa) use ($reserva) {
            $ocupada = ($mesa->estado === MesaEstado::OCUPADA->value || $mesa->estado === MesaEstado::POR_LIMPIAR->value);
            if ($ocupada) {
                throw new InvalidArgumentException("La mesa {$mesa->nombre} no está disponible.");
            }
        });

        $reserva->update([
            'estado' => 'confirmada',
            'confirmado_por' => $usuario?->id ?? auth()->id(),
        ]);

        $reserva->mesas->each(fn (Mesa $mesa) => $mesa->update(['estado' => MesaEstado::RESERVADA->value]));

        $this->auditar('reserva.confirmada', $reserva);

        return $reserva;
    }

    public function marcarLlego(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'llego']);
        $reserva->mesas->each(fn (Mesa $mesa) => $mesa->update(['estado' => MesaEstado::OCUPADA->value]));
        $this->auditar('reserva.llego', $reserva);

        return $reserva;
    }

    public function finalizar(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'finalizada']);
        $this->liberarMesas($reserva);
        $this->auditar('reserva.finalizada', $reserva);

        return $reserva;
    }

    public function cancelar(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'cancelada']);
        $this->liberarMesas($reserva);
        $this->auditar('reserva.cancelada', $reserva);

        return $reserva;
    }

    public function marcarNoShow(Reserva $reserva): Reserva
    {
        $reserva->update(['estado' => 'no_mostro']);
        $this->liberarMesas($reserva);
        $this->auditar('reserva.no_mostro', $reserva);

        return $reserva;
    }

    public function reservasDelDia(string $fecha): \Illuminate\Database\Eloquent\Collection
    {
        return Reserva::with(['mesas', 'cliente', 'confirmadoPor'])
            ->where('fecha', $fecha)
            ->orderBy('hora_llegada')
            ->get();
    }

    private function liberarMesas(Reserva $reserva): void
    {
        $reserva->mesas->each(function (Mesa $mesa) {
            if ($mesa->estado === MesaEstado::RESERVADA->value || $mesa->estado === MesaEstado::OCUPADA->value) {
                $mesa->update(['estado' => MesaEstado::LIBRE->value]);
            }
        });
    }

    private function seSolapan(string $aIni, string $aFin, string $bIni, string $bFin): bool
    {
        return $aIni < $bFin && $bIni < $aFin;
    }

    private function sumarMinutos(string $hora, int $minutos): string
    {
        return \Illuminate\Support\Carbon::createFromFormat('H:i:s', $hora)->addMinutes($minutos)->format('H:i:s');
    }

    private function auditar(string $accion, Reserva $reserva): void
    {
        app(AuditoriaService::class)->registrar(
            accion: $accion,
            entidad: 'reserva',
            entidadId: $reserva->id,
            descripcion: "{$reserva->nombre_contacto} · {$reserva->fecha->toDateString()} {$reserva->hora_llegada}",
        );
    }
}
```
Nota: `Mesa` puede no tener columna `nombre` en sus fillable (usa `numero`). En `confirmar` usar `$mesa->nombre ?? ('Mesa '.$mesa->numero)`. Verificar `Mesa::create` en mesas/index: usa `numero` y `nombre` (el test F4 usó `numero` y `nombre`). Ajustar el mensaje a `Mesa #{$mesa->numero}`.

- [ ] **Step 8: Ruta interna** (routes/web.php, dentro de grupo auth)

```php
    \Livewire\Volt\Volt::route('reservas', 'reservas.index')->middleware('role:mesero,cajero,gerente')->name('reservas');
```

- [ ] **Step 9: Ejecutar y verificar GREEN**

Run: `php artisan test --filter Fase5ReservasTest`
Expected: PASS (8 tests).

---

### Task 4: RES-01 — vista interna Reservas

**Files:**
- Create: `resources/views/livewire/reservas/index.blade.php`
- Test: `tests/Feature/Fase5ReservasTest.php` (ampliar)

**Interfaces:**
- Consumes: `ReservaService` (Task 3), ruta `route('reservas')`.
- Produces: componente Volt `reservas.index` (métodos: `confirmar()`, `marcarLlego()`, `finalizar()`, `cancelar()`, `marcarNoShow()`, `cambiarFecha()`, `abrirCrear()`, `crearReserva()`, `cambiarEstadoFiltro()`, prop `fecha`, `filtrarEstado`, `reservaSeleccionada`, `crearForm`).

- [ ] **Step 1: Escribir el test (RED) — ampliar Fase5ReservasTest**

```php
    public function test_pantalla_reservas_renders(): void
    {
        $admin = \App\Models\User::create(['name' => 'Ad', 'email' => 'ad@test.com', 'password' => bcrypt('secret'), 'role_id' => \App\Models\Role::where('slug', 'admin')->value('id'), 'activo' => true]);
        $this->actingAs($admin)->get(route('reservas'))->assertOk();
        $this->actingAs($admin)->get(route('reservas'))->assertSeeVolt('reservas.index');
        $this->actingAs($admin)->get(route('reservas'))->assertSee('RES-01');
    }

    public function test_agenda_muestra_reservas_del_dia(): void
    {
        $this->crearMesas();
        $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'Clara Estrada', 'telefono_contacto' => '300', 'fecha' => '2026-09-20', 'hora_llegada' => '13:00', 'personas' => 2]);

        $admin = \App\Models\User::create(['name' => 'Ad', 'email' => 'ad2@test.com', 'password' => bcrypt('secret'), 'role_id' => \App\Models\Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        \Livewire\Volt\Volt::actingAs($admin)
            ->test('reservas.index')
            ->set('fecha', '2026-09-20')
            ->assertSee('Clara Estrada');
    }

    public function test_confirmar_desde_ui_bloquea_mesa(): void
    {
        [$m1] = $this->crearMesas();
        $reserva = $this->service->crear(['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'Ana', 'telefono_contacto' => '300', 'fecha' => '2026-09-20', 'hora_llegada' => '13:00', 'personas' => 2, 'mesa_ids' => [$m1->id]]);
        $admin = \App\Models\User::create(['name' => 'Ad', 'email' => 'ad3@test.com', 'password' => bcrypt('secret'), 'role_id' => \App\Models\Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        \Livewire\Volt\Volt::actingAs($admin)
            ->test('reservas.index')
            ->set('fecha', '2026-09-20')
            ->set('reservaSeleccionada', $reserva->id)
            ->call('confirmar')
            ->assertHasNoErrors();

        $this->assertSame('confirmada', $reserva->fresh()->estado);
        $this->assertSame(MesaEstado::RESERVADA->value, $m1->fresh()->estado);
    }
```
Nota: en el test `test_agenda_muestra_reservas_del_dia`, el componente arranca `fecha` en la fecha de hoy; setear `fecha` después del `->test()` dispara `cambiarFecha`. Si `with()` recarga, `assertSee` busca el HTML final — el setter `cambiarFecha()` re-renderiza.

- [ ] **Step 2: Ejecutar y verificar FAIL**

Run: `php artisan test --filter Fase5ReservasTest`
Expected: FAIL — componente `reservas.index` no existe.

- [ ] **Step 3: Componente Volt `reservas.index`** (agenda + crear + ciclo de vida; tokens Aura Gastro; modal crear con disponibilidad)

```blade
<?php

use App\Models\Reserva;
use App\Services\ReservaService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $fecha = '';
    public string $filtrarEstado = 'todas';
    public ?int $reservaSeleccionada = null;
    public bool $modalCrear = false;

    public array $crearForm = [
        'nombre_contacto' => '',
        'telefono_contacto' => '',
        'fecha' => '',
        'hora_llegada' => '13:00',
        'personas' => 2,
        'mesa_ids' => [],
    ];

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $this->crearForm['fecha'] = now()->toDateString();
    }

    public function cambiarFecha(): void
    {
        $this->fecha = $this->fecha ?: now()->toDateString();
    }

    public function with(): array
    {
        $service = app(ReservaService::class);

        return [
            'reservas' => $service->reservasDelDia($this->fecha)
                ->when($this->filtrarEstado !== 'todas', fn ($col) => $col->where('estado', $this->filtrarEstado)),
            'conteo' => [
                'solicitadas' => $service->reservasDelDia($this->fecha)->where('estado', 'solicitada')->count(),
                'confirmadas' => $service->reservasDelDia($this->fecha)->where('estado', 'confirmada')->count(),
                'canceladas' => $service->reservasDelDia($this->fecha)->where('estado', 'cancelada')->count(),
            ],
            'disponibles' => $service->verificarDisponibilidad($this->fecha, $this->crearForm['hora_llegada'], (int) $this->crearForm['personas']),
        ];
    }

    public function abrirCrear(): void
    {
        $this->modalCrear = true;
    }

    public function crearReserva(): void
    {
        $validated = $this->validate([
            'crearForm.nombre_contacto' => ['required', 'string', 'max:255'],
            'crearForm.telefono_contacto' => ['required', 'string', 'max:30'],
            'crearForm.fecha' => ['required', 'date', 'after_or_equal:' . now()->toDateString()],
            'crearForm.hora_llegada' => ['required', 'date_format:H:i'],
            'crearForm.personas' => ['required', 'integer', 'min:1'],
            'crearForm.mesa_ids' => ['nullable', 'array'],
        ]);

        app(ReservaService::class)->crear($validated['crearForm'], 'sistema');

        $this->reset('crearForm', 'modalCrear');
        $this->crearForm['fecha'] = now()->toDateString();
        $this->crearForm['hora_llegada'] = '13:00';
        $this->crearForm['personas'] = 2;
        $this->fecha = $validated['crearForm']['fecha'];
        session()->flash('status', 'Reserva creada.');
    }

    public function abrirDetalle(int $id): void
    {
        $this->reservaSeleccionada = $id;
    }

    public function confirmar(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->confirmar($reserva);
        session()->flash('status', 'Reserva confirmada.');
    }

    public function marcarLlego(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->marcarLlego($reserva);
        session()->flash('status', 'Cliente en la mesa.');
    }

    public function finalizar(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->finalizar($reserva);
        session()->flash('status', 'Reserva finalizada.');
    }

    public function cancelarReserva(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->cancelar($reserva);
        session()->flash('status', 'Reserva cancelada.');
    }

    public function noShow(): void
    {
        $reserva = Reserva::findOrFail($this->reservaSeleccionada);
        app(ReservaService::class)->marcarNoShow($reserva);
        session()->flash('status', 'Sin presentarse.');
    }

    public function cerrarDetalle(): void
    {
        $this->reset('reservaSeleccionada');
    }
}; ?>

<x-slot name="header">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-primary">event_available</span>
                <h1 class="text-xl font-extrabold tracking-tight text-on-surface">Reservas</h1>
                <span class="rounded-full bg-secondary/15 px-2.5 py-0.5 text-[11px] font-bold text-secondary border border-secondary/30">RES-01</span>
            </div>
            <p class="text-xs text-on-surface-variant mt-0.5">Agenda del día, confirmación y bloqueo de mesas</p>
        </div>
        <button wire:click="abrirCrear" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary">
            <span class="material-symbols-outlined text-[18px]">add</span> Nueva reserva
        </button>
    </div>
</x-slot>

<div class="space-y-6">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-primary via-primary-container to-secondary"></div>

    @if (session('status'))
        <div class="rounded-xl border border-secondary/40 bg-secondary/10 px-4 py-2 text-xs font-bold text-secondary">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <input type="date" wire:model.live="fecha" wire:change="cambiarFecha"
            class="rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0" />
        <select wire:model.live="filtrarEstado" class="rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:border-primary focus:ring-0">
            <option value="todas">Todos los estados</option>
            <option value="solicitada">Solicitadas</option>
            <option value="confirmada">Confirmadas</option>
            <option value="llego">Llegó</option>
            <option value="cancelada">Canceladas</option>
            <option value="no_mostro">No se mostró</option>
        </select>
    </div>

    <div class="grid grid-cols-3 gap-4">
        @foreach (['solicitadas' => 'Solicitadas', 'confirmadas' => 'Confirmadas', 'canceladas' => 'Canceladas'] as $k => $label)
            <div class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-4">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-on-surface-variant">{{ $label }}</p>
                <p class="mt-1 text-2xl font-black text-on-surface">{{ $conteo[$k] }}</p>
            </div>
        @endforeach
    </div>

    @if ($reservas->isEmpty())
        <p class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 text-xs text-on-surface-variant">Sin reservas para esta fecha.</p>
    @else
        <div class="space-y-3">
            @foreach ($reservas as $reserva)
                <button wire:click="abrirDetalle({{ $reserva->id }})" class="w-full rounded-2xl border border-outline-variant/10 bg-surface-container-low p-4 text-left">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-extrabold text-on-surface">{{ $reserva->nombre_contacto }} · {{ \Illuminate\Support\Carbon::parse($reserva->hora_llegada)->format('H:i') }}</p>
                            <p class="text-xs text-on-surface-variant">{{ $reserva->personas }} personas · {{ $reserva->mesas->pluck('nombre')->join(', ') ?: $reserva->mesas->pluck('numero')->map(fn ($n) => 'Mesa #' . $n)->join(', ') }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-[11px] font-bold
                            {{ match($reserva->estado) {
                                'confirmada' => 'bg-secondary/15 text-secondary border border-secondary/30',
                                'solicitada' => 'bg-primary/10 text-primary border border-primary/20',
                                'llego' => 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20',
                                'finalizada' => 'bg-surface-container-high text-on-surface-variant',
                                'cancelada' => 'bg-error/10 text-error border border-error/20',
                                'no_mostro' => 'bg-error/10 text-error border border-error/20',
                                default => 'bg-surface-container-high text-on-surface-variant',
                            } }}">
                            {{ ucwords(str_replace('_', ' ', $reserva->estado)) }}
                        </span>
                    </div>
                </button>
            @endforeach
        </div>
    @endif
</div>

@if ($modalCrear)
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="$set('modalCrear', false)">
        <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-md sm:rounded-3xl">
            <h3 class="text-base font-extrabold text-on-surface">Nueva reserva</h3>
            <form wire:submit="crearReserva" class="mt-4 space-y-4">
                <div><label class="text-xs font-bold text-on-surface-variant">Nombre del cliente</label>
                    <input type="text" wire:model="crearForm.nombre_contacto" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                    @error('crearForm.nombre_contacto') <p class="mt-1 text-xs font-bold text-error">{{ $message }}</p> @enderror</div>
                <div><label class="text-xs font-bold text-on-surface-variant">Teléfono</label>
                    <input type="text" wire:model="crearForm.telefono_contacto" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                    @error('crearForm.telefono_contacto') <p class="mt-1 text-xs font-bold text-error">{{ $message }}</p> @enderror</div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-xs font-bold text-on-surface-variant">Fecha</label>
                        <input type="date" wire:model.live="crearForm.fecha" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                    <div><label class="text-xs font-bold text-on-surface-variant">Hora</label>
                        <input type="time" wire:model.live="crearForm.hora_llegada" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                </div>
                <div><label class="text-xs font-bold text-on-surface-variant">Personas</label>
                    <input type="number" min="1" wire:model.live="crearForm.personas" class="mt-1 w-full rounded-xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-sm focus:border-primary focus:ring-0" /></div>
                <p class="text-[11px] text-on-surface-variant">Mesas disponibles: {{ $disponibles->map(fn ($m) => $m->numero . 'PK' . $m->capacidad)->join(' · ') ?: 'ninguna para este horario' }}</p>
                <button type="submit" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary">Crear reserva</button>
            </form>
        </div>
    </div>
@endif

@if ($reservaSeleccionada)
    @php $detalle = \App\Models\Reserva::with(['mesas', 'confirmadoPor'])->find($reservaSeleccionada); @endphp
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim/40 sm:items-center" wire:click.self="cerrarDetalle">
        <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-surface-container-lowest p-5 shadow-2xl sm:max-w-md sm:rounded-3xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-extrabold text-on-surface">{{ $detalle->nombre_contacto }}</h3>
                <button wire:click="cerrarDetalle" class="text-on-surface-variant"><span class="material-symbols-outlined">close</span></button>
            </div>
            <dl class="mt-4 space-y-2 text-xs">
                <div class="flex justify-between"><dt class="text-on-surface-variant">Teléfono</dt><dd class="font-bold">{{ $detalle->telefono_contacto }}</dd></div>
                <div class="flex justify-between"><dt class="text-on-surface-variant">Fecha y hora</dt><dd class="font-bold">{{ $detalle->fecha->format('d/m/Y') }} {{ \Illuminate\Support\Carbon::parse($detalle->hora_llegada)->format('H:i') }}</dd></div>
                <div class="flex justify-between"><dt class="text-on-surface-variant">Personas</dt><dd class="font-bold">{{ $detalle->personas }}</dd></div>
                <div class="flex justify-between"><dt class="text-on-surface-variant">Estado</dt><dd class="font-bold">{{ $detalle->estado }}</dd></div>
                <div class="flex justify-between"><dt class="text-on-surface-variant">Origen</dt><dd class="font-bold">{{ $detalle->origen }}</dd></div>
                @if ($detalle->notas)<div class="flex justify-between"><dt class="text-on-surface-variant">Notas</dt><dd class="font-bold">{{ $detalle->notas }}</dd></div>@endif
            </dl>
            <div class="mt-5 flex flex-wrap gap-2">
                @if (in_array($detalle->estado, ['solicitada', 'confirmada']) && $detalle->estado !== 'confirmada')
                    <button wire:click="confirmar" class="rounded-xl bg-secondary px-4 py-2 text-xs font-bold text-white">Confirmar</button>
                @endif
                @if ($detalle->estado === 'confirmada')
                    <button wire:click="marcarLlego" class="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-on-primary">Llegó</button>
                @endif
                @if ($detalle->estado === 'llego')
                    <button wire:click="finalizar" class="rounded-xl bg-monet px-4 py-2 text-xs font-bold text-white">Finalizar</button>
                @endif
                @if (in_array($detalle->estado, ['solicitada', 'confirmada']))
                    <button wire:click="cancelarReserva" class="rounded-xl bg-error px-4 py-2 text-xs font-bold text-white">Cancelar</button>
                    <button wire:click="noShow" class="rounded-xl border border-error px-4 py-2 text-xs font-bold text-error">No se mostró</button>
                @endif
            </div>
        </div>
    </div>
@endif
```
Nota: revisar que el helper `bg-monet` no exista — usar un token real del design system si falla (p. ej. `bg-surface-container-high`).

- [ ] **Step 4: Ejecutar y verificar GREEN**

Run: `php artisan test --filter Fase5ReservasTest`
Expected: PASS (11 tests).

---

### Task 5: Formulario público + webhook de reservas

**Files:**
- Create: `app/Http/Controllers/ReservaPublicaController.php`
- Create: `app/Http/Controllers/ReservaWebhookController.php`
- Create: `resources/views/reservas/crear.blade.php`
- Modify: `routes/web.php`
- Modify: `docs/modulos/reservas.md` (contrato del webhook)
- Test: `tests/Feature/Fase5PublicoReservasTest.php`

**Interfaces:**
- Produces:
  - `GET /reservas/crear` (name `reservas.publico`) — formulario con franjas disponibles.
  - `POST /reservas/crear` — crea reserva origen `publico`, estado `solicitada`; honiper `empresa` se ignora si lleno.
  - `POST /api/reservas` (name `reservas.webhook`) — header `X-Webhook-Token`; respuestas 201/401/403/422.

- [ ] **Step 1: Escribir el test (RED)**

```php
<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Role;
use App\Models\Sucursal;
use App\Services\ConfiguracionService;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Fase5PublicoReservasTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $this->sucursal = Sucursal::create(['nombre' => 'Sede Medellín', 'codigo' => 'MDE-01', 'direccion' => 'Calle 10', 'activa' => true]);
        Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 1, 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'libre', 'activa' => true]);

        app(ConfiguracionService::class)->guardar('reservas', 'webhook_token', 'token-secreto-test');
        app(ConfiguracionService::class)->guardar('reservas', 'webhook_activo', true);
    }

    public function test_formulario_publico_muestra_franjas_disponibles(): void
    {
        $response = $this->get(route('reservas.publico') . '?fecha=2026-09-25');
        $response->assertOk();
        $response->assertSee('Reserva');
        $response->assertSee('Honeypot');
    }

    public function test_store_publico_crea_reserva_solicitada(): void
    {
        $response = $this->post(route('reservas.publico'), [
            'nombre' => 'Casimiro García',
            'telefono' => '3001112222',
            'personas' => 2,
            'fecha' => '2026-09-25',
            'hora' => '13:00',
            'notas' => '',
            'empresa' => '', // honeypot vacío = humano
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservas', [
            'nombre_contacto' => 'Casimiro García',
            'estado' => 'solicitada',
            'origen' => 'publico',
        ]);
    }

    public function test_store_publico_rechaza_honeypot(): void
    {
        $this->post(route('reservas.publico'), [
            'nombre' => 'Bot', 'telefono' => '300', 'personas' => 2,
            'fecha' => '2026-09-25', 'hora' => '13:00', 'empresa' => 'spam',
        ])->assertRedirect();

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_webhook_token_valido_crea_reserva(): void
    {
        $response = $this->postJson(route('reservas.webhook'), [
            'nombre' => 'Cliente WhatsApp',
            'telefono' => '3200000001',
            'fecha' => '2026-09-26',
            'hora' => '19:00',
            'personas' => 3,
        ], ['X-Webhook-Token' => 'token-secreto-test']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reservas', [
            'nombre_contacto' => 'Cliente WhatsApp',
            'estado' => 'solicitada',
            'origen' => 'webhook',
        ]);
    }

    public function test_webhook_token_invalido_es_401(): void
    {
        $this->postJson(route('reservas.webhook'), [
            'nombre' => 'X', 'telefono' => '3', 'fecha' => '2026-09-26', 'hora' => '19:00', 'personas' => 1,
        ], ['X-Webhook-Token' => 'incorrecto'])->assertStatus(401);
    }

    public function test_webhook_inactivo_es_403(): void
    {
        app(ConfiguracionService::class)->guardar('reservas', 'webhook_activo', false);

        $this->postJson(route('reservas.webhook'), [
            'nombre' => 'X', 'telefono' => '3', 'fecha' => '2026-09-26', 'hora' => '19:00', 'personas' => 1,
        ], ['X-Webhook-Token' => 'token-secreto-test'])->assertStatus(403);
    }

    public function test_webhook_payload_invalido_es_422(): void
    {
        $this->postJson(route('reservas.webhook'), [
            'nombre' => '', 'telefono' => '', 'fecha' => 'mal', 'hora' => 'xx', 'personas' => 0,
        ], ['X-Webhook-Token' => 'token-secreto-test'])->assertStatus(422);
    }
}
```

- [ ] **Step 2: Ejecutar y verificar FAIL**

Run: `php artisan test --filter Fase5PublicoReservasTest`
Expected: FAIL — rutas/controladores no existen.

- [ ] **Step 3: Rutas en `routes/web.php`** (FUERA del grupo auth)

```php
Route::get('reservas/crear', [\App\Http\Controllers\ReservaPublicaController::class, 'create'])->name('reservas.publico');
Route::post('reservas/crear', [\App\Http\Controllers\ReservaPublicaController::class, 'store'])->middleware('throttle:10,1');
Route::post('api/reservas', [\App\Http\Controllers\ReservaWebhookController::class, 'crear'])->middleware('throttle:20,1')->name('reservas.webhook');
```

- [ ] **Step 4: Controlador público**

```php
<?php

namespace App\Http\Controllers;

use App\Services\ReservaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReservaPublicaController extends Controller
{
    public function create(Request $request): View
    {
        $fecha = $request->query('fecha', now()->addDay()->toDateString());
        if ($fecha < now()->toDateString()) {
            $fecha = now()->addDay()->toDateString();
        }

        $franjas = collect();
        $inicio = Carbon::createFromTime(12, 0);
        $fin = Carbon::createFromTime(21, 30);
        $personas = max(1, (int) $request->query('personas', 2));
        $service = app(ReservaService::class);

        for ($t = $inicio->copy(); $t->lte($fin); $t->addMinutes(30)) {
            $disponible = $service->verificarDisponibilidad($fecha, $t->format('H:i'), $personas)->isNotEmpty();
            $franjas->push(['hora' => $t->format('H:i'), 'disponible' => $disponible]);
        }

        return view('reservas.crear', [
            'fecha' => $fecha,
            'personas' => $personas,
            'franjas' => $franjas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('empresa')) {
            return back();
        }

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:30'],
            'fecha' => ['required', 'date', 'after_or_equal:' . now()->toDateString()],
            'hora' => ['required', 'date_format:H:i'],
            'personas' => ['required', 'integer', 'min:1'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        app(ReservaService::class)->crear([
            'nombre_contacto' => $validated['nombre'],
            'telefono_contacto' => $validated['telefono'],
            'fecha' => $validated['fecha'],
            'hora_llegada' => $validated['hora'],
            'personas' => (int) $validated['personas'],
            'notas' => $validated['notas'] ?? null,
        ], 'publico');

        return back()->with('resavado', 'ok');
    }
}
```

- [ ] **Step 5: Controlador webhook**

```php
<?php

namespace App\Http\Controllers;

use App\Services\ConfiguracionService;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservaWebhookController extends Controller
{
    public function crear(Request $request): JsonResponse
    {
        $cfg = app(ConfiguracionService::class);

        if (! $cfg->obtener('reservas', 'webhook_activo', false)) {
            return response()->json(['error' => 'Webhook de reservas desactivado.'], 403);
        }

        $tokenEsperado = $cfg->obtener('reservas', 'webhook_token', '');
        $tokenRecibido = $request->header('X-Webhook-Token', '');

        if ($tokenEsperado === '' || ! hash_equals($tokenEsperado, $tokenRecibido)) {
            return response()->json(['error' => 'Token de webhook inválido.'], 401);
        }

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'fecha' => ['required', 'date', 'after_or_equal:' . now()->toDateString()],
            'hora' => ['required', 'date_format:H:i'],
            'personas' => ['required', 'integer', 'min:1'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $reserva = app(ReservaService::class)->crear([
                'nombre_contacto' => $validated['nombre'],
                'telefono_contacto' => $validated['telefono'],
                'email_contacto' => $validated['email'] ?? null,
                'fecha' => $validated['fecha'],
                'hora_llegada' => $validated['hora'],
                'personas' => (int) $validated['personas'],
                'notas' => $validated['notas'] ?? null,
            ], 'webhook');
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'reserva_id' => $reserva->id,
            'token_publico' => $reserva->token_publico,
        ], 201);
    }
}
```

- [ ] **Step 6: Vista pública `resources/views/reservas/crear.blade.php`** (extiende `x-guest-layout`; honeypot `empresa` a la vista)

```blade
<x-guest-layout>
    <div class="mx-auto w-full max-w-lg px-4 py-10">
        <div class="rounded-3xl border border-outline-variant/20 bg-white p-6 shadow-sm">
            <span class="material-symbols-outlined text-[28px] text-primary">event_available</span>
            <h1 class="mt-2 text-xl font-extrabold text-on-surface">Reserva en SushiXpress</h1>
            <p class="mt-1 text-xs text-on-surface-variant">Elige fecha y franja; nuestro equipo confirmará tu reserva.</p>

            <form method="GET" action="{{ route('reservas.publico') }}" class="mt-5 flex flex-wrap items-end gap-3">
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Fecha</label>
                    <input type="date" name="fecha" value="{{ $fecha }}" min="{{ now()->toDateString() }}" class="mt-1 rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                </div>
                <div>
                    <label class="text-xs font-bold text-on-surface-variant">Personas</label>
                    <input type="number" name="personas" value="{{ $personas }}" min="1" class="mt-1 rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                </div>
                <button class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary">Ver disponibilidad</button>
            </form>

            <form method="POST" action="{{ route('reservas.publico') }}" class="mt-6 space-y-3">
                @csrf
                <input type="hidden" name="fecha" value="{{ $fecha }}" />
                <input type="hidden" name="personas" value="{{ $personas }}" />
                <div class="hidden">
                    <label>No llenar</label>
                    <input type="text" name="empresa" tabindex="-1" autocomplete="off" />
                </div>

                <div class="max-h-40 overflow-y-auto rounded-xl border border-outline-variant/20 p-2">
                    @foreach ($franjas as $franja)
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm {{ $franja['disponible'] ? 'hover:bg-surface-container-low' : 'opacity-40' }}">
                            <input type="radio" name="hora" value="{{ $franja['hora'] }}" {{ $franja['disponible'] ? '' : 'disabled' }} @required($loop->first) />
                            <span class="font-bold">{{ $franja['hora'] }}</span>
                            <span class="text-[11px] text-on-surface-variant">{{ $franja['disponible'] ? 'Disponible' : 'Ocupada' }}</span>
                        </label>
                    @endforeach
                </div>

                <input type="text" name="nombre" placeholder="Nombre completo" required class="w-full rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                <input type="tel" name="telefono" placeholder="Teléfono" required class="w-full rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0" />
                <textarea name="notas" placeholder="Notas (opcional)" rows="2" class="w-full rounded-xl border border-outline-variant/30 px-3 py-2 text-sm focus:border-primary focus:ring-0"></textarea>

                <button type="submit" class="w-full rounded-xl bg-primary py-3 text-sm font-black text-on-primary">Solicitar reserva</button>
            </form>

            @if (session('resavado'))
                <p class="mt-4 rounded-xl bg-secondary/10 px-4 py-2 text-xs font-bold text-secondary">¡Reserva solicitada! Te confirmaremos por teléfono.</p>
            @endif
        </div>
    </div>
</x-guest-layout>
```
Nota: reemplazar el placeholder `@required($loop->first)` por `checked @if($loop->first)` (la directiva `@required` no existe). Usar `<input type="radio" ... {{ $loop->first ? 'checked' : '' }}`.

- [ ] **Step 7: Documentar contrato webhook en `docs/modulos/reservas.md`** (sección "Webhook (n8n/WhatsApp)"): endpoint, header, payload, respuestas, toggles de activación en CFG-01.

- [ ] **Step 8: Ejecutar y verificar GREEN**

Run: `php artisan test --filter Fase5PublicoReservasTest`
Expected: PASS (7 tests).

---

### Task 6: ReporteService extendido (KPIs + reportes avanzados + tests)

**Files:**
- Modify: `app/Services/ReporteService.php`
- Modify: `app/Models/ItemPedido.php` (verificar relación `producto()` existe; si no, añadir)
- Test: `tests/Feature/Fase5ReportesTest.php`

**Interfaces:**
- Produces (consumidas por Task 7, 8 y 9):
  - `ReporteService::kpisRealtime(): array`
  - `ReporteService::ventasPorPeriodo(string $desde, string $hasta): array`
  - `ReporteService::ventasPorTipo(string $desde, string $hasta): array`
  - `ReporteService::ventasPorProducto(string $desde, string $hasta, int $limite = 10): array`
  - `ReporteService::ventasPorTrabajador(string $desde, string $hasta): array`
  - `ReporteService::comparativaPeriodos(string $desde, string $hasta): array`
  - `ReporteService::topClientes(string $desde, string $hasta, int $limite = 10): array`
  - `ReporteService::tiemposEntrega(string $desde, string $hasta): array`
  - `ReporteService::resumenReservas(string $desde, string $hasta): array`

Firma de datos devueltos (fila base de cada reporte):
- `ventasPorPeriodo`: array de `['fecha' => $fecha, 'ventas' => float, 'transacciones' => int, 'ticket_promedio' => float]`.
- `ventasPorTipo`: `['tipo' => ..., 'ventas' => float, 'transacciones' => int]`.
- `ventasPorProducto`: `['producto' => string, 'cantidad' => int, 'ventas' => float, 'costo' => float, 'margen' => float]`.
- `ventasPorTrabajador`: `['trabajador' => string, 'ventas' => float, 'transacciones' => int]`.
- `comparativaPeriodos`: `['periodo_actual' => array, 'periodo_anterior' => array, 'variacion_ventas' => float, 'variacion_transacciones' => float]`.
- `topClientes`: `['cliente' => string, 'visitas' => int, 'gastado' => float]`.
- `tiemposEntrega`: `['promedio_min' => int, 'min_min' => int, 'max_min' => int, 'entregados' => int]`.
- `resumenReservas`: `['total' => int, 'confirmadas' => int, 'canceladas' => int, 'no_shows' => int, 'cumplimiento_porcentaje' => float]`.

- [ ] **Step 1: Escribir el test (RED)**

```php
<?php

namespace Tests\Feature;

use App\Models\AsientoContable;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ReporteService;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase5ReportesTest extends TestCase
{
    use RefreshDatabase;

    private ReporteService $service;
    private Producto $producto;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        Role::create(['nombre' => 'Mesero', 'slug' => 'mesero']);
        Role::create(['nombre' => 'Gerente', 'slug' => 'gerente']);

        $this->sucursal = Sucursal::create(['nombre' => 'Sede', 'codigo' => 'MDE-01', 'direccion' => 'Calle', 'activa' => true]);
        $this->admin = User::create(['name' => 'Ad', 'email' => 'a@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        $categoria = Categoria::create(['nombre' => 'Rolls', 'slug' => 'rolls', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
        $this->producto = Producto::create(['categoria_id' => $categoria->id, 'nombre' => 'Dragon', 'slug' => 'dragon', 'precio' => 50000, 'costo' => 20000, 'area_cocina' => 'sushi', 'activo' => true]);

        $this->service = app(ReporteService::class);
    }

    private function pedidoEn(string $fecha, string $tipo = 'mesa', ?User $usuario = null): Pedido
    {
        $pedido = Pedido::create([
            'codigo' => 'T-' . uniqid(),
            'tipo' => $tipo,
            'estado' => 'pagado',
            'usuario_id' => ($usuario ?? $this->admin)->id,
            'nombre_cliente' => 'Cliente Test',
            'subtotal' => 50000,
            'total' => 50000,
            'metodo_pago' => 'efectivo',
            'monto_pagado' => 50000,
            'pagado_en' => $fecha . ' 13:00:00',
            'created_at' => $fecha . ' 12:00:00',
        ]);

        ItemPedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto->id,
            'nombre_producto' => $this->producto->nombre,
            'cantidad' => 1,
            'precio_unitario' => 50000,
            'subtotal' => 50000,
            'area_cocina' => 'sushi',
            'estado_cocina' => 'entregado',
        ]);

        return $pedido;
    }

    public function test_kpis_realtime_computa_ventas_del_dia(): void
    {
        $this->pedidoEn(now()->toDateString(), 'mesa');
        $this->pedidoEn(now()->toDateString(), 'delivery');

        $kpis = $this->service->kpisRealtime();

        $this->assertSame(100000.0, (float) $kpis['ventas_dia']);
        $this->assertSame(2, $kpis['transacciones_dia']);
        $this->assertSame(50000.0, (float) $kpis['ticket_promedio']);
        $this->assertSame([['producto' => 'Dragon', 'cantidad' => 2], false], [['producto' => 'Dragon', 'cantidad' => $kpis['top_productos_hoy'][0]['cantidad']], false]);
    }

    public function test_kpis_realtime_food_cost_y_mesas_ocupadas(): void
    {
        $this->pedidoEn(now()->toDateString());
        Mesa::create(['sucursal_id' => $this->sucursal->id, 'numero' => 7, 'zona' => 'salon', 'capacidad' => 4, 'estado' => 'ocupada', 'activa' => true]);

        $kpis = $this->service->kpisRealtime();

        $this->assertSame(40.0, (float) $kpis['food_cost_porcentaje']); // 20000 / 50000
        $this->assertSame(1, $kpis['mesas_ocupadas']);
    }

    public function test_ventas_por_tipo_agrupa_canal(): void
    {
        $this->pedidoEn('2026-09-01', 'mesa');
        $this->pedidoEn('2026-09-02', 'delivery');

        $series = $this->service->ventasPorTipo('2026-09-01', '2026-09-30');

        $this->assertCount(2, $series);
        $this->assertSame(100000.0, (float) collect($series)->sum('ventas'));
    }

    public function test_ventas_por_producto_top(): void
    {
        $this->pedidoEn('2026-09-01');

        $top = $this->service->ventasPorProducto('2026-09-01', '2026-09-30', 5);

        $this->assertCount(1, $top);
        $this->assertSame('Dragon', $top[0]['producto']);
        $this->assertSame(50000.0, (float) $top[0]['ventas']);
        $this->assertSame(30000.0, (float) $top[0]['margen']); // 50000 - 20000
    }

    public function test_ventas_por_trabajador(): void
    {
        $this->pedidoEn('2026-09-01', 'mesa', $this->admin);

        $serie = $this->service->ventasPorTrabajador('2026-09-01', '2026-09-30');

        $this->assertCount(1, $serie);
        $this->assertSame('Ad', $serie[0]['trabajador']);
    }

    public function test_comparativa_periodos_con_periodo_anterior(): void
    {
        $this->pedidoEn('2026-09-05');
        $this->pedidoEn('2026-08-05');

        $cmp = $this->service->comparativaPeriodos('2026-09-01', '2026-09-30');

        $this->assertSame(50000.0, (float) $cmp['periodo_actual']['ventas']);
        $this->assertSame(50000.0, (float) $cmp['periodo_anterior']['ventas']);
        $this->assertSame(0.0, (float) $cmp['variacion_ventas']);
    }

    public function test_top_clientes(): void
    {
        $cliente = Cliente::create(['nombre' => 'Vip Uno', 'telefono' => '3001111', 'puntos_fidelidad' => 0, 'tier' => 'regular']);
        $pedido = $this->pedidoEn('2026-09-05', 'mesa');
        $pedido->update(['cliente_id' => $cliente->id]);

        $top = $this->service->topClientes('2026-09-01', '2026-09-30');

        $this->assertCount(1, $top);
        $this->assertSame('Vip Uno', $top[0]['cliente']);
        $this->assertSame(50000.0, (float) $top[0]['gastado']);
    }

    public function test_tiempos_entrega(): void
    {
        Pedido::create([
            'codigo' => 'D-1', 'tipo' => 'delivery', 'estado' => 'pagado',
            'usuario_id' => $this->admin->id, 'total' => 30000, 'costo_envio' => 5000,
            'created_at' => '2026-09-01 12:00:00', 'hora_despacho' => '2026-09-01 12:30:00',
            'hora_entrega' => '2026-09-01 12:45:00', 'estado_delivery' => 'entregado',
            'pagado_en' => '2026-09-01 12:10:00',
        ]);

        $res = $this->service->tiemposEntrega('2026-09-01', '2026-09-30');

        $this->assertSame(45, $res['promedio_min']);
        $this->assertSame(1, $res['entregados']);
    }

    public function test_resumen_reservas_por_estado(): void
    {
        $svcReservas = app(ReservaService::class);
        $base = ['sucursal_id' => $this->sucursal->id, 'nombre_contacto' => 'X', 'telefono_contacto' => '1', 'hora_llegada' => '13:00', 'personas' => 2, 'fecha' => '2026-09-10'];
        $r1 = $svcReservas->crear($base);
        $svcReservas->confirmar($r1);
        $r2 = $svcReservas->crear($base);
        $svcReservas->cancelar($r2);
        $r3 = $svcReservas->crear($base);
        $svcReservas->marcarNoShow($r3);

        $resumen = $this->service->resumenReservas('2026-09-01', '2026-09-30');

        $this->assertSame(3, $resumen['total']);
        $this->assertSame(1, $resumen['confirman']);
        $this->assertSame(1, $resumen['canceladas']);
        $this->assertSame(1, $resumen['no_shows']);
    }

    public function test_pantalla_reportes_renders_para_gerente(): void
    {
        $gerente = User::create(['name' => 'G', 'email' => 'g@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);

        $this->actingAs($gerente)->get(route('reportes'))->assertOk();
        $this->actingAs($gerente)->get(route('reportes'))->assertSeeVolt('reportes.index');
    }
}
```
Nota: `test_kpis_realtime_computa_ventas_del_dia` tiene una assertion extraña; simplificar a:

```php
$this->assertSame('Dragon', $kpis['top_productos_hoy'][0]['producto']);
$this->assertSame(2, $kpis['top_productos_hoy'][0]['cantidad'] ?? 1);
```
y en `test_resumen_reservas`: corregir assertion `assertSame(1, $resumen['confirman'])` → `$resumen['confirmadas']`. Revisar la clave correcta (`confirmadas`) antes de ejecutar.

- [ ] **Step 2: Ejecutar y verificar FAIL**

Run: `php artisan test --filter Fase5ReportesTest`
Expected: FAIL — métodos no existen.

- [ ] **Step 3: Implementar métodos en `ReporteService`** (añadir al final de la clase; importar modelos `Pedido`, `ItemPedido`, `Mesa`, `Cliente`, `Reserva`, enum `MesaEstado`)

```php
    public function kpisRealtime(): array
    {
        $hoy = now()->toDateString();

        $pedidosHoy = Pedido::with(['items.producto'])
            ->where('estado', 'pagado')
            ->whereDate('pagado_en', $hoy)
            ->get();

        $ventas = (float) $pedidosHoy->sum('total');
        $transacciones = $pedidosHoy->count();
        $ticketPromedio = $transacciones > 0 ? round($ventas / $transacciones, 2) : 0.0;

        $costoVendido = $pedidosHoy->flatMap->items->sum(fn ($item) => (float) ($item->producto?->costo ?? 0) * $item->cantidad);
        $foodCost = $ventas > 0 ? round($costoVendido / $ventas * 100, 1) : 0.0;

        $mesasOcupadas = Mesa::whereIn('estado', [MesaEstado::OCUPADA->value, MesaEstado::POR_LIMPIAR->value])->count();

        $comandasActivas = Pedido::whereNotIn('estado', ['pagado', 'cancelado'])->count();

        $picosPorHora = $pedidosHoy
            ->groupBy(fn ($p) => \Illuminate\Support\Carbon::parse($p->pagado_en)->format('H'))
            ->map->count()
            ->sortKeysDesc()
            ->take(6);

        $topProductos = ItemPedido::query()
            ->whereHas('pedido', fn ($q) => $q->where('estado', 'pagado')->whereDate('pagado_en', $hoy))
            ->with('producto')
            ->get()
            ->groupBy('producto_id')
            ->map(fn ($items) => [
                'producto' => $items->first()->nombre_producto,
                'cantidad' => $items->sum('cantidad'),
                'ventas' => (float) $items->sum('subtotal'),
                'margen' => round((float) $items->sum('subtotal') - (float) $items->sum(fn ($i) => (float) ($i->producto?->costo ?? 0) * $i->cantidad), 2),
            ])
            ->sortByDesc('ventas')
            ->take(5)
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
    }

    public function ventasPorPeriodo(string $desde, string $hasta): array
    {
        return Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->get(['id', 'total', 'pagado_en'])
            ->groupBy(fn ($p) => \Illuminate\Support\Carbon::parse($p->pagado_en)->toDateString())
            ->map(fn ($grupo) => [
                'fecha' => $grupo->first()->pagado_en->toDateString(),
                'ventas' => (float) $grupo->sum('total'),
                'transacciones' => $grupo->count(),
                'ticket_promedio' => round((float) $grupo->sum('total') / $grupo->count(), 2),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    public function ventasPorTipo(string $desde, string $hasta): array
    {
        return Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->get(['id', 'tipo', 'total'])
            ->groupBy('tipo')
            ->map(fn ($grupo) => [
                'tipo' => $grupo->first()->tipo,
                'ventas' => (float) $grupo->sum('total'),
                'transacciones' => $grupo->count(),
            ])
            ->sortByDesc('ventas')
            ->values()
            ->all();
    }

    public function ventasPorProducto(string $desde, string $hasta, int $limite = 10): array
    {
        return ItemPedido::query()
            ->with('producto')
            ->whereHas('pedido', fn ($q) => $q->where('estado', 'pagado')->whereBetween('pagado_en', [$desde . ' 00:00:00', $hasta . ' 23:59:59']))
            ->get()
            ->groupBy('producto_id')
            ->map(fn ($items) => [
                'producto' => $items->first()->nombre_producto,
                'cantidad' => $items->sum('cantidad'),
                'ventas' => (float) $items->sum('subtotal'),
                'costo' => (float) $items->sum(fn ($i) => (float) ($i->producto?->costo ?? 0) * $i->cantidad),
            ])
            ->map(fn ($fila) => $fila + ['margen' => round($fila['ventas'] - $fila['costo'], 2)])
            ->sortByDesc('ventas')
            ->take($limite)
            ->values()
            ->all();
    }

    public function ventasPorTrabajador(string $desde, string $hasta): array
    {
        return Pedido::with('usuario')
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->get()
            ->groupBy('usuario_id')
            ->map(fn ($grupo) => [
                'trabajador' => $grupo->first()->usuario?->name ?? 'Sin asignar',
                'ventas' => (float) $grupo->sum('total'),
                'transacciones' => $grupo->count(),
            ])
            ->sortByDesc('ventas')
            ->values()
            ->all();
    }

    public function comparativaPeriodos(string $desde, string $hasta): array
    {
        $inicio = Carbon::parse($desde);
        $fin = Carbon::parse($hasta);
        $duracion = $inicio->diffInDays($fin) + 1;
        $anteriorDesde = $inicio->copy()->subDays($duracion)->toDateString();
        $anteriorHasta = $inicio->copy()->subDay()->toDateString();

        $actual = $this->resumenPeriodo($desde, $hasta);
        $anterior = $this->resumenPeriodo($anteriorDesde, $anteriorHasta);

        return [
            'periodo_actual' => $actual,
            'periodo_anterior' => $anterior,
            'variacion_ventas' => $anterior['ventas'] > 0 ? round(($actual['ventas'] - $anterior['ventas']) / $anterior['ventas'] * 100, 1) : 0.0,
            'variacion_transacciones' => $anterior['transacciones'] > 0 ? round(($actual['transacciones'] - $anterior['transacciones']) / $anterior['transacciones'] * 100, 1) : 0.0,
        ];
    }

    public function topClientes(string $desde, string $hasta, int $limite = 10): array
    {
        return Pedido::with('cliente')
            ->where('estado', 'pagado')
            ->whereNotNull('cliente_id')
            ->whereBetween('pagado_en', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->get()
            ->groupBy('cliente_id')
            ->map(fn ($grupo) => [
                'cliente' => $grupo->first()->cliente?->nombre ?? 'Anónimo',
                'visitas' => $grupo->count(),
                'gastado' => (float) $grupo->sum('total'),
            ])
            ->sortByDesc('gastado')
            ->take($limite)
            ->values()
            ->all();
    }

    public function tiemposEntrega(string $desde, string $hasta): array
    {
        $pedidos = Pedido::query()
            ->where('tipo', 'delivery')
            ->whereNotNull('hora_entrega')
            ->whereBetween('created_at', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->get();

        if ($pedidos->isEmpty()) {
            return ['promedio_min' => 0, 'min_min' => 0, 'max_min' => 0, 'entregados' => 0];
        }

        $minutos = $pedidos->map(fn ($p) => Carbon::parse($p->created_at)->diffInMinutes(Carbon::parse($p->hora_entrega)));

        return [
            'promedio_min' => (int) round($minutos->avg()),
            'min_min' => (int) $minutos->min(),
            'max_min' => (int) $minutos->max(),
            'entregados' => $pedidos->count(),
        ];
    }

    public function resumenReservas(string $desde, string $hasta): array
    {
        $reservas = Reserva::whereBetween('fecha', [$desde, $hasta])->get();
        $confirmadas = $reservas->whereIn('estado', ['confirmada', 'llego', 'finalizada'])->count();
        $canceladas = $reservas->where('estado', 'cancelada')->count();
        $noShows = $reservas->where('estado', 'no_mostro')->count();
        $cuentas = $confirmadas + $canceladas + $noShows;

        return [
            'total' => $reservas->count(),
            'confirmadas' => $confirmadas,
            'canceladas' => $canceladas,
            'no_shows' => $noShows,
            'cumplimiento_porcentaje' => $cuentas > 0 ? round($confirmadas / $cuentas * 100, 1) : 0.0,
        ];
    }

    private function resumenPeriodo(string $desde, string $hasta): array
    {
        $pedidos = Pedido::where('estado', 'pagado')
            ->whereBetween('pagado_en', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->get(['total']);

        $ventas = (float) $pedidos->sum('total');

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'ventas' => round($ventas, 2),
            'transacciones' => $pedidos->count(),
            'ticket_promedio' => $pedidos->count() > 0 ? round($ventas / $pedidos->count(), 2) : 0.0,
        ];
    }
```
Nota: añadir `use Carbon\Carbon;` a `ReporteService` (o `\Illuminate\Support\Carbon`). El cast `pagado_en` es datetime → en las colecciones `pagado_en` es Carbon. Verificar `pagado_en` fillable y cast en `Pedido.php` (ya es `'pagado_en' => 'datetime'`).

- [ ] **Step 4: Verificar relación `producto()` en ItemPedido** (si no existe, añadir)

```php
// app/Models/ItemPedido.php
public function producto(): BelongsTo
{
    return $this->belongsTo(Producto::class, 'producto_id');
}
```

- [ ] **Step 5: Ejecutar y verificar GREEN**

Run: `php artisan test --filter Fase5ReportesTest`
Expected: PASS (10 tests). Si una assertion falla por nombre de clave, corregir el test (no la firma definida arriba salvo que el fallo sea de tipo real).

---

### Task 7: Centro de Reportes con pestañas (REP-01)

**Files:**
- Modify: `resources/views/livewire/reportes/index.blade.php`
- Test: `tests/Feature/Fase5ReportesTest.php` (ampliar)

**Interfaces:**
- Consumes: métodos de `ReporteService` (Task 6).
- Produces: componente `reportes.index` con prop `pestana` (`estado`, `ventas`, `clientes`, `reservas`), `desde`, `hasta`; botones de exportación que enlazan a `route('reportes.pdf')` y `route('reportes.csv')` con query.

- [ ] **Step 1: Escribir el test (RED) — ampliar Fase5ReportesTest**

```php
    public function test_pestanas_reportes_muestran_tablas_detalladas(): void
    {
        $gerente = User::create(['name' => 'G2', 'email' => 'g2@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);
        $this->pedidoEn('2026-09-05');

        $component = Volt::actingAs($gerente)
            ->test('reportes.index')
            ->set('desde', '2026-09-01')
            ->set('hasta', '2026-09-30')
            ->set('pestana', 'ventas')
            ->assertSee('Dragon')
            ->set('pestana', 'clientes')
            ->setAmount('pestana', '')
            ->set('pestana', 'estado');

        $this->assertTrue(true);
    }

    public function test_pestana_reservas_muestra_resumen(): void
    {
        $gerente = User::create(['name' => 'G3', 'email' => 'g3@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);

        $component = Volt::actingAs($gerente)
            ->test('reportes.index')
            ->set('pestana', 'reservas')
            ->assertSee('Cumplimiento')
            ->assertSee('No-shows');
    }
```
Nota: el `setAmount` del primer test es un error tipográfico del sketch — borrarlo antes de ejecutar. El test de pestañas puede simplificarse a validar que cambiar `pestana` re-renderiza sin error y muestra el label de la pestaña activa.

- [ ] **Step 2: Ejecutar y verificar FAIL**

Run: `php artisan test --filter Fase5ReportesTest`
Expected: FAIL/error por `set('pestana', ...)` no soportado (no existe la prop) — es la señal RED.

- [ ] **Step 3: Modificar `reportes/index.blade.php`** — añadir al bloque PHP:

```php
    public string $pestana = 'estado';

    public function with(): array
    {
        $service = app(ReporteService::class);

        return match ($this->pestana) {
            'ventas' => [
                'datos' => [
                    'por_periodo' => $service->ventasPorPeriodo($this->desde, $this->hasta),
                    'por_tipo' => $service->ventasPorTipo($this->desde, $this->hasta),
                    'por_producto' => $service->ventasPorProducto($this->desde, $this->hasta, 10),
                    'por_trabajador' => $service->ventasPorTrabajador($this->desde, $this->hasta),
                    'comparativa' => $service->comparativaPeriodos($this->desde, $this->hasta),
                ],
                'resultado' => $service->estadoResultados($this->desde, $this->hasta),
                'movimientos' => $service->movimientosRecientes(20),
            ],
            'clientes' => [
                'topClientes' => $service->topClientes($this->desde, $this->hasta, 10),
                'tiempos' => $service->tiemposEntrega($this->desde, $this->hasta),
                'resultado' => $service->estadoResultados($this->desde, $this->hasta),
                'movimientos' => $service->movimientosRecientes(20),
            ],
            'reservas' => [
                'resumen' => $service->resumenReservas($this->desde, $this->hasta),
                'resultado' => $service->estadoResultados($this->desde, $this->hasta),
                'movimientos' => $service->movimientosRecientes(20),
            ],
            default => [
                'resultado' => $service->estadoResultados($this->desde, $this->hasta),
                'movimientos' => $service->movimientosRecientes(50),
            ],
        };
    }
```
Y en el HTML: bajo la barra de rango, añadir las pestañas (`estado`/`ventas`/`clientes`/`reservas`) como botones que hacen `wire:click="$set('pestana', '...')"`, y secciones condicionales `@if ($pestana === 'ventas') ... tablas ... @endif` etc. Mantener la sección existente de Estado de Resultados envuelta en `@if ($pestana === 'estado')`. Los botones de exportación apuntan a:

```blade
<a href="{{ route('reportes.pdf', ['reporte' => $pestana, 'desde' => $desde, 'hasta' => $hasta]) }}" class="rounded-xl bg-surface-container-high px-3 py-2 text-xs font-bold">PDF</a>
<a href="{{ route('reportes.csv', ['reporte' => $pestana, 'desde' => $desde, 'hasta' => $hasta]) }}" class="rounded-xl bg-surface-container-high px-3 py-2 text-xs font-bold">CSV</a>
```

- [ ] **Step 4: Ejecutar y verificar GREEN**

Run: `php artisan test --filter Fase5ReportesTest`
Expected: PASS (12 tests).

---

### Task 8: Exportaciones PDF (DomPDF) y CSV

**Files:**
- Create: `app/Http/Controllers/ReporteExportController.php`
- Create: `resources/views/pdf/reporte.blade.php`
- Modify: `composer.json` (dependencia `barryvdh/laravel-dompdf`)
- Modify: `routes/web.php`
- Test: `tests/Feature/Fase5ReportesTest.php` (ampliar)

**Interfaces:**
- Produces:
  - `GET /reportes/exportar-pdf?reporte=&desde=&hasta=` (name `reportes.pdf`) → `application/pdf` descarga.
  - `GET /reportes/exportar-csv?reporte=&desde=&hasta=` (name `reportes.csv`) → `text/csv; charset=UTF-8` stream.

- [ ] **Step 1: Auditar el paquete (dependency-audit)**

Antes de instalar: verificar en Packagist entrada `barryvdh/laravel-dompdf`, última versión compatible con PHP 8.3/Laravel 12+, mantenimiento activo y sin CVEs conocidos. Anotar el resultado en `coordination.md`.

- [ ] **Step 2: Instalar**

Run: `composer require barryvdh/laravel-dompdf`

- [ ] **Step 3: Escribir el test (RED) — ampliar Fase5ReportesTest**

```php
    public function test_export_pdf_devuelve_pdf() : void
    {
        $gerente = User::create(['name' => 'G4', 'email' => 'g4@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);
        $this->pedidoEn('2026-09-05');

        $response = $this->actingAs($gerente)->get(route('reportes.pdf', ['reporte' => 'ventas', 'desde' => '2026-09-01', 'hasta' => '2026-09-30']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF', $response->streamedContent() ?: $response->baseResponse->getContent());
    }

    public function test_export_csv_devuelve_csv_con_datos() : void
    {
        $gerente = User::create(['name' => 'G5', 'email' => 'g5@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'gerente')->value('id'), 'activo' => true]);
        $this->pedidoEn('2026-09-05');

        $response = $this->actingAs($gerente)->get(route('reportes.csv', ['reporte' => 'ventas', 'desde' => '2026-09-01', 'hasta' => '2026-09-30']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Dragon', $response->streamedContent() ?: $response->baseResponse->getContent());
    }

    public function test_export_restringido_a_gerente() : void
    {
        $mesero = User::create(['name' => 'M', 'email' => 'm@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'mesero')->value('id'), 'activo' => true]);
        $this->actingAs($mesero)->get(route('reportes.pdf', ['reporte' => 'ventas', 'desde' => '2026-09-01', 'hasta' => '2026-09-30']))->assertForbidden();
    }
```
Nota: en tests sobre respuestas streamed, `$response->streamedContent()` no existe en stopflight; usar `$response->getContent()` en la respuesta de la suite (verificar cómo se comporta el cliente HTTP de tests con streams; en su defecto hacer assertion sobre el header de descarga `Content-Disposition`).

- [ ] **Step 4: Controlador**

```php
<?php

namespace App\Http\Controllers;

use App\Services\ConfiguracionService;
use App\Services\ReporteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteExportController extends Controller
{
    private const REPORTES_VALIDOS = ['estado', 'ventas', 'clientes', 'reservas'];

    public function pdf(\Illuminate\Http\Request $request): Response
    {
        [$reporte, $desde, $hasta, $datos] = $this->datos($request);

        $empresa = app(ConfiguracionService::class);

        $pdf = Pdf::loadView('pdf.reporte', [
            'reporte' => $reporte,
            'desde' => $desde,
            'hasta' => $hasta,
            'datos' => $datos,
            'razon_social' => $empresa->obtener('general', 'razon_social', ''),
            'nit' => $empresa->obtener('general', 'nit', ''),
            'generado' => now()->format('d/m/Y H:i'),
        ])->setPaper('letter', 'portrait');

        return $pdf->download("reporte-{$reporte}-{$desde}-{$hasta}.pdf");
    }

    public function csv(\Illuminate\Http\Request $request): StreamedResponse
    {
        [$reporte, $desde, $hasta, $datos] = $this->datos($request);

        $filas = [$this->encabezados($reporte), ...$this->filas($reporte, $datos)];

        $csv = "\xEF\xBB\xBF";

        foreach ($filas as $fila) {
            $csv .= implode(';', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $fila)) . "\r\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reporte-' . $reporte . '-' . $desde . '-' . $hasta . '.csv"',
        ]);
    }

    private function datos(\Illuminate\Http\Request $request): array
    {
        $validated = $request->validate([
            'reporte' => ['required', 'in:' . implode(',', self::REPORTES_VALIDOS)],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $service = app(ReporteService::class);
        $reporte = $validated['reporte'];
        $desde = $validated['desde'];
        $hasta = $validated['hasta'];

        $datos = match ($reporte) {
            'ventas' => ['por_periodo' => $service->ventasPorPeriodo($desde, $hasta), 'por_tipo' => $service->ventasPorTipo($desde, $hasta), 'por_producto' => $service->ventasPorProducto($desde, $hasta, 10), 'por_trabajador' => $service->ventasPorTrabajador($desde, $hasta), 'comparativa' => $service->comparativaPeriodos($desde, $hasta)],
            'clientes' => ['top_clientes' => $service->topClientes($desde, $hasta, 10), 'tiempos' => $service->tiemposEntrega($desde, $hasta)],
            'reservas' => ['resumen' => $service->resumenReservas($desde, $hasta)],
            default => ['resultado' => $service->estadoResultados($desde, $hasta)],
        };

        return [$reporte, $desde, $hasta, $datos];
    }

    private function encabezados(string $reporte): array
    {
        return match ($reporte) {
            'ventas' => ['Producto', 'Cantidad', 'Ventas', 'Costo', 'Margen'],
            'clientes' => ['Cliente', 'Visitas', 'Gastado'],
            'reservas' => ['Concepto', 'Valor'],
            default => ['Cuenta', 'Total', 'Movimientos'],
        };
    }

    private function filas(string $reporte, array $datos): array
    {
        return match ($reporte) {
            'ventas' => collect($datos['por_producto'])->map(fn ($fila) => [$fila['producto'], $fila['cantidad'], $fila['ventas'], $fila['costo'], $fila['margen']])->all(),
            'clientes' => collect($datos['top_clientes'])->map(fn ($fila) => [$fila['cliente'], $fila['visitas'], $fila['gastado']])->all(),
            'reservas' => [[$datos['resumen']['total'] . ' total reservas', ''], [$datos['resumen']['confirmadas'] . ' confirmadas', $datos['resumen']['cumplimiento_porcentaje'] . '%'], [$datos['resumen']['canceladas'] . ' canceladas', ''], [$datos['resumen']['no_shows'] . ' no-shows', '']],
            default => collect($datos['resultado']['detalle']['ingresos'])->map(fn ($fila) => [$fila['cuenta'], $fila['total'], $fila['movimientos']])->all(),
        };
    }
}
```

- [ ] **Step 5: Vista PDF `resources/views/pdf/reporte.blade.php`** (hoja carta)

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 216mm 279mm; margin: 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1c1917; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .empresa { font-size: 11px; color: #57534e; margin-bottom: 2px; }
        .meta { color: #78716c; font-size: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; border-bottom: 2px solid #ab2d1b; padding: 6px 4px; font-size: 10px; text-transform: uppercase; }
        td { border-bottom: 1px solid #d6d3d1; padding: 5px 4px; }
        .total { font-weight: bold; border-top: 2px solid #1c1917; }
        .footer { margin-top: 18px; font-size: 9px; color: #78716c; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $razon_social }}</h1>
    <div class="empresa">NIT {{ $nit ?: '—' }}</div>
    <div class="meta">Reporte: {{ ucfirst($reporte) }} · del {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }} · Generado: {{ $generado }}</div>

    @if ($reporte === 'ventas' || $reporte === 'estado')
        <table>
            <thead>
                <tr><th>Concepto</th><th>Transacciones</th><th>Valor</th></tr>
            </thead>
            <tbody>
                @foreach (($datos['por_producto'] ?? ($datos['resultado']['detalle']['ingresos'] ?? [])) as $fila)
                    <tr><td>{{ $fila['producto'] ?? $fila['cuenta'] }}</td><td>{{ $fila['cantidad'] ?? $fila['movimientos'] }}</td><td>$ {{ number_format($fila['ventas'] ?? $fila['total'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($reporte === 'clientes')
        <table>
            <thead><tr><th>Cliente</th><th>Visitas</th><th>Gastado</th></tr></thead>
            <tbody>
                @foreach ($datos['top_clientes'] as $fila)
                    <tr><td>{{ $fila['cliente'] }}</td><td>{{ $fila['visitas'] }}</td><td>$ {{ number_format($fila['gastado'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p>Tiempo promedio de entrega: {{ $datos['tiempos']['promedio_min'] }} min ({{ $datos['tiempos']['entregados'] }} entregas).</p>
    @elseif ($reporte === 'reservas')
        <table>
            <thead><tr><th>Métrica</th><th>Valor</th></tr></thead>
            <tbody>
                <tr><td>Total reservas</td><td>{{ $datos['resumen']['total'] }}</td></tr>
                <tr><td>Confirmadas</td><td>{{ $datos['resumen']['confirmadas'] }}</td></tr>
                <tr><td>Canceladas</td><td>{{ $datos['resumen']['canceladas'] }}</td></tr>
                <tr><td>No se mostraron</td><td>{{ $datos['resumen']['no_shows'] }}</td></tr>
                <tr class="total"><td>Cumplimiento</td><td>{{ $datos['resumen']['cumplimiento_porcentaje'] }}%</td></tr>
            </tbody>
        </table>
    @endif

    <div class="footer">SushiXpress · {{ $razon_social }} · Documento generado por el sistema</div>
</body>
</html>
```

- [ ] **Step 6: Rutas** (dentro del grupo auth)

```php
    Route::get('reportes/exportar-pdf', [\App\Http\Controllers\ReporteExportController::class, 'pdf'])->middleware('role:gerente')->name('reportes.pdf');
    Route::get('reportes/exportar-csv', [\App\Http\Controllers\ReporteExportController::class, 'csv'])->middleware('role:gerente')->name('reportes.csv');
```

- [ ] **Step 7: Ejecutar y verificar GREEN**

Run: `php artisan test --filter Fase5ReportesTest`
Expected: PASS (15 tests).

---

### Task 9: KPIs reales en Dashboard (DASH-01)

**Files:**
- Modify: `resources/views/dashboard.blade.php` (con lock activo — tarea de coordinación)
- Test: `tests/Feature/Fase5ReportesTest.php` (ampliar) o nuevo `tests/Feature/Fase5DashboardTest.php`

**Interfaces:**
- Consumes: `ReporteService::kpisRealtime()` (Task 6).
- Produces: dashboard con KPIs reales en COP + tarjeta REP-01 activa (link a `route('reportes')`) y tarjeta RES-01 (link a `route('reservas')`).

- [ ] **Step 1: Escribir el test (RED)**

```php
    public function test_dashboard_muestra_kpis_reales(): void
    {
        $this->pedidoEn(now()->toDateString());
        $admin = User::create(['name' => 'A', 'email' => 'a2@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('50.000');
    }
```

- [ ] **Step 2: Ejecutar y verificar FAIL**

Run: `php artisan test --filter Fase5DashboardTest` (o filter del archivo que contenga el test)
Expected: FAIL — el dashboard muestra datos mock (no contiene `50.000`).

- [ ] **Step 3: Modificar `dashboard.blade.php`** — al inicio del `<x-app-layout>` añadir:

```blade
<?php $kpis = app(\App\Services\ReporteService::class)->kpisRealtime(); ?>
```
- Sustituir los 4 valores de las cards KPI (el bloque "Tarjetas Bento", líneas ~58–142) por datos reales:
  - Ventas Facturadas Hoy → `${{ number_format($kpis['ventas_dia'], 0, ',', '.') }}`, subnote "{{ $kpis['transacciones_dia'] }} transacciones".
  - Ticket Promedio → `${{ number_format($kpis['ticket_promedio'], 0, ',', '.') }}`.
  - Ocupación de Salón → `{{ $kpis['mesas_ocupadas'] }}` mesas ocupadas (con barra simple de % si se desea).
  - Comandas KDS → `{{ $kpis['comandas_cocina_activas'] }}` en cocina.
- Añadir (en el bloque de KPIs o como mini-barra) el food cost: `Costo de comida {{ $kpis['food_cost_porcentaje'] }}%`.
- Activar la tarjeta "Reportes DIAN & Analítica" (convertir el `<div>` final en `<a href="{{ route('reportes') }}" wire:navigate>` con estilos activos y badge `✓ Operativo · REP-01`) y añadir una tarjeta "Reservas" (`<a href="{{ route('reservas') }}" wire:navigate>` con badge `RES-01`).

- [ ] **Step 4: Ejecutar y verificar GREEN**

Run: `php artisan test --filter Fase5DashboardTest`
Expected: PASS.

---

### Task 10: Navegación, resultados, coordinación y cierre

**Files:**
- Modify: `resources/views/livewire/layout/navigation.blade.php` (sidebar desktop y drawer móvil: añadir Reservas, Reportes, Configuración)
- Modify: `routes/web.php` (verificar todas las rutas de Fase 5 presentes)
- Modify: `coordination.md`
- Delete: `.locks/fase5-reservas-reportes.lock`

**Interfaces:** — integración.

- [ ] **Step 1: Test de navegación (bootstrap para próximas fases)**

```php
    public function test_navegacion_incluye_modulos_fase5(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a3@t.com', 'password' => bcrypt('secret'), 'role_id' => Role::where('slug', 'admin')->value('id'), 'activo' => true]);

        $html = $this->actingAs($admin)->get(route('dashboard'))->getContent();

        $this->assertStringContainsString(route('reservas'), $html);
        $this->assertStringContainsString(route('reportes'), $html);
        $this->assertStringContainsString(route('configuracion'), $html);
    }
```
(En `navigation.blade.php` hay sidebar desktop y drawer móvil — añadir los enlaces en ambos bloques; `Reportes` puede aparecer en el submenú si existe; seguirlo.)

- [ ] **Step 2: Añadir enlaces de navegación** (sidebar desktop + drawer móvil): Reservas (`route('reservas')`), Reportes (`route('reportes')`), Configuración (`route('configuracion')`, solo admin si se prefiere con `@can`/role check).

- [ ] **Step 3: Migrate + suite completa**

Run: `php artisan migrate:fresh --force --seed`
Run: `php artisan test`
Expected: SETUP ok + suite completa 100% verde (≥102 tests).

- [ ] **Step 4: Actualizar `coordination.md`** (sección Última Actualización + historial + Pendientes Fase 5 → `[x]`) y **eliminar lock**:

```bash
Remove-Item .locks/fase5-reservas-reportes.lock
```

- [ ] **Step 5: Registrar nota para Antigravity** (resumen de lo construido, contratos de webhook y configuración, y recordatorio de que Dashboard y Navigation fueron modificados — verificar diff).

---

## Self-Review (ejecutar antes de entregar)

1. **Cobertura de spec:** Reservas interna (Task 3-4), pública (Task 5), webhook (Task 5), Configuración/DIAN (Task 1-2), KPIs dashboard (Task 9), reportes avanzados por pestañas (Task 6-7), PDF+CSV (Task 8), nav + coordinación (Task 10). ✔
2. **Placeholder scan:** los pasos "verificar y corregir" están marcados como notas de verificación, no como placeholders (los tests y código están escritos). ✔
3. **Consistencia de tipos:** `kpisRealtime()`/`ventasPorTipo()`/etc. se definen en Task 6 con firmas idénticas a las usadas en Task 7-9. `ReservaService` firmas usadas idénticas en Task 3-6. `ConfiguracionService::obtener/guardar` iguales en Task 1-5-8. ✔