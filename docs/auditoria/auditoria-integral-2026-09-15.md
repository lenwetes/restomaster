# Auditoría Integral RestoMaster — 2026-09-15 (Verificada sobre working tree post-remediación)

> **Metodología:** SOLO LECTURA. 4 auditores en paralelo (seguridad, lógica/bugs, casos límite/concurrencia, rendimiento/deps) + **verificación manual** de los hallazgos críticos por el auditor principal (lectura de código, no suposiciones). Suite ejecutada: **286/286 tests · 920 assertions · VERDE (68s)**. Base: working tree actual (HEAD `05e9f68` + remediación R1–R8 y rebranding de Antigravity, **nada commiteado aún**).

---

## 1. Puntuación comparativa

| Dimensión | 2026-09-10 (≈8.0/10, pre-features) | 2026-09-15 15:10 (pre-remediación, HEAD 05e9f68) | **2026-09-15 AHORA** (working tree c/R1-R8) | Tendencia |
|---|---|---|---|---|
| Seguridad infraestructura/deploy | 6.5 | 4.0 | **7.5** | 🔺 (secretos fuera de compose, `AUTO_SEED` off, sesiones `database` y no `file`) |
| Seguridad aplicación/acceso | 4.5 | 4.0 | **5.0** | 🔺 leve (login `activo`, `EnsureUserIsActive`, honeypots/throttle, webhook token) |
| Lógica de negocio / dinero | 7.5 | 4.0 | **5.5** | 🔺 (R5-R8 corregidos) |
| Casos límite / concurrencia | 6.5 | 4.5 | **5.5** | 🔺 parcial (`lockForUpdate` en cobro/canje; persisten races en turnos/caja) |
| Rendimiento / caching | 8.5 | 3.0 | **4.0** | 🔺 (R17 backup streaming OK; caché de modelos rota en prod) |
| Código / dependencias | 8.5 | 6.5 | **7.5** | 🔺 (Pint 0, arquitectura Services limpia; dead-code menor) |
| Pruebas | 8.5 | 8.0 | **8.5** | 🟰 (286/920; faltan tests de los gaps de dinero) |
| Operación / robustez | 7.0 | 4.5 | **6.0** | 🔺 (scheduler+supervisord+backup rotado; sin alertas ni purgas) |
| **GLOBAL (peso promedio)** | **≈8.0/10** | **≈55/100** | **≈62/100** | 🔺 **+7 pts vs pre-remediación** |

**Veredicto:** la remediación R1–R8 mejoró infraestructura y arregló los bugs transaccionales que rompían la operación diaria (comandas duplicadas, cobro que ignoraba carrito, Reporte Z con campos inexistentes). **Pero la plataforma NO está lista para producción con datos reales multi-caja/multi-sucursal**: hay un cluster de defectos de caja (turno global, cobro invisible sin turno, lost-update, doble cierre, clasificación de pagos) que hace que **el arqueo no cuadre** en cuanto hay ≥2 cajeros o ≥2 cajas, y un defecto de caché que **rompe producción** con store persistente.

---

## 2. Respuestas a las preguntas operativas

### 2.1 ¿Qué ocurre cuando el disco duro está lleno?

Superficies que llenan el disco y su comportamiento actual:

| Superficie | Estado actual | Qué pasa al llenarse |
|---|---|---|
| `storage/logs/laravel.log` | Crecimiento sin límite; `LOG_LEVEL` sigue en `debug` en compose por retrocompatibilidad | PHP muere en silencio al no poder escribir; la app falla sin explicación |
| `storage/app/backups/*.dump` | BackupDatabaseCommand **ya rota** a 14 copias (`--keep=14`) | OK, pero si el dump se trunca a mitad por disco lleno, **no se verifica** (`pg_dump` en streaming, sin chequeo por carácter ni `pg_restore --list`) → backup corrupto reportado como *exitoso* |
| `auditorias` (tabla) | Crece por cada operación auditable **sin política de retención/purga** | Consultas lentas y BD inflada |
| `trabajos_impresion` | `contenido_raw` (tickets) acumulado si la cola se atasca o el worker muere; sin purga | DB crece con tickets huérfanos |
| Sesiones (`sessions` en BD, driver `database`) | Correcto: sesiones en BD, no en archivo (R4 corregido) | Sin impacto de disco |
| Cache (`database`/`file`) | `CACHE_STORE=database` (o `file` en compose) | Oleadas de lecturas/escrituras compiten por el mismo disco |

**Consecuencia práctica:** el peor caso es que **un backup truncado + masa de logs/auditorías compitan por el 100% del volumen**. No hay alerta: `restomaster:health` **solo reporta**, no dispara nada.
**Fix mínimo:** `LOG_LEVEL=warning` en producción, validar `disk_free_space()` y el exit code del dump (verificar integridad `pg_restore --list` o `tail` del stream), comando de purga de `auditorias` (>6 meses) y `trabajos_impresion` (>30 días), y alerta de umbral de disco en HealthCheck.

### 2.2 ¿Qué capacidad de caching tiene la plataforma?

Inventario real de `Cache::remember` en el working tree (verificado en código):

| Punto | Archivo:línea | Qué guarda | TTL | Store objetivo | Estado |
|---|---|---|---|---|---|
| Notificaciones | ~~NotificacionService~~ | — | — | — | **Sin caché**: quedó removido tras el incidente de `__PHP_Incomplete_Class` (ver `coordination.md:10`) → **4 queries por render + por `wire:poll.15s` por usuario** |
| Menú público | `MenuService.php:23` | `Collection` Eloquent | 300s | database/file | 🔴 **ROTA en prod** (ver defecto P1) |
| Categorías POS | `terminal.blade.php:520` | `Collection` Eloquent | 60s | database/file | 🔴 **ROTA en prod** |
| Mesas POS | `terminal.blade.php:527` | `Collection` Eloquent | 30s | database/file | 🔴 **ROTA en prod** |
| Catálogo menú QR | `menu-publico.blade.php:172,190` | — | — | — | Sin caché (consulta directa por render) |
| Catálogo delivery | `pedido-publico.blade.php:220+`, `delivery/index:208` | — | — | — | Sin caché (consulta directa por render) |
| Dashboard KPIs | `ReporteService.php:82-134` | — | — | — | **Sin caché**: materializa `Pedido::with('items.producto')` del día en cada GET `/dashboard` (2-4 MB de modelos) |
| Reportes | `reportes/index.blade.php:18-44` | — | — | — | SQL agregado eficiente pero sin caché por pestaña |

**Conclusión de capacidad de caching:** **≈0% efectivo en producción.** Las 3 entradas que usan caché guardan **modelos Eloquent**, que con `config/cache.php:134` (`serializable_classes => false`) producen `__PHP_Incomplete_Class` al leer desde un store persistente (`database`/`file`) — el mismo accidente que ya ocurrió con Notificaciones y está documentado como regla del proyecto (`.ai/rules/services.md`). En `array` (tests / dev) funcionan porque `serialize=false`; por eso la suite no lo detecta. **Con 2-3 sucursales y 10-20 terminales, toda carga operativa (lógica + caché + sesiones + colas) cae en la misma PostgreSQL.**
**Fix mínimo:** cachear solo datos planos (IDs/counts) con TTL ≥ poll (20-30s), `kpisRealtime` con agregados SQL + `Cache::remember(...,30,...)`, catálogo público reutilizando `MenuService` (ya invalida), y Redis para caché/sesión/colas.

### 2.3 Problemas comunes en apps online vs local

| Problema | ¿Aplica? | Estado |
|---|---|---|
| Sesiones perdidas en redeploy por driver `file` sin volumen | ✅ (era R4) | **CORREGIDO**: `SESSION_DRIVER=database` como default en compose, `.env.example` y `.env.production.example` |
| Workers de cola caídos → tickets que no imprimen y `failed_jobs` creciendo silencioso | ✅ | `QUEUE_CONNECTION=database`, un solo worker vía superviseord; **sin monitorización/alerta** (el `wire:submit` de ticket depende de la cola) |
| Scheduler que nunca corre sin cron | ✅ | **RESUELTO**: `routes/console.php` con `withoutOverlapping`/`onOneServer` + supervisord; solo falta en setups no-Docker |
| Caché que funciona en local y revienta en línea (store `array` vs persistente) | ✅ | **ABIERTO (P1)**: es exactamente este caso; la suite en `array` no lo detecta |
| `APP_DEBUG=true` fugado en línea | ✅ | **CORREGIDO**: compose con `APP_DEBUG:-false` |
| Tiempos: `now()` vs `created_at`, timezone | ✅ | `APP_TIMEZONE=America/Bogota` + Carbon locale `es` (corregidos 09-11); revisar si `turnos_caja.apertura_en` compara correctamente con `pagado_en` (cobros "de otro día" al cerrar turno pasada la medianoche) |
| Colisión con puertos/BD locales (Postgres nativo vs contenedor) | ✅ local | Ya resuelto en deploy (puerto 5434, `pg_isready`) |
| File-lock de Windows con compilación Volt al correr suites dos procesos a la vez | ✅ local | Documentado 09-09 (error transitorio "Class contents not found"); correr suites en secuencia |
| Honeypots/rate-limit en público | ✅ | Presentes en delivery y reservas web; menú QR tiene throttle de ruta |
| Logs/backups que llenan el disco | ✅ | **ABIERTO (sección 2.1)** |

---

## 3. Hallazgos por categoría

> Severidad: 🔴 bloqueante / 🟠 alto / 🟡 medio / ⚪ bajo. Referencias verificadas por lectura de código.

### 3.1 Errores de lógica (dinero y estados)

- **🔴 D1 — Cobro sin turno abierto queda invisibilizado para siempre.** `app/Services/PedidoService.php:200-203`: si no hay `TurnoCaja` abierto, `cobrarPedido` **cobra igual** y el pedido queda `pagado` con `turno_caja_id = null`. `CajaService::generarReporteZ` arma todo desde `$turno->pedidos()` (`:277`) → esa venta **jamás aparece en ningún arqueo ni Reporte Z**; nadie podrá cuadrar esa caja. *Fix: exigir turno abierto (o crearlo) antes de cobrar.*
- **🔴 D2 — Lost update en acumuladores del turno.** `CajaService::vincularCobroPedido:181-211`: lee `total_ventas_*` y hace `save()` **sin `lockForUpdate()`** del turno. Con dos cobros concurrentes al mismo turno, el segundo sobreescribe el total con base a una lectura vieja → el turno queda con menos ventas de las reales → sobrante en caja. *Fix: `TurnoCaja::whereKey(...)->lockForUpdate()` dentro de la transacción o UPDATE atómico por método.*
- **🔴 D3 — Re-cobro y doble acumulación de puntos en delivery.** `DeliveryService::marcarEntregado:116-139`: ejecuta `update(estado='entregado')` **antes** del chequeo `if ($metodoPago && $pedido->estado !== 'pagado')`. Como el modelo ya quedó en `entregado`, la condición **siempre es verdadera** → un pedido ya pagado online (webhook) que se vuelve a marcar con método de pago: **se re-cobra**, se sobreescriben `metodo_pago/monto_pagado` y `acumularPuntosPorPedido` **suma puntos dos veces**. *Fix: capturar estado del DB (o `refresh()`) antes del `update`, y usar `lockForUpdate`.*
- **🟠 D4 — Clasificación de pagos incorrecta distorsiona el arqueo.** `CajaService:186-194`: clasifica con `if efectivo / elseif tarjeta* / else → transferencia`. El POS envía `mixto`, `datafono`, `datáfono`, `nequi`, `daviplata`, que caen en el `else` → un datáfono de $150.000 sale como "transferencia" en el Reporte Z; `terminal.blade.php:249-254` tampoco incluye `mixto` en el reset de `montoPagado`. *Fix: mapear `datafono/datáfono → tarjeta`, desglosar `mixto`, campo dedicado para billeteras.*
- **🟠 D5 — Merge del carrito pierde el descuento manual.** `terminal.blade.php:280-303` (`enviarACocina`) y `:382-404` (`procesarCobro`): en la rama de pedido existente solo se agregan items con `diferencia > 0`; **nunca se persisten `descuento`, `descuento_puntos`, `puntos_canjeados`, `costo_envio`** del nuevo carrito → el cliente paga el total viejo (sin el descuento aplicado en pantalla). *Fix: actualizar esos campos al mergear y recalcular totales.*
- **🟠 D6 — Reducciones/eliminaciones del carrito no se persisten → sobrecobro.** `terminal.blade.php:284-294, 386-403`: solo agrega la diferencia positiva; si el mesero reduce 2→1 items, `pedido->total` sigue en $100.000 mientras el modal muestra $50.000 → el cobro se hace contra el total de BD. *Fix: sincronizar cantidades (mínimo 1) o marcar items a descartar.*
- **🟠 D7 — "Cambio fantasma" por canje de puntos después de fijar `montoPagado`.** `terminal.blade.php:423-438`: para tarjeta se fija `montoPagado = pedido->total` (pre-canje) y **luego** `canjearPuntos` baja `total` vía `recalcularTotales()` (FidelizacionService:150). `cobrarPedido` calcula `cambio = montoPagado - total` → entrega un "cambio" inflado en efectivo sin haberlo dado. Además canje y cobro no son atómicos: si el cobro falla, los puntos ya quedaron canjeados. *Fix: canjear antes de fijar el monto, en la misma transacción.*
- **🟠 D8 — Doble apertura y doble cierre de turno por carreras.** `CajaService::abrirTurno:51-57` (read-check-create sin `lockForUpdate` y **sin índice único parcial** `(caja_id) WHERE estado='abierto'`) y `cerrarTurno:232-249` (chequeo de estado sobre lectura sin lock + `update` sin condición `where estado`) → dos turnos abiertos para la misma caja, o **doble asiento de sobrante/faltante y doble Reporte Z**. *Fix: índice único parcial + lock pesimista + UPDATE condicional con validación de filas afectadas.*
- **🟡 D9 — `montoPagado` sin tope ni validación de número finito.** `terminal.blade.php:427-431` y `getCambioProperty:239`: solo exige `>= total`; un operador puede escribir $99.999.999 y el sistema entrega un "cambio" gigante. *Fix: `montoPagado > 0`, `is_finite`, tope razonable.*
- **🟡 D10 — Descuento por puntos evadible.** Prop `descuentoPuntos` pública (`terminal.blade.php:31`) sin `#[Locked]` ni re-hook `updatedDescuentoPuntos`; `PedidoService::crearPedido:46-53` solo acota el descuento, la validación contra los puntos reales del cliente corre solo si `puntosCanjeados>0`, y `canjearPuntos` es evadible vía `$set`. *Fix: recálculo server-side desde `cliente->puntos_fidelidad`.*
- **🟡 D11 — Reporte Z no integra `descuento_puntos` ni `costo_envio`.** `CajaService::generarReporteZ:279-281`: `total = subtotal − descuento − descuento_puntos + envio`; el reporte solo muestra `descuento` → no cuadra la igualdad `subtotal − descuentos = ventas`. *Fix: desglosar ambos campos.*
- **🟡 D12 — Redondeo inconsistente de puntos.** `FidelizacionService:48` (`floor(total/10000)`) vs `terminal.blade.php` `canjearPuntos` (`ceil`): el cliente "ve" más puntos de los que recibe en el borde de cada $10.000.

### 3.2 Posibles bugs

- **🔴 A3 — Guard de sucursal roto + SQL error en KDS.** La tabla `pedidos` **no tiene** `sucursal_id` (verificado en schema y migraciones: solo existe en `mesas`, `cajas`, `reservas`, `users`). `kds.blade.php:88-90` ejecuta `where('sucursal_id', auth()->user()->sucursal_id)` cuando el usuario tiene sucursal → **SQLSTATE 42703 → pantalla de cocina 500**. Los guards `kds.blade.php:42,52,67` y `terminal.blade.php:281,383` comparan `$pedido->sucursal_id` que siempre es `null` → **son no-op** (el único que funciona real es el de mesa `:273`, porque `mesas.sucursal_id` sí existe). En **delivery no hay ningún check** → cross-sucursal sin restricción. *Fix: migración `pedidos.sucursal_id` + backfill + heredar sucursal de mesa/cliente + scoping en queries/policies.*
- **🟠 D15 — `liberarMesas` salta el guard de comandas activas.** `ReservaService:226-233`: libera a `LIBRE` con solo `estado in (RESERVADA, OCUPADA)`, sin verificar `Pedido` activo de la mesa (el guard equivalente sí está en `MesaService::cambiarEstado`). Un comensal que ya pidió (comanda en cocina) cuya reserva se cierra → mesa `LIBRE` con comanda viva. *Fix: exigir pedido no activo o delegar en `MesaService`.*
- **🟠 D16 — Doble booking de mesa en reservas (TOCTOU).** `ReservaService:93-111` confirma con `lockForUpdate` solo sobre la propia reserva; `verificarDisponibilidad` lee mesas ocupadas sin lock compartido → dos confirmaciones concurrentes asignan la misma mesa.
- **🟡 D14 — Lost update en puntos del cliente.** `FidelizacionService::acumularPuntosPorPedido:53-86` y `ajustarPuntos:160-184` leen y escriben `puntos_fidelidad/total_gastado/visitas_count` sin `lockForUpdate` (a diferencia de `canjearPuntos:104` que sí lo usa) → dos cobros simultáneos de un mismo cliente pierden puntos.
- **🟡 D17 — Inventario marcado "descontado" sin haber descontado.** `InventarioService:16-40`: pone `inventario_descontado=true` antes de verificar receta; si el producto no tiene receta, el retorno `false` ya dejó el flag puesto → el item jamás se descuenta cuando se agregue la receta.
- **🟡 D18 — `marcarItemEntregado`** (`PedidoService:151-165`): si el `estado` ya era `'entregado'` y todos los items `entregado`, no pasa a `pagado` — correcto; pero un KDS que entrega items de un pedido luego cobrado por terminal, y la mesa se libera por `cobrarPedido` — sin conflicto. *(Verificado sano.)*
- **🟡 F-14 — Gestión de trabajadores:** `TrabajadorService:111-115` arroja "no puede desactivarse a sí mismo" para **cualquier** admin (compara `isAdmin()` en vez de `id !== auth()->id()`) → impide desactivar admins y el mensaje engaña.
- **⚪ F-19 — `ClienteService::crear:36`** escribe `visitas_totales` que no existe (columna real `visitas_count`) → se descarta silenciosamente. Dead code.

### 3.3 Casos límite / problemas de seguridad

- **🔴 A1 — Credenciales demo conocidas y commiteadas.** `AdminUserSeeder.php:28` (`env('DEMO_USERS_PASSWORD') ?: 'restomaster2026'`), `.env.example:66` con el valor real, `login.blade.php:15` fallback `'restomaster2026'`. Cualquiera que clone el repo conoce la clave del admin. **CWE-798.** *Fix: env obligatorio, rotar credenciales, autocompletar solo si `config('auth.demo_password')` no es null (ya está planeado en el plan de reparación).*
- **🔴 A2 — `costoEnvio` manipulable en el checkout público.** `delivery/pedido-publico.blade.php:26` (`public float $costoEnvio = 8000.0` sin `#[Locked]`), `:164` `$total = $subtotal + $this->costoEnvio` **sin clamp** y sin regla de validación (solo nombre/tel/dirección/método, `:126-135`). Un cliente puede `$set('costoEnvio', 0)` o negativo vía Livewire → paga sin flete o con total menor al subtotal. **CWE-204.** *Fix: tarifa fija server-side.*
- **🟠 A5 — Auto-autorización por nombre en egresos.** `CajaService:104-118`: valida que `autorizadoPor` no sea el propio cajero y que el nombre exista como admin/gerente — **pero si el nombre no existe en la BD, no bloquea** → un cajero escribe un nombre inventado y se auto-aprueba el egreso. *Fix: aprobación con `User` autenticado.*
- **🟠 A6 — Reportes y export sin filtro de sucursal.** `ReporteService:23,141,160,175,204,246` y `ReporteExportController::datos`: finanzas de todas las sucursales visibles/exportables para cualquier gerente.
- **🟠 A8/M6 — Menú QR público sin límite de envío ni validación de longitudes.** `mesa/menu-publico.blade.php:109-146`: comandas ilimitadas contra la cola de cocina (throttle de ruta existe, pero sin honeypot ni límite de items).
- **🟡 A9/R12 — Códigos `DLV-` colisionan.** `pedido-publico.blade.php:175`: `'DLV-'.substr(uniqid(),-5)` (~1M códigos) → en ~1.200 pedidos hay >50% de probabilidad de colisión; `codigo` es `unique` → 500 sin manejo.
- **🟡 A10 — `mesa.numero` sin `unique (sucursal_id, numero)`** → QR `/m/{numero}` ambiguo entre sucursales.
- **🟡 R14 — Cantidades sin tope en público y POS.** `items_pedido.cantidad` int sin CHECK; `PedidoService` solo hace `max(1, ...)` → cantidades gigantes desbordan `NUMERIC(10,2)` o generan totales absurdos.
- **🟡 R15 — Impresora "virtual" duplicada** en cada comanda sin impresora (`ImpresionService:40+` crea nueva por llamada).
- **⚠️ Verificado sano:** `cobrarPedido` con `lockForUpdate` + `abort_if('pagado')` (H5); `canjearPuntos` con lock; `liquidarRecaudoRepartidor` idempotente (`recaudo_liquidado=false` + lock); FKs financieras `restrict`/`nullOnDelete` e índices de auditoría agregados (`2026_09_15_170000`); webhooks con `hash_equals` + throttle; CSV con protección anti-fórmula; `{!! $qrSvg !!}` seguro (SVG de BaconQrCode); sin `$guarded=[]`; `preventLazyLoading` en no-prod; login rate-limited + `Session::regenerate()`; register deshabilitado.

### 3.4 Código innecesario / deuda técnica

- ⚪ **Enums muertos:** `app/Enums/TurnoCajaEstado.php` (0 referencias; el código usa strings `'abierto'/'cerrado'`) y `PedidoEstado::SOLICITADO_QR` (se usa el string crudo en `PedidoService:225`). `PedidoEstado::EN_PROCESO` sí se usa en `Pedido::scopeEnCocina`.
- ⚪ **`ClienteService::crear` escribe `visitas_totales` inexistente** (ver F-19).
- ⚪ **package.json**: `concurrently` declarado y sin uso; **lockfile desincronizado** (el `package-lock.json` arrastra Tailwind v4.3.3 y `@tailwindcss/vite`/`@tailwindcss/oxide` huérfanos que no están en `package.json`; `npm ci` puede fallar). Regenerar con `npm install --package-lock-only`.
- ⚪ **Caché de NotificacionService removida** — correcto como parche, pero quedó la deuda de cachear datos planos (sección 2.2).

---

## 4. Acciones prioritarias (quick wins)

1. **P1** (caché Eloquent → `__PHP_Incomplete_Class` en prod) — cachear scalars/IDs o `Cache::put` de JSON; es *quick win* y bloqueante de cualquier deploy.
2. **D8** (índice único parcial `(caja_id) WHERE estado='abierto'` + locks en apertura/cierre) — 1 migración + 10 líneas; elimina la familia de races de caja.
3. **A1** (rotar credenciales demo + quitar fallbacks) — bloqueante de seguridad.
4. **A2** (costo envío fijo server-side) — 5 líneas.
5. **D3** (guard de `marcarEntregado` con estado previo) — 3 líneas; evita re-cobro y puntos duplicados.
6. **D1/D2/D4** (turno obligatorio + lock de turno + clasificación `datafono/mixto`) — núcleo para que el arqueo cuadre con ≥2 cajas.
7. **A3** (migración `pedidos.sucursal_id` + guards reales + scoping KDS/delivery) — prerequisito de multi-sucursal.
8. **D5/D6/D7** (merge del carrito: persistir descuento, sincronizar cantidades, canje antes de `montoPagado`) — son los bugs que el mesero ve en el día a día.
9. **P2/P3** (kpisRealtime SQL + TTL adecuado, notificaciones con TTL ≥ poll).
10. **Backups/DC** (verificación de integridad post-dump + alerta de disco + purgas). Ver `docs/auditoria/plan-reparacion-riesgos-nuevos-2026-09-15.md` (8 tareas TDD) que ya cubre D4/D5 (partes), A1-A3 y P2/P3/P4.

---

## 5. Métricas

- **Suite:** 286/286 tests · 920 assertions · 68s · **VERDE** (incluye `AuditoriaLote*` de Antigravity y `Remediacion*` de OpenCode).
- **Pint:** 0 violaciones (no re-ejecutado en esta sesión; sin cambios de código).
- **N+1:** no se detectaron severos en pantallas críticas (eager loading + `preventLazyLoading`).
- **Índices faltantes:** cubiertos los críticos por `2026_09_10_*` y `2026_09_15_170000`; pendiente índice único parcial de turnos (D8) y `(sucursal_id, numero)` en mesas (A10).

**Conclusión:** **≈62/100 — MEDIO.** Avance real vs los 55/100 previos y muy lejos del 8.0/10 de la fase 2 (menos features, sin fecha real de producción). Los bloqueantes de hoy son dinero/caja y caché, no deploy/infra. La suite 286/920 no cubre esos gaps: **se recomienda añadir tests RED por cada bug de la sección 3 antes de mergear** (varios ya están en el plan de reparación).