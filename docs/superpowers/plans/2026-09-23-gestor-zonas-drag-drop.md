# Gestor de Zonas + Drag & Drop Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zones become per-sucursal catalog data (CRUD + fixed palette) and tables move between zones by touch-drag on the map.

**Architecture:** New `zonas` table + `Zona` model/policy/seeder; `mesas.zona` stays a slug string (no data migration); `MesaService::moverMesa` holds the move logic; `mesas/index` Volt view gains a manager modal, dynamic rendering from the catalog, and Pointer-Events drag calling `$wire.call('moverMesaAZona')`.

**Tech Stack:** Laravel 13 + Livewire 4 (Volt single-file component) + Tailwind v3.4 + vanilla JS Pointer Events (no new dependencies).

**Spec:** `docs/superpowers/specs/2026-09-23-gestor-zonas-drag-drop-design.md`

## Global Constraints

- PHP 8.3, PSR-12, `vendor\bin\pint --test <files>` must pass (Windows shell).
- Tests run with `php artisan test --filter <Name>`.
- Authorization server-side in every mutating method (`$this->authorize(...)`); role slugs: `admin`, `gerente`, `cajero`, `mesero`.
- NO git commits without the user's explicit approval (repo rule overrides any commit step).
- No new npm/composer dependencies.
- `mesas.zona` remains a string slug; never migrate it to a foreign key in this plan.

---

## File Map

- Create: `database/migrations/20XX_XX_XX_XXXXXX_create_zonas_table.php` (via artisan, then fill in)
- Create: `app/Models/Zona.php` (model + palette/icon consts + scopes)
- Create: `app/Policies/ZonaPolicy.php` (mirrors `MesaPolicy` role pattern)
- Create: `database/seeders/ZonaSeeder.php` (4 classic zones per sucursal + adopt orphan slugs)
- Modify: `app/Services/MesaService.php` (add `moverMesa`)
- Modify: `resources/views/livewire/mesas/index.blade.php` (validation, `with()`, manager modal, dynamic render, `moverMesaAZona`, drag script)
- Create: `tests/Feature/ZonaGestionTest.php` (all new tests)
- Modify: `docs/superpowers/specs/2026-09-23-gestor-zonas-drag-drop-design.md` (only if spec changes during work)
- Modify: `coordination.md` (final registration entry only)

---

### Task 1: Migración `zonas` + modelo `Zona` + seeder

**Files:**
- Create: `database/migrations/<timestamp>_create_zonas_table.php`
- Create: `app/Models/Zona.php`
- Create: `database/seeders/ZonaSeeder.php`

**Interfaces:**
- Consumes: `sucursales.id`
- Produces: `Zona` model with `Zona::PALETA` (color key => Tailwind classes), `Zona::ICONOS` (icon key => Material Symbol), scopes `deSucursal($id)`, `activas()`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ZonaGestionTest.php
<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZonaGestionTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['nombre' => 'Admin', 'slug' => 'admin']);
        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Test',
            'codigo' => 'TST-01',
            'direccion' => 'Calle 1',
            'telefono' => '3000000000',
            'activa' => true,
        ]);
        $this->admin = User::factory()->create(['role_id' => $roleAdmin->id, 'sucursal_id' => $this->sucursal->id]);
    }

    public function test_seeder_crea_zonas_clasicas_por_sucursal(): void
    {
        $this->seed(\Database\Seeders\ZonaSeeder::class);

        $this->assertEquals(4, Zona::where('sucursal_id', $this->sucursal->id)->count());
        $this->assertDatabaseHas('zonas', ['sucursal_id' => $this->sucursal->id, 'slug' => 'salon']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter test_seeder_crea_zonas_clasicas_por_sucursal`
Expected: FAIL with "table zonas does not exist" (or class not found).

- [ ] **Step 3: Create migration, model, seeder (minimal code)**

Run: `php artisan make:migration create_zonas_table`

Migration `up()`:

```php
Schema::create('zonas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
    $table->string('nombre', 60);
    $table->string('slug', 40);
    $table->string('color', 30)->default('terracota');
    $table->string('icono', 40)->default('table_restaurant');
    $table->integer('orden')->default(0);
    $table->boolean('activa')->default(true);
    $table->timestamps();
    $table->unique(['sucursal_id', 'slug'], 'zonas_sucursal_slug_unique');
    $table->index(['sucursal_id', 'activa', 'orden'], 'zonas_sucursal_activa_orden_idx');
});
```

`down()`: `Schema::dropIfExists('zonas');`

Model `app/Models/Zona.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zona extends Model
{
    use HasFactory;

    /**
     * Clave de paleta => clases Tailwind del tema Aura (única fuente de color del mapa).
     *
     * @var array<string, array{tinte: string, punto: string, pastilla: string}>
     */
    public const PALETA = [
        'terracota' => ['tinte' => 'bg-primary-container/70', 'punto' => 'bg-primary', 'pastilla' => 'bg-primary'],
        'salvia' => ['tinte' => 'bg-secondary-container/70', 'punto' => 'bg-secondary', 'pastilla' => 'bg-secondary'],
        'lavanda' => ['tinte' => 'bg-tertiary-container/70', 'punto' => 'bg-tertiary', 'pastilla' => 'bg-tertiary'],
        'ambar' => ['tinte' => 'bg-amber-200/70', 'punto' => 'bg-amber-600', 'pastilla' => 'bg-amber-600'],
        'esmeralda' => ['tinte' => 'bg-emerald-200/70', 'punto' => 'bg-emerald-600', 'pastilla' => 'bg-emerald-600'],
        'indigo' => ['tinte' => 'bg-indigo-200/70', 'punto' => 'bg-indigo-600', 'pastilla' => 'bg-indigo-600'],
        'rosa' => ['tinte' => 'bg-rose-200/70', 'punto' => 'bg-rose-600', 'pastilla' => 'bg-rose-600'],
        'pizarra' => ['tinte' => 'bg-surface-container-high/60', 'punto' => 'bg-outline-variant', 'pastilla' => 'bg-status-cleaning'],
    ];

    public const ICONOS = [
        'mesa' => 'table_restaurant',
        'barra' => 'local_bar',
        'terraza' => 'deck',
        'vip' => 'diamond',
        'patio' => 'outdoor_garden',
        'jardin' => 'park',
        'balcon' => 'balcony',
        'privado' => 'meeting_room',
    ];

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'slug',
        'color',
        'icono',
        'orden',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activa' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function scopeDeSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}
```

Seeder `database/seeders/ZonaSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Mesa;
use App\Models\Sucursal;
use App\Models\Zona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZonaSeeder extends Seeder
{
    public function run(): void
    {
        $clasicas = [
            ['nombre' => 'Salón Principal', 'slug' => 'salon', 'color' => 'terracota', 'icono' => 'mesa', 'orden' => 1],
            ['nombre' => 'Barra / Bar', 'slug' => 'barra', 'color' => 'lavanda', 'icono' => 'barra', 'orden' => 2],
            ['nombre' => 'Terraza', 'slug' => 'terraza', 'color' => 'salvia', 'icono' => 'terraza', 'orden' => 3],
            ['nombre' => 'Área VIP', 'slug' => 'vip', 'color' => 'indigo', 'icono' => 'vip', 'orden' => 4],
        ];

        foreach (Sucursal::all(['id']) as $sucursal) {
            foreach ($clasicas as $z) {
                Zona::firstOrCreate(
                    ['sucursal_id' => $sucursal->id, 'slug' => $z['slug']],
                    $z + ['activa' => true]
                );
            }

            // Adoptar slugs históricos con mesas (patio, primer_piso, personalizados)
            $huerfanos = Mesa::where('sucursal_id', $sucursal->id)
                ->distinct()
                ->pluck('zona')
                ->filter(fn ($slug) => $slug && ! Zona::where('sucursal_id', $sucursal->id)->where('slug', $slug)->exists());
            foreach ($huerfanos as $i => $slug) {
                Zona::firstOrCreate(
                    ['sucursal_id' => $sucursal->id, 'slug' => $slug],
                    ['nombre' => ucfirst(str_replace(['-', '_'], ' ', $slug)), 'color' => 'pizarra', 'icono' => 'mesa', 'orden' => 90 + $i, 'activa' => true]
                );
            }
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter test_seeder_crea_zonas_clasicas_por_sucursal`
Expected: PASS.

- [ ] **Step 5: Run pint on the new files**

Run: `vendor\bin\pint --test app\Models\Zona.php database\seeders\ZonaSeeder.php database\migrations\<timestamp>_create_zonas_table.php`
Expected: PASS (fix with `vendor\bin\pint <files>` if needed, then re-run `--test`).

---

### Task 2: `ZonaPolicy` + `MesaService::moverMesa`

**Files:**
- Create: `app/Policies/ZonaPolicy.php`
- Modify: `app/Services/MesaService.php` (append `moverMesa` method)
- Test: `tests/Feature/ZonaGestionTest.php` (append methods)

**Interfaces:**
- Consumes: `Zona` model, `Mesa` model, `User->role?->slug`
- Produces: `ZonaPolicy::{viewAny,view,create,update,mover}`, `MesaService::moverMesa(Mesa $mesa, string $zonaSlug): Mesa` (throws `DomainException` on invalid/inactive zone or cross-sucursal zone)

- [ ] **Step 1: Write the failing tests** (append to `ZonaGestionTest`)

```php
public function test_policy_zonas_solo_gerente_admin_gestionan_y_cajero_mueve(): void
{
    $gerente = User::factory()->create(['role_id' => Role::create(['nombre' => 'Gerente', 'slug' => 'gerente'])->id]);
    $cajero = User::factory()->create(['role_id' => Role::create(['nombre' => 'Cajero', 'slug' => 'cajero'])->id]);
    $mesero = User::factory()->create(['role_id' => Role::create(['nombre' => 'Mesero', 'slug' => 'mesero'])->id]);

    $this->assertTrue($gerente->can('create', \App\Models\Zona::class));
    $this->assertTrue($gerente->can('update', \App\Models\Zona::class));
    $this->assertFalse($cajero->can('create', \App\Models\Zona::class));
    $this->assertTrue($cajero->can('mover', \App\Models\Zona::class));
    $this->assertFalse($mesero->can('mover', \App\Models\Zona::class));
}

public function test_mover_mesa_actualiza_zona_y_rechaza_invalida(): void
{
    $this->seed(\Database\Seeders\ZonaSeeder::class);
    $mesa = \App\Models\Mesa::create([
        'sucursal_id' => $this->sucursal->id,
        'numero' => '99',
        'capacidad' => 4,
        'zona' => 'salon',
        'estado' => 'libre',
    ]);

    $movida = app(\App\Services\MesaService::class)->moverMesa($mesa, 'terraza');

    $this->assertSame('terraza', $movida->fresh()->zona);

    $this->expectException(\DomainException::class);
    app(\App\Services\MesaService::class)->moverMesa($mesa->fresh(), 'zona-fantasma');
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter "test_policy_zonas_solo_gerente_admin_gestionan_y_cajero_mueve|test_mover_mesa_actualiza_zona_y_rechaza_invalida"`
Expected: FAIL (policy class and method do not exist).

- [ ] **Step 3: Write minimal implementation**

`app/Policies/ZonaPolicy.php` (mirrors `MesaPolicy` role pattern exactly):

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zona;

class ZonaPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function view(User $user, ?Zona $zona = null): bool
    {
        return in_array($user->role?->slug, ['mesero', 'cajero', 'gerente', 'admin']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function update(User $user, ?Zona $zona = null): bool
    {
        return in_array($user->role?->slug, ['gerente', 'admin']);
    }

    public function mover(User $user, ?Zona $zona = null): bool
    {
        // Cajero, gerente y admin pueden mover mesas entre zonas (mesero no)
        return in_array($user->role?->slug, ['cajero', 'gerente', 'admin']);
    }
}
```

Append to `app/Services/MesaService.php` (same style as neighboring methods: typed params, `DomainException` on rule violations):

```php
public function moverMesa(Mesa $mesa, string $zonaSlug): Mesa
{
    $zona = Zona::where('slug', $zonaSlug)
        ->where('sucursal_id', $mesa->sucursal_id)
        ->where('activa', true)
        ->first();

    if (! $zona) {
        throw new \DomainException("La zona [{$zonaSlug}] no existe o está inactiva en esta sucursal.");
    }

    $mesa->update(['zona' => $zona->slug]);

    return $mesa->fresh();
}
```

Add import at top of `MesaService.php`: `use App\Models\Zona;` (keep alphabetical order with existing `use` lines).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter "test_policy_zonas_solo_gerente_admin_gestionan_y_cajero_mueve|test_mover_mesa_actualiza_zona_y_rechaza_invalida|test_seeder_crea_zonas_clasicas_por_sucursal"`
Expected: PASS (all 3).

- [ ] **Step 5: Run pint on touched files**

Run: `vendor\bin\pint --test app\Policies\ZonaPolicy.php app\Services\MesaService.php tests\Feature\ZonaGestionTest.php`
Expected: PASS.

---

### Task 3: Validación dinámica + chips de zona dinámicos en crear/editar mesa

**Files:**
- Modify: `resources/views/livewire/mesas/index.blade.php` (validation rule line ~105, chips `@foreach` line ~1623, add `use Illuminate\Validation\Rule;` to the PHP header `use` block)
- Test: `tests/Feature/ZonaGestionTest.php` (append Volt test)

**Interfaces:**
- Consumes: `Zona::deSucursal()->activas()` for the current sucursal (same resolution as `mount()`: `Auth::user()?->sucursal_id ?? Sucursal::value('id')`), `Zona::PALETA` not needed here
- Produces: `formMesa.zona` accepts any active zone slug; chips render from catalog

- [ ] **Step 1: Write the failing test** (append)

```php
public function test_crear_mesa_acepta_zona_nueva_del_catalogo(): void
{
    $this->seed(\Database\Seeders\ZonaSeeder::class);
    \App\Models\Zona::create([
        'sucursal_id' => $this->sucursal->id,
        'nombre' => 'Jardín',
        'slug' => 'jardin',
        'color' => 'salvia',
        'icono' => 'terraza',
        'orden' => 5,
        'activa' => true,
    ]);

    \Livewire\Volt\Volt::actingAs($this->admin)
        ->test('mesas.index')
        ->set('formMesa.numero', 'J-01')
        ->set('formMesa.zona', 'jardin')
        ->set('formMesa.capacidad', 4)
        ->call('guardarMesa')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mesas', ['numero' => 'J-01', 'zona' => 'jardin']);
}
```

NOTE: Verify the actual save method name in the component before running (`guardarMesa` is used by the create/edit form `wire:submit="guardarMesa"` at line ~1605; if the method is named differently, use the real name — do NOT rename component methods).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter test_crear_mesa_acepta_zona_nueva_del_catalogo`
Expected: FAIL with validation error on `formMesa.zona` (the `in:` rule rejects `jardin`).

- [ ] **Step 3: Minimal implementation**

3a. Add `use Illuminate\Validation\Rule;` to the `use` block at the top of `resources/views/livewire/mesas/index.blade.php`.

3b. Replace the rule (line ~105):

```php
'formMesa.zona' => ['required', 'string', 'max:40', Rule::exists('zonas', 'slug')->where(fn ($q) => $q->where('sucursal_id', $this->sucursalEnContexto())->where('activa', true))],
```

3c. Add this helper method next to the other component methods (mirrors `mount()` sucursal resolution):

```php
public function sucursalEnContexto(): ?int
{
    return Auth::user()?->sucursal_id ?? \App\Models\Sucursal::value('id');
}
```

Check `Auth` and `Sucursal` are already imported in the blade header; if not, add the `use` lines.

3d. Replace the hardcoded chips `@foreach (['salon' => ...] as $val => $label)` (line ~1623) with catalog chips for the current sucursal:

```blade
@foreach (\App\Models\Zona::deSucursal($this->sucursalEnContexto())->activas()->orderBy('orden')->orderBy('nombre')->get() as $zonaOpcion)
    <button
        type="button"
        wire:click="$set('formMesa.zona', '{{ $zonaOpcion->slug }}')"
        class="h-10 px-3 rounded-xl text-xs font-bold text-left border transition-all flex items-center justify-between
               {{ $formMesa['zona'] === $zonaOpcion->slug ? 'bg-primary/10 border-primary text-primary shadow-sm' : 'bg-surface-container-low border-outline-variant/30 text-on-surface-variant hover:bg-surface-container' }}"
    >
        <span>{{ $zonaOpcion->nombre }}</span>
        @if($formMesa['zona'] === $zonaOpcion->slug)
            <span class="material-symbols-outlined text-[16px]">check</span>
        @endif
    </button>
@endforeach
```

Keep the surrounding grid, label, and `@error('formMesa.zona')` untouched.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter test_crear_mesa_acepta_zona_nueva_del_catalogo`
Expected: PASS.

- [ ] **Step 5: Run pint + related suites**

Run: `vendor\bin\pint --test resources\views\livewire\mesas\index.blade.php tests\Feature\ZonaGestionTest.php`
Expected: PASS (if pint flags pre-existing drift elsewhere in the blade, verify via temp-copy diff that none of the flagged lines are yours, and do NOT reformat other agents' code).
Run: `php artisan test --filter "RemediacionSistemaRotoTest|TurnoCajaMultipleShiftsTest"`
Expected: PASS (no regressions in mesas render).

---

### Task 4: Render dinámico del mapa/tabs/filtros desde el catálogo

**Files:**
- Modify: `resources/views/livewire/mesas/index.blade.php` (`with()` + map branch + tabs; DELETE the fixed maps)
- Test: `tests/Feature/ZonaGestionTest.php` (append render test)

**Interfaces:**
- Consumes: `Zona::PALETA`, `Zona::ICONOS`, `Zona::deSucursal()->activas()->ordered`
- Produces: `with()` returns `'zonasCatalogo'` (keyed by slug); map/tabs/filter chips read it; unknown slugs fall to neutral style at the end

- [ ] **Step 1: Write the failing test** (append)

```php
public function test_mapa_muestra_zona_nueva_con_su_color(): void
{
    $this->seed(\Database\Seeders\ZonaSeeder::class);
    \App\Models\Zona::create([
        'sucursal_id' => $this->sucursal->id,
        'nombre' => 'Jardín',
        'slug' => 'jardin',
        'color' => 'salvia',
        'icono' => 'terraza',
        'orden' => 5,
        'activa' => true,
    ]);
    \App\Models\Mesa::create([
        'sucursal_id' => $this->sucursal->id,
        'numero' => 'J-01',
        'capacidad' => 4,
        'zona' => 'jardin',
        'estado' => 'libre',
    ]);

    \Livewire\Volt\Volt::actingAs($this->admin)
        ->test('mesas.index')
        ->assertSee('Jardín')
        ->assertSee('bg-secondary-container/70', false);
}
```

NOTE: `assertSee('bg-secondary-container/70', false)` checks the raw class string (second param disables escaping). Confirm the exact tinte string from `Zona::PALETA['salvia']['tinte']` when writing.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter test_mapa_muestra_zona_nueva_con_su_color`
Expected: FAIL (map renders only fixed zones; `Jardín` absent).

- [ ] **Step 3: Minimal implementation**

3a. In `with()`, add (sucursal resolution identical to Task 3 helper):

```php
'zonasCatalogo' => \App\Models\Zona::deSucursal($this->sucursalEnContexto())->activas()->orderBy('orden')->orderBy('nombre')->get()->keyBy('slug'),
```

3b. In the map branch, replace every use of the fixed maps with catalog lookups:
- `$zonasEtiqueta[$zona] ?? ucfirst($zona)` → `$zonasCatalogo[$zona]->nombre ?? ucfirst(str_replace(['-', '_'], ' ', $zona))`
- `$zonasIcono[$zona] ?? '...'` → `\App\Models\Zona::ICONOS[$zonasCatalogo[$zona]->icono ?? 'mesa'] ?? 'table_restaurant'`
- `$tintesZona[$zona] ?? $default` → `\App\Models\Zona::PALETA[$zonasCatalogo[$zona]->color ?? 'pizarra']['tinte'] ?? 'bg-surface-container-high/60'`
- `$puntosZona[$zona] ?? ...` → same pattern with `['punto']`
- Zone ordering: order `$mesasPorZona` by catalog `orden` (fallback 99 for unknown), replacing `$rankingZonasFlip`.
- Zone tabs (`$zonasTabs`) and filter chips (`$zonasConfig[$zKey] ?? [...]` at line ~584): same catalog pattern; unknown slugs get neutral `pizarra` style + `table_restaurant` icon, sorted last.
- DELETE the now-unused fixed arrays (`$zonasEtiqueta`, `$zonasIcono`, `$tintesZona`, `$puntosZona`, `$rankingZonas`, `$zonasConfig`).

3c. Active-zone pill colors in the per-table status pills (`$pildora`) are STATE colors, not zone colors — DO NOT touch them.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter test_mapa_muestra_zona_nueva_con_su_color`
Expected: PASS.

- [ ] **Step 5: Run pint + mesas suites**

Run: `vendor\bin\pint --test resources\views\livewire\mesas\index.blade.php tests\Feature\ZonaGestionTest.php`
Run: `php artisan test --filter "ZonaGestionTest|RemediacionSistemaRotoTest|Fase1OperacionesTest"`
Expected: all PASS.

---

### Task 5: Modal "Gestionar zonas" (crear/editar/desactivar)

**Files:**
- Modify: `resources/views/livewire/mesas/index.blade.php` (state + methods + modal markup, placed next to the terminal-management modals)
- Test: `tests/Feature/ZonaGestionTest.php` (append Volt tests)

**Interfaces:**
- Consumes: `ZonaPolicy::{create,update}`, `Zona` model, `Mesa::where(sucursal,zona)->count()` guard
- Produces: component state (`$modalZonasOpen`, `$zonaForm`, `$zonaEditandoId`), methods `abrirModalZonas`, `guardarZona`, `iniciarEdicionZona`, `alternarZona` (desactivar/reactivar)

- [ ] **Step 1: Write the failing tests** (append; adjust method names ONLY if the component uses different ones — check first, do not invent)

```php
public function test_gestionar_zonas_crea_y_desactivar_bloqueada_con_mesas(): void
{
    $this->seed(\Database\Seeders\ZonaSeeder::class);

    $t = \Livewire\Volt\Volt::actingAs($this->admin)->test('mesas.index');

    $t->call('abrirModalZonas')
        ->set('zonaForm.nombre', 'Jardín')
        ->set('zonaForm.color', 'salvia')
        ->set('zonaForm.icono', 'terraza')
        ->call('guardarZona')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('zonas', ['slug' => 'jardin', 'sucursal_id' => $this->sucursal->id]);

    \App\Models\Mesa::create([
        'sucursal_id' => $this->sucursal->id,
        'numero' => 'J-01',
        'capacidad' => 4,
        'zona' => 'jardin',
        'estado' => 'libre',
    ]);

    $t->call('alternarZona', \App\Models\Zona::where('slug', 'jardin')->value('id'))
        ->assertSee('tiene mesas asignadas');
}

public function test_mesero_no_puede_gestionar_zonas(): void
{
    $mesero = \App\Models\User::factory()->create([
        'role_id' => \App\Models\Role::create(['nombre' => 'Mesero', 'slug' => 'mesero'])->id,
    ]);

    \Livewire\Volt\Volt::actingAs($mesero)
        ->test('mesas.index')
        ->call('abrirModalZonas')
        ->assertForbidden();
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter "test_gestionar_zonas_crea_y_desactivar_bloqueada_con_mesas|test_mesero_no_puede_gestionar_zonas"`
Expected: FAIL (methods do not exist).

- [ ] **Step 3: Minimal implementation** (follow the existing terminal modals pattern in the same file: `modalGestionTerminalesOpen`, `formEditarCaja`, `guardarEdicionCaja`, `@can('create', ...)` gating)

State (next to terminal state):

```php
public bool $modalZonasOpen = false;

public ?int $zonaEditandoId = null;

public array $zonaForm = [
    'nombre' => '',
    'color' => 'terracota',
    'icono' => 'mesa',
    'orden' => 0,
];
```

Methods:

```php
public function abrirModalZonas(): void
{
    $this->authorize('create', \App\Models\Zona::class);
    $this->zonaEditandoId = null;
    $this->zonaForm = ['nombre' => '', 'color' => 'terracota', 'icono' => 'mesa', 'orden' => 0];
    $this->modalZonasOpen = true;
}

public function iniciarEdicionZona(int $id): void
{
    $this->authorize('update', \App\Models\Zona::class);
    $zona = \App\Models\Zona::deSucursal($this->sucursalEnContexto())->findOrFail($id);
    $this->zonaEditandoId = $zona->id;
    $this->zonaForm = ['nombre' => $zona->nombre, 'color' => $zona->color, 'icono' => $zona->icono, 'orden' => $zona->orden];
}

public function guardarZona(): void
{
    $this->authorize($this->zonaEditandoId ? 'update' : 'create', \App\Models\Zona::class);

    $this->validate([
        'zonaForm.nombre' => 'required|string|max:60',
        'zonaForm.color' => 'required|in:terracota,salvia,lavanda,ambar,esmeralda,indigo,rosa,pizarra',
        'zonaForm.icono' => 'required|in:mesa,barra,terraza,vip,patio,jardin,balcon,privado',
        'zonaForm.orden' => 'required|integer|min:0|max:99',
    ]);

    $datos = $this->zonaForm + ['sucursal_id' => $this->sucursalEnContexto()];
    if ($this->zonaEditandoId) {
        $zona = \App\Models\Zona::deSucursal($this->sucursalEnContexto())->findOrFail($this->zonaEditandoId);
        $zona->update($datos);
    } else {
        $datos['slug'] = \Illuminate\Support\Str::slug($datos['nombre']);
        if (! $this->zonaEditandoId && \App\Models\Zona::deSucursal($this->sucursalEnContexto())->where('slug', $datos['slug'])->exists()) {
            // Flash en vez de abort(422): un abort en una acción Livewire devuelve
            // respuesta de error sin re-render, por lo que el motivo nunca sería visible.
            $this->mensajeFlash = 'Ya existe una zona con ese nombre en esta sucursal.';
            $this->tipoFlash = 'error';

            return;
        }
        $zona = \App\Models\Zona::create($datos);
    }

    $this->modalZonasOpen = false;
    $this->zonaEditandoId = null;
    $this->dispatch('notificacion', ['mensaje' => "Zona {$zona->nombre} guardada.", 'tipo' => 'success']);
}

public function alternarZona(int $id): void
{
    $this->authorize('update', \App\Models\Zona::class);
    $zona = \App\Models\Zona::deSucursal($this->sucursalEnContexto())->findOrFail($id);

    if ($zona->activa) {
        $mesas = \App\Models\Mesa::where('sucursal_id', $zona->sucursal_id)->where('zona', $zona->slug)->count();
        if ($mesas > 0) {
            // Flash en vez de abort(422): un abort en una acción Livewire devuelve
            // respuesta de error sin re-render, por lo que el motivo nunca sería visible.
            $this->mensajeFlash = "La zona {$zona->nombre} tiene mesas asignadas: reasigna primero.";
            $this->tipoFlash = 'error';

            return;
        }
    }

    $zona->update(['activa' => ! $zona->activa]);
    $this->dispatch('notificacion', ['mensaje' => "Zona {$zona->nombre} actualizada.", 'tipo' => 'info']);
}
```

Modal markup: copy the structure of the terminal-management modal in the same file (header + list + form + close), with:
- Button opening it next to "Gestionar Terminales": `@can('create', App\Models\Zona::class)` + `wire:click="abrirModalZonas"`, label "Gestionar Zonas", icon `map`.
- List rows: color dot (`Zona::PALETA[$z->color]['punto']`), nombre, `N mesas` count, orden, estado badge, edit + activate/deactivate buttons.
- Form: nombre (text input — reuse the `data-miles`? NO, plain text), color swatches (8 buttons from `Zona::PALETA` keys showing the dot), icono select (8 options from `Zona::ICONOS`), orden (number input — the global autoselect in `app.js` covers it, no `data-miles`).
- `wire:confirm` on the deactivate button: "¿Desactivar esta zona? Las mesas deben estar reasignadas."

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter "test_gestionar_zonas_crea_y_desactivar_bloqueada_con_mesas|test_mesero_no_puede_gestionar_zonas"`
Expected: PASS.

- [ ] **Step 5: Run pint + mesas suites**

Run: `vendor\bin\pint --test resources\views\livewire\mesas\index.blade.php tests\Feature\ZonaGestionTest.php`
Run: `php artisan test --filter "ZonaGestionTest|Fase1OperacionesTest|MeseroAsignacionYPropinasTest"`
Expected: all PASS.

---

### Task 6: `moverMesaAZona` + drag & drop táctil

**Files:**
- Modify: `resources/views/livewire/mesas/index.blade.php` (method + `data-mesa-id`/`data-zona-drop` attributes + `@push('scripts')` drag script; layout `app.blade.php` already has `@stack('scripts')`)
- Test: `tests/Feature/ZonaGestionTest.php` (append method tests; DnD gesture itself = manual checklist below)

**Interfaces:**
- Consumes: `MesaService::moverMesa`, `ZonaPolicy::mover`, `$wire.call` from Alpine scope
- Produces: `moverMesaAZona(int $mesaId, string $zonaSlug): void`; DOM contract: mesa buttons carry `data-mesa-id` AND `data-zona-actual="<slug>"`, zone floor containers carry `data-zona-drop="<slug>"`, map - [x] **Step 1: Write the failing tests** (append)

```php
public function test_mover_mesa_cambia_zona_y_notifica(): void
{
    $this->seed(\Database\Seeders\ZonaSeeder::class);
    $cajero = \App\Models\User::factory()->create([
        'role_id' => \App\Models\Role::create(['nombre' => 'Cajero', 'slug' => 'cajero'])->id,
        'sucursal_id' => $this->sucursal->id,
    ]);
    $mesa = \App\Models\Mesa::create([
        'sucursal_id' => $this->sucursal->id,
        'numero' => 'M-01',
        'capacidad' => 4,
        'zona' => 'salon',
        'estado' => 'libre',
    ]);

    \Livewire\Volt\Volt::actingAs($cajero)
        ->test('mesas.index')
        ->call('moverMesaAZona', $mesa->id, 'terraza')
        ->assertHasNoErrors();

    $this->assertSame('terraza', $mesa->fresh()->zona);
}

public function test_mover_mesa_rechaza_zona_ajena_y_permiso(): void
{
    $this->seed(\Database\Seeders\ZonaSeeder::class);
    $mesero = \App\Models\User::factory()->create([
        'role_id' => \App\Models\Role::create(['nombre' => 'Mesero', 'slug' => 'mesero'])->id,
        'sucursal_id' => $this->sucursal->id,
    ]);
    $mesa = \App\Models\Mesa::create([
        'sucursal_id' => $this->sucursal->id,
        'numero' => 'M-02',
        'capacidad' => 2,
        'zona' => 'salon',
        'estado' => 'libre',
    ]);

    \Livewire\Volt\Volt::actingAs($mesero)
        ->test('mesas.index')
        ->call('moverMesaAZona', $mesa->id, 'terraza')
        ->assertForbidden();
}
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter "test_mover_mesa_cambia_zona_y_notifica|test_mover_mesa_rechaza_zona_ajena_y_permiso"`
Expected: FAIL (method does not exist).

- [x] **Step 3: Minimal implementation**

3a. Component method (next to `cambiarEstado`-style methods):

```php
public function moverMesaAZona(int $mesaId, string $zonaSlug): void
{
    $this->authorize('mover', \App\Models\Zona::class);

    $mesa = \App\Models\Mesa::where('sucursal_id', $this->sucursalEnContexto())->findOrFail($mesaId);
    app(\App\Services\MesaService::class)->moverMesa($mesa, $zonaSlug);

    $this->dispatch('notificacion', ['mensaje' => "Mesa #{$mesa->numero} movida a {$zonaSlug}.", 'tipo' => 'success']);
}
```

Check `MesaService` is imported in the blade header; if not, the fully-qualified `app(...)` call above works without import.

3b. DOM contract (map branch only):
- On each mesa `<button>`: add `data-mesa-id="{{ $mesa->id }}"` (keep existing `wire:key`/`wire:click` — tap still opens the sheet).
- On each zone floor container: add `data-zona-drop="{{ $zona }}"`.
- On the map wrapper: add `x-data="mapaMesas()"` (Alpine is loaded globally; do NOT add `x-data` to the mesa buttons themselves so Livewire keeps morphing them).

3c. Append at the end of the blade file (after the last `@endif`, before nothing else):

```blade
@push('scripts')
<script>
window.mapaMesas = () => ({
    arrastrando: null,
    fantasma: null,
    temporizador: null,
    zonaDestino: null,

    iniciarArrastre(origen, mesaId) {
        this.cancelarArrastre();
        this.arrastrando = { mesaId, origen };
        origen.style.touchAction = 'none';
        this.temporizador = window.setTimeout(() => this.recoger(origen), 250);
    },

    recoger(origen) {
        this.fantasma = origen.cloneNode(true);
        Object.assign(this.fantasma.style, {
            position: 'fixed', zIndex: 9999, pointerEvents: 'none',
            opacity: '0.85', transform: 'scale(1.05)',
            margin: '0', left: '0px', top: '0px',
        });
        document.body.appendChild(this.fantasma);
        origen.classList.add('opacity-40');
    },

    moverFantasma(evento) {
        if (!this.fantasma) {
            return;
        }
        const punto = evento.touches && evento.touches[0] ? evento.touches[0] : evento;
        this.fantasma.style.left = (punto.clientX - 40) + 'px';
        this.fantasma.style.top = (punto.clientY - 40) + 'px';
        const bajo = document.elementFromPoint(punto.clientX, punto.clientY);
        const sala = bajo ? bajo.closest('[data-zona-drop]') : null;
        const slug = sala ? sala.getAttribute('data-zona-drop') : null;
        if (slug !== this.zonaDestino) {
            document.querySelectorAll('[data-zona-drop].ring-4').forEach((el) => el.classList.remove('ring-4', 'ring-white'));
            this.zonaDestino = slug;
            if (sala) {
                sala.classList.add('ring-4', 'ring-white');
            }
        }
    },

    soltar() {
        if (this.fantasma && this.zonaDestino && this.arrastrando
            && this.zonaDestino !== this.arrastrando.origen.getAttribute('data-zona-actual')) {
            this.$wire.call('moverMesaAZona', this.arrastrando.mesaId, this.zonaDestino);
        }
        this.cancelarArrastre();
    },

    cancelarArrastre() {
        window.clearTimeout(this.temporizador);
        if (this.arrastrando) {
            this.arrastrando.origen.style.touchAction = '';
            this.arrastrando.origen.classList.remove('opacity-40');
        }
        if (this.fantasma) {
            this.fantasma.remove();
        }
        document.querySelectorAll('[data-zona-drop].ring-4').forEach((el) => el.classList.remove('ring-4', 'ring-white'));
        this.arrastrando = null;
        this.fantasma = null;
        this.zonaDestino = null;
    },
});
document.addEventListener('pointerdown', (e) => {
    const btn = e.target.closest ? e.target.closest('[data-mesa-id]') : null;
    if (!btn || e.button === 2) {
        return;
    }
    const root = btn.closest('[x-data]');
    const comp = root && window.Alpine ? window.Alpine.$data(root) : null;
    if (comp && comp.iniciarArrastre) {
        comp.iniciarArrastre(btn, Number(btn.getAttribute('data-mesa-id')));
    }
}, { passive: true });
```
```js
let compMapa = null;
document.addEventListener('pointerdown', (e) => {
    const btn = e.target.closest ? e.target.closest('[data-mesa-id]') : null;
    if (!btn || e.button === 2) {
        compMapa = null;
        return;
    }
    const root = btn.closest('[x-data]');
    compMapa = root && window.Alpine && typeof window.Alpine.$data === 'function' ? window.Alpine.$data(root) : null;
    if (compMapa && compMapa.iniciarArrastre) {
        compMapa.iniciarArrastre(btn, Number(btn.getAttribute('data-mesa-id')));
    }
}, { passive: true });
document.addEventListener('pointermove', (e) => {
    if (compMapa && compMapa.moverFantasma) {
        compMapa.moverFantasma(e);
    }
}, { passive: true });
document.addEventListener('pointerup', () => {
    if (compMapa && compMapa.soltar) {
        compMapa.soltar();
    }
    compMapa = null;
});
document.addEventListener('pointercancel', () => {
    if (compMapa && compMapa.cancelarArrastre) {
        compMapa.cancelarArrastre();
    }
    compMapa = null;
});
```

Rules: never `preventDefault` on `pointerdown` (the tap → sheet `wire:click` must keep working; a quick tap ends with no ghost so `soltar()` is a no-op by its guards). `touch-action: none` applies ONLY while the ghost exists (set in `recoger`, cleared in `cancelarArrastre`), so page scroll never breaks.

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter "test_mover_mesa_cambia_zona_y_notifica|test_mover_mesa_rechaza_zona_ajena_y_permiso"`
Expected: PASS.

- [x] **Step 5: Manual mobile checklist (no automated test covers gestures)**

On a real phone: (1) hold a table 250ms → ghost follows finger; (2) drop on another zone → table moves + toast + counts update; (3) drop outside → nothing changes; (4) quick tap still opens the sheet; (5) page scroll works normally. Record PASS/FAIL per item in `coordination.md`.

- [x] **Step 6: Run pint + full mesas suites**

Run: `vendor\bin\pint --test resources\views\livewire\mesas\index.blade.php tests\Feature\ZonaGestionTest.php app\Models\Zona.php app\Policies\ZonaPolicy.php app\Services\MesaService.php database\seeders\ZonaSeeder.php`
Run: `php artisan test --filter "ZonaGestionTest|RemediacionSistemaRotoTest|Fase1OperacionesTest|MeseroAsignacionYPropinasTest|TurnoCajaMultipleShiftsTest"`
Expected: all PASS (if pint flags pre-existing drift in the blade, verify via temp-copy diff that flagged lines are not yours and do NOT reformat others' code).

---

### Task 7: Registration (no commit without approval)

- [x] **Step 1:** Append one entry to `coordination.md` under a new `## Última Actualización` header summarizing files + test counts.
- [ ] **Step 2:** STOP. Do not commit, push, or reconstruir assets. Report done and wait for the user's explicit order. Report done and wait for the user's explicit order.
