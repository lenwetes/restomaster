# Sistema de Privilegios por Usuario + Plantillas por Rol Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** El admin otorga/quita permisos por usuario con plantillas por rol o checks individuales de 3 estados (heredar/otorgar/quitar), sin cambiar la conducta de nadie el día 1.

**Architecture:** Catálogo en `config/permisos.php` + pivot `permission_user(tipo grant/deny)` + plantillas por slug de rol + `PermisoService` + segundo `Gate::before` en `AppServiceProvider` que solo decide ante fila explícita (sin fila → `null` → policies legacy intactas). Admin con bypass total previo (ya existe, no se toca).

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Livewire Volt, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-18-permisos-plantillas-design.md`

## Global Constraints

- PHP 8.3 + PSR-12 (4 espacios), tipos declarados, sin comentarios redundantes.
- TDD: test RED primero, verlo fallar por la razón correcta, luego mínimo código GREEN.
- NO commits en ningún task (regla del repo: solo con petición explícita del usuario).
- `vendor/bin/pint --test` limpio al final; suite completa `php artisan test` verde sin modificar tests existentes.
- Estados string en BD (`tipo` = `'grant'`/`'deny'`), `$fillable` explícito donde aplique, `abort_unless(...isAdmin(), 403)` en métodos Livewire nuevos.
- Excluidos del catálogo (scoped logic que sigue en policy): `pedidos.liquidar_repartidor`, `pedidos.cocinar`.
- Sin accesos a módulo (`modulos.*`) en v1: menús sin cambios (fuera de alcance, deuda explícita).

---

## File Structure

- `database/migrations/2026_09_18_100000_create_permission_user_table.php` — pivot (T1).
- `app/Models/User.php` — `permisoExplicito()`, `olvidarPermisosMemo()` (T1).
- `config/permisos.php` — catálogo 46 keys + mapa modelo→prefijo + plantillas 7 roles (T2).
- `app/Services/PermisoService.php` — `resolverKey`, `aplicarPlantilla`, `guardarChecks`, `criticas` (T3).
- `app/Providers/AppServiceProvider.php` — segundo `Gate::before` (T4).
- `resources/views/livewire/trabajadores/index.blade.php` — modal permisos + auto-apply en `guardarNuevo` (T5).
- `resources/views/livewire/pos/terminal.blade.php`, `caja/control.blade.php`, `mesas/index.blade.php` — migración de checks inline a `$user->can()` donde haya ability 1:1 (T6).
- `app/Console/Commands/VerificarPermisosCommand.php` — `permisos:verificar` (T7).
- `tests/Feature/PermisosPrivilegiosTest.php` — tests T1–T5, T7 (TDD por task).

---

### Task 1: Pivot + `User::permisoExplicito()`

**Files:**
- Create: `database/migrations/2026_09_18_100000_create_permission_user_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/PermisosPrivilegiosTest.php`

Encabezado del archivo de test (imports para todos los tasks):

```php
<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Role;
use App\Models\User;
use App\Services\PermisoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;
```

**Interfaces:**
- Consumes: nada.
- Produces: `User::permisoExplicito(string $key): ?bool`, `User::olvidarPermisosMemo(): void`, tabla `permission_user`.

- [ ] **Step 1: Write the failing test**

```php
public function test_permiso_explicito_devuelve_grant_deny_o_null(): void
{
    $mesero = $this->crearUsuarioMesero();

    $this->assertNull($mesero->permisoExplicito('pedidos.cobrar'));

    DB::table('permission_user')->insert([
        'user_id' => $mesero->id, 'permission' => 'pedidos.cobrar',
        'tipo' => 'deny', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $mesero->olvidarPermisosMemo();

    $this->assertFalse($mesero->permisoExplicito('pedidos.cobrar'));
}
```

(Helper `crearUsuarioMesero()` privado en el test: crea rol `mesero` si falta y `User::factory()->create(['role_id' => ...])`.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter=permiso_explicito_devuelve`
Expected: FAIL (tabla `permission_user` no existe).

- [ ] **Step 3: Write migration + model methods**

```php
// database/migrations/2026_09_18_100000_create_permission_user_table.php
Schema::create('permission_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('permission', 64);
    $table->string('tipo', 16);
    $table->timestamps();
    $table->unique(['user_id', 'permission']);
});
```

```php
// app/Models/User.php (agregar import Illuminate\Support\Facades\DB)
protected ?array $permisosMemo = null;

public function permisoExplicito(string $key): ?bool
{
    $this->permisosMemo ??= DB::table('permission_user')
        ->where('user_id', $this->id)
        ->pluck('tipo', 'permission')
        ->all();

    if (! array_key_exists($key, $this->permisosMemo)) {
        return null;
    }

    return $this->permisosMemo[$key] === 'grant';
}

public function olvidarPermisosMemo(): void
{
    $this->permisosMemo = null;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter=permiso_explicito_devuelve`
Expected: PASS.

---

### Task 2: Catálogo + plantillas + test de completitud

**Files:**
- Create: `config/permisos.php`
- Test: `tests/Feature/PermisosPrivilegiosTest.php`

**Interfaces:**
- Consumes: nada (lee las 8 policies como fuente de verdad).
- Produces: `config('permisos.catalogo')` (46 keys: key → ['modulo','label']), `config('permisos.mapa')` (modelo → prefijo), `config('permisos.plantillas')` (slug → lista de keys; `'admin' => '*'`).

- [ ] **Step 1: Write the failing tests**

```php
public function test_catalogo_cubre_todas_las_abilities_sin_scope(): void
{
    $catalogo = array_keys(config('permisos.catalogo'));
    $excluidas = ['liquidarRepartidor', 'cocinar'];

    foreach (glob(app_path('Policies/*.php')) as $archivo) {
        $clase = 'App\\Policies\\'.basename($archivo, '.php');
        foreach (get_class_methods($clase) as $metodo) {
            if (in_array($metodo, $excluidas, true)) {
                continue;
            }
            $this->assertContains(
                config('permisos.mapa')[str_replace('App\\Models\\', '', $this->modeloDePolicy($clase))].'.'.$this->snake($metodo),
                $catalogo,
                "Ability sin mapear: {$clase}::{$metodo}"
            );
        }
    }
}

public function test_cada_rol_seed_tiene_plantilla_y_toda_key_existe(): void
{
    $plantillas = config('permisos.plantillas');
    foreach (['admin', 'gerente', 'cajero', 'mesero', 'cocina', 'barra', 'delivery'] as $slug) {
        $this->assertArrayHasKey($slug, $plantillas, "Sin plantilla: {$slug}");
    }
    $catalogo = array_keys(config('permisos.catalogo'));
    foreach ($plantillas as $slug => $keys) {
        if ($keys === '*') {
            continue;
        }
        foreach ($keys as $key) {
            $this->assertContains($key, $catalogo, "Key fantasma en plantilla {$slug}: {$key}");
        }
    }
}

public function test_spot_checks_plantillas(): void
{
    $plantillas = config('permisos.plantillas');
    $this->assertNotContains('pedidos.aplicar_descuento', $plantillas['mesero']);
    $this->assertNotContains('caja.eliminar', $plantillas['gerente']);
    $this->assertNotContains('caja.eliminar', $plantillas['cajero']);
    $this->assertContains('pedidos.cobrar', $plantillas['mesero']);
    $this->assertContains('turnos.abrir', $plantillas['cajero']);
}
```

(H helpers `modeloDePolicy()`/`snake()` privados en el test: mapa fijo `['PedidoPolicy' => 'Pedido', ...]` y `Str::snake()`.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter="catalogo_cubre|cada_rol_seed|spot_checks"`
Expected: FAIL (config no existe).

- [ ] **Step 3: Write `config/permisos.php`**

Catálogo (key → modulo/label), 46 entradas exactas:

```php
'catalogo' => [
    'pedidos.ver' => ['modulo' => 'Pedidos', 'label' => 'Ver comandas'],
    'pedidos.crear' => ['modulo' => 'Pedidos', 'label' => 'Crear pedidos'],
    'pedidos.actualizar' => ['modulo' => 'Pedidos', 'label' => 'Modificar pedidos'],
    'pedidos.eliminar' => ['modulo' => 'Pedidos', 'label' => 'Eliminar pedidos'],
    'pedidos.cobrar' => ['modulo' => 'Pedidos', 'label' => 'Cobrar (procesar cobro)'],
    'pedidos.enviar_cocina' => ['modulo' => 'Pedidos', 'label' => 'Enviar comanda a cocina'],
    'pedidos.aplicar_descuento' => ['modulo' => 'Pedidos', 'label' => 'Aplicar descuentos'],
    'pedidos.canjear_puntos' => ['modulo' => 'Pedidos', 'label' => 'Canjear puntos'],
    'pedidos.gestionar_delivery' => ['modulo' => 'Pedidos', 'label' => 'Gestionar delivery'],
    'mesas.ver' => ['modulo' => 'Mesas', 'label' => 'Ver mesas'],
    'mesas.crear' => ['modulo' => 'Mesas', 'label' => 'Crear mesas'],
    'mesas.actualizar' => ['modulo' => 'Mesas', 'label' => 'Editar mesas'],
    'mesas.eliminar' => ['modulo' => 'Mesas', 'label' => 'Eliminar mesas'],
    'mesas.cambiar_estado' => ['modulo' => 'Mesas', 'label' => 'Cambiar estado operativo'],
    'caja.ver' => ['modulo' => 'Caja', 'label' => 'Ver cajas'],
    'caja.crear' => ['modulo' => 'Caja', 'label' => 'Crear cajas/terminales'],
    'caja.actualizar' => ['modulo' => 'Caja', 'label' => 'Editar cajas'],
    'caja.eliminar' => ['modulo' => 'Caja', 'label' => 'Eliminar cajas'],
    'turnos.ver' => ['modulo' => 'Turnos', 'label' => 'Ver turnos'],
    'turnos.abrir' => ['modulo' => 'Turnos', 'label' => 'Abrir turno'],
    'turnos.cerrar' => ['modulo' => 'Turnos', 'label' => 'Cerrar turno'],
    'turnos.arqueo' => ['modulo' => 'Turnos', 'label' => 'Arqueo'],
    'turnos.guardar_movimiento' => ['modulo' => 'Turnos', 'label' => 'Registrar movimientos'],
    'clientes.ver' => ['modulo' => 'Clientes', 'label' => 'Ver clientes'],
    'clientes.crear' => ['modulo' => 'Clientes', 'label' => 'Crear clientes'],
    'clientes.actualizar' => ['modulo' => 'Clientes', 'label' => 'Editar clientes'],
    'clientes.eliminar' => ['modulo' => 'Clientes', 'label' => 'Eliminar clientes'],
    'clientes.ajustar_puntos' => ['modulo' => 'Clientes', 'label' => 'Ajustar puntos'],
    'reservas.ver' => ['modulo' => 'Reservas', 'label' => 'Ver reservas'],
    'reservas.crear' => ['modulo' => 'Reservas', 'label' => 'Crear reservas'],
    'reservas.actualizar' => ['modulo' => 'Reservas', 'label' => 'Editar reservas'],
    'reservas.eliminar' => ['modulo' => 'Reservas', 'label' => 'Eliminar reservas'],
    'reservas.confirmar' => ['modulo' => 'Reservas', 'label' => 'Confirmar reservas'],
    'reservas.cancelar' => ['modulo' => 'Reservas', 'label' => 'Cancelar reservas'],
    'insumos.ver' => ['modulo' => 'Inventario', 'label' => 'Ver insumos'],
    'insumos.crear' => ['modulo' => 'Inventario', 'label' => 'Crear insumos'],
    'insumos.actualizar' => ['modulo' => 'Inventario', 'label' => 'Editar insumos'],
    'insumos.eliminar' => ['modulo' => 'Inventario', 'label' => 'Eliminar insumos'],
    'insumos.registrar_compra' => ['modulo' => 'Inventario', 'label' => 'Registrar compra'],
    'insumos.registrar_merma' => ['modulo' => 'Inventario', 'label' => 'Registrar merma'],
    'insumos.ajuste_fisico' => ['modulo' => 'Inventario', 'label' => 'Ajuste físico'],
    'cxp.ver' => ['modulo' => 'CxP', 'label' => 'Ver cuentas por pagar'],
    'cxp.crear' => ['modulo' => 'CxP', 'label' => 'Crear cuentas por pagar'],
    'cxp.actualizar' => ['modulo' => 'CxP', 'label' => 'Editar cuentas por pagar'],
    'cxp.eliminar' => ['modulo' => 'CxP', 'label' => 'Eliminar cuentas por pagar'],
    'cxp.registrar_pago' => ['modulo' => 'CxP', 'label' => 'Registrar pago'],
],
'mapa' => [
    'Pedido' => 'pedidos', 'Mesa' => 'mesas', 'Caja' => 'caja',
    'TurnoCaja' => 'turnos', 'Cliente' => 'clientes', 'Reserva' => 'reservas',
    'Insumo' => 'insumos', 'CuentaPorPagar' => 'cxp',
],
```

Plantillas (verificadas contra las 8 policies el 2026-09-18):

```php
'plantillas' => [
    'admin' => '*',
    'gerente' => [/* 45 keys: todas menos caja.eliminar */
        'pedidos.ver','pedidos.crear','pedidos.actualizar','pedidos.eliminar','pedidos.cobrar','pedidos.enviar_cocina','pedidos.aplicar_descuento','pedidos.canjear_puntos','pedidos.gestionar_delivery',
        'mesas.ver','mesas.crear','mesas.actualizar','mesas.eliminar','mesas.cambiar_estado',
        'caja.ver','caja.crear','caja.actualizar',
        'turnos.ver','turnos.abrir','turnos.cerrar','turnos.arqueo','turnos.guardar_movimiento',
        'clientes.ver','clientes.crear','clientes.actualizar','clientes.eliminar','clientes.ajustar_puntos',
        'reservas.ver','reservas.crear','reservas.actualizar','reservas.eliminar','reservas.confirmar','reservas.cancelar',
        'insumos.ver','insumos.crear','insumos.actualizar','insumos.eliminar','insumos.registrar_compra','insumos.registrar_merma','insumos.ajuste_fisico',
        'cxp.ver','cxp.crear','cxp.actualizar','cxp.eliminar','cxp.registrar_pago',
    ],
    'cajero' => [/* 25 keys */
        'pedidos.ver','pedidos.crear','pedidos.actualizar','pedidos.cobrar','pedidos.enviar_cocina','pedidos.aplicar_descuento','pedidos.canjear_puntos','pedidos.gestionar_delivery',
        'mesas.ver','mesas.cambiar_estado',
        'caja.ver',
        'turnos.ver','turnos.abrir','turnos.cerrar','turnos.arqueo','turnos.guardar_movimiento',
        'clientes.ver','clientes.crear','clientes.actualizar','clientes.ajustar_puntos',
        'reservas.ver','reservas.crear','reservas.actualizar','reservas.confirmar','reservas.cancelar',
    ],
    'mesero' => [/* 13 keys */
        'pedidos.ver','pedidos.crear','pedidos.actualizar','pedidos.cobrar','pedidos.enviar_cocina','pedidos.canjear_puntos',
        'mesas.ver','mesas.cambiar_estado',
        'reservas.ver','reservas.crear','reservas.actualizar','reservas.confirmar','reservas.cancelar',
    ],
    'cocina' => ['pedidos.ver'],
    'barra' => ['pedidos.ver'],
    'delivery' => ['pedidos.ver','pedidos.gestionar_delivery'],
],
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter="catalogo_cubre|cada_rol_seed|spot_checks"`
Expected: PASS.

---

### Task 3: `PermisoService` (resolver + aplicar + guardar)

**Files:**
- Create: `app/Services/PermisoService.php`
- Test: `tests/Feature/PermisosPrivilegiosTest.php`

**Interfaces:**
- Consumes: `config('permisos.*')`, `User::permisoExplicito()`, `User::olvidarPermisosMemo()`.
- Produces: `resolverKey(string $ability, mixed $target): ?string`, `plantilla(string $slug): array`, `aplicarPlantilla(User $u, string $slug): void`, `guardarChecks(User $u, array $checks): array` (retorna diff `['otorgados'=>[], 'quitados'=>[]]`), `criticas(): array`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_resolver_mapea_ability_mas_modelo_y_null_si_no_mapea(): void
{
    $svc = app(PermisoService::class);
    $this->assertSame('pedidos.cobrar', $svc->resolverKey('cobrar', new Pedido));
    $this->assertSame('caja.eliminar', $svc->resolverKey('delete', Caja::class));
    $this->assertNull($svc->resolverKey('cocinar', new Pedido));
    $this->assertNull($svc->resolverKey('liquidarRepartidor', new Pedido));
    $this->assertNull($svc->resolverKey('cobrar', null));
}

public function test_aplicar_plantilla_crea_grants_y_guardar_sincroniza_con_diff(): void
{
    $svc = app(PermisoService::class);
    $mesero = $this->crearUsuarioMesero();

    $svc->aplicarPlantilla($mesero, 'mesero');
    $this->assertTrue((bool) $mesero->fresh()->permisoExplicito('pedidos.cobrar'));
    $this->assertNull($mesero->fresh()->permisoExplicito('caja.eliminar'));

    $diff = $svc->guardarChecks($mesero, [
        'pedidos.cobrar' => 'quitar',
        'caja.ver' => 'otorgar',
    ]);
    $this->assertSame(['pedidos.cobrar'], $diff['quitados']);
    $this->assertSame(['caja.ver'], $diff['otorgados']);
    $this->assertFalse((bool) $mesero->fresh()->permisoExplicito('pedidos.cobrar'));
    $this->assertTrue((bool) $mesero->fresh()->permisoExplicito('caja.ver'));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter="resolver_mapea|aplicar_plantilla_crea"`
Expected: FAIL (clase no existe).

- [ ] **Step 3: Write minimal service**

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PermisoService
{
    public function resolverKey(string $ability, mixed $target): ?string
    {
        $clase = $target instanceof \Stringable || is_object($target)
            ? class_basename($target)
            : (is_string($target) && class_exists($target) ? class_basename($target) : null);

        if (! $clase) {
            return null;
        }

        $prefijo = config('permisos.mapa')[$clase] ?? null;
        if (! $prefijo) {
            return null;
        }

        $key = $prefijo.'.'.Str::snake($ability);
        $catalogo = array_keys(config('permisos.catalogo', []));

        return in_array($key, $catalogo, true) ? $key : null;
    }
```

(OJO: abilities camelCase del policy — `enviarCocina` → snake `enviar_cocina` ✓, `guardarMovimiento` → `guardar_movimiento` ✓, `aplicarDescuento` → `aplicar_descuento` ✓, `viewAny` → `view_any` ✗. El catálogo usa `ver`, no `view_any`. Resolver: mapear `viewAny→ver`, `view→ver`, `create→crear`, `update→actualizar`, `delete→eliminar`, resto snake. Implementar ese alias en el método.)

```php
    protected array $alias = [
        'viewAny' => 'ver', 'view' => 'ver', 'create' => 'crear',
        'update' => 'actualizar', 'delete' => 'eliminar',
    ];

    // dentro de resolverKey, antes de componer $key:
    $habilidad = $this->alias[$ability] ?? Str::snake($ability);
    $key = $prefijo.'.'.$habilidad;
```

```php
    public function plantilla(string $slug): array
    {
        $plantillas = config('permisos.plantillas', []);

        if (($plantillas[$slug] ?? null) === '*') {
            return array_keys(config('permisos.catalogo', []));
        }

        return $plantillas[$slug] ?? [];
    }

    public function aplicarPlantilla(User $usuario, string $slug): void
    {
        $this->guardarChecks($usuario, array_fill_keys($this->plantilla($slug), 'otorgar'));
    }

    public function guardarChecks(User $usuario, array $checks): array
    {
        $catalogo = array_keys(config('permisos.catalogo', []));
        $antes = DB::table('permission_user')->where('user_id', $usuario->id)->pluck('tipo', 'permission')->all();
        $otorgados = [];
        $quitados = [];

        foreach ($checks as $key => $estado) {
            if (! in_array($key, $catalogo, true)) {
                throw new InvalidArgumentException("Permiso desconocido: {$key}.");
            }
            if (! in_array($estado, ['otorgar', 'quitar', 'heredar'], true)) {
                throw new InvalidArgumentException("Estado inválido para {$key}: {$estado}.");
            }

            $tenia = $antes[$key] ?? null;

            if ($estado === 'heredar') {
                if ($tenia !== null) {
                    DB::table('permission_user')->where('user_id', $usuario->id)->where('permission', $key)->delete();
                    $quitados[] = $key;
                }

                continue;
            }

            $tipo = $estado === 'otorgar' ? 'grant' : 'deny';
            DB::table('permission_user')->updateOrInsert(
                ['user_id' => $usuario->id, 'permission' => $key],
                ['tipo' => $tipo, 'updated_at' => now(), 'created_at' => now()]
            );

            if ($tenia !== $tipo) {
                if ($estado === 'otorgar') {
                    $otorgados[] = $key;
                } else {
                    $quitados[] = $key;
                }
            }
        }

        $usuario->olvidarPermisosMemo();

        return ['otorgados' => $otorgados, 'quitados' => $quitados];
    }

    public function criticas(): array
    {
        return ['pedidos.cobrar', 'turnos.abrir', 'pedidos.eliminar', 'caja.eliminar'];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter="resolver_mapea|aplicar_plantilla_crea"`
Expected: PASS.

---

### Task 4: `Gate::before` 3 estados + tests de enforcement

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:50-55` (AGREGAR segundo closure; NO tocar el bypass admin existente)
- Test: `tests/Feature/PermisosPrivilegiosTest.php`

**Interfaces:**
- Consumes: `PermisoService::resolverKey()`, `User::permisoExplicito()`.
- Produces: enforcement global (sin API nueva; verificado vía `$user->can()`).

- [ ] **Step 1: Write the failing tests**

```php
public function test_gate_deny_explicito_niega_aunque_el_rol_lo_permita(): void
{
    $mesero = $this->crearUsuarioMesero();
    DB::table('permission_user')->insert([
        'user_id' => $mesero->id, 'permission' => 'pedidos.cobrar',
        'tipo' => 'deny', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->assertFalse($mesero->fresh()->can('cobrar', Pedido::class));
}

public function test_gate_grant_explicito_otorga_aunque_el_rol_no_lo_tenga(): void
{
    $mesero = $this->crearUsuarioMesero();
    DB::table('permission_user')->insert([
        'user_id' => $mesero->id, 'permission' => 'pedidos.aplicar_descuento',
        'tipo' => 'grant', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->assertTrue($mesero->fresh()->can('aplicarDescuento', Pedido::class));
}

public function test_gate_sin_filas_mantiene_legacy_y_admin_pasa_con_set_vacio(): void
{
    $mesero = $this->crearUsuarioMesero();
    $this->assertTrue($mesero->can('cobrar', Pedido::class));
    $this->assertFalse($mesero->can('aplicarDescuento', Pedido::class));

    $admin = $this->crearUsuarioAdmin();
    $this->assertTrue($admin->can('cualquierCosa', Pedido::class));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter="gate_deny_explicito|gate_grant_explicito|gate_sin_filas"`
Expected: FAIL (deny/grant ignorados; legacy manda).

- [ ] **Step 3: Write minimal enforcement** (después del bypass admin existente, `AppServiceProvider.php:55`)

```php
        // Permisos explícitos por usuario (3 estados): deny→false, grant→true, sin fila→null (legacy)
        Gate::before(function (User $user, string $ability, array $arguments) {
            $key = app(\App\Services\PermisoService::class)->resolverKey($ability, $arguments[0] ?? null);

            if ($key === null) {
                return null;
            }

            return $user->permisoExplicito($key);
        });
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter="gate_deny_explicito|gate_grant_explicito|gate_sin_filas"`
Expected: PASS.

---

### Task 5: UI en Trabajadores (modal + plantilla + preview + guardar)

**Files:**
- Modify: `resources/views/livewire/trabajadores/index.blade.php` (métodos + modal; sigue el patrón `abort_unless(isAdmin)` del archivo)
- Test: `tests/Feature/PermisosPrivilegiosTest.php`

**Interfaces:**
- Consumes: `PermisoService` (plantilla/guardar/criticas), `TrabajadorService::crear()` (retorna `User`), `AuditoriaService::registrar()`.
- Produces: `abrirModalPermisos(int)`, `aplicarPlantillaPermisos()`, `cambiarCheck(string $key, string $estado)`, `guardarPermisos()`, props `permisosUserId/permisosPlantilla/permisosChecks`. (OJO: las keys llevan punto, NO usar `wire:model` con ellas; los radios usan `wire:change="cambiarCheck('key', $event.target.value)"`.)

- [ ] **Step 1: Write the failing tests**

```php
public function test_admin_guarda_permisos_con_preview_y_auditoria(): void
{
    $admin = $this->crearUsuarioAdmin();
    $mesero = $this->crearUsuarioMesero();

    Volt::actingAs($admin)
        ->test('trabajadores.index')
        ->call('abrirModalPermisos', $mesero->id)
        ->set('permisosPlantilla', 'cajero')
        ->call('aplicarPlantillaPermisos')
        ->call('cambiarCheck', 'pedidos.cobrar', 'quitar')
        ->call('guardarPermisos')
        ->assertDispatched('notificacion');

    $this->assertFalse((bool) $mesero->fresh()->permisoExplicito('pedidos.cobrar'));
    $this->assertTrue((bool) $mesero->fresh()->permisoExplicito('caja.ver'));
    $this->assertDatabaseHas('auditorias', ['accion' => 'usuarios.permisos_actualizados', 'entidad_id' => $mesero->id]);
}

public function test_no_admin_no_puede_guardar_permisos_403(): void
{
    $mesero = $this->crearUsuarioMesero();
    $otro = $this->crearUsuarioMesero('otro@x.com');

    Volt::actingAs($mesero)
        ->test('trabajadores.index')
        ->call('abrirModalPermisos', $otro->id)
        ->assertForbidden();
}

public function test_admin_no_puede_editar_sus_propios_permisos_403(): void
{
    $admin = $this->crearUsuarioAdmin();

    Volt::actingAs($admin)
        ->test('trabajadores.index')
        ->call('abrirModalPermisos', $admin->id)
        ->assertForbidden();
}

public function test_guardar_rechaza_key_fantasma(): void
{
    $admin = $this->crearUsuarioAdmin();
    $mesero = $this->crearUsuarioMesero();

    Volt::actingAs($admin)
        ->test('trabajadores.index')
        ->call('abrirModalPermisos', $mesero->id)
        ->call('cambiarCheck', 'no.existe', 'otorgar')
        ->assertHasErrors('permisosChecks');
}

public function test_crear_usuario_aplica_plantilla_de_su_rol(): void
{
    $admin = $this->crearUsuarioAdmin();
    $rolMesero = Role::where('slug', 'mesero')->first();

    Volt::actingAs($admin)
        ->test('trabajadores.index')
        ->set('nuevo.nombre', 'Mesero Nuevo')
        ->set('nuevo.email', 'nuevo@x.com')
        ->set('nuevo.telefono', '3001112233')
        ->set('nuevo.password', 'password')
        ->set('nuevo.role_id', $rolMesero->id)
        ->call('guardarNuevo')
        ->assertDispatched('notificacion');

    $creado = User::where('email', 'nuevo@x.com')->first();
    $this->assertTrue((bool) $creado->permisoExplicito('pedidos.cobrar'));
    $this->assertNull($creado->permisoExplicito('caja.eliminar'));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter="admin_guarda_permisos|no_admin_no_puede|no_puede_editar_sus|rechaza_key_fantasma|crear_usuario_aplica"`
Expected: FAIL (métodos no existen).

- [ ] **Step 3: Write minimal UI + methods** (en `resources/views/livewire/trabajadores/index.blade.php`; importa `App\Services\PermisoService`, `Illuminate\Validation\Rule`, `Illuminate\Validation\ValidationException`)

```php
public ?int $permisosUserId = null;

public ?int $permisosPlantillaRolId = null;

public array $permisosChecks = [];

public bool $mostrarModalPermisos = false;

public function abrirModalPermisos(int $userId): void
{
    abort_unless(auth()->user()?->isAdmin(), 403);
    abort_if($userId === auth()->id(), 403, 'No puedes modificar tus propios permisos.');

    $usuario = User::findOrFail($userId);
    $this->permisosUserId = $usuario->id;
    $this->permisosPlantillaRolId = $usuario->role_id;
    $this->permisosChecks = [];

    foreach (DB::table('permission_user')->where('user_id', $usuario->id)->pluck('tipo', 'permission')->all() as $key => $tipo) {
        $this->permisosChecks[$key] = $tipo === 'grant' ? 'otorgar' : 'quitar';
    }

    $this->mostrarModalPermisos = true;
}

public function aplicarPlantillaPermisos(): void
{
    abort_unless(auth()->user()?->isAdmin(), 403);

    $rol = Role::find($this->permisosPlantillaRolId);
    abort_if(! $rol, 404);

    $this->permisosChecks = [];
    foreach (app(PermisoService::class)->plantilla($rol->slug) as $key) {
        $this->permisosChecks[$key] = 'otorgar';
    }
}

public function cambiarCheck(string $key, string $estado): void
{
    abort_unless(auth()->user()?->isAdmin(), 403);

    if (! array_key_exists($key, config('permisos.catalogo', []))) {
        throw ValidationException::withMessages(['permisosChecks' => "Permiso desconocido: {$key}."]);
    }

    if (! in_array($estado, ['otorgar', 'heredar', 'quitar'], true)) {
        throw ValidationException::withMessages(['permisosChecks' => "Estado inválido: {$estado}."]);
    }

    $this->permisosChecks[$key] = $estado;
}

public function guardarPermisos(): void
{
    abort_unless(auth()->user()?->isAdmin(), 403);
    abort_if($this->permisosUserId === auth()->id(), 403, 'No puedes modificar tus propios permisos.');

    $usuario = User::findOrFail($this->permisosUserId);

    $this->validate([
        'permisosChecks' => 'array',
        'permisosChecks.*' => 'in:otorgar,heredar,quitar',
    ]);

    foreach (array_keys($this->permisosChecks) as $key) {
        if (! array_key_exists($key, config('permisos.catalogo', []))) {
            throw ValidationException::withMessages(['permisosChecks' => "Permiso desconocido: {$key}."]);
        }
    }

    $diff = app(PermisoService::class)->guardarChecks($usuario, $this->permisosChecks);

    app(AuditoriaService::class)->registrar(
        accion: 'usuarios.permisos_actualizados',
        entidad: 'usuario',
        entidadId: $usuario->id,
        descripcion: "Permisos actualizados para {$usuario->name}.",
        datos: $diff,
    );

    $this->mostrarModalPermisos = false;
    $this->dispatch('notificacion', ['mensaje' => 'Permisos guardados correctamente.', 'tipo' => 'success']);
}
```

En `guardarNuevo()`, después de `app(TrabajadorService::class)->crear($this->nuevo)` (línea 75) capturar el usuario y aplicar plantilla:

```php
$nuevoUsuario = app(TrabajadorService::class)->crear($this->nuevo);
$rolNuevo = Role::find($this->nuevo['role_id']);
app(PermisoService::class)->aplicarPlantilla($nuevoUsuario, $rolNuevo->slug);
```

Modal Blade (radios SIN `wire:model` — las keys llevan punto):

```blade
@foreach(config('permisos.catalogo') as $key => $meta)
    @php $estadoActual = $checks[$key] ?? 'heredar'; @endphp
    <label>{{ $meta['label'] }}</label>
    @foreach(['otorgar' => '＋', 'heredar' => '＝', 'quitar' => '－'] as $valor => $simbolo)
        <label>
            <input
                type="radio"
                value="{{ $valor }}"
                @checked($estadoActual === $valor)
                wire:change="cambiarCheck('{{ $key }}', $event.target.value)"
            />
            <span title="{{ $valor }}">{{ $simbolo }}</span>
        </label>
    @endforeach
@endforeach
```

(`$checks` = `array_merge(array_fill_keys(array_keys(config('permisos.catalogo')), 'heredar'), $permisosChecks)` calculado en el `with()` o antes del modal; grupos por `$meta['modulo']` con `@foreach` agrupado.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter="admin_guarda_permisos|no_admin_no_puede|no_puede_editar_sus|rechaza_key_fantasma|crear_usuario_aplica"`
Expected: PASS.

---

### Task 6: Migrar checks inline POS/caja/mesas a `$user->can()`

**Files:**
- Modify: `resources/views/livewire/pos/terminal.blade.php`, `resources/views/livewire/caja/control.blade.php`, `resources/views/livewire/mesas/index.blade.php` (solo donde aplique)
- Test: suite existente (sin tests nuevos; el backstop `Gate` ya está cubierto en T4)

**Interfaces:**
- Consumes: abilities del catálogo T2 (verificación 1:1).
- Produces: botones/acciones que respetan grants/denies explícitos.

- [ ] **Step 1: Inventariar checks role-based en los 3 archivos**

Run: `Select-String -Path resources/views/livewire/pos/terminal.blade.php,resources/views/livewire/caja/control.blade.php,resources/views/livewire/mesas/index.blade.php -Pattern "role\?->slug|isMesero\(\)|isCajero\(\)|isAdmin\(\)|isGerente\(\)" | Select-Object Filename,LineNumber,Line`
Expected: lista concreta; clasificar cada hit: (a) identidad de dominio — mesa propia, pantallas admin, auto-relevo — SE DEJA con comentario `{{-- rol intencional, no permiso --}}`; (b) duplicado de ability del catálogo — SE MIGRA a `$user->can('ability', Modelo::class)`.

- [ ] **Step 2: Migrar solo la clase (b)**

Ejemplo (patrón; aplicar por hit):

```blade
{{-- antes --}}
@if(in_array(auth()->user()?->role?->slug, ['cajero', 'gerente', 'admin']))
{{-- después --}}
@if(auth()->user()?->can('aplicarDescuento', App\Models\Pedido::class))
```

Regla: si no hay ability 1:1 en el catálogo, NO se toca (queda con comentario de rol intencional).

- [ ] **Step 3: Verificar que nada cambió para usuarios legacy**

Run: `php artisan test`
Expected: PASS 385+ tests (más los nuevos de T1–T5).

---

### Task 7: Comando `permisos:verificar` + cierre

**Files:**
- Create: `app/Console/Commands/VerificarPermisosCommand.php`
- Modify: `coordination.md` (registrar cierre del plan)

**Interfaces:**
- Consumes: `permission_user`, `User`, `PermisoService::criticas()`.
- Produces: `php artisan permisos:verificar` (dry-run de revisión).

- [ ] **Step 1: Write the failing test**

```php
public function test_comando_verificar_lista_usuarios_con_permisos_explicitos(): void
{
    $mesero = $this->crearUsuarioMesero();
    DB::table('permission_user')->insert([
        'user_id' => $mesero->id, 'permission' => 'caja.ver',
        'tipo' => 'grant', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('permisos:verificar')
        ->assertSuccessful()
        ->expectsOutputToContain($mesero->email);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter=comando_verificar_lista`
Expected: FAIL (comando no existe).

- [ ] **Step 3: Write minimal command**

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerificarPermisosCommand extends Command
{
    protected $signature = 'permisos:verificar';

    protected $description = 'Lista usuarios con permisos explícitos y sus grants/denies (dry-run de revisión).';

    public function handle(): int
    {
        $filas = DB::table('permission_user as pu')
            ->join('users as u', 'u.id', '=', 'pu.user_id')
            ->select('u.name', 'u.email', 'pu.permission', 'pu.tipo')
            ->orderBy('u.email')
            ->orderBy('pu.permission')
            ->get();

        if ($filas->isEmpty()) {
            $this->info('Sin permisos explícitos: todo el sistema corre con lógica legacy por rol.');

            return self::SUCCESS;
        }

        foreach ($filas->groupBy('email') as $email => $grupo) {
            $this->line("{$grupo->first()->name} <{$email}>:");
            foreach ($grupo as $fila) {
                $marca = $fila->tipo === 'grant' ? '＋' : '－';
                $this->line("  {$marca} {$fila->permission}");
            }
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/PermisosPrivilegiosTest.php --filter=comando_verificar_lista`
Expected: PASS.

- [ ] **Step 5: Cierre global**

Run: `php artisan test`
Expected: PASS suite completa (385 + nuevos).

Run: `vendor/bin/pint --test app/Services/PermisoService.php app/Models/User.php app/Providers/AppServiceProvider.php app/Console/Commands/VerificarPermisosCommand.php database/migrations/2026_09_18_100000_create_permission_user_table.php config/permisos.php tests/Feature/PermisosPrivilegiosTest.php`
Expected: PASS (Pint no toca Blade; revisar a ojo el diff del modal).

Registrar cierre en `coordination.md` (entrada con fecha, tests y archivos).