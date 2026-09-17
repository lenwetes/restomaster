# Plan de Remediación — Auditoría Integral #3 (2026-09-17)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar los bloqueantes (🅲) y altos (🅰) de la re-auditoría integral verificada del 2026-09-17, con tests RED→GREEN, sin romper la suite actual (364/364, 1238 assertions).

**Architecture:** Corrección por capa: (1) infraestructura/secretos, (2) integridad de datos (timestamptz), (3) notificaciones/rendimiento, (4) idempotencia de cobro (dinero), (5) doble conteo COD (dinero), (6) RBAC/IDOR por sucursal, (7) SQL nativo en KPIs, (8) lógica de puntos, (9) consistencia RBAC de turnos, (10) propinas en contabilidad. Cada tarea termina en un commit independiente con su test.

**Tech Stack:** Laravel 13.31, PHP 8.3, PostgreSQL 17, Livewire/Volt 3, PHPUnit 12, Tailwind v3 vía CDN (no build), Blade.

**Spec:** Hallazgos verificados en vivo (chat, auditoría #3) + `coordination.md` entrada 2026-09-17 17:00. Baseline verificado: `php artisan test` → 364 passed · 1238 assertions (≈195 s) · Pint 0 · composer audit 0 · npm audit 0.

## Global Constraints

- **Commits por tarea, mensajes en español, estilo repo** (`fix(sec): ...`, `feat(...): ...`, `test(...): ...`).
- `serializable_classes => false` en `config/cache.php:134` → **NUNCA cachear modelos/colecciones Eloquent**, solo arrays/json planos.
- `app.timezone = America/Bogota` (UTC−5, sin DST). La sesión PostgreSQL actual es GMT → conversiones de fechas deben ser explícitas; nunca asumir tz.
- No definir valores por defecto que sean secretos reales en compose/docs/seeds. `secrets-scan` final sobre el diff → 0.
- Autorización server-side SIEMPRE por rol y sucursal (policy + service), nunca solo UI.

---

### Task 1: Eliminar secreto `SecretResto2026!` de infraestructura y docs + endurecer test

**Contexto:** `origin/main` y `origin/master` (público) contienen `SecretResto2026!` en `compose.yml`, `docker-compose.coolify.yml`, `docker-compose.yml` y `docs/despliegue-coolify.md:46`. El working tree local conserva la fuga en `docker-compose.yml:31/65`. El test `RemediacionInfraSeguridadTest::test_r1` solo busca `sushixpress2026`/`sushixpress_secure_password`/base64 APP_KEY → **no detecta el secreto actual**.

**Files:**
- Modify: `docker-compose.yml:31,65` (quitar default real)
- Modify: `docs/despliegue-coolify.md:44-49` (tabla de variables sin valor real)
- Test: `tests/Feature/RemediacionInfraSeguridadTest.php:25-72`

**Interfaces:**
- Consumes: nada.
- Produces: `docker-compose.yml` sin secretos; test que falla ante `SecretResto2026!` o el patrón `XXpasswordXX!`.

- [ ] **Step 1: Test RED — el escaneo del compose además cubre el secreto vigente y la documentación**

Replace `test_r1_no_existen_secretos_hardcodeados_en_docker_compose` (línea 25) con este set de asserts:

```php
public function test_r1_no_existen_secretos_hardcodeados_en_docker_compose(): void
{
    $archivosCompose = array_merge(
        glob(base_path('compose*.yml')) ?: [],
        glob(base_path('docker-compose.*')) ?: []
    );

    $this->assertNotEmpty($archivosCompose, 'No se encontraron archivos docker-compose en la raíz.');

    $secretosConocidos = [
        'SecretResto2026!',
        'sushixpress2026',
        'sushixpress_secure_password',
        'base64:ryJ8oRftsst90c9',
    ];

    foreach ($archivosCompose as $archivo) {
        $contenido = File::get($archivo);

        foreach ($secretosConocidos as $secreto) {
            $this->assertStringNotContainsString(
                $secreto,
                $contenido,
                "Secreto comprometido '{$secreto}' presente en {$archivo}. Usar \${DB_PASSWORD} sin default real."
            );
        }

        // Auto-seed habilitado por defecto (R2)
        $this->assertStringNotContainsString('AUTO_SEED:-true', $contenido, "AUTO_SEED=true por defecto en {$archivo}.");
        // APP_DEBUG en producción por defecto (R1)
        $this->assertStringNotContainsString('APP_DEBUG:-true', $contenido, "APP_DEBUG=true por defecto en {$archivo}.");
    }

    // La wiki de despliegue no debe publicar el valor real de la contraseña
    $despliegue = File::get(base_path('docs/despliegue-coolify.md'));
    foreach ($secretosConocidos as $secreto) {
        $this->assertStringNotContainsString($secreto, $despliegue, "Secreto filtrado en docs/despliegue-coolify.md.");
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=RemediacionInfraSeguridadTest`
Expected: FAIL — "Secreto comprometido 'SecretResto2026!' presente en docker-compose.yml" y en docs/despliegue-coolify.md.

- [ ] **Step 3: Implementación mínima**

```bash
# docker-compose.yml:31 y :65 → eliminar el default real
```

Reemplazar en `docker-compose.yml`:
- L31: `DB_PASSWORD: "${DB_PASSWORD:-SecretResto2026!}"` → `DB_PASSWORD: "${DB_PASSWORD}"`
- L65: `POSTGRES_PASSWORD: "${DB_PASSWORD:-SecretResto2026!}"` → `POSTGRES_PASSWORD: "${DB_PASSWORD}"`

En `docs/despliegue-coolify.md` (tabla ~L46): reemplazar el valor por placeholder:
```markdown
| `DB_PASSWORD` | *(generar con `openssl rand -base64 42`)* | Contraseña de base de datos — obligatoria, sin valor por defecto |
```

- [ ] **Step 4: Ejecutar y verificar que pasa**

Run: `php artisan test --filter=RemediacionInfraSeguridadTest`
Expected: PASS (los otros asserts de test_r1 y r2/r3 intactos).

- [ ] **Step 5: Rotación real (operación manual, fuera de código)**

El secreto está en el historial de ramas públicas (origin/main y origin/master). La contraseña **debe rotarse** en cualquier despliegue que haya usado compose con esos defaults, y considerar reescribir el historial (`git filter-repo`) o el secreto como comprometido para siempre (CWE-798). Esto queda documentado en `coordination.md`, no se automatiza en este plan.

- [ ] **Step 6: Commit**

```bash
git add docker-compose.yml docs/despliegue-coolify.md tests/Feature/RemediacionInfraSeguridadTest.php
git commit -m "fix(sec): eliminar DB_PASSWORD real del compose y wiki, endurecer test_r1 contra SecretResto2026!"
```

---

### Task 2: Migración correctiva `timestamptz` (+5h) y fijar zona horaria de la conexión

**Contexto:** La migración `2026_09_17_210000` convirtió 6 tablas a `timestamptz` con `USING col AT TIME ZONE 'UTC'`, pero los valores fueron escritos por Laravel en `America/Bogota` (UTC−5). Resultado verificado: instantes de pedidos/turnos/movimientos/asientos/items/auditorias desplazados **−5 horas**. Además el session timezone de PG es GMT, por lo que **escrituras nuevas** a esas columnas interpretarían el string local como UTC y seguirían −5h. Fix: (1) `config/database.php` fuerza session tz a Bogota (soportado por `PostgresConnector:156-159`), (2) migración correctiva `+ INTERVAL '5 hours'` sobre las 6 tablas, (3) no tocar las ~25 tablas restantes que siguen con `timestamp without time zone` (correctas).

**Files:**
- Modify: `config/database.php` (bloque `pgsql`)
- Create: `database/migrations/2026_09_17_220000_fix_timestamptz_bogota_offset.php`
- Test: `tests/Feature/RemediacionDineroTurnosTest.php:164-187` (nuevo assert de tz)

**Interfaces:**
- Consumes: nada.
- Produces: columna `actualizacion` de tipo `array` de tabla→columnas en la nueva migración; conexión `pgsql` con clave `timezone`.

- [ ] **Step 1: Test RED — la conexión pgsql declara timezone Bogota y el cast de prueba no desvía fechas**

Añadir a `tests/Feature/RemediacionDineroTurnosTest.php`:

```php
use Illuminate\Support\Facades\DB;

public function test_connection_pgsql_fija_timezone_america_bogota(): void
{
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requiere PostgreSQL.');
    }

    $timezone = config('database.connections.pgsql.timezone');
    $this->assertSame('America/Bogota', $timezone, 'La conexión debe fijar timezone America/Bogota (UTC-5) para lecturas/escrituras consistentes de timestamptz.');
    $this->assertSame('America/Bogota', DB::selectOne('SHOW TIME ZONE')->TimeZone);
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=test_connection_pgsql_fija_timezone_america_bogota`
Expected: FAIL — `SHOW TIME ZONE` = estático (local "GMT") y `config` posiblemente null o "UTC".

- [ ] **Step 3: Implementación — timezone en configuración y migración correctiva**

En `config/database.php`, dentro de `'connections' => ['pgsql' => [ ... ]]` añadir la clave (junto a `sslmode` existente):

```php
'timezone' => env('DB_TIMEZONE', 'America/Bogota'),
```

Nueva migración `database/migrations/2026_09_17_220000_fix_timestamptz_bogota_offset.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las 6 tablas que la migración 2026_09_17_210000 convirtió con AT TIME ZONE 'UTC',
     * cuando Laravel escribió los valores en America/Bogota (UTC-5).
     */
    protected array $tablas = [
        'pedidos' => ['pagado_en', 'hora_despacho', 'hora_entrega', 'created_at', 'updated_at'],
        'turnos_caja' => ['apertura_en', 'cierre_en', 'created_at', 'updated_at'],
        'movimientos_caja' => ['created_at', 'updated_at'],
        'asientos_contables' => ['created_at', 'updated_at'],
        'items_pedido' => ['iniciado_en', 'listo_en', 'created_at', 'updated_at'],
        'auditorias' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tablas as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach ($columnas as $columna) {
                if (Schema::hasColumn($tabla, $columna)) {
                    $col = '"'.$tabla.'"."'.$columna.'"';
                    DB::statement("UPDATE $tabla SET {$columna} = ({$col}) + INTERVAL '5 hours' WHERE {$columna} IS NOT NULL");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tablas as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach ($columnas as $columna) {
                if (Schema::hasColumn($tabla, $columna)) {
                    $col = '"'.$tabla.'"."'.$columna.'"';
                    DB::statement("UPDATE $tabla SET {$columna} = ({$col}) - INTERVAL '5 hours' WHERE {$columna} IS NOT NULL");
                }
            }
        }
    }
};
```

**Nota crítica:** `$columna` se interpola de un array fijo hardcodeado (sin input externo), por lo que no hay inyección SQL; se mantiene `DB::statement` por consistencia con la migración original.

- [ ] **Step 4: Validación de datos en vivo (operativa, antes de aplicar en producción)**

```sql
-- Spot-check: pedidos de hoy deben caer en horario comercial local (Bogota)
SELECT id, codigo, pagado_en,
       pagado_en AT TIME ZONE 'America/Bogota' AS pago_bogota
FROM pedidos
WHERE pagado_en IS NOT NULL
ORDER BY pagado_en DESC
LIMIT 10;

-- Verificar que tras la corrección no quedan horas > 23:59 ni desfases de 5h
SELECT (pagado_en - (pagado_en AT TIME ZONE 'America/Bogota')) AS diff
FROM pedidos WHERE pagado_en IS NOT NULL LIMIT 1;
-- diff debe ser 00:00 tras corregir
```

- [ ] **Step 5: Ejecutar y verificar que pasa**

Run: `php artisan test --filter=test_connection_pgsql_fija_timezone_america_bogota`
Expected: PASS (config `America/Bogota` y `SHOW TIME ZONE` = `America/Bogota`). Como la migración corre con la nueva conexión, los valores nuevos se interpretan correctamente.

- [ ] **Step 6: Commit**

```bash
git add config/database.php database/migrations/2026_09_17_220000_fix_timestamptz_bogota_offset.php tests/Feature/RemediacionDineroTurnosTest.php
git commit -m "fix(db): corregir offset -5h del timestamptz (AT TIME ZONE UTC) y fijar timezone America/Bogota en conexion"
```

---

### Task 3: Notificaciones — cache de resultados planos + poll visible de 30s

**Contexto:** `navigation.blade.php:124` tiene `wire:poll.15s` global; cada ciclo ejecuta `NotificacionService::obtenerResumen` (4 queries + N+1 `.with`) por cada usuario autenticado. El estado actual de `NotificacionService` NO cachea (el hallazgo R2 de la pre-commit quedó invalidado por el commit final). Con `serializable_classes=false` la solución es cachear **arrays planos** con TTL ≥ intervalo de poll, y reducir el poll a `.30s.visible` (solo cuando el dropdown está en viewport).

**Files:**
- Modify: `app/Services/NotificacionService.php:17-79`
- Modify: `resources/views/livewire/layout/navigation.blade.php:124`
- Test: `tests/Feature/NotificacionServiceCacheTest.php` (nuevo)

**Interfaces:**
- Consumes: nada.
- Produces: `obtenerResumen(?User $usuario = null): array` con clave de cache por usuario; `resources/views/livewire/layout/navigation.blade.php` con `wire:poll.30s.visible`.

- [ ] **Step 1: Test RED — la caché almacena arrays planos y respeta el rol**

```php
<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\NotificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NotificacionServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_obtener_resumen_cachea_y_devuelve_array_plano(): void
    {
        Cache::spy();

        $role = Role::create(['nombre' => 'Cocina', 'slug' => 'cocina', 'descripcion' => 'Cocina']);
        $sucursal = Sucursal::create(['nombre' => 'S1', 'codigo' => 'S1', 'direccion' => 'x', 'activa' => true]);
        $usuario = User::create([
            'name' => 'Chef',
            'email' => 'chef@test.com',
            'password' => bcrypt('clave-segura'),
            'role_id' => $role->id,
            'sucursal_id' => $sucursal->id,
            'activo' => true,
        ]);

        app(NotificacionService::class)->obtenerResumen($usuario);

        // La clave de caché por usuario debe consultarse
        Cache::shouldHaveReceived('remember')
            ->withArgs(fn ($clave) => str_contains($clave, 'notif.resumen.') && str_contains($clave, (string) $usuario->id))
            ->once();
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=NotificacionServiceCacheTest`
Expected: FAIL — `remember` nunca llamado (no hay caché).

- [ ] **Step 3: Implementación**

En `app/Services/NotificacionService.php`:

```php
use Illuminate\Support\Facades\Cache;

public function obtenerResumen(?User $usuario = null): array
{
    $usuario = $usuario ?? auth()->user();
    $clave = 'notif.resumen.'.($usuario?->id ?? 'anon');

    return Cache::remember($clave, now()->addSeconds(15), function () use ($usuario) {
        return $this->consultarResumen($usuario);
    });
}
```

Y convertir `consultarResumen` para que devuelva **arrays planos** en vez de colecciones (cumple `serializable_classes=false`). En el `return` final:

```php
return [
    'total' => $total,
    'pedidos_qr' => $pedidosQr->map(fn ($p) => [
        'id' => $p->id,
        'codigo' => $p->codigo,
        'mesa_numero' => $p->mesa?->numero,
        'mesa_zona' => $p->mesa?->zona,
        'usuario_id' => $p->usuario_id,
    ])->values()->all(),
    'platos_listos' => $platosListos->map(fn ($i) => [
        'id' => $i->id,
        'listo_en' => $i->listo_en?->toIso8601String(),
        'mesa_numero' => $i->pedido?->mesa?->numero,
        'producto' => $i->producto?->nombre,
        'pedido_codigo' => $i->pedido?->codigo,
    ])->values()->all(),
    'stock_critico' => $stockCritico->map(fn ($i) => [
        'id' => $i->id,
        'nombre' => $i->nombre,
        'stock_actual' => $i->stock_actual,
        'stock_minimo' => $i->stock_minimo,
    ])->values()->all(),
    'reservas_hoy' => $reservasHoy->map(fn ($r) => [
        'id' => $r->id,
        'nombre_contacto' => $r->nombre_contacto,
        'hora_llegada' => $r->hora_llegada,
        'personas' => $r->personas,
        'estado' => $r->estado,
    ])->values()->all(),
    'rol_consultado' => $rol,
];
```

En `resources/views/livewire/layout/navigation.blade.php:124`:

```blade
<div class="relative" x-data="{ openNotif: false }" wire:poll.30s.visible>
```

(Si la vista renderiza directamente `notificaciones['pedidos_qr']` con `->numero`/`->nombre`, los `foreach` del bloque de notificaciones deben adaptarse a las claves planas `mesa_numero`, `producto`, etc. — revisar el bloque `@if($total > 0 ...)` más abajo en la misma vista.)

- [ ] **Step 4: Correr y verificar que pasa + suite completa no rompe la vista**

Run: `php artisan test --filter=NotificacionServiceCacheTest && php artisan view:cache`
Expected: PASS + compilación de vistas OK.

- [ ] **Step 5: Commit**

```bash
git add app/Services/NotificacionService.php resources/views/livewire/layout/navigation.blade.php tests/Feature/NotificacionServiceCacheTest.php
git commit -m "fix(perf): cachear resumen de notificaciones como arrays planos y reducir poll a 30s visible"
```

---

### Task 4: Idempotencia de cobro — doble-fire de `procesarCobro` no crea 2º pedido

**Contexto:** `terminal.blade.php:672-792`. Si `procesarCobro()` se dispara dos veces (doble tap, reintento de red, dos pestañas), en la rama de **nuevo pedido** (L727-753) se crea un segundo `Pedido` y se cobra de nuevo → doble cargo al cliente + doble asiento + doble canje de puntos. Fix: columna `idempotencia_uuid` única en `pedidos`; el pedido nacido dentro del intento se marca con esa clave; `procesarCobro` verifica antes de crear.

**Files:**
- Modify: `app/Models/Pedido.php` (fillable + cast)
- Create: `database/migrations/2026_09_17_221000_add_idempotencia_uuid_to_pedidos.php`
- Modify: `app/Services/PedidoService.php:23-140` (`crearPedido` acepta `idempotencia_uuid` y reutiliza)
- Modify: `resources/views/livewire/pos/terminal.blade.php:672-753`
- Test: `tests/Feature/RemediacionDineroTurnosTest.php` (nuevo test); `tests/Feature/AuditoriaLote8HigieneTest.php` no cambia

**Interfaces:**
- Consumes: `PedidoService::crearPedido(array $datos, array $items, ?User $usuario = null): Pedido` (extiende `$datos`).
- Produces: `crearPedido` devuelve el pedido existente si `$datos['idempotencia_uuid']` ya tiene uno; `procesarCobro` genera `(string) Str::uuid()` al cargar el componente.

- [ ] **Step 1: Test RED — doble creación con misma idempotencia_uuid no duplica pedido**

En `tests/Feature/RemediacionDineroTurnosTest.php`:

```php
public function test_crear_pedido_con_misma_idempotencia_uuid_no_duplica(): void
{
    $s = $this->crearSucursal('SID*');
    $user = $this->crearUsuario('admin', $s->id);
    $uuid = (string) \Illuminate\Support\Str::uuid();

    $menu = app(MenuService::class);
    $categoria = $menu->crearCategoria(['nombre' => 'P', 'icono' => '🍜', 'orden' => 1, 'activo' => true]);
    $producto = $menu->crearProducto([
        'categoria_id' => $categoria->id,
        'nombre' => 'Ramen',
        'precio' => 30000.00,
        'costo' => 8000.00,
        'area_cocina' => 'cocina',
        'activo' => true,
    ]);
    $items = [['producto_id' => $producto->id, 'cantidad' => 1]];

    $primero = app(PedidoService::class)->crearPedido(
        ['tipo' => 'mesa', 'sucursal_id' => $s->id, 'idempotencia_uuid' => $uuid],
        $items,
        $user
    );

    $segundo = app(PedidoService::class)->crearPedido(
        ['tipo' => 'mesa', 'sucursal_id' => $s->id, 'idempotencia_uuid' => $uuid],
        $items,
        $user
    );

    $this->assertSame($primero->id, $segundo->id, 'El reintento con la misma clave debe reutilizar el pedido.');
    $this->assertSame(1, Pedido::where('idempotencia_uuid', $uuid)->count());
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=test_crear_pedido_con_misma_idempotencia_uuid_no_duplica`
Expected: FAIL — se crean 2 pedidos.

- [ ] **Step 3: Implementación**

Migración `database/migrations/2026_09_17_221000_add_idempotencia_uuid_to_pedidos.php`:

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
            $table->uuid('idempotencia_uuid')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropUnique(['idempotencia_uuid']);
            $table->dropColumn('idempotencia_uuid');
        });
    }
};
```

`app/Models/Pedido.php` añadir a `$fillable` el campo `idempotencia_uuid`.

En `PedidoService::crearPedido` (L23-28), dentro del `DB::transaction`, antes de generar el código:

```php
$idempotenciaUuid = $datos['idempotencia_uuid'] ?? null;
if ($idempotenciaUuid) {
    $existente = Pedido::where('idempotencia_uuid', $idempotenciaUuid)->first();
    if ($existente) {
        return $existente->fresh(['items', 'mesa']);
    }
}
```

Y al `Pedido::create([...])` añadir `'idempotencia_uuid' => $idempotenciaUuid`.

En `terminal.blade.php`: dentro de `procesarCobro()`, antes de la rama `if ($this->descuento > 0)`, añadir guard de UI + generar clave persistente en la clase:

```php
if (empty($this->idempotenciaUuid)) {
    $this->idempotenciaUuid = (string) Str::uuid();
}
```

Y en la rama de **nuevo pedido** (L737 `crearPedido(...)`), pasar `'idempotencia_uuid' => $this->idempotenciaUuid`. Para la rama de pedido existente (mesa con pedido activo) se reutiliza el pedido existente por diseño (R5/FIXED) — no se toca.

- [ ] **Step 4: Verificar que pasa + regenerar uuid tras cobro exitoso**

Run: `php artisan test --filter=test_crear_pedido_con_misma_idempotencia_uuid_no_duplica`
Expected: PASS.

Además, tras `cobrarPedido()` exitoso en `procesarCobro` (L780-787) resetear para que la próxima venta no reutilice: `$this->idempotenciaUuid = null;` antes de `$this->limpiarCarrito();`. (Regresión lógica de puntos D7 ya corregida, no alterar.)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_17_221000_add_idempotencia_uuid_to_pedidos.php app/Models/Pedido.php app/Services/PedidoService.php resources/views/livewire/pos/terminal.blade.php tests/Feature/RemediacionDineroTurnosTest.php
git commit -m "fix(money): idempotencia_uuid en pedidos impide doble-fire de procesarCobro (doble cobro)"
```

---

### Task 5: H1 — doble conteo COD entre `marcarEntregado` y `liquidarRecaudoRepartidor`

**Contexto:** `DeliveryService::marcarEntregado:123-171` (cobro contra entrega) ya vincula el cobro al turno vía `vincularCobroPedido` → suma a `total_ventas_efectivo` y crea asiento. Luego `liquidarRecaudoRepartidor:176-217` vuelve a registrar `ingreso` del mismo total → **el monto esperado de la caja cuenta el mismo efectivo 2 veces** y hay doble asiento (una vez "Venta POS" y otra "Movimiento ingreso"). El intento previo de fix solo cambió un comentario (`:153`). Fix: la liquidación del repartidor NO registra un segundo movimiento de caja; solo marca `recaudo_liquidado=true` (policy `liquidarRepartidor` ya autoriza cajero/gerente/admin/repartidor propio).

**Files:**
- Modify: `app/Services/DeliveryService.php:176-217`
- Test: `tests/Feature/RemediacionDineroTurnosTest.php` (nuevos asserts en test existente o test nuevo)

**Interfaces:**
- Consumes: `CajaService::vincularCobroPedido`, `FidelizacionService::acumularPuntosPorPedido`, `InventarioService::descontarPorPedido`.
- Produces: `liquidarRecaudoRepartidor(User $repartidor, TurnoCaja $turno): float` devuelve el total recaudado **sin** crear movimiento/asiento adicional.

- [ ] **Step 1: Test RED — liquidar no duplica asientos ni el monto esperado**

```php
public function test_liquidar_recaudo_no_duplica_asientos_ni_esperado(): void
{
    $s = $this->crearSucursal('SCOD');
    $user = $this->crearUsuario('admin', $s->id);
    $turno = $this->abrirTurnoEn($s->id, $user);

    $cliente = Cliente::create(['nombre' => 'COD', 'telefono' => '3007778899', 'activo' => true]);

    $menu = app(MenuService::class);
    $categoria = $menu->crearCategoria(['nombre' => 'Y', 'icono' => '🍥', 'orden' => 1, 'activo' => true]);
    $producto = $menu->crearProducto([
        'categoria_id' => $categoria->id,
        'nombre' => 'Delivery Item',
        'precio' => 60000.00,
        'costo' => 15000.00,
        'area_cocina' => 'sushi',
        'activo' => true,
    ]);

    $pedido = app(DeliveryService::class)->crearPedidoDelivery([
        'sucursal_id' => $s->id,
        'user_id' => $user->id,
        'cliente_id' => $cliente->id,
        'estado_delivery' => 'en_ruta',
        'costo_envio' => 6000.00,
    ]);
    $pedido->update(['sucursal_id' => $s->id, 'repartidor_id' => $user->id, 'estado_delivery' => 'entregado']);
    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'nombre_producto' => $producto->nombre,
        'cantidad' => 1,
        'precio_unitario' => 60000.00,
        'subtotal' => 60000.00,
        'area_cocina' => 'sushi',
        'estado_cocina' => 'listo',
    ]);
    $pedido->recalcularTotales();
    $pedido->refresh();

    $entregado = app(DeliveryService::class)->marcarEntregado($pedido, 'efectivo', (float) $pedido->total);
    $entregado->refresh();
    $asientosAntes = AsientoContable::where('referencia_tipo', 'pedido')->where('referencia_id', $pedido->id)->count();

    $totalLiquidado = app(DeliveryService::class)->liquidarRecaudoRepartidor($user, $turno);

    $this->assertSame((float) $pedido->total, (float) $totalLiquidado);
    $this->assertSame($asientosAntes, AsientoContable::where('referencia_tipo', 'pedido')->where('referencia_id', $pedido->id)->count(),
        'La liquidación no debe sumar asientos de venta (doble conteo COD).');
    $this->assertSame(0, AsientoContable::where('concepto', 'like', '%Liquidación recaudo delivery%')->count(),
        'No debe crearse un movimiento ingreso extra por el recaudo ya contado como venta.');

    $turno->refresh();
    $this->assertSame((float) $pedido->total, (float) $turno->total_ventas_efectivo,
        'El efectivo del COD se cuenta una sola vez en el turno.');
    $this->assertTrue($entregado->recaudo_liquidado);
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=test_liquidar_recaudo_no_duplica_asientos_ni_esperado`
Expected: FAIL — se crea movimiento 'ingreso' de recaudo (2º conteo / asiento extra).

- [ ] **Step 3: Implementación mínima**

En `DeliveryService::liquidarRecaudoRepartidor`, reemplazar el bloque L200-213 de `if ($totalRecaudado > 0) { ... registrarMovimiento(...); }` por solo retorno del total (mantener consulta, `lockForUpdate`, `update recaudo_liquidado=true`):

```php
if ($totalRecaudado <= 0) {
    return 0.0;
}

app(AuditoriaService::class)->registrar(
    usuario: auth()->user(),
    accion: 'delivery.recaudo_liquidado',
    entidad: 'delivery',
    entidadId: $repartidor->id,
    descripcion: "Recaudo delivery liquidado: {$repartidor->name} · {$updatedCount} pedidos · \$".number_format($totalRecaudado, 2),
    datos: ['pedidos' => $pedidoIds, 'total' => $totalRecaudado],
);

return $totalRecaudado;
```

(Verificar que `Auth::user()` pueda ser null en CLI/imports — usar `auth()->user()` opcional si el componente llama con sesión; si no hay usuario, pasar null igual que hace `registrarMovimiento`.)

- [ ] **Step 4: Ejecutar y verificar que pasa**

Run: `php artisan test --filter=test_liquidar_recaudo_no_duplica_asientos_ni_esperado && php artisan test --filter=RemediacionDineroTurnosTest`
Expected: PASS — nuevo test y los 12 tests de turnos (incluidos `test_marcar_entregado_no_reeistra_un_pedido_ya_pagado` y `test_cobro_contra_entrega_se_vincula_al_turno_de_la_sucursal`).

- [ ] **Step 5: Commit**

```bash
git add app/Services/DeliveryService.php tests/Feature/RemediacionDineroTurnosTest.php
git commit -m "fix(money): H1 COD - liquidarRecaudoRepartidor ya no duplica ventas/asientos (era 2x en monto esperado)"
```

---

### Task 6: RBAC/IDOR por sucursal — QR, reservas y caja

**Contexto (verificados):** `atenderPedidoQrActual` (`terminal.blade.php:810-834`) filtra por `mesa_id` y `canal_origen` SIN sucursal; `asignarMeseroAPedidoQr` (`PedidoService:308-350`) valida rol y concurrencia pero **no** la sucursal → un mesero de sucursal B puede tomar pedidos QR de A (IDOR horizontal). `ReservaService::verificarDisponibilidad` (L18-47) cruza sucursales (overbooking/choque de mesas de otras sucursales), y `crear`/`confirmar` no fijan sucursal. `caja/control:214` y `terminal:634` usan `Caja::findOrFail` sin sucursal.

**Files:**
- Modify: `resources/views/livewire/pos/terminal.blade.php:810-834`
- Modify: `app/Services/PedidoService.php:308-350`
- Modify: `app/Services/ReservaService.php:18-92,94-150`
- Modify: `resources/views/livewire/caja/control.blade.php:~214` y `terminal.blade.php:~634` (a verificar línea exacta al editar)
- Test: `tests/Feature/AuditoriaLote8HigieneTest.php` (extender con test de sucursal) o test nuevo `tests/Feature/RbacSucursalTest.php`

**Interfaces:**
- Consumes: `User::sucursal_id`, `Pedido::sucursal_id`, `Mesa::sucursal_id`, `Reserva::sucursal_id`.
- Produces: `asignarMeseroAPedidoQr` lanza `AuthorizationException` si el pedido es de otra sucursal; `verificarDisponibilidad` acepta `?int $sucursalId = null` y filtra mesas.

- [ ] **Step 1: Test RED — mesero no puede asignar/atender pedido QR de otra sucursal, reservas por sucursal**

```php
public function test_mesero_no_puede_asignar_pedido_qr_de_otra_sucursal(): void
{
    $sA = $this->crearSucursal('QRA');
    $sB = $this->crearSucursal('QRB');
    $meseroA = $this->crearUsuario('mesero', $sA->id);
    $meseroB = $this->crearUsuario('mesero', $sB->id);

    $mesa = Mesa::create(['numero' => '1', 'zona' => 'salon', 'capacidad' => 4, 'sucursal_id' => $sB->id, 'estado' => 'libre']);

    $menu = app(MenuService::class);
    $categoria = $menu->crearCategoria(['nombre' => 'Q', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
    $producto = $menu->crearProducto([
        'categoria_id' => $categoria->id,
        'nombre' => 'Qr Item',
        'precio' => 10000.00,
        'costo' => 3000.00,
        'area_cocina' => 'sushi',
        'activo' => true,
    ]);
    $pedidoQr = app(PedidoService::class)->crearPedido([
        'tipo' => 'mesa',
        'mesa_id' => $mesa->id,
        'sucursal_id' => $sB->id,
        'canal_origen' => 'qr_mesa',
        'estado' => 'solicitado_qr',
        'usuario_id' => null,
    ], [['producto_id' => $producto->id, 'cantidad' => 1]]);

    $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
    app(PedidoService::class)->asignarMeseroAPedidoQr($pedidoQr->id, $meseroA);
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=test_mesero_no_puede_asignar_pedido_qr_de_otra_sucursal`
Expected: FAIL — no lanza excepción (asigna cross-sucursal).

- [ ] **Step 3: Implementación**

En `PedidoService::asignarMeseroAPedidoQr`, dentro del transaction tras el `firstOrFail`:

```php
if ($mesero->sucursal_id && $pedido->sucursal_id && $pedido->sucursal_id !== $mesero->sucursal_id) {
    throw new AuthorizationException('No puede atender pedidos de otra sucursal.');
}
```

En `terminal.blade.php::atenderPedidoQrActual`, añadir filtro por sucursal al query (L816-821):

```php
$sucursalId = auth()->user()?->sucursal_id;
$pedido = Pedido::where('mesa_id', $this->mesaId)
    ->where('canal_origen', 'qr_mesa')
    ->where('estado', 'solicitado_qr')
    ->whereNull('usuario_id')
    ->when($sucursalId, function ($q) use ($sucursalId) {
        $q->whereHas('mesa', fn ($m) => $m->where('sucursal_id', $sucursalId));
    })
    ->latest()
    ->first();
```

En `ReservaService::verificarDisponibilidad`, añadir param `?int $sucursalId = null` y filtrar mesas:

```php
public function verificarDisponibilidad(string $fecha, string $horaInicio, int $personas, int $duracionMin = 120, ?int $sucursalId = null): Collection
{
    ...
    $query = Mesa::query()
        ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
        ->whereNotIn('id', $mesasBloqueadas)
        ->whereNotIn('estado', [MesaEstado::OCUPADA->value, MesaEstado::POR_LIMPIAR->value]);
    ...
}
```

En `ReservaService::crear` (L61-77): forzar sucursal desde usuario autenticado cuando no se pasa o validar contra ella:

```php
$sucursalId = (int) ($datos['sucursal_id'] ?? auth()->user()?->sucursal_id);
if ($sucursalId === 0) {
    throw new InvalidArgumentException('Debe indicarse la sucursal de la reserva.');
}
if (auth()->user()?->sucursal_id && $sucursalId !== auth()->user()->sucursal_id) {
    throw new AuthorizationException('No puede crear reservas en otra sucursal.');
}
// usar $sucursalId en Reserva::create
```

En `ReservaService::confirmar` (L96-107): filtrar `Mesa::whereIn('id', $mesaIds)` por `where('sucursal_id', $reservaLocked->sucursal_id)` y validar solapes por la MISMA sucursal en `validarMesasParaConfirmar`.

En `caja/control.blade.php:~214` y `terminal.blade.php:~634` (`Caja::findOrFail(...)`): anteponer scoping:

```php
$caja = Caja::where('sucursal_id', auth()->user()?->sucursal_id)->findOrFail($cajaId);
```

- [ ] **Step 4: Ejecutar y verificar que pasa**

Run: `php artisan test --filter=test_mesero_no_puede_asignar_pedido_qr_de_otra_sucursal && php artisan test --filter=AuditoriaLote8HigieneTest`
Expected: PASS. Más `php artisan test --filter=Reserva` (Suite reservas existente) para regresión.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PedidoService.php app/Services/ReservaService.php resources/views/livewire/pos/terminal.blade.php resources/views/livewire/caja/control.blade.php tests/Feature/RbacSucursalTest.php
git commit -m "fix(authz): IDOR por sucursal en QR, reservas y caja (scope sucursal en servicio y políticas)"
```

---

### Task 7: KPIs — `picosPorHora` en SQL y `whereDate` → `whereBetween`

**Contexto:** `ReporteService::kpisRealtime` (L84-151): `whereDate('pagado_en', $hoy)` (L90, L98, L121) impide uso de índice; `picosPorHora` (L112-118) materializa `get()->groupBy` en PHP (todas las filas de pedidos del día). Fix: `whereBetween('pagado_en', [$hoy.' 00:00:00', $hoy.' 23:59:59'])` y agrupar en SQL con `to_char(pagado_en AT TIME ZONE 'America/Bogota','HH24')`.

**Files:**
- Modify: `app/Services/ReporteService.php:84-151`
- Test: `tests/Feature/ReporteKpisTest.php` (nuevo)

**Interfaces:**
- Consumes: `Pedido` con `estado=pagado`, `pagado_en` timestamptz.
- Produces: `kpisRealtime(?int $sucursalId = null): array` con `picos_por_hora` como `array<int, int>` array keyed by hora `'HH'` → contador.

- [ ] **Step 1: Test RED — KPIs respetan hora local Bogota y no explotan con pedidos de horas distintas**

```php
public function test_picos_por_hora_agrupa_por_hora_local(): void
{
    $s = Sucursal::create(['nombre' => 'KPI', 'codigo' => 'KPI', 'direccion' => 'x', 'activa' => true]);
    $admin = User::create(['name' => 'K', 'email' => 'k@t.com', 'password' => bcrypt('clave-segura-1'), 'role_id' => Role::create(['nombre' => 'A', 'slug' => 'admin', 'descripcion' => 'A'])->id, 'sucursal_id' => $s->id, 'activo' => true]);

    $menu = app(MenuService::class);
    $cat = $menu->crearCategoria(['nombre' => 'K', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
    $prod = $menu->crearProducto(['categoria_id' => $cat->id, 'nombre' => 'K', 'precio' => 1000, 'costo' => 100, 'area_cocina' => 'sushi', 'activo' => true]);

    // dos pedidos a las 09:00 y uno a las 21:00 hora Bogota
    foreach ([9, 21] as $hora) {
        $p = app(PedidoService::class)->crearPedido(
            ['tipo' => 'mesa', 'sucursal_id' => $s->id],
            [['producto_id' => $prod->id, 'cantidad' => 1]],
            $admin
        );
        $p->update(['estado' => 'pagado', 'pagado_en' => now()->startOfDay()->addHours($hora)]);
    }
    app(PedidoService::class)->crearPedido(
        ['tipo' => 'mesa', 'sucursal_id' => $s->id],
        [['producto_id' => $prod->id, 'cantidad' => 1]],
        $admin
    )->update(['estado' => 'pagado', 'pagado_en' => now()->startOfDay()->addHours(9)]);

    $kpis = app(ReporteService::class)->kpisRealtime($s->id);

    // 09 y 21 locales = 2 y 1 respectivamente (no confundir con UTC: 14/02)
    $this->assertSame(3, $kpis['transacciones_dia']);
    $horas = array_map('intval', array_keys($kpis['picos_por_hora']));
    sort($horas);
    $this->assertSame([9, 21], array_values($horas));
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=test_picos_por_hora_agrupa_por_hora_local`
Expected: FAIL o mapeo de horas a UTC (14/02) si el runner está en otra zona; de cualquier modo el assert de horas exactas debe fallar si no se fuerza hora local.

**Nota:** Los tests corren con `config('app.timezone')=America/Bogota`; al crear con `now()->startOfDay()->addHours(9)` el valor es coherente. Verificar que `pagado_en` se guarda como timestamptz correcto.

- [ ] **Step 3: Implementación**

En `ReporteService::kpisRealtime`:

```php
$hoyInicio = $hoy.' 00:00:00';
$hoyFin = $hoy.' 23:59:59';

$pedidosQuery = Pedido::query()
    ->where('estado', 'pagado')
    ->whereBetween('pagado_en', [$hoyInicio, $hoyFin])
    ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId));
```

Reemplazar `whereDate('pagado_en', $hoy)` en L98 y L121 por `whereBetween` equivalente (clonando `$pedidosQuery` en L98 para `$costoVendido` y `topProductos`). Reemplazar `picosPorHora` (L112-118):

```php
$picosPorHora = (clone $pedidosQuery)
    ->selectRaw("to_char(pagado_en AT TIME ZONE 'America/Bogota', 'HH24') as hora, count(*) as total")
    ->groupByRaw("to_char(pagado_en AT TIME ZONE 'America/Bogota', 'HH24')")
    ->orderByDesc('total')
    ->limit(6)
    ->get()
    ->mapWithKeys(fn ($r) => [((int) $r->hora) => (int) $r->total])
    ->sortKeysDesc();
```

**Nota:** `to_char(... 'HH24')` es "hora local Bogota" explícita; el `whereBetween` usa strings locales consistentes con la conexión ahora en Bogota (Task 2). Si se prefiere timeout-safe, usar `whereRaw("pagado_en >= ?::timestamptz", [$hoyInicio])`.

- [ ] **Step 4: Ejecutar y verificar que pasa**

Run: `php artisan test --filter=ReporteKpisTest && php artisan test --filter=Reporte`
Expected: PASS (nuevo + regresión de reportes existentes).

- [ ] **Step 5: Commit**

```bash
git add app/Services/ReporteService.php tests/Feature/ReporteKpisTest.php
git commit -m "perf(kpis): picosPorHora y top en SQL nativo con hora local; whereDate -> whereBetween (indice)"
```

---

### Task 8: Lógica de puntos — `descuentoPuntos` sin canje no debe aplicar

**Contexto:** `PedidoService::crearPedido` (L74-92): si `$descuentoPuntos > 0` pero `$puntosCanjeados === 0` y hay cliente con puntos, se aplica `$descuentoPuntosAplicado = min($remanente, $descuentoPuntos)` (L119) **sin canjear puntos** → descuento gratis. Fix: exigir `$puntosCanjeados >= 1` y `$clienteId` para aplicar cualquier `descuento_puntos`.

**Files:**
- Modify: `app/Services/PedidoService.php:74-92,116-120`
- Test: `tests/Feature/AuditoriaLote8HigieneTest.php` o nuevo `tests/Feature/PuntosFidelidadServiceTest.php`

**Interfaces:**
- Consumes: `FidelizacionService::calcularDescuentoPorPuntos(int)`.
- Produces: `crearPedido` lanza `InvalidArgumentException` si `descuento_puntos>0` sin `puntos_canjeados`, o resetea `descuento_puntos` a 0.

- [ ] **Step 1: Test RED — descuentoPuntos sin puntos canjeados no aplica**

```php
public function test_descuento_puntos_sin_canje_rechazado(): void
{
    $s = $this->crearSucursal('PTS');
    $admin = $this->crearUsuario('admin', $s->id);
    $cliente = Cliente::create(['nombre' => 'P', 'telefono' => '3009998877', 'activo' => true, 'puntos_fidelidad' => 500]);

    $menu = app(MenuService::class);
    $cat = $menu->crearCategoria(['nombre' => 'P', 'icono' => '🍣', 'orden' => 1, 'activo' => true]);
    $prod = $menu->crearProducto(['categoria_id' => $cat->id, 'nombre' => 'P', 'precio' => 50000, 'costo' => 5000, 'area_cocina' => 'sushi', 'activo' => true]);
    $items = [['producto_id' => $prod->id, 'cantidad' => 1]];

    try {
        app(PedidoService::class)->crearPedido([
            'tipo' => 'mesa',
            'sucursal_id' => $s->id,
            'cliente_id' => $cliente->id,
            'descuento_puntos' => 25000,
            'puntos_canjeados' => 0,
        ], $items, $admin);
        $this->fail('No debe aplicarse descuento de puntos sin canjear puntos.');
    } catch (\InvalidArgumentException $e) {
        $this->assertStringContainsString('puntos', $e->getMessage());
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=test_descuento_puntos_sin_canje_rechazado`
Expected: FAIL — no lanza y crea pedido con descuento.

- [ ] **Step 3: Implementación**

En `PedidoService::crearPedido`, al inicio del bloque de puntos (L74):

```php
if ($descuentoPuntos > 0 || $puntosCanjeados > 0) {
    if ($descuentoPuntos > 0 && $puntosCanjeados < 1) {
        throw new \InvalidArgumentException('Para aplicar descuento por puntos debe indicar cuántos puntos canjear.');
    }
    ...
}
```

- [ ] **Step 4: Ejecutar y verificar que pasa + regresión fidelización**

Run: `php artisan test --filter=test_descuento_puntos_sin_canje_rechazado && php artisan test --filter=Fidelizacion`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PedidoService.php tests/Feature/PuntosFidelidadServiceTest.php
git commit -m "fix(money): no aplicar descuento_puntos sin puntos_canjeados (descuento gratis evadible)"
```

---

### Task 9: RBAC de turnos — alinear `abrirTurno` con la política (sin mesero)

**Contexto:** `CajaService::abrirTurno` (L130) permite `mesero` y acepta cualquier rol que empiece por `cajero` (`str_starts_with`), en contradicción con `TurnoCajaPolicy::abrir` (solo `cajero`, `gerente`, `admin`). La UI (caja/navigation) tampoco lo permite. Fix: autorización única server-side en el servicio alineada con la política.

**Files:**
- Modify: `app/Services/CajaService.php:130-132`
- Test: `tests/Feature/RemediacionDineroTurnosTest.php`

**Interfaces:**
- Consumes: `User::role->slug`, `TurnoCajaPolicy`.
- Produces: `abrirTurno` lanza `AuthorizationException` a `mesero`.

- [ ] **Step 1: Test RED — mesero no abre turno**

```php
public function test_abrir_turno_rechaza_mesero(): void
{
    $s = $this->crearSucursal('SMB');
    $mesero = $this->crearUsuario('mesero', $s->id);
    $caja = Caja::create(['sucursal_id' => $s->id, 'nombre' => 'Caja', 'codigo' => 'CAJA-M', 'activa' => true]);

    $this->expectException(AuthorizationException::class);
    app(CajaService::class)->abrirTurno($caja, $mesero, 50000.00);
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=test_abrir_turno_rechaza_mesero`
Expected: FAIL — no lanza (mesero pasa la allowlist del servicio).

- [ ] **Step 3: Implementación mínima**

En `CajaService::abrirTurno` (L130):

```php
if (! in_array($cajero->role?->slug, ['cajero', 'gerente', 'admin'], true)) {
    throw new AuthorizationException('El usuario no tiene permisos para abrir turnos de caja.');
}
```

- [ ] **Step 4: Ejecutar y verificar pasa (y no rompe cajero)**

Run: `php artisan test --filter=test_abrir_turno_rechaza_mesero && php artisan test --filter=test_indice_unico_parcial_impide_dos_turnos_abiertos_por_caja`
Expected: PASS (ambos). NOTA: si el negocio quiere que `mesero` abra turno, la decisión es de producto; el plan asume política vigente (coordination.md line: PENDIENTE para Antigravity). Cambiarlo aquí requiere actualizar `TurnoCajaPolicy::abrir` y la vista `caja/control.blade.php` como segundo paso — NO mezclar.

- [ ] **Step 5: Commit**

```bash
git add app/Services/CajaService.php tests/Feature/RemediacionDineroTurnosTest.php
git commit -m "fix(authz): abrirTurno alineado con TurnoCajaPolicy (sin mesero, sin prefijo cajero*)"
```

---

### Task 10: Propinas — registrar asiento contable separado y dosificar tamaño de tests

**Contexto:** (Medio) Al cobrar con `propina > 0` no se genera asiento contable de propina; la propina queda solo en `monto_pagado`/`cambio` del pedido, invisibles para contabilidad. Opcional/adyacente a este plan: asiento adicional `propinas` en `cobrarPedido`. También se corrige el defecto de nombre del test (`acumula`).

**Files:**
- Modify: `app/Services/PedidoService.php` (`cobrarPedido`, ~L207-300)
- Test: `tests/Feature/RemediacionDineroTurnosTest.php:115-130` (renombrar + aserción estricta)

**Interfaces:**
- Consumes: modelo `AsientoContable`, `cobrarPedido(Pedido, string $metodoPago, float $montoPagado, ?float $montoEfectivoMixto, float $propina, float $porcentajePropina)`.
- Produces: asiento tipo `ingreso` cuenta `propinas` cuando `$propina > 0`.

- [ ] **Step 1: Test RED — propina genera asiento contable**

```php
public function test_cobro_con_propina_genera_asiento_propinas(): void
{
    $s = $this->crearSucursal('SPR');
    $admin = $this->crearUsuario('admin', $s->id);
    $turno = $this->abrirTurnoEn($s->id, $admin);
    $pedido = $this->crearPedidoConItems($admin, $s->id, 1, 100000.00);

    app(PedidoService::class)->cobrarPedido($pedido, 'efectivo', 110000.00, null, 10000.00, 10.0);

    $asientoPropina = AsientoContable::where('cuenta', 'propinas')->latest()->first();
    $this->assertNotNull($asientoPropina);
    $this->assertSame(10000.00, (float) $asientoPropina->monto);
    $this->assertSame('ingreso', $asientoPropina->tipo);
}
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run: `php artisan test --filter=test_cobro_con_propina_genera_asiento_propinas`
Expected: FAIL — no hay asiento de `propinas`.

- [ ] **Step 3: Implementación mínima**

En `PedidoService::cobrarPedido`, dentro del `DB::transaction`, tras marcar el pedido pagado (donde se vincula el turno), añadir:

```php
if ($propina > 0) {
    AsientoContable::create([
        'fecha' => now()->toDateString(),
        'tipo' => 'ingreso',
        'cuenta' => 'propinas',
        'concepto' => "Propina pedido {$pedido->codigo} ({$porcentajePropina}%)",
        'monto' => $propina,
        'referencia_tipo' => 'pedido',
        'referencia_id' => $pedido->id,
        'user_id' => $pedido->usuario_id ?? auth()->id(),
    ]);
}
```

**Ojo:** verificar que `no rompe` `vincularCobroPedido` (idempotencia por `referencia_tipo='pedido'`; la propina usa la misma referencia) → la guard de idempotencia de `vincularCobroPedido` consulta por `referencia_tipo='pedido'` (CajaService:271-277) y se conserva; el asiento de propina se crea UNA vez porque `cobrarPedido` solo corre una vez por flujo (Task 4 previene el doble-fire).

- [ ] **Step 4: Ejecutar y verificar + renombrar test con defecto de nombre**

Run: `php artisan test --filter=test_cobro_con_propina_genera_asiento_propinas && php artisan test --filter=RemediacionDineroTurnosTest`
Expected: PASS. Renombrar en el mismo archivo `test_vincular_pedido_mixto_doble_invocacion_acumula_desglose_sin_perder_metodo` → `test_vincular_pedido_mixto_doble_invocacion_no_duplica_desglose` y endurecer `assertEquals((float)...)` a `assertEqualsWithDelta` o `assertSame` (mantener lectura del test).

- [ ] **Step 5: Commit**

```bash
git add app/Services/PedidoService.php tests/Feature/RemediacionDineroTurnosTest.php
git commit -m "feat(money): asiento contable de propinas al cobrar; renombrar test doble vinculacion"
```

---

### Task 11: Verificación final de plan — suite completa + skills + coordination.md

**Files:**
- Modify: `coordination.md` (entrada de cierre)
- Run skills: `secrets-scan`, `laravel-security-review`, `dependency-audit`, `code-review-gate`

**Interfaces:**
- Consumes: resultado de Tasks 1-10.
- Produces: suite verde 364+ y coordinación actualizada.

- [ ] **Step 1: Suite completa + Pint + audits**

```bash
php artisan test            # esperado: TODOS PASS, incluye los 10 nuevos
vendor/bin/phpunit --testdox 2>&1 | Select-String -Pattern "FAIL" | Measure-Object  # 0
php artisan pint --test      # 0 archivos pendientes
composer audit               # 0 advisories
npm audit --omit=dev         # 0 vulnerabilities
```

- [ ] **Step 2: Skills de calidad/seguridad sobre el diff**

Scan con `secrets-scan` sobre `git diff` de HEAD..HEAD~11 y confirmar 0 hallazgos: `rg -n "SecretResto2026|restomaster2026|sushixpress2026|base64:" <diff>` → 0. Aplicar `laravel-security-review` desde OpenCode (`skill` tool) sobre los archivos tocados en dinero: `PedidoService`, `CajaService`, `DeliveryService`, `terminal.blade.php`, `ReporteService`.

- [ ] **Step 3: Registro en coordination.md**

Añadir entrada al inicio:

```markdown
2026-09-17 22:00 | OpenCode | ✅ **PLAN DE REMEDIACIÓN AUDITORÍA #3 EJECUTADO** — Tasks 1-11 en `docs/superpowers/plans/2026-09-17-remediacion-auditoria-3.md`. Corregidos/verificados: R1 secretos fuera de compose/docs + test endurecido 🅲; timezone conexión America/Bogota + migración +5h (corrige -5h) 🅲; notif cache arrays + poll 30s.visible 🅰; idempotencia_uuid pedidos (doble-fire cobro) 🅲; H1 COD sin doble asiento 🅲; IDOR sucursal QR/reservas/caja 🅰; KPIs SQL nativo 🅰; puntos sin canje rechazado 🅰; abrirTurno sin mesero 🅰; asiento propinas 🟢. Suite VERDE (baseline 364 + nuevos). **Pendiente manual:** rotar DB_PASSWORD en despliegues afectados y reescribir historial si se desea eliminar el secreto de ramas públicas.
```

- [ ] **Step 4: Commit final**

```bash
git add coordination.md
git commit -m "docs(coordination): registrar cierre de plan de remediacion auditoria 3 (tasks 1-11)"
```

---

## Self-Review (writing-plans)

- **Cobertura del spec:** Bloqueantes 🅲 1 (secretos), 2 (notif), 3 (timestamptz), 4 (doble-fire), 5 (COD) ≈ Tasks 1-5; altos 🅰 IDOR (6), KPIs SQL (7), puntos (8), RBAC turno (9); media propinas (10); verificación + coordinación (11). Todas las prioridades de `coordination.md` tienen tarea. La decisión `¿mesero abre turno?` queda documentada como no resuelta (política vigente) en Task 9 Step 4. La R2/cache-notif se reformuló al estado real del HEAD (sin cache) → caché de arrays planos.
- **Placeholders:** No hay "TODO/TBD"; cada paso trae código real, comandos y asserts. La línea exacta de `caja/control:214` y `terminal:634` se marca para localizar al editar (los anchors pueden variar tras Tasks anteriores; el plan instruye `grep` implícito).
- **Consistencia de tipos:** `obtenerResumen(?User): array`; `crearPedido(array $datos, array $items, ?User): Pedido` + `idempotencia_uuid`; `kpisRealtime(?int): array` → `picos_por_hora` map hora→count; `abrirTurno(Caja, User, float, ?string): TurnoCaja`; `liquidarRecaudoRepartidor(User, TurnoCaja): float`; `verificarDisponibilidad(..., ?int $sucursalId)` — sin colisiones de nombres entre tareas.