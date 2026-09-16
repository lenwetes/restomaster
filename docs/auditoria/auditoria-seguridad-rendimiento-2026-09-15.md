# Auditoría Integral — Seguridad, Rendimiento, Robustez y Lógica (2026-09-15)

- **Fecha:** 2026-09-15
- **Autor:** OpenCode (solo lectura; 4 subagentes paralelos + verificación manual de críticos)
- **Objeto:** HEAD `05e9f68` (rama `master`)
- **Skills aplicadas:** secrets-scan · laravel-security-review · authz-rbac-check · dependency-audit · config-env-guard · performance-audit · laravel-best-practices · laravel-best-practices
- **Suite verificada:** 253/253 tests reportados · Pint 0 · `composer audit` 0 · `npm audit --omit=dev` 0
- **Plan de remediación detallado (34 ítems R1–R34 en 8 lotes):** `docs/auditoria/remediacion-seguridad-rendimiento-2026-09-15.md`

---

## Veredicto global

La calificación ponderada original es **≈55/100** (auditoría sobre HEAD `05e9f68`). La app tiene buenos cimientos (policies server-side, precios SIEMPRE tomados de la BD, `decimal:2` en dinero, `lockForUpdate` en cobro y asignación de mesero QR, 0 CVEs, sin N+1 severos en módulos core nuevos) pero **NO estaba lista para producción con datos reales**: 6 hallazgos bloqueantes (500s, duplicación de comandas, un Reporte Z fiscal que imprime $0.00, y secretos de deploy expuestos en el repositorio).

> **🔁 RE-AUDITORÍA 16:55 (misma fecha):** tras la remediación R1-R8 de Antigravity, los **6 bloqueantes están FIXED y verificados** (274/274 tests verdes, 881 assertions). El estado ACTUAL completo con los nuevos riesgos (IDOR por sucursal, cluster demo `restomaster2026`, bug `mixto`/`datafono`, dashboard sin caché) está en **`docs/auditoria/reauditoria-2026-09-15-verificada.md`**. Aplicación sigue mejorando pero **antes de producción quedan pendientes**: IDOR sucursal (R24), dinero `mixto`/`datafono`, eliminación de credenciales demo y caché de dashboard.

---

## 1. Puntuación comparativa

| Dominio | Auditoría 2026-09-10 | **Actual (2026-09-15)** | Tendencia | Justificación |
|---|---|---|---|---|
| Seguridad infraestructura/deploy | 6.5/10 | **4.0/10** | 🔻 | Secretos reales en docker-compose (APP_KEY, DB_PASSWORD, DEMO pass), `AUTO_SEED=true` resetea passwords, `SESSION_DRIVER=file` sin volumen, sin TLS/sslmode |
| Seguridad código | 7.5/10 | **7.8/10** | 🔺+ | Orphan: sin SQLi/XSS/mass assignment; login sin validar `activo` (R3), IDOR sucursal en mutaciones (R24), CSV injection (R31), `autorizadoPor` falsificable (R27) |
| Integridad dinero | 8.0/10 | **5.8/10** | 🔻 | Reporte Z con campos inexistentes (imprime $0.00), cobro ignora carrito, doble descuento inventario fuera de tx, ingresos no suman al saldo |
| Robustez / casos límite | 7.2/10 | **5.5/10** | 🔻 | Duplicación de comandas, cobro tarjeta con cambio residual, transiciones de mesa sin máquina de estados, concurrencia CXP/fidelización/delivery |
| Rendimiento / caché | 7.2/10 | **4.2/10** | 🔻 | **Sin capacidad de caché real** (ver §4), reportes materializan en PHP, polling global 10s, backup materializa BD completa en memoria |
| Operaciones / backup | — | **3.0/10** | 🆕 | Backup no restaurable (addslashes, sin sequences), sin rotación, sin scheduler, catch silencia fallos |
| Mantenibilidad | 8.6/10 | **7.8/10** | 🔻 | 3 docker-compose duplicados, 3 jobs de impresión copiados, permisos muertos en policies, Volt gordos |
| Dependencias | 9/10 | **9.5/10** | 🔺+ | 0 CVEs composer/npm; sin paquetes nuevos sospechosos |
| **Calificación GLOBAL** | **≈8.0/10** | **≈5.5/10** | 🔻 | **NO listo para producción. 6 bloqueantes + 6 altos.** |

> El descenso NO significa que la app empeoró en 5 días: significa que la auditoría del 15-09 subió el estándar (verificó runtime/BD/deploy, no solo el diff) y encontró defectos que el evaluativo anterior no cubría: **documento fiscal roto, lógica POS corruptora de datos, y secretos en el repo**.

---

## 2. Hallazgos por categoría solicitada

### 2.1. Errores de lógica (causa raíz analizada)

| # | Hallazgo | Causa raíz | Fix | Ref |
|---|---|---|---|---|
| L1 | `enviarACocina` duplica items de pedido activo | `mount()`/`updatedMesaId()` cargan items del pedido nuevo al carrito, y `enviarACocina` SIEMPRE llama `crearPedido` (pedido NUEVO) con el carrito completo → cocina recibe comanda duplicada, inventario descuenta 2× | Reutilizar pedido activo vía `PedidoService::agregarItem` SOLO para los items nuevos | R5 |
| L2 | `procesarCobro` ignora el carrito con pedido existente | Bloque `:312-334`: si hay pedido activo se cobra el de la BD (sin items nuevos) pero `montoPagado` se valida contra `$this->total` del carrito → cargo incompleto o validación contra total que no se cobra | Merge del carrito en el pedido vía `agregarItem`; validar ALWAYS contra `$pedido->total` (DB); mover validación al service | R6 |
| L3 | Cobro con tarjeta conserva `montoPagado` residual de efectivo | `montoPagado` no se resetea al cambiar método → ticket "TARJETA, PAGÓ $100.000, CAMBIO $50.000" | En `updatedMetodoPago`/abrir modal: tarjeta→`montoPagado = total`; efectivo→ input libre | R7 |
| L4 | Reporte Z usa campos inexistentes | `ImpresionService:621-633` lee `monto_apertura`/`total_ventas`/`total_ingresos`/`monto_cierre_real` que NO existen en `turnos_caja` (reales: `monto_inicial`, `total_ventas_efectivo/_tarjeta/_transferencia`, `monto_real_efectivo`) → **doc fiscal imprime $0.00** | Mapear campos reales + `totalIngresos` | R8 |
| L5 | `wire:submit` hacia métodos inexistentes en CXP | `cxp/index.blade.php:254` (`registrarAbono`) y `:298` (`guardarCuenta`) no existen en el componente → botón muerto/500 | Renombrar a `registrarPago`/`crearCuenta` | R15 |

### 2.2. Posibles bugs

| # | Hallazgo | Severidad | Ref |
|---|---|---|---|
| `eliminarMesa` rompe con reservas históricas | `MesaService:96-117` elimina sin validar `reserva_mesa` + audita pre-delete → QueryException con reserva histórica, log falso | 🔴 Bloq | evaluativo A1 (Ya FIXED por Antigravity en 2026-09-11 según coordination) |
| `sumarMonto` reemplaza en vez de sumar | `terminal:292-295` → botón "agregar $20.000" pisa el monto escrito | 🟢 Baja | R32/R7 |
| `return;` silencioso en `procesarCobro` | `:305-307`: monto insuficiente → no hace nada sin mensaje | 🟡 Media | R32 |
| Polling global 10s | `navigation.blade.php:124` + `NotificacionService` → 4 queries/usuario/10s ininterrumpido | 🟠 Alta | R19 |
| `descuento` modal permite 0/negativos en inventario | `inventario:103-154`: `min:0.01` ausente en mermas/compras/ajustes | 🟡 Media | R33 |
| Códigos de pedido `ORD-…`+`uniqid(4)` | Colisión bajo concurrencia → 2 pedidos con mismo código | 🟢 Baja | R34 |

### 2.3. Casos límite y problemas de seguridad

| # | Hallazgo | Severidad | Ref |
|---|---|---|---|
| Secretos hardcodeados EN EL REPO | `docker-compose.yml:14,26,37,59` (×3 archivos): `APP_KEY`, `DB_PASSWORD`, `DEMO_USERS_PASSWORD=sushixpress2026`, `APP_DEBUG=true`. Robo de sesión, acceso a BD (puerto mapeado), login como admin | 🔴 Crítico | R1 |
| Auto-seed resetea contraseñas en cada boot | `AUTO_SEED=true` + `AdminUserSeeder::updateOrCreate` revierte passwords y re-inyecta demo data en prod | 🔴 Crítico | R2 |
| Login no valida usuarios `activo` | `LoginForm:33` `Auth::attempt(email,password)` sin `activo=>true` → empleado dado de baja conserva permisos | 🔴 Crítico | R3 |
| Sesiones `file` sin volumen + sin TLS | `SESSION_DRIVER=file` (perdidas en redeploy/no share entre réplicas), sin `SESSION_SECURE_COOKIE`, `sslmode=prefer` | 🟠 Alta | R4 |
| Doble descuento de inventario | `InventarioService:20-31` verifica flag `inventario_descontado` FUERA de la transacción → KDS y cobro concurrentes descuentan 2× | 🟠 Alta | R10 |
| CXP: abonos concurrentes sobrepagan | `CuentasPorPagarService:53-72` sin `lockForUpdate` sobre la cuenta | 🟡 Media | R11 |
| Fidelización: doble canje concurrente | `FidelizacionService:103-142` sin lock → puntos negativos | 🟡 Media | R12 |
| Delivery: doble liquidación repartidor | `DeliveryService:150-178` sin `lockForUpdate` ni `where('recaudo_liquidado',false)` condicional → ingreso de caja duplicado | 🟡 Media | R13 |
| Reservas: `sync` mesas antes de validar + TOCTOU | `ReservaService:94-97,196-213` | 🟡 Media | R14 |
| IDOR sucursal en mutaciones de dinero/estado | `terminal:312`, `kds:50-64`, `caja mount` operan IDs sin verificar `sucursal_id` del operador | 🟡 Media | R24 |
| Transiciones de mesa sin máquina de estados | `MesaService:30-36` permite `ocupada→libre` con comanda activa | 🟡 Media | R25 |
| Arqueo "ciego" no es ciego | `control.blade.php:189-196` precarga `montoContado` | 🟢 Baja | R26 |
| `autorizadoPor` texto libre falsificable | `control:156-162`, `CajaService:104-111` | 🟢 Baja | R27 |
| CSV formula injection | `ReporteExportController:43` sin prefijo `'` para `= + - @` | 🟢 Baja | R31 |

### 2.4. Código innecesario / deuda técnica

| # | Hallazgo | Ref |
|---|---|---|
| 3 docker-compose duplicados (`yml/yaml/compose.yml` idénticos, secretos triplicados) | R28 |
| 3 jobs de impresión copia-pega → consolidar en `ImprimirTrabajoJob` | R29 |
| Permisos muertos en policies (Insumo/Cliente/Pedido vs rutas reales) | R30 |
| Eager-load inútil `Producto::with('categoria')` en menu/index:180 | R23 |
| Volt gordos (terminal 1745, menu 1272, caja 1013) — mantenibilidad | evaluativo |
| Form Requests inexistentes (regla `.ai/rules/code.md`) | evaluativo |
| `#[Computed]` solo 1 uso; reportes re-ejecutan por render | evaluativo |
| Enums `PedidoEstado`/`TurnoCajaEstado` dead code parcial (ya migrado en fases) | evaluativo |

---

## 3. Respuestas a tus dudas

### 3.1. ¿Qué pasa cuando el disco duro está lleno?

En cadena, por orden de impacto:

1. **PostgreSQL falla en escritura** → inserts/updates lanzan `SQLSTATE[53100]: Disk full` → un cobro se ejecuta "parcial" si no está en transacción; con transacción se revierte (bien). Pero el **turno de caja abierto** puede quedar en un estado inconsistente si el fallo ocurre a medias.
2. **El backup lo empeora**: `BackupDatabaseCommand` escribe el dump en el MISMO disco lleno → el dump se corrompe o el proceso muere con `fwrite(): Write error` **que el catch de `:110-112` silencia**. Sin rotación, cada backup agota más disco (círculo vicioso: BD llena → más backups → menos espacio).
3. **Logs**: `storage/logs` y Docker layer crecen sin límite → una vez lleno, el propio error logging falla y la app queda en un estado que ni siquiera registra el motivo.
4. **Sesiones/caché en archivo**: con `SESSION_DRIVER=file`, una sesión llena el FS → logins válidos pero sesión no escrita → el cajero "pierde" la sesión sin explicación.

**Mitigaciones (ya en plan R17):** `pg_dump` streaming, rotación de N backups, `disk_free` check en HealthCheck (ya existe `:59`) + cron para monitorear, volumen separado para DB, y log rotation.

### 3.2. ¿Qué capacidad de caching tiene nuestra plataforma?

**Prácticamente 0 / 100 en capacidad efectiva.** Motivo:

- `Cache::remember` se usa **0 veces** en queries de catálogo/menú. `MenuService` re-consulta la carta completa en cada render (y el menú público por keystroke de búsqueda `.live`).
- No hay **invalidación** (`Cache::forget`) en ninguna mutación: aunque se cacheara, un cambio de precio no se reflejaría sin borrar manualmente.
- El único "caché" que existe es el de **Vista compilada** de Laravel (blade) y el **`array` store solo en tests**; en producción se usa el **archivo** (o Redis, si se configura), pero nada se almacena en él.

El polling de notificaciones (10s × 4 queries/usuario) multiplica la carga sin caché. **La capacidad efectiva de caché es ~0%** — toda mejora es ganancia neta (R19-R20: cachear catálogo 300s + invalidar, cachear resumen de notificaciones 8s, `.debounce.300ms`).

### 3.3. Problemas comunes en apps locales y en línea (relevantes aquí)

| Problema | Riesgo en tu stack | Verificación/ Fix |
|---|---|---|
| **Sesiones que se pierden** | Cierto en Docker: `SESSION_DRIVER=file` sin volumen → cada redeploy desloguea (y entre réplicas no comparten) | R4: `database`/`redis` |
| **Cookies sin flag `Secure` tras proxy/TLS** | `secure` sin default → si el proxy no termina TLS bien, la cookie vuela por HTTP o el login se rompe | R4: `SESSION_SECURE_COOKIE`, TrustProxies |
| **Postgres habla en claro** | `sslmode=prefer` → tráfico de BD capturable en la red | R4: `DB_SSLMODE=require` |
| **Relojes desincronizados (NTP)** | Con timestamps `now()` no se nota en local; en línea, 2 servidores desfasados corrompen turnos/reservas/KPI ("pedido pagado ayer") | sanity: `date` en todos los nodos + NTP |
| **Zonas horarias** | Ya corregido `America/Bogota` (coordination 2026-09-11), pero verificar que Postgres/sesiones usen el mismo TZ en prod | `set timezone` en sesión BD |
| **Queue workers caídos** | `QUEUE_CONNECTION=database` + jobs de impresión: si el worker muere, los tickets no salen y `failed_jobs` crece silencioso | `php artisan queue:monitor` / supervisor + alerta |
| **Migraciones en deploy** | No correrlas dentro de un endpoint HTTP | El entrypoint de Docker sí las corre en boot — ok si es transaccional y con backup previo |
| **Rate limiting perdido tras proxy** | Si se loguea `X-Forwarded-For` mal, Laravel ve 1 sola IP y throttlea a TODOS o a ninguno | TrustProxies correcto |
| **Reloj/polling** | `wire:poll.10s` global ≈ 360 queries/min con 15 cajas abiertas | R19: cachear + subir intervalos |

---

## 4. Resumen de severidad

| Severidad | Cantidad | IDs |
|---|---|---|
| 🔴 Críticos | 6 | R1, R2, R3, R5, R6, R8 |
| 🟠 Altos | 6 | R4, R10, R15, R17, R18, R19 |
| 🟡 Medios | 13 | R7, R9, R11, R12, R13, R14, R20, R21, R22, R24, R25, R27, R32 |
| 🟢 Bajos | 9 | R16, R23, R26, R28, R29, R30, R31, R33, R34 |

---

## 5. Prioridad de acción

1. **Sales a producción:** lotes L1 (secretos/sesiones/login), L2 (lógica POS), L3 (Reporte Z).
2. **Antes de escalar volumen:** L4 (concurrencia), L5 (bugs funcionales + backup), L6 (caché/reporte).
3. **Siguiente sprint:** L7 (IDOR/máquina de estados), L8 (higiene).

Ejecución completa con código, tests y criterios de aceptación en `docs/auditoria/remediacion-seguridad-rendimiento-2026-09-15.md` (destinatario: Antigravity). Protocolo estricto: RED→GREEN→Pint→verificación, `authorize()` server-side en todo dinero/estado, rotar secretos (el historial git no perdona), y registro en `coordination.md`.

---

*Generado por OpenCode · 2026-09-15 · Auditoría solo lectura · contrastada con suite 253/253 y verificación manual de los 6 críticos.*