# Gestión de Proveedores (F1 CRUD+facturas, F2 comparador) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Módulo Proveedores con fichas CRUD vinculadas al inventario, facturas multi-insumo que mueven Kardex y generan CxP a crédito, y comparador de precios vs otros proveedores y referencia de mercado.

**Architecture:** Tablas `proveedores/compras/compra_lineas` + columnas en `insumos`/`cuentas_por_pagar`; `ProveedorService` + `CompraService::registrarFactura/anularFactura` que reutilizan `InventarioService::registrarCompra()` por línea y `CuentasPorPagarService::crear()` a crédito; `ProveedorPolicy`/`CompraPolicy` + catálogo de privilegios extendido; UI Volt `/proveedores` espejo `cxp/index`.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Livewire Volt, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-18-gestion-proveedores-design.md`

## Global Constraints

- PHP 8.3 + PSR-12 (4 espacios), tipos declarados, sin comentarios redundantes.
- TDD: test RED primero, verlo fallar por la razón correcta, luego mínimo código GREEN.
- NO commits en ningún task (regla del repo: solo con petición explícita del usuario).
- `vendor/bin/pint --test` limpio al final; suite completa verde salvo fallos pre-existentes documentados en el ledger del workspace.
- Estados string en BD; `$fillable` explícito; `abort_unless(...isAdmin()/...)` NO — roles gerente/admin vía `middleware('role:gerente,admin')` en ruta + `$this->authorize()` / `abort_unless` con policy en métodos que mutan.
- Sin dependencias nuevas. Policies existentes intactas.

---

## File Structure

- `database/migrations/2026_09_18_110000_create_proveedores_table.php` (T1)
- `database/migrations/2026_09_18_110001_create_compras_tables.php` (compras + compra_lineas) (T1)
- `database/migrations/2026_09_18_110002_add_proveedor_to_insumos_table.php` (T1)
- `database/migrations/2026_09_18_110003_add_compra_to_cxp_table.php` (T1)
- `app/Models/Proveedor.php`, `app/Models/Compra.php`, `app/Models/CompraLinea.php` (T1)
- `app/Models/Insumo.php`, `app/Models/CuentaPorPagar.php` (agregados T1)
- `app/Policies/ProveedorPolicy.php`, `app/Policies/CompraPolicy.php` (T2)
- `config/permisos.php` (7 keys + 2 mapa + 2 plantillas) (T2)
- `tests/Feature/PermisosPrivilegiosTest.php` (mapa + spots) (T2)
- `app/Services/ProveedorService.php`, `app/Services/CompraService.php` (T3)
- `resources/views/livewire/proveedores/index.blade.php`, `routes/web.php`, `resources/views/livewire/layout/navigation.blade.php` (T4)
- `resources/views/livewire/inventario/index.blade.php` (campos) (T5)
- Ficha comparador/KPIs + reporte en proveedores/index + `CompraService::gastoPorProveedor` + `ProveedorService::fichaResumen/comparadorInsumo` (T6)
- `tests/Feature/ProveedoresTest.php` (T1, T3–T6), coordinación (T7)

---

### Task 1: Migraciones + modelos + relaciones

**Files:**
- Create: las 4 migrations + `app/Models/Proveedor.php` + `app/Models/Compra.php` + `app/Models/CompraLinea.php`
- Modify: `app/Models/Insumo.php` (fillable/casts/relación), `app/Models/CuentaPorPagar.php` (fillable/relación)
- Test: `tests/Feature/ProveedoresTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: tablas, modelos con relaciones (`proveedor->compras/insumos`, `compra->proveedor/lineas/cxp/usuario`, `linea->compra/insumo`, `insumo->proveedor`, `cxp->compra`).

- [ ] **Step 1: Write the failing tests**

```php
public function test_migraciones_crean_tablas_y_columnas(): void
{
    foreach (['proveedores', 'compras', 'compra_lineas'] as $tabla) {
        $this->assertTrue(Schema::hasTable($tabla), "Falta tabla {$tabla}.");
    }
    foreach (['proveedor_id', 'precio_referencia_mercado'] as $col) {
        $this->assertTrue(Schema::hasColumn('insumos', $col), "Falta insumos.{$col}.");
    }
    $this->assertTrue(Schema::hasColumn('cuentas_por_pagar', 'compra_id'));
}

public function test_factura_duplicada_por_proveedor_violenta_unique(): void
{
    $prov = Proveedor::create(['nombre' => 'Distribuidora Andina']);
    Compra::create(['proveedor_id' => $prov->id, 'numero_factura' => 'F-001', 'fecha' => now()->toDateString(), 'subtotal' => 100, 'forma_pago' => 'contado', 'estado' => 'registrada', 'user_id' => 1]);

    $this->expectException(QueryException::class);
    Compra::create(['proveedor_id' => $prov->id, 'numero_factura' => 'F-001', 'fecha' => now()->toDateString(), 'subtotal' => 50, 'forma_pago' => 'contado', 'estado' => 'registrada', 'user_id' => 1]);
}

public function test_relaciones_proveedor_compra_lineas(): void
{
    $prov = Proveedor::create(['nombre' => 'Plaza Mayorista']);
    $this->assertInstanceOf(Collection::class, $prov->compras);
    $this->assertInstanceOf(Collection::class, $prov->insumos);
}
```

(Header del test: `namespace Tests\Feature;` + imports `Proveedor, Compra, Insumo, Schema, QueryException, Collection, TestCase` + `use RefreshDatabase`. `user_id => 1`: con `RefreshDatabase` sin seeders no hay users → la FK `users` fallaría. OJO: crear usuario قبل: `$user = User::factory()->create();` y usar `$user->id`. El ejecutor debe hacerlo así — ajustado abajo en implementación.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ProveedoresTest.php`
Expected: FAIL (tablas/modelos no existen).

- [ ] **Step 3: Write migrations + models**

```php
// 110000
Schema::create('proveedores', function (Blueprint $table) {
    $table->id();
    $table->string('nombre')->unique();
    $table->string('nit')->nullable()->unique();
    $table->string('telefono', 20)->nullable();
    $table->string('email')->nullable();
    $table->string('direccion')->nullable();
    $table->string('contacto')->nullable();
    $table->unsignedSmallInteger('dias_credito')->default(0);
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

```php
// 110001
Schema::create('compras', function (Blueprint $table) {
    $table->id();
    $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
    $table->string('numero_factura', 64);
    $table->date('fecha');
    $table->decimal('subtotal', 12, 2);
    $table->string('forma_pago', 16);
    $table->string('estado', 16)->default('registrada');
    $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
    $table->timestamps();
    $table->unique(['proveedor_id', 'numero_factura']);
});
Schema::create('compra_lineas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
    $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
    $table->decimal('cantidad', 10, 3);
    $table->decimal('costo_unitario', 12, 2);
    $table->decimal('subtotal', 12, 2);
    $table->timestamps();
});
```

```php
// 110002
Schema::table('insumos', function (Blueprint $table) {
    $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
    $table->decimal('precio_referencia_mercado', 12, 2)->nullable();
});
// 110003
Schema::table('cuentas_por_pagar', function (Blueprint $table) {
    $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
});
```

Modelos (fillable/casts/relaciones exactos):

```php
// Proveedor: fillable nombre,nit,telefono,email,direccion,contacto,dias_credito,activo;
// casts activo=>boolean; compras(): HasMany(Compra::class); insumos(): HasMany(Insumo::class, 'proveedor_id');
// Compra: fillable proveedor_id,numero_factura,fecha,subtotal,forma_pago,estado,user_id;
// casts fecha=>date, subtotal=>decimal:2; proveedor(): BelongsTo; lineas(): HasMany(CompraLinea::class, 'compra_id');
// cxp(): HasOne(CuentaPorPagar::class, 'compra_id'); usuario(): BelongsTo(User::class, 'user_id');
// CompraLinea: fillable compra_id,insumo_id,cantidad,costo_unitario,subtotal;
// casts cantidad=>decimal:3, costo_unitario/subtotal=>decimal:2; compra()/insumo() BelongsTo.
// Insumo: fillable += proveedor_id, precio_referencia_mercado; casts += precio_referencia_mercado=>decimal:2;
// proveedor(): BelongsTo(Proveedor::class, 'proveedor_id');
// CuentaPorPagar: fillable += compra_id; compra(): BelongsTo(Compra::class, 'compra_id');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ProveedoresTest.php`
Expected: PASS.

---

### Task 2: Policies + catálogo + Gate tests

**Files:**
- Create: `app/Policies/ProveedorPolicy.php`, `app/Policies/CompraPolicy.php`
- Modify: `config/permisos.php` (7 keys + 2 mapa + 2 plantillas), `tests/Feature/PermisosPrivilegiosTest.php` (mapa `$modelos` += 2 + spots)
- Test: `tests/Feature/ProveedoresTest.php`

**Interfaces:**
- Consumes: T1 (modelos), catálogo T-previo.
- Produces: abilities `proveedores.ver/crear/actualizar/eliminar`, `compras.ver/crear/anular` exigibles vía `$user->can()` (Gate::before existente las resuelve por mapa).

Helper de test (agregar en este task, lo usan T2–T6):

```php
private function crearUsuario(string $slug, ?string $email = null): User
{
    $rol = Role::firstOrCreate(['slug' => $slug], ['nombre' => ucfirst($slug)]);
    static $n = 0;

    return User::factory()->create([
        'role_id' => $rol->id,
        'email' => $email ?? $slug.'-'.(++$n).'@test.com',
    ]);
}
```

(imports `Role, User` en el test.)

- [ ] **Step 1: Write the failing tests**

```php
public function test_gerente_puede_gestionar_proveedores_y_mesero_no(): void
{
    $gerente = $this->crearUsuario('gerente');
    $mesero = $this->crearUsuario('mesero');

    $this->assertTrue($gerente->can('create', Proveedor::class));
    $this->assertFalse($mesero->can('create', Proveedor::class));
    $this->assertTrue($gerente->can('anular', Compra::class));
    $this->assertFalse($mesero->can('anular', Compra::class));
}

public function test_deny_explicito_bloquea_gestion_de_proveedores(): void
{
    $gerente = $this->crearUsuario('gerente');
    DB::table('permission_user')->insert(['user_id' => $gerente->id, 'permission' => 'proveedores.crear', 'tipo' => 'deny', 'created_at' => now(), 'updated_at' => now()]);

    $this->assertFalse($gerente->fresh()->can('create', Proveedor::class));
}
```

(Helper `crearUsuario(string $slug, ?string $email = null)` privado: `Role::firstOrCreate(['slug' => $slug], ['nombre' => ucfirst($slug)])` + factory con `role_id`. NO asume seeders.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ProveedoresTest.php --filter="gerente_puede_gestionar|deny_explicito_bloquea"`
Expected: FAIL (policies no existen → Gate sin policy = deny para gerente también).

- [ ] **Step 3: Write policies + catálogo**

```php
// ProveedorPolicy (viewAny/view/create/update/delete → gerente/admin; delete con ?Proveedor = null igual que MesaPolicy)
public function viewAny(User $user): bool { return in_array($user->role?->slug, ['gerente', 'admin'], true); }
// ... view/create/update/delete idénticos
// CompraPolicy: viewAny/view/create/anular → gerente/admin (anular: public function anular(User $user, ?Compra $compra = null): bool)
```

`config/permisos.php`: catalogo += `proveedores.ver/crear/actualizar/eliminar` (labels 'Ver/Crear/Editar/Eliminar proveedores', modulo 'Proveedores') + `compras.ver/crear/anular` (labels 'Ver compras', 'Registrar factura', 'Anular factura', modulo 'Compras'); mapa += `'Proveedor' => 'proveedores', 'Compra' => 'compras'`; plantillas `gerente` += las 7 keys; `admin => '*'` ya las cubre.

`tests/Feature/PermisosPrivilegiosTest.php`: mapa `$modelos` += `'ProveedorPolicy' => 'Proveedor', 'CompraPolicy' => 'Compra'`; spots += `assertContains('proveedores.crear', $plantillas['gerente'])`, `assertContains('compras.anular', $plantillas['gerente'])`, `assertNotContains('proveedores.crear', $plantillas['mesero'])`, `assertNotContains('compras.crear', $plantillas['cajero'])`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ProveedoresTest.php --filter="gerente_puede_gestionar|deny_explicito_bloquea"; php artisan test tests/Feature/PermisosPrivilegiosTest.php`
Expected: PASS ambos.

---

### Task 3: `ProveedorService` + `CompraService`

**Files:**
- Create: `app/Services/ProveedorService.php`, `app/Services/CompraService.php`
- Test: `tests/Feature/ProveedoresTest.php`

**Interfaces:**
- Consumes: `InventarioService::registrarCompra()` (por línea), `CuentasPorPagarService::crear()`, `AuditoriaService::registrar()`, helper `crearUsuario()` de T2.
- Produces: `registrarFactura(array $cab, array $lineas, ?User $u = null): Compra`, `anularFactura(Compra $c, ?User $u = null): Compra`, CRUD proveedor + `vincularInsumo()`.

Helper de test (agregar en este task):

```php
private function crearInsumo(array $over = []): Insumo
{
    return Insumo::create(array_merge([
        'nombre' => 'Insumo Test '.Str::random(6),
        'codigo' => 'INS-'.Str::upper(Str::random(6)),
        'unidad_medida' => 'kg',
    ], $over));
}
```

(import `Str` + `Insumo` en el test; `nombre/codigo/unidad_medida` son los NOT NULL de `2026_09_09_191000_create_insumos_table.php`). En cada test que cobre/registre, crear admin local: `$admin = $this->crearUsuario('admin');` (NO existe propiedad `$this->admin`).

- [ ] **Step 1: Write the failing tests**

```php
public function test_factura_contado_mueve_kardex_y_no_crea_cxp(): void
{
    $admin = $this->crearUsuario('admin');
    $prov = Proveedor::create(['nombre' => 'Andina']);
    $insumo = $this->crearInsumo(['stock_actual' => 10, 'costo_unitario' => 5000]);

    $compra = app(CompraService::class)->registrarFactura(
        ['proveedor_id' => $prov->id, 'numero_factura' => 'F-100', 'fecha' => now()->toDateString(), 'forma_pago' => 'contado'],
        [['insumo_id' => $insumo->id, 'cantidad' => 4, 'costo_unitario' => 6000]],
        $admin
    );

    $this->assertSame('registrada', $compra->estado);
    $this->assertEquals(24000.0, (float) $compra->subtotal);
    $this->assertEquals(14.0, (float) $insumo->fresh()->stock_actual);
    $this->assertEquals(5285.71, (float) $insumo->fresh()->costo_unitario); // (10*5000+4*6000)/14
    $this->assertDatabaseMissing('cuentas_por_pagar', ['compra_id' => $compra->id]);
}

public function test_factura_credito_crea_cxp_con_vencimiento(): void
{
    $admin = $this->crearUsuario('admin');
    $prov = Proveedor::create(['nombre' => 'Plaza', 'nit' => '9001', 'dias_credito' => 15]);
    $insumo = $this->crearInsumo();

    $compra = app(CompraService::class)->registrarFactura(
        ['proveedor_id' => $prov->id, 'numero_factura' => 'F-200', 'fecha' => '2026-09-01', 'forma_pago' => 'credito'],
        [['insumo_id' => $insumo->id, 'cantidad' => 2, 'costo_unitario' => 10000]],
        $admin
    );

    $cxp = CuentaPorPagar::where('compra_id', $compra->id)->first();
    $this->assertNotNull($cxp);
    $this->assertEquals(20000.0, (float) $cxp->saldo_pendiente);
    $this->assertSame('2026-09-16', $cxp->fecha_vencimiento->toDateString());
}

public function test_factura_duplicada_y_linea_invalida_abortan_422(): void
{
    // ... registra F-300, reintenta igual proveedor+factura → InvalidArgumentException; cantidad 0 → InvalidArgumentException
}

public function test_anular_revierte_kardex_y_borra_cxp_sin_pagos(): void
{
    // ... factura crédito sin pagos → anular → estado anulada, stock vuelve, cxp eliminada
}

public function test_anular_bloquea_con_pagos_o_sin_stock(): void
{
    // ... cxp con 1 pago → DomainException; stock consumido bajo la línea → DomainException
}

public function test_eliminar_proveedor_con_compras_bloquea_422(): void
{
    // ... crear + desactivar ok (activo=false); eliminar con compras → InvalidArgumentException; sin compras → eliminado
}
```

(Helpers: `crearInsumo(array $over = [])` con defaults válidos (nombre único, unidad, stock, costo, categoria... usar solo columnas requeridas NOT NULL — verificar con `SHOW`? NO: usar factory si existe `Insumo::factory()`, si no crear con mínimos + `activo => true`. El ejecutor verifica la migración de insumos para NOT NULLs. `admin` = helper `crearUsuario('admin')` en setUp o por test.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ProveedoresTest.php --filter="factura_contado|factura_credito|factura_duplicada|anular_revierte|anular_bloquea|eliminar_proveedor"`
Expected: FAIL (servicios no existen).

- [ ] **Step 3: Write services**

```php
// ProveedorService: crear (nombre required+unique, nit unique nullable → InvalidArgumentException como TrabajadorService),
// actualizar, desactivar (activo=false + audit), eliminar (abort 422 si compras()->exists() + audit), vincularInsumo(Proveedor, Insumo).
// Auditoría: proveedor.creado/actualizado/desactivado/eliminado.
// CompraService::registrarFactura:
return DB::transaction(function () use ($cabecera, $lineas, $usuario) {
        $proveedor = Proveedor::find($cabecera['proveedor_id'] ?? null);
        if (! $proveedor || ! $proveedor->activo) { throw new InvalidArgumentException('Proveedor inválido o inactivo.'); }
        if (empty($lineas)) { throw new InvalidArgumentException('La factura requiere al menos una línea.'); }
        if (Compra::where('proveedor_id', $proveedor->id)->where('numero_factura', $cabecera['numero_factura'] ?? '')->exists()) { throw new InvalidArgumentException('Factura ya registrada para este proveedor.'); }
    $subtotal = 0;
    foreach ($lineas as $l) { /* valida insumo/cantidad/costo; $subtotal += round(cantidad*costo,2) */ }
    $compra = Compra::create([... 'subtotal' => $subtotal, 'estado' => 'registrada', 'user_id' => $usuario?->id ?? auth()->id()]);
    foreach ($lineas as $l) {
        CompraLinea::create([...]);
        app(InventarioService::class)->registrarCompra($l['insumo_id'], (float) $l['cantidad'], (float) $l['costo_unitario'], $proveedor->nombre, $compra->numero_factura, $usuario?->id);
    }
    if (($cabecera['forma_pago'] ?? 'contado') === 'credito') {
        app(CuentasPorPagarService::class)->crear([
            'proveedor_nombre' => $proveedor->nombre, 'proveedor_nit' => $proveedor->nit,
            'numero_factura' => $compra->numero_factura, 'concepto' => "Compra factura {$compra->numero_factura} (".count($lineas)." líneas)",
            'monto_total' => $subtotal, 'fecha_emision' => $compra->fecha->toDateString(),
            'fecha_vencimiento' => Carbon::parse($compra->fecha)->addDays((int) $proveedor->dias_credito)->toDateString(),
            'compra_id' => $compra->id, 'user_id' => $usuario?->id,
        ]);
    }
    app(AuditoriaService::class)->registrar(accion: 'compra.registrada', ...);
    return $compra->fresh(['lineas', 'proveedor']);
});
// anularFactura: lock + estado registrada? + cxp con pagos? block 422 + por línea: lock insumo, stock>=qty? si no DomainException,
$restante = round($stock - $qty, 3);
$nuevoProm = $restante > 0 ? round(($stock*$avg - $qty*$costoLinea)/$restante, 2) : $avg;
update + MovimientoInventario::create(['tipo' => 'devolucion_compra', cantidad, saldos, costo_unitario => costoLinea, costo_total, motivo "Anulación factura X", referencia_documento => factura]);
// cxp sin pagos → delete; estado anulada + audit compra.anulada.
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ProveedoresTest.php`
Expected: PASS.

---

### Task 4: UI Proveedores + ruta + sidebar

**Files:**
- Create: `resources/views/livewire/proveedores/index.blade.php`
- Modify: `routes/web.php` (1 línea), `resources/views/livewire/layout/navigation.blade.php` (2 bloques: desktop + móvil)
- Test: `tests/Feature/ProveedoresTest.php`

**Interfaces:**
- Consumes: T3 (servicios), `ProveedorPolicy`/`CompraPolicy` (authorize en métodos).
- Produces: ruta `proveedores`, CRUD + ficha + factura desde UI.

- [ ] **Step 1: Write the failing tests**

```php
public function test_ruta_proveedores_403_para_mesero_y_200_para_gerente(): void
{
    $this->actingAs($this->crearUsuario('mesero'))->get(route('proveedores'))->assertForbidden();
    $this->actingAs($this->crearUsuario('gerente'))->get(route('proveedores'))->assertOk();
}

public function test_admin_crea_proveedor_y_registra_factura_desde_ui(): void
{
    $gerente = $this->crearUsuario('gerente');
    $insumo = $this->crearInsumo();

    Volt::actingAs($gerente)->test('proveedores.index')
        ->set('nuevo.nombre', 'Andina SAS')->set('nuevo.nit', '900123')
        ->set('nuevo.telefono', '3001112233')->call('guardarProveedor')
        ->assertDispatched('notificacion');

    $prov = Proveedor::where('nit', '900123')->first();
    $this->assertNotNull($prov);

    Volt::actingAs($gerente)->test('proveedores.index')
        ->call('abrirFactura', $prov->id)
        ->set('factura.numero', 'F-900')->set('factura.forma_pago', 'contado')
        ->set('lineas', [['insumo_id' => $insumo->id, 'cantidad' => 3, 'costo_unitario' => 7000]])
        ->call('guardarFactura')
        ->assertDispatched('notificacion');

    $this->assertDatabaseHas('compras', ['proveedor_id' => $prov->id, 'numero_factura' => 'F-900', 'estado' => 'registrada']);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ProveedoresTest.php --filter="ruta_proveedores|admin_crea_proveedor"`
Expected: FAIL (ruta/vista no existen).

- [ ] **Step 3: Write route + sidebar + component**

Ruta (después de la línea cxp en `routes/web.php`):
```php
Volt::route('proveedores', 'proveedores.index')->middleware('role:gerente,admin')->name('proveedores');
```

Sidebar (desktop tras bloque Inventario + móvil tras su Inventario): `<a href="{{ route('proveedores') }}" wire:navigate ...>` icono `local_shipping`, label `Proveedores`, badge `PRV`, activo con `request()->routeIs('proveedores')`, copiando clases del bloque Inventario vecino.

Componente (props: `busqueda`, `soloActivos=true`, `nuevo[]`, `edicion[]+enEdicion`, `fichaId`, `pestana='datos'`, `factura[]`, `lineas[]`; métodos con `$this->authorize('create'|'update'|'delete', Proveedor::class)` y `$this->authorize('create'|'anular', Compra::class)`):
`abrirCrear/guardarProveedor` (valida nombre required|unique:proveedores,nombre + nit nullable|unique), `abrirEditar/guardarEdicion`, `desactivar`, `eliminar` (muestra error si 422), `abrirFicha($id)/setPestana` (pestañas: datos, insumos vinculados con último costo, facturas, cxp con saldo), `abrirFactura/guardarFactura` (delega a `CompraService::registrarFactura`, try/catch muestra mensaje), `confirmarAnulacion/anularFactura`, `with()` (proveedores con `withCount('compras')` + search + filtro activos, insumos activos para líneas, compras del proveedor en ficha).
Modales: crear/editar (campos spec §3), factura (cabecera + líneas dinámicas agregar/quitar), anular (confirmación), ficha (tabs). Botones visibles según `@can` (create/update/delete Proveedor, create/anular Compra).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ProveedoresTest.php --filter="ruta_proveedores|admin_crea_proveedor"`
Expected: PASS.

---

### Task 5: Integración inventario

**Files:**
- Modify: `resources/views/livewire/inventario/index.blade.php`
- Test: `tests/Feature/ProveedoresTest.php`

**Interfaces:**
- Consumes: `insumos.proveedor_id`, `precio_referencia_mercado` (T1), `InsumoPolicy::update` existente.

- [ ] **Step 1: Inventariar modal de insumo (comando exacto)**

Run: `Select-String -Path resources/views/livewire/inventario/index.blade.php -Pattern 'public function (abrir|guardar|editar).*nsumo|edicion.*proveedor|nuevo.*proveedor|wire:model="(nuevo|edicion)\.' | Select-Object LineNumber,Line | Select-Object -First 30`
Expected: nombres exactos de props/métodos del crear/editar de insumo.

- [ ] **Step 2: Write the failing tests**

```php
public function test_insumo_guarda_proveedor_y_precio_referencia(): void
{
    $gerente = $this->crearUsuario('gerente');
    $prov = Proveedor::create(['nombre' => 'Andina']);

    // vía el método de edición existente descubierto en Step 1 (abrir+set+guardar)
    // ... set proveedor_id + precio_referencia_mercado ... guardar ...

    $this->assertSame($prov->id, $insumo->fresh()->proveedor_id);
    $this->assertEquals(7500.0, (float) $insumo->fresh()->precio_referencia_mercado);
}

public function test_ficha_insumo_muestra_proveedor_vinculado(): void
{
    // ... actingAs gerente test inventario.index ... assertSee(nombre proveedor)
}
```

(Ajustar nombres de métodos/props a lo hallado en Step 1; el contrato —persistir ambos campos y mostrar nombre— no cambia.)

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ProveedoresTest.php --filter="insumo_guarda_proveedor|ficha_insumo_muestra"`
Expected: FAIL (campos no existen en el form).

- [ ] **Step 4: Implementar**

Agregar al crear/editar de insumo: select `proveedor_id` (opciones `Proveedor::where('activo', true)->orderBy('nombre')->get()`, validación `nullable|exists:proveedores,id`) + input numérico `precio_referencia_mercado` (`nullable|numeric|min:0`) + persistencia en el create/update existente + link "Ver ficha" (`route('proveedores')`) junto al nombre del proveedor en el detalle. Autorización: la existente del método (`update` Insumo).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ProveedoresTest.php --filter="insumo_guarda_proveedor|ficha_insumo_muestra"`
Expected: PASS.

---

### Task 6 (F2): Comparador + KPIs + reporte

**Files:**
- Modify: `app/Services/ProveedorService.php` (+`fichaResumen`, `comparadorInsumo`), `app/Services/CompraService.php` (+`gastoPorProveedor`), `resources/views/livewire/proveedores/index.blade.php` (tab Comparador + KPIs + reporte)
- Test: `tests/Feature/ProveedoresTest.php`

**Interfaces:**
- Consumes: `compra_lineas` + `precio_referencia_mercado` (T1).
- Produces: `fichaResumen(Proveedor $p, string $desde, string $hasta): array`, `comparadorInsumo(int $insumoId): array`, `gastoPorProveedor(string $desde, string $hasta): Collection`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_comparador_detecta_mejor_precio_y_delta_vs_referencia(): void
{
    // Andina vende Salmón a 6000 (ayer), Plaza a 5500 (hoy); referencia 5800.
    // comparadorInsumo → 2 filas; mejor = Plaza 5500; delta Andina = +9.09% vs Plaza? NO:
    // deltas vs REFERENCIA: Andina +3.45%, Plaza -5.17%; mejor precio Plaza.
}

public function test_ficha_resumen_y_gasto_por_proveedor_agregan_en_sql(): void
{
    // 2 facturas Andina (24000+10000) + 1 Plaza (11000) en rango →
    // ficha Andina: total 34000, facturas 2, ticket 17000, cxp_saldo X; gasto: Andina 34000 (75.56%), Plaza 11000.
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ProveedoresTest.php --filter="comparador_detecta|ficha_resumen"`
Expected: FAIL (métodos no existen).

- [ ] **Step 3: Write services + UI**

```php
// CompraService::gastoPorProveedor — agregado SQL, sin materializar filas:
return Compra::where('estado', 'registrada')->whereBetween('fecha', [$desde, $hasta])
    ->selectRaw('proveedor_id, COUNT(*) AS facturas, SUM(subtotal) AS total')
    ->groupBy('proveedor_id')->with('proveedor:id,nombre')->orderByDesc('total')->get();

// ProveedorService::fichaResumen: total/count/avg desde compras registrada en rango (SQL) +
// cxp_saldo = CuentaPorPagar::whereHas compra del proveedor + estado pendiente → sum(saldo) (SQL) +
// participacion = total / total global rango (2 queries) + dias_promedio_pago desde pagos (avg fecha_pago - fecha_emision en días, SQL datediff postgres).
// comparadorInsumo: últimas fechas por proveedor (groupBy MAX) + línea más reciente por (proveedor, fecha) with proveedor;
// mejor = min ultimo_costo; deltas = (costo - referencia)/referencia*100 (referencia null → delta null).
```

UI: tab `comparador` en ficha (selector insumo → tabla proveedor/último costo/fecha/mejor★/delta vs referencia + alerta si último > referencia); header ficha con KPIs (total rango, facturas, ticket, CxP saldo); sección reporte gasto (inputs desde/hasta + tabla proveedor/facturas/total/participación).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ProveedoresTest.php --filter="comparador_detecta|ficha_resumen"`
Expected: PASS.

---

### Task 7: Cierre

**Files:**
- Modify: `coordination.md` (UNA fila append en Historial adiciones, formato existente, fecha hoy, agente OpenCode)

**Interfaces:** ninguna nueva.

- [ ] **Step 1: Suite completa**

Run: `php artisan test`
Expected: PASS; tolerados SOLO fallos pre-existentes documentados en el ledger del workspace. Otro fallo = regresión → corregir antes de reportar.

- [ ] **Step 2: Pint**

Run: `vendor/bin/pint --test app/Models/Proveedor.php app/Models/Compra.php app/Models/CompraLinea.php app/Models/Insumo.php app/Models/CuentaPorPagar.php app/Policies/ProveedorPolicy.php app/Policies/CompraPolicy.php app/Services/ProveedorService.php app/Services/CompraService.php app/Console/Commands/VerificarPermisosCommand.php database/migrations/2026_09_18_11000*.php config/permisos.php tests/Feature/ProveedoresTest.php tests/Feature/PermisosPrivilegiosTest.php`
Expected: PASS (Pint no toca Blade; diffs de blades a ojo).

- [ ] **Step 3: coordination.md**

Append UNA fila: fecha, OpenCode, tarea "Gestión de proveedores F1+F2 (plan 2026-09-18, subagent-driven, suite X/Y)", archivos del plan.
