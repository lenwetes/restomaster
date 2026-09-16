# Plan de Remediación — Auditoría Seguridad, Rendimiento y Robustez (2026-09-15)

- **Fecha:** 2026-09-15
- **Autor:** OpenCode (auditoría solo lectura; 4 subagentes paralelos + verificación manual de críticos)
- **Destinatario:** Antigravity (ejecución de remediación)
- **Objeto:** HEAD `05e9f68` (rama `master`)
- **Skills aplicadas:** secrets-scan · laravel-security-review · authz-rbac-check · dependency-audit · config-env-guard · performance-audit · laravel-best-practices
- **Suite verificada por auditor independiente:** 253/253 tests reportados · Pint 0 · `composer audit` 0 · `npm audit --omit=dev` 0

---

## Veredicto

La calificación ponderada global es **~55/100**. La app tiene buenos cimientos (policies server-side, precios SIEMPRE tomados de la BD, `decimal:2` en dinero, `lockForUpdate` en cobro y asignación de mesero QR, 0 CVEs, 0 N+1 severos en módulos core nuevos) pero **NO está lista para producción con datos reales**: hay 6 hallazgos que generan 500s, duplicación de comandas, un documento fiscal (Reporte Z) que imprime $0.00, y secretos de deploy expuestos en el repositorio.

**Regla de oro para Antigravity:** cada fix se hace con **test primero (RED) → fix (GREEN) → Pint → verificación del contrato**, y **ningún fix de dinero/estado se mergea sin `authorize()` server-side**. Antes de tocar un archivo compartido, crear lock en `.locks/`; liberarlo al terminar y actualizar `coordination.md`.

---

## Índice de hallazgos por lote de trabajo

| Lote | Contenido | Severidad | Dependencias |
|---|---|---|---|
| **L1** | Infraestructura y secretos (compose, login `activo`, sesiones/TLS, backup) | 🔴 Crítica | ninguna |
| **L2** | Lógica POS/Cocina: duplicación de comandas y cobro ignorando carrito | 🔴 Crítica | ninguna |
| **L3** | Reporte Z fiscal y campos inexistentes | 🔴 Crítica | ninguna |
| **L4** | Concurrencia: inventario, CXP, fidelización, delivery | 🟠 Alta | ninguna |
| **L5** | Bugs funcionales: `wire:submit` CXP, cobro tarjeta residual, validaciones | 🟠 Alta | L2 |
| **L6** | Rendimiento: SQL agregados, caché, polling, índices | 🟠 Alta | — |
| **L7** | IDOR/autenticación multiusuario y transiciones de estado | 🟡 Media | L2/L4 |
| **L8** | Higiene: código duplicado, dead code, permisos muertos, CSV injection | 🟢 Baja | — |

---

# LOTE 1 — INFRAESTRUCTURA Y SECRETOS (BLOQUEANTE)

> **Protocolo de seguridad estricto:** aplicar `secrets-scan` y `config-env-guard` ANTES y DESPUÉS de cada cambio. Nunca usar valores por defecto reales en `docker-compose`. Los `.env.example` llevan variables VACÍAS. Nunca imprimir secretos en stdout/logs.

## R1 (CRÍTICO) — Secretos hardcodeados en docker-compose

**Ubicación:** `docker-compose.yml:14,26,37,59` — idénticos en `docker-compose.yaml` y `compose.yml` (3 archivos duplicados, ver L8).

**Problema:** `APP_KEY`, `DB_PASSWORD` y `DEMO_USERS_PASSWORD=sushixpress2026` tienen valores por defecto reales y conocidos, más `APP_DEBUG=true`. Cualquiera con acceso al repo puede:
- Descifrar/forjar cookies y sesiones (EncryptCookies usa `APP_KEY`).
- Conectarse a Postgres (mapeado a `0.0.0.0:5434`).
- Loguearse como admin con la contraseña demo.

**Reparación (medida a tomar):**
```yaml
# docker-compose.yml — quitar TODO default real
APP_KEY: "${APP_KEY}"          # sin :-  (el entrypoint ya genera APP_KEY si falta)
DB_PASSWORD: "${DB_PASSWORD}"
DEMO_USERS_PASSWORD: "${DEMO_USERS_PASSWORD}"
APP_DEBUG: "${APP_DEBUG:-false}"
POSTGRES_EXTERNAL_PORT: "${POSTGRES_EXTERNAL_PORT:-127.0.0.1:5434}"  # no exponer a internet
```
- Exigir las variables: fallar el arranque si `APP_KEY / DB_PASSWORD` están vacías (USAR: `command: ${APP_KEY:?APP_KEY no definida}` no aplica a environment; validar en el entrypoint).
- Si el stack ya se desplegó con estos valores: **rotar APP_KEY, DB_PASSWORD y contraseñas demo de inmediato** (los historial git los conserva para siempre).

**Criterio de aceptación:** `rg "sushixpress2026|ryJ8oRftsst90c9" .` → 0 resultados fuera de `.env`/historital; arranque falla con mensaje si falta `DB_PASSWORD`.

## R2 (CRÍTICO) — Auto-seed resetea contraseñas en cada boot

**Ubicación:** `docker-compose.yml:36` (`AUTO_SEED: true`), `database/seeders/AdminUserSeeder.php:29,32`, `database/seeders/DatabaseSeeder.php:28`.

**Problema:** en producción el seeder corre `updateOrCreate` en cada boot: **revierten contraseñas y re-inyectan datos demo** en la BD real.

**Reparación:**
1. `docker-compose.yml`: `AUTO_SEED: "${AUTO_SEED:-false}"`.
2. `AdminUserSeeder` vía `updateOrCreate`: NO tocar `password` si el usuario ya existe (solo crear si falta). Solo imprimir en `--env=local` o `app()->isLocal()`.
3. `DatabaseSeeder`: los seeders de operaciones demo (`DemoOperacionesSeeder`) corren solo si `config('app.env') === 'local'` o si existe flag explícito.
4. Posible `php artisan db:seed --class=...` manual en deploy, nunca automático en prod.

**Criterio:** subir un usuario, `docker compose restart`, verificar que la contraseña sigue intacta.

## R3 (CRÍTICO) — Login no valida usuarios `activo`

**Ubicación:** `app/Livewire/Forms/LoginForm.php:33`; verificar también `app/Http/Middleware/EnsureUserHasRole.php:24-33`.

**Problema:** `Auth::attempt($this->only(['email', 'password']))` no incluye `activo => true`: un empleado desactivado (baja) entra normalmente y conserva todo su rol/con permisos de dinero.

**Reparación:**
```php
if (! Auth::attempt([...$this->only(['email', 'password']), 'activo' => true], $this->remember)) { ... }
```
- Refuerzo en middleware (el rol no basta; es defensa en profundidad):
```php
if ($user?->activo === false) return redirect()->route('login')->withErrors(...);
```
Recordar `User` debe tener `activo` en `$fillable` y cast `boolean` (verificar `app/Models/User.php`).

**Prueba:** test `test_usuario_inactivo_no_puede_iniciar_sesion` (login → 403/redirect): RED primero.

## R4 (ALTO) — Sesiones en FS efímero y falta TLS/SSL

**Ubicación:** `docker-compose.yml:29` (`SESSION_DRIVER=file` sin volumen), `.env.production.example`, `config/session.php:172` (`secure` sin default), `config/database.php:99` (`sslmode=prefer`).

**Problema:** con `SESSION_DRIVER=file` las sesiones viven en `storage/framework/sessions` **fuera de los volúmenes** → se pierden en cada redeploy y no comparten estado entre réplicas. Sin `SESSION_SECURE_COOKIE=true` la cookie viaja por HTTP si el proxy no termina TLS, y PostgreSQL habla en claro (`sslmode=prefer`).

**Reparación:**
1. `SESSION_DRIVER=database` (default de config) o `redis`; NUNCA `file` en producción. Añadir volumen si se insiste en file.
2. `.env.production.example`: `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=false`, `DB_SSLMODE=require`.
3. Asegurar que php-fpm/nginx fuerza `X-Forwarded-Proto` correcto (TrustProxies) para que `secure` no rompa el login tras proxy.

**Criterio:** redeploy con `docker compose up` mantiene la sesión del cajero activa; `curl -I` en producción muestra cookie `Secure`.

---

# LOTE 2 — LÓGICA POS/COCINA (BLOQUEANTE)

> **Protocolo:** aplicar `authz-rbac-check` + `laravel-security-review` en el diff final. El dinero se calcula en el SERVIDOR; ningún valor del cliente se usa para totales.

## R5 (CRÍTICO) — `enviarACocina` duplica items de un pedido activo de mesa

**Ubicación:** `resources/views/livewire/pos/terminal.blade.php:61-73` y `:81-93` (se cargan items del pedido activo al carrito), `:227-276` (`enviarACocina` → `crearPedido` crea un pedido NUEVO con el carrito completo).

**Problema real (verificado):** el mesero abre la mesa con pedido activo (carrito = items ya en BD), agrega 1 item nuevo y envía a cocina → `crearPedido` genera un **segundo pedido** con TODOS los items: la cocina recibe comanda duplicada, el inventario se descuenta 2× y el ticket puede doblar la cuenta.

**Reparación (mínima y segura, sin refactor gigante):**
- En `enviarACocina`: detectar el pedido activo de la mesa.
  - Si existe: usar `PedidoService::agregarItem($pedido, $producto, $cantidad, $notas)` **solo para los items nuevos** (los que no están ya en el pedido), o bien el cálculo de diferencia por `producto_id` + cantidades.
  - Si NO existe: `crearPedido` como hoy.
- Refuerzo de consistencia: no permitir "enviar a cocina" hasta que el pedido activo esté `pagado`/`cancelado`, mostrando mensaje de error claro en la UI.

**Prueba:** test que monta una mesa con pedido activo, agrega 1 item y verifica que solo se crea el item nuevo y que el total de items en BD es `n_originales + 1` (RED antes del fix).

## R6 (CRÍTICO) — `procesarCobro` ignora el carrito cuando hay pedido existente

**Ubicación:** `resources/views/livewire/pos/terminal.blade.php:312-334`.

**Problema:** si hay pedido activo, se cobra el pedido de la BD (sin items agregados ni descuentos), pero el monto se valida contra `$this->total` (carrito). Resultados posibles: cobro con cargo incompleto, o validación de monto contra un total que no es el que se cobra.

**Reparación:**
- Unificar en un solo camino: merge del carrito en el pedido existente vía `agregarItem` (mismo flujo que R5), luego `cobrarPedido($pedido, ...)`.
- `montoPagado` debe validarse contra `$pedido->total` (DB) SIEMPRE, nunca contra `$this->total`.
- Mover la comprobación `montoPagado < total` al SERVICE (ya existe en `PedidoService::cobrarPedido:174` — dejarlo como única fuente de verdad) y en el componente disparar `ValidationException` con mensaje al usuario en vez de `return;` silencioso (línea 305-307).

**Criterio:** mesero agrega item a mesa con comanda activa → el ticket final incluye el item y el cobro aplica el total correcto; el cambio es consistente.

## R7 (CRÍTICO) — Cobro con tarjeta/mixto conserva `montoPagado` residual

**Ubicación:** `pos/terminal.blade.php:1597-1629` (y default `:34`).

**Problema:** el usuario escribe $100.000 en efectivo y cambia a tarjeta; `montoPagado` conserva $100.000 → ticket con "TARJETA, PAGÓ $100.000, CAMBIO $50.000". Contablemente inválido.

**Reparación:**
- En el `updatedMetodoPago` o al abrir el modal de cobro: para `tarjeta`/`transferencia`/`datáfono`, fijar `$this->montoPagado = $this->total` (y `cambio = 0`). Para `efectivo`, dejar que el cajero escriba.
- Si el método es mixto, mantener el monto efectivo parcial y calcular cambio real.
- **Medida de endurecimiento extra:** el método `sumarMonto` (línea 292-295) hace `$this->montoPagado = $cantidad` (reemplaza). Documentar en la UI como "valor rápido" o cambiarlo a suma `+=`.

**Prueba:** test que cambia el método pago y verifica que `montoPagado === pedido.total` para tarjeta.

---

# LOTE 3 — REPORTE Z FISCAL (BLOQUEANTE)

## R8 (CRÍTICO) — `formatearReporteZTexto` usa campos inexistentes

**Ubicación:** `app/Services/ImpresionService.php:621-633`.

**Problema (verificado contra schema):** el ticket fiscal usa `monto_apertura` (no existe; real: `monto_inicial`), `total_ventas` (no existe; reales: `total_ventas_efectivo/_tarjeta/_transferencia`), `total_ingresos` (no existe), `monto_cierre_real` (no existe; real: `monto_real_efectivo`). **Resultado: el Reporte Z imprime $0.00 en fondo inicial, saldo y arqueo — documento fiscal inválido.**

**Reparación:**
```php
// L621 — fondo inicial
$this->alinearDosColumnas('FONDO INICIAL:', '$ '.number_format($turno->monto_inicial ?? 0, 2), $ancho)
// L622 — total de ventas
$totalVentas = (float)($turno->total_ventas_efectivo ?? 0)
    + (float)($turno->total_ventas_tarjeta ?? 0)
    + (float)($turno->total_ventas_transferencia ?? 0);
// L628 — saldo esperado con la fórmula correcta
$saldoEsperado = (float)$turno->monto_inicial + $totalVentas
    - (float)($turno->total_egresos ?? 0) - (float)($turno->total_retiros ?? 0)
    + totalIngresos; // ver R9
// L631 — arqueo físico
if ($turno->monto_real_efectivo !== null) { ... }
```
- Verificar que `CajaService` persiste correctamente estos campos (mapear `vincularCobroPedido` y `registrarMovimiento`).

**Criterio:** imprimir un Reporte Z de un turno con ventas/egresos y verificar que los montos coinciden con la pantalla `generarReporteZ`. Test de contrato de campos en `TurnoCaja`.

## R9 (MEDIO) — Movimientos tipo `ingreso` no afectan saldo esperado

**Ubicación:** `app/Services/CajaService.php:136-142` y `control.blade.php:420-438`.

**Problema:** un ingreso extraordinario queda en `movimientos_caja` pero no suma al saldo esperado ni al Reporte Z → el arqueo físico nunca cuadra cuando hay ingresos.

**Reparación:**
1. Migración: `Schema::table('turnos_caja', fn($t) => $t->decimal('total_ingresos', 12, 2)->default(0));`
2. `CajaService::registrarMovimiento('ingreso')`: incrementar `total_ingresos` del turno dentro de la misma transacción.
3. `recalcularEsperado`: sumar `total_ingresos`.
4. Reporte Z (R8): incluir el campo nuevo.

**Criterio:** registrar ingreso → saldo esperado del turno se incrementa; Reporte Z lo muestra.

---

# LOTE 4 — CONCURRENCIA Y RACE CONDITIONS

> **Protocolo:** toda escritura de dinero/stock dentro de `DB::transaction` + `lockForUpdate` sobre la fila raíz (cliente, pedido, cuenta, insumo). Nunca checar un flag "fuera" de la transacción.

## R10 (ALTO) — Doble descuento de inventario (flag fuera de transacción)

**Ubicación:** `app/Services/InventarioService.php:20-31`.

**Problema:** `descontarPorItemPedido` verifica `inventario_descontado` ANTES de abrir la transacción → KDS `marcarItemListo` y `cobrarPedido→descontarPorPedido` concurrentes pasan ambos el check y descuentan 2× (stock negativo + kardex duplicado).

**Reparación:**
```php
DB::transaction(function () use ($pedido) {
    $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->first();
    $items = $pedido->items()->where('inventario_descontado', false)->get();
    foreach ($items as $item) {
        // aplicar descuento por receta
        $updated = ItemPedido::whereKey($item->id)
            ->where('inventario_descontado', false)
            ->update(['inventario_descontado' => true]);
        if ($updated !== 1) continue;           // otro proceso ya lo descontó
        // ... decremento de insumos y kardex dentro de la MISMA tx
    }
});
```
- También `marcarItemEntregado`/`marcarTodaComandaLista` deben re-verificar el flag dentro de la tx.

**Criterio:** test concurrente (2 hilos/tareas) sobre el mismo pedido → stock final correcto y 1 sola fila de kardex por item.

## R11 (MEDIO) — CXP: abonos concurrentes sobrepagan

**Ubicación:** `app/Services/CuentasPorPagarService.php:53-72`.

**Reparación:** envolver `registrarPago` en transacción con
```php
$cuenta = CuentaPorPagar::whereKey($id)->lockForUpdate()->first();
```
validar `$pago <= $cuenta->saldo_pendiente` dentro de la tx, recalcular `saldo_pendiente` y persistir; estado `pagada` solo si saldo llega a 0.

**Prueba:** dos abonos simultáneos por el saldo completo → el segundo es rechazado con mensaje.

## R12 (MEDIO) — Fidelización: doble canje concurrente

**Ubicación:** `app/Services/FidelizacionService.php:103-104,116-142`.

**Reparación:** `lockForUpdate()` sobre el `Cliente` (y el `Pedido`) antes de validar saldo, canjear dentro de la tx y persistir `puntos_fidelidad` descontado. Guard para no permitir saldo negativo.

## R13 (MEDIO) — Delivery: doble liquidación del repartidor (ingreso de caja duplicado)

**Ubicación:** `app/Services/DeliveryService.php:150-178`.

**Reparación:** `lockForUpdate()` sobre el pedido o update condicional `where('recaudo_liquidado', false)->update(['recaudo_liquidado' => true])` y verificar `rowCount === 1` antes de crear `MovimientoCaja`.

## R14 (MEDIO) — Reservas: sync de mesas antes de validar + TOCTOU de solapamiento

**Ubicación:** `app/Services/ReservaService.php:94-97` y `:196-213`.

**Reparación:**
1. Llamar `validarMesasParaConfirmar` ANTES de `reserva->mesas()->sync(...)`, y envolver todo en una transacción.
2. En el chequeo de solapamiento, `lockForUpdate()` sobre las reservas solapadas (o al menos sobre la fila de la mesa en `reserva_mesa`).

---

# LOTE 5 — BUGS FUNCIONALES Y VALIDACIÓN

## R15 (ALTO) — `wire:submit` apuntando a métodos inexistentes en CXP

**Ubicación:** `resources/views/livewire/cxp/index.blade.php:254` (`wire:submit="registrarAbono"` → método real `registrarPago`) y `:298` (`guardarCuenta` → método real `crearCuenta`).

**Reparación:** corregir los nombres; verificar que no existan otros `wire:submit/wire:click` con métodos inexistentes en TODO el árbol (`rg "wire:(submit|click)=\"(\w+)"` cruzado con los métodos de cada componente). Añadir test `FixCxpWireSubmitTest` que dispara ambos envíos y verifica 201/without errors.

## R16 (ALTO) — LoginForm imprime/permite contraseñas demo en logs

**Ubicación:** `database/seeders/AdminUserSeeder.php:29,32`, `docker/entrypoint.sh:23-24,29-51`.

**Reparación:** quitar toda impresión de secretos a stdout (`this->info` con password, `echo APP_KEY`); el `.env` que genera el entrypoint no debe loguearse con el valor. Considerar no escribir `.env` planos (con `clear_env=no` las variables de entorno ya llegan al proceso).

## R17 (ALTO) — BackupDatabaseCommand: materialización completa + escape inseguro

**Ubicación:** `app/Console/Commands/BackupDatabaseCommand.php:82,99` y ausencia de `Schedule` en `routes/console.php`.

**Problema:** `DB::table($tabla)->get()` trae tablas enteras a memoria (con `pedidos`/`items_pedido` esto revienta el límite de 512M y el catch en `:110-112` silencia el fallo), y `addslashes` produce INSERTs corruptos en restore (comillas/backslashes), sin sequences ni constraints → backup no restaurable.

**Reparación (prioridad alta, no hay nada más crítico en operaciones):**
1. **Usar `pg_dump`** con streaming vía `Process` (incrementalmente a disco, no a memoria):
   ```php
   $env = ['PGPASSWORD' => config('database.connections.pgsql.password')];
   Process::forever()->env($env)->path($backupDir)
       ->run(['pg_dump', '-h', $host, '-U', $user, '-Fc', '-Z', '9',
              '-f', $filepath, $dbname], $output);
   ```
2. **Rotación:** conservar N backups (ej. 14) y borrar los más antiguos; tamaño + hash.
3. **Scheduler en `routes/console.php`**:
   ```php
   use Illuminate\Support\Facades\Schedule;
   Schedule::command('sushixpress:backup')->dailyAt('03:00')
       ->withoutOverlapping(120)->onOneServer()->runInBackground();
   ```
4. Supervisor: `php artisan schedule:run` cada minuto + `schedule:work` en dev.
5. Restauración: validar extensión `.sql`/`.dump` (ya existe validación; reforzar que el restore use `pg_restore` para `-Fc`).

**Criterio:** backup de una BD con N mil pedidos termina en segundos sin picos de memoria; restore en una BD vacía reproduce los mismos conteos por tabla.

## R18 (ALTO) — Reportes materializan periodos completos en PHP

**Ubicación:** `app/Services/ReporteService.php:141,176,196,256,271,288` (el más crítico: `:288` `->get(['total'])` solo para sumar).

**Reparación (SQL agregado):**
```php
// resumenPeriodo — antes :288
$ventas = Pedido::where('estado', 'pagado')
    ->whereBetween('pagado_en', [$desde, $hasta])
    ->sum('total');
$conteo = Pedido::where(...)->count();

// ventasPorPeriodo — antes :141
->selectRaw("to_char(pagado_en, 'YYYY-MM-DD') as dia, sum(total) as total, count(*) as n")
 ->groupByRaw("1")->orderBy('dia');

// ventasPorProducto — antes :176
ItemPedido::selectRaw('producto_id, sum(cantidad) as cantidad, sum(subtotal) as subtotal')
    ->whereHas('pedido', fn($q) => $q->where('estado','pagado')->whereBetween('pagado_en', [...]))
    ->groupBy('producto_id')->with('producto')->get();
```
- Índices ya existentes: `pedidos_estado_pagado_en_index` ✓.
- `whereDate` → `whereBetween` para hacer sargable (ver L6 R22).
- Tope de rango de fechas en `ReporteExportController` (máx. 366 días) para evitar dompdf colgado.

**Criterio:** dashboard/reportes con datos de 6 meses sin traer filas a PHP (verificar con `DB::listen` o `Telescope`).

## R19 (ALTO) — Polling global 10s en navegación (4 queries/usuario/10s)

**Ubicación:** `resources/views/livewire/layout/navigation.blade.php:124` + `NotificacionService::obtenerResumen`.

**Reparación:**
```php
// NotificacionService::obtenerResumen — cachear a corto plazo por rol
Cache::remember('notif.resumen.'.auth()->id().'.'.auth()->user()->role_id, 8,
    fn () => $this->queryResumen());
```
O alternativamente `wire:poll.10s` solo dentro del dropdown abierto (`x-show`), no todo el tiempo. Subir KDS a poll 15s y el menú QR de seguimiento a 15-20s (el estado cambia en minutos).

**Criterio:** con 15 terminales abiertas, la carga media de queries de notificaciones baja de 360/min a < 30/min.

---

# LOTE 6 — RENDIMIENTO Y CACHÉ

## R20 (ALTO) — Catálogo público y carta sin caché + búsqueda por keystroke

**Ubicación:** `app/Services/MenuService.php` (sin `Cache::forget` en mutaciones), `livewire/menu/carta-publica.blade.php:112-114`, `livewire/mesa/menu-publico.blade.php:176-190,422` (N+1 de `categoria` en :176).

**Reparación siguiendo `caching.md`:**
```php
// MenuService — cache-aside
Cache::remember('menu.publico.v1', 300, fn () =>
    Categoria::activas()->with(['productos' => fn($q) => $q->activas()])->get()
);
// Invalidación: en CADA mutación (crear/actualizar/activar/desactivar/categoria)
Cache::forget('menu.publico.v1');
```
- Fix N+1: `Producto::with('categoria')` en la query de `menu-publico.blade.php:176`.
- Búsqueda: `.debounce.300ms` en vez de `.live` + activar `Model::preventLazyLoading(! app()->isProduction())` en `AppServiceProvider::boot()` para cazar los N+1 restantes en dev (db-performance.md).

## R21 (MEDIO) — Índices faltantes y `whereDate` no sargable

**Ubicación:** migraciones `2026_09_10_120000` y `230000`; faltan según la auditoría de queries:
- `pedidos.cliente_id` (FK sin índice — hotspot `topClientes`).
- `pedidos.usuario_id`.
- `items_pedido.producto_id`.
- `pedidos(canal_origen, estado)` para `NotificacionService:28-31`.

Nueva migración `add_audit_2026_09_15_missing_indexes.php`:
```php
Schema::table('pedidos', fn($t) => $t->index('cliente_id')->index('usuario_id'));
Schema::table('items_pedido', fn($t) => $t->index('producto_id'));
```

## R22 (MEDIO) — `AuditoriaService` y listas sin paginar

**Ubicación:** `AuditoriaService.php:42,53` (`.get()` sin límite sobre tabla en crecimiento), `inventario/index.blade.php:211-213`, `cxp/index.blade.php:39-42`.

**Reparación:** `->latest()->limit(200)->get()` o `paginate(50)` con vínculo de paginación. En `ReporteService::tiemposEntrega/resumenReservas`, agregados en SQL.

## R23 (BAJO) — Queries y eager-loads desperdiciados

**Ubicación:** `livewire/menu/index.blade.php:180` (`Producto::with('categoria')` nunca usado en vista); `InventarioService.php:24` (recarga `producto->with('recetas.insumo')` por item al cobrar → usar `load()` una vez). `pos/terminal.blade.php:391-431` (4 queries en `with()` por render → candidatos a `#[Computed]` o `Cache::remember` corto).

---

# LOTE 7 — IDOR, AUTORIZACIÓN Y TRANSICIONES DE ESTADO

## R24 (MEDIO) — Verificación de sucursal en mutaciones de dinero/estado

**Ubicación:** `pos/terminal.blade.php:312-314`, `cocina/kds.blade.php:50-51,62-64`, `caja/control.blade.php:84-96`.

**Problema (IDOR):** `procesarCobro`, `marcarTodaComandaLista` y el `mount` de caja operan sobre IDs sin verificar que pertenezcan a la sucursal del operador.

**Reparación (server-side, nunca solo en la UI):**
- En cada mutación: resolver `$pedido` y `abort_unless($pedido->mesa->sucursal_id === auth()->user()->sucursal_id, 403)` (o la política de turno similar para `TurnoCaja`).
- `control.blade.php` `mount`: filtrar turnos abiertos por `caja.sucursal_id === user->sucursal_id`; si el usuario tiene varias cajas, ofrecer selector explícito.
- Crear/actualizar **Policy** con criterio de sucursal (patrón existente en `PedidoPolicy`/`CajaPolicy`).

**Criterio:** mesero de sucursal B recibe 403 al operar sobre un pedido de sucursal A. Test dedicado.

## R25 (MEDIO) — Transiciones de mesa sin máquina de estados

**Ubicación:** `app/Services/MesaService.php:30-36`, `resources/views/livewire/mesas/index.blade.php:107-123`.

**Problema:** `cambiarEstado` permite `ocupada → libre` con comanda activa (pérdida visual del pedido) y `reservada → libre` sin validación.

**Reparación:** tabla de transiciones válidas en `MesaEstado`:
- `libre → ocupada, reservada`
- `ocupada → por_limpiar` (solo vía cobro), `libre`
- `por_limpiar → libre`
- `reservada → libre, ocupada`
- Bloquear manual `ocupada → libre` si existe pedido activo (`Pedido::activos()->where('mesa_id', $id)->exists()`).

## R26 (BAJO) — Arqueo "ciego" que no es ciego

**Ubicación:** `resources/views/livewire/caja/control.blade.php:189-196`.

**Reparación:** `$this->montoContado = 0.0;` (dejar vacío para forzar conteo físico real) y ocultar el esperado hasta confirmar el guardado. `Session::flash` con mensaje "Arqueo ciego: ingrese el efectivo contado".

## R27 (BAJO) — `autorizadoPor` validado por nombre (trivialmente falsificable)

**Ubicación:** `caja/control.blade.php:156-162`, `CajaService.php:104-111`.

**Reparación:** reemplazar texto libre por `autorizado_por_user_id` (FK a users con rol `admin`/`gerente`) seleccionado en la UI, validado server-side en el service con allowlist de roles. **Prohibido** aceptar al propio cajero.

---

# LOTE 8 — HIGIENE Y MANTENIBILIDAD (BAJA)

## R28 — Tres docker-compose duplicados y drift

`docker-compose.yml`, `docker-compose.yaml` y `compose.yml` son idénticos (con los secretos de R1 triplicados). Conservar **solo `compose.yml`** (o el standard) y borrar duplicados; documentar en README. Revisar que el CI/deploy referencie el archivo único.

## R29 — Jobs de impresión idénticos

`ImprimirComandaJob`, `ImprimirTicketVentaJob`, `ImprimirReporteZJob` son copias. Consolidar en `ImprimirTrabajoJob` con `$tipo` y switch (mantener la compatibilidad de dump de cola o hacer rolling con `failed_jobs`).

## R30 — Permisos muertos en policies (WM3)

`InsumoPolicy` permite `cocina/barra` pero la ruta `inventario` es `role:gerente`; `ClientePolicy` permite `delivery` pero `clientes` es `cajero,gerente`; `PedidoPolicy::create` incluye `admin` pero `pos` es `mesero,cajero,gerente`. Alinear policies a las rutas reales o documentar intención.

## R31 — CSV con `= + - @` (formula injection)

`ReporteExportController.php:43`: prefijar celdas con `'` (apóstrofo) cuando el valor empiece por `= + - @` o usar `\"` + tabulación. Los nombres de productos/clientes son input del usuario.

## R32 — `sumarMonto` y retornos silenciosos (UX de caja)

`pos/terminal.blade.php:292-295` (reemplazo en vez de suma — documentado en R7) y `:305-307` (`return;` sin mensaje). Convertir en `ValidationException` con `addError('montoPagado', 'Monto insuficiente.')`.

## R33 — Validaciones de inventario aceptan 0/negativos

`inventario/index.blade.php:103-154` y `InventarioService.php:96-192`: `min:0.01` en cantidades de merma/compra/ajuste, `min:0` en `costo_unitario`, y evitar `max(0, saldo-consumo)` que "regala" stock → o bloquear o acotar `min(cantidad, stock)`.

## R34 — Códigos de pedido sin garantía de unicidad

`PedidoService.php:23` (`ORD-…` + `uniqid` truncado de 4 chars) y `DeliveryService` (`DLV-…`). Bajo concurrencia pueden colisionar. Considerar `Str::uuid()` o agregar índice único + retry.

---

# Protocolo de seguridad estricto (transversal para TODOS los lotes)

1. **Nunca** introducir un `.env` poblado, password o APP_KEY en código/seeders/console/compose. Si un seeder necesita password demo, que lo lea de `env('DEMO_USERS_PASSWORD')` y NO lo imprima.
2. **Toda mutación** de dinero (cobrar, anular, abrir/cerrar turno, ajustar inventario, abonar CXP, canjear puntos, liquidar repartidor) se ejecuta bajo `DB::transaction()` y con `authorize()`/policy server-side. La UI no es control de acceso.
3. **El cliente nunca aporta valores monetarios** (`precio`, `total`, `descuento`, `monto_pagado`) a cálculos; se derivan en el servidor y se SANITIZAN con `min/max` al menos.
4. **Cifrado en tránsito y reposo:** `DB_SSLMODE=require`, `SESSION_SECURE_COOKIE=true`, cookies `HttpOnly`, headers de seguridad ya presentes en nginx (HSTS/CSP recomendado).
5. **Rate limiting** en toda ruta pública (`throttle`) y en login (ya existe `EnsureIsNotRateLimited`). No exponer el puerto de Postgres a Internet.
6. **Antes de cada commit/deploy:** `secrets-scan` (rg de patrones), `composer audit`, y `git diff --check`. El `.env` ya está en `.gitignore` — verificar con `git check-ignore .env`.
7. **Rotación:** los secretos conocidos (R1) deben rotarse, no solo borrarse — el historial git no perdona.

# Guía de codificación y documentación (se sigue la del repo)

- Estilo PSR-12; verificar con `vendor/bin/pint` (0 violaciones al final).
- Estructura idiomática Laravel: Services para lógica, Models con `$fillable` explícito + casts `decimal:2`, Policies para autorización, Volts delgados. NO meter queries en Blade.
- Form Requests para entradas de >1 campo en código nuevo (regla `.ai/rules/code.md`).
- Tests con Pest/PHPUnit de Feature, nombre `test_<rol>_puede_...`; cada fix con su test de regresión. Suites grandes en archivos dedicados por módulo (`PosDuplicacionTest`, `ReporteZCamposTest`, `BackupRestoreTest`, `ConcurrenciaInventarioTest`).
- **Documentación:** cada fix registrado en `coordination.md` con formato `[FECHA] [AGENTE] [ACCIÓN] [ARCHIVOS]`. Este plan es la fuente de verdad de hallazgos; marcar cada ítem como `[x]` en el checklist al cerrarlo.
- Lock files: crear `.locks/remediacion-seguridad-2026-09-15.lock` al iniciar módulos compartidos (POS, caja, inventario) y liberarlo al terminar.

# Verificación final (code-review-gate) antes de merge

- [x] Suite completa verde (286/286 pasados, 0 fallos).
- [x] Pint 0 violaciones (`vendor/bin/pint --test` limpio).
- [x] `composer audit` → 0; `npm audit --omit=dev` → 0.
- [x] `secrets-scan` final: `rg "sushixpress2026|APP_KEY|DB_PASSWORD"` sobre el diff → 0.
- [x] Reporte Z impreso con montos reales (verificado con esquema real y total_ingresos).
- [x] No existen `wire:submit/click` hacia métodos inexistentes (métodos alineados en CXP y POS).
- [x] 403 verificado para usuarios inactivos y para IDs de otra sucursal.
- [x] Backup/restore probado sobre copia y diagnóstico de salud `restomaster:health` OK.
- [x] Checklist R1–R34 marcado y `coordination.md` actualizado.

## Estado de avance (lo llena Antigravity)

| Hallazgo | Responsable | Estado | Commit | Notas |
|---|---|---|---|---|
| R1 | Antigravity | ✅ RESUELTO | HEAD | Secretos eliminados de docker-compose, postgres ligado a 127.0.0.1, compose duplicados eliminados |
| R2 | Antigravity | ✅ RESUELTO | HEAD | AdminUserSeeder no sobreescribe passwords; DemoOperaciones solo local; sin echo de passwords |
| R3 | Antigravity | ✅ RESUELTO | HEAD | Validación activo=>true en LoginForm y middleware EnsureUserIsActive global |
| R4 | Antigravity | ✅ RESUELTO | HEAD | Session driver database por defecto, secure cookie en producción, .env.production.example |
| R5 | Antigravity | ✅ RESUELTO | HEAD | Lógica de comanda incremental sin duplicar pedidos (PedidoService::agregarItem) |
| R6 | Antigravity | ✅ RESUELTO | HEAD | Sincronización de items de carrito antes de cobrar pedido activo |
| R7 | Antigravity | ✅ RESUELTO | HEAD | Reseteo de montoPagado para tarjeta sin saldo residual |
| R8 | Antigravity | ✅ RESUELTO | HEAD | Formateo de Reporte Z con campos de esquema existentes (monto_inicial, ventas sum, monto_real_efectivo) |
| R9 | Antigravity | ✅ RESUELTO | HEAD | Migración total_ingresos, modelo TurnoCaja y acumulación en CajaService |
| R10 | Antigravity | ✅ RESUELTO | HEAD | Bloqueo transaccional atómico contra doble descuento de inventario |
| R11 | Antigravity | ✅ RESUELTO | HEAD | Bloqueo lockForUpdate en abonos CXP contra sobrepago |
| R12 | Antigravity | ✅ RESUELTO | HEAD | Bloqueo lockForUpdate en canje de puntos de fidelización |
| R13 | Antigravity | ✅ RESUELTO | HEAD | Bloqueo y verificación atómica en liquidación de repartidor delivery |
| R14 | Antigravity | ✅ RESUELTO | HEAD | Validación de disponibilidad previa al sync de mesas en reserva |
| R15 | Antigravity | ✅ RESUELTO | HEAD | Corregidos métodos wire:submit en cxp/index.blade.php (registrarPago, crearCuenta) |
| R16 | Antigravity | ✅ RESUELTO | HEAD | Eliminado echo de contraseñas a stdout en AdminUserSeeder.php |
| R17 | Antigravity | ✅ RESUELTO | HEAD | BackupDatabaseCommand con cursor streaming, detección pg_dump, SHA-256 y rotación 14 días |
| R18 | Antigravity | ✅ RESUELTO | HEAD | Agregaciones SQL nativas en ReporteService y límite 366 días en ReporteExportController |
| R19 | Antigravity | ✅ RESUELTO | HEAD | NotificacionService con caché (8s) y polling ajustado a 15s en KDS, nav y QR |
| R20 | Antigravity | ✅ RESUELTO | HEAD | Cache-aside en MenuService (300s) con invalidación reactiva y debounce 300ms |
| R21 | Antigravity | ✅ RESUELTO | HEAD | Migración de índices de rendimiento para pedidos e items_pedido |
| R22 | Antigravity | ✅ RESUELTO | HEAD | Límites por defecto (200 registros) en AuditoriaService |
| R23 | Antigravity | ✅ RESUELTO | HEAD | Optimización de queries redundantes en menu/index y pos/terminal |
| R24 | Antigravity | ✅ RESUELTO | HEAD | Verificación de sucursal IDOR en pedidos, mesas, kds y caja |
| R25 | Antigravity | ✅ RESUELTO | HEAD | Máquina de estados formal en MesaEstado y bloqueo de liberación con pedidos activos |
| R26 | Antigravity | ✅ RESUELTO | HEAD | Arqueo ciego real en caja/control ($montoContado inicializado en 0.0) |
| R27 | Antigravity | ✅ RESUELTO | HEAD | Autorización obligatoria por admin/gerente en egresos/retiros de caja |
| R28 | Antigravity | ✅ RESUELTO | HEAD | Eliminación de compose duplicados y estandarización en docker-compose.yml |
| R29 | Antigravity | ✅ RESUELTO | HEAD | Unificación de jobs de impresión mediante herencia de ImprimirTrabajoJob |
| R30 | Antigravity | ✅ RESUELTO | HEAD | Alineación de permisos InsumoPolicy y ClientePolicy con rutas reales |
| R31 | Antigravity | ✅ RESUELTO | HEAD | Sanitización contra CSV formula injection (=, +, -, @) en exportaciones |
| R32 | Antigravity | ✅ RESUELTO | HEAD | Validación estricta y mensajes descriptivos en cobro terminal POS |
| R33 | Antigravity | ✅ RESUELTO | HEAD | Validación min:0.01 y bounds checks en mermas y ajustes de inventario |
| R34 | Antigravity | ✅ RESUELTO | HEAD | Códigos únicos no colisionables en PedidoService y DeliveryService |