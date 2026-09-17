# Re-Auditoría Verificada RestoMaster — 2026-09-17 (post-remediación Auditoría #3)

> **Metodología:** SOLO LECTURA. Misma base que la Auditoría #3 (8 dominios en paralelo) + **verificación manual en vivo** de cada fix de los 11 tasks del plan (`docs/superpowers/plans/2026-09-17-remediacion-auditoria-3.md`): lectura de código, configuración y esquema DB, no suposiciones. Suite ejecutada: **373/373 tests · 1261 assertions · VERDE (73s)**. Base: HEAD (12 commits adelante de origin/master, commits `1512b7e`→`71238bc`).

---

## 1. Estado de los 11 tasks de la remediación

| Task | Hallazgo Auditoría #3 | Fix verificado | Evidencia |
|---|---|---|---|
| 1 | `SecretResto2026!` en infraestructura/docs | ✅ **CORREGIDO** | `docker-compose.yml:31/65` usan `"${DB_PASSWORD}"` sin default; `docs/despliegue-coolify.md` sin secreto; test `RemediacionInfraSeguridadTest.php:35` ahora falla ante `SecretResto2026!`. Backups `.sql` en `storage/app/backups` git-ignored. `.env` ignorado ✓ |
| 2 | Migración timestamptz con −5h; sin timezone en conexión | ✅ **CORREGIDO** | `config/database.php:100` → `'timezone' => env('DB_TIMEZONE', 'America/Bogota')`. Migración `2026_09_17_220000_fix_timestamptz_bogota_offset` (+5h, 6 tablas) **RAN en BD dev**. Verificado en vivo: `session_tz = America/Bogota`; pedido `ORD-20260916-160240` pagado 16:17 Bogota = 21:17 UTC ✓ |
| 3 | Cache de colecciones Eloquent + poll 15s | ✅ **CORREGIDO** | `NotificacionService::obtenerResumen` cachea **arrays planos** TTL 15s (`Cache::remember`, ≥ poll); `navigation.blade.php:124` → `wire:poll.30s.visible` |
| 4 | Double-fire `procesarCobro` sin idempotency | ✅ **CORREGIDO** | Migración `2026_09_17_221000_add_idempotencia_uuid_to_pedidos` (uuid nullable UNIQUE) **RAN**; guard en `PedidoService.php:29`; `terminal.blade.php:741-761` genera/envía y `:801` limpia |
| 5 | H1: doble conteo COD (asiento/movimiento) | ✅ **CORREGIDO** | `DeliveryService::liquidarRecaudoRepartidor:178-217` ya NO crea `movimiento_caja`; solo auditoría + `recaudo_liquidado=true`. Test `Fase4ClientesDeliveryTest` endurecido (assertDatabaseMissing + `total_ventas_efectivo`) |
| 6 | IDOR sucursal (reservas/QR/caja) | ✅ **CORREGIDO** | `ReservaService:70` lanza `AuthorizationException` cross-sucursal; `PedidoService:347-348` guard mesero↔pedido; `terminal.blade.php:491/696` `abort_if(403)` mesa/otra sucursal; scoping por `sucursal_id` en caja/pos/reportes |
| 7 | KPIs materializan en PHP + `whereDate` mata índice | ✅ **CORREGIDO** | `ReporteService.php:116-117` SQL nativo `to_char(... AT TIME ZONE 'America/Bogota', 'HH24')`; rangos con `whereBetween('pagado_en', ...)` en toda la clase |
| 8 | `descuentoPuntos` sin canje aplicable | ✅ **CORREGIDO** | `PedidoService.php:85-105`: exige `puntos_canjeados >= 1` si `descuento_puntos > 0` y `cliente_id` con saldo suficiente; valida permiso de rol |
| 9 | `abrirTurno` permitía `mesero` | ✅ **CORREGIDO** | `CajaService.php:130-132`: allowlist `['cajero','gerente','admin']`, resto `AuthorizationException`. Tests actualizados (cajero autorizado en setUp) |
| 10 | Propinas sin asiento contable | ✅ **CORREGIDO** | `PedidoService.php:290-301`: `AsientoContable` `tipo=ingreso, cuenta=propinas` al cobrar con propina |
| 11 | Coordinación/cierre | ✅ **CORREGIDO** | Commits `c772306` (Pint) y `71238bc` (coordination.md) |

**Todos los tasks verificados por lectura de código/config/DB y por la suite.**

---

## 2. Puntuación comparativa (recalculo)

| Dimensión | Auditoría #3 (pre-fix) | **AHORA (verificada)** | Delta |
|---|---|---|---|
| Secretos | 45 | **95** | 🔺 +50 (Task 1; único residuo = historial git remoto pendiente de rotación) |
| Seguridad aplicación/acceso | 58 | **72** | 🔺 (Task 6 IDOR cerrado, Task 3 sin práctica peligrosa de caché, rutas `auth`+`role` confirmadas, sin `guarded=[]`, XSS limpio salvo `$qrSvg` verificado) |
| RBAC | 58 | **78** | 🔺 (Task 8 canje, Task 9 `abrirTurno`; guías `role:` en `routes/web.php:40-57` confirmadas) |
| Rendimiento | 52 | **72** | 🔺 (poll 30s visible, arrays planos cache, KPIs SQL nativo, `whereBetween` index-friendly) |
| PostgreSQL | 62 | **82** | 🔺 (timestamptz correcto + timezone conexión América/Bogota; `idempotencia_uuid` UNIQUE; índices `sucursal_id`; migraciones apply en dev) |
| Lógica / dinero | 44 | **78** | 🔺 (Task 4 idempotencia, Task 5 H1 COD, Task 8 puntos, Task 10 propinas; mecánica de turno/cobro ya endurecida en batches previos) |
| WCAG / táctil | 94 / 92 | **94 / 92** | 🟰 (sin cambios en UI en esta remediación) |
| Tests | 87 | **92** | 🔺 (373 tests · 1261 assertions vs 364 · 1238) |
| **GLOBAL (peso promedio)** | **≈64/100** | **≈78/100** | 🔺 **+14 pts** |

**Veredicto:** la remediación de la Auditoría #3 está **ejecutada y verificada**. Se cierran los 6 bloqueantes de dinero/seguridad reportados (secretos en infra, caché Eloquent, −5h timestamps, double-fire cobro, doble conteo COD, IDOR sucursal) y los de rendimiento/DB. La plataforma queda operativamente sólida para el siguiente lote de fases.

---

## 3. Hallazgos residuales (pendientes, no bloqueantes de los tasks)

| Severidad | Hallazgo | Estado |
|---|---|---|
| 🔴 Manual | `SecretResto2026!` **vivo en historial de `origin/main` y `origin/master`** (12 commits sin push) | Rotar `DB_PASSWORD` en despliegues afectados + reescribir historial remoto. Documentado como pendiente manual en `coordination.md` |
| 🟡 | `terminal.blade.php:34` `$descuentoPuntos` pública sin `#[Locked]` | El server-side (Task 8) aplica el límite al persistir; cliente puede "ver" un monto distinto pero no puede exceder saldo real |
| 🟡 | `docs/despliegue-coolify.md` actualizado con placeholder | Verificado correcto; rotar valor real en el VPS/Coolify antes de redeploy |
| ⚪ | WCAG/táctil no re-auditados visualmente en esta pasada (sin cambios en UI) | Scores heredados de Auditoría #3 (94/92) |

---

## 4. Métricas

- **Suite:** 373/373 tests · 1261 assertions · 73s · **VERDE**
- **Pint:** 0 violaciones (`pint --test` sobre tests modificados y suite completa)
- **composer audit:** 0 advisory · **npm audit --omit=dev:** 0 vulnerabilidades
- **Secretos:** 0 en working tree (solo referencias históricas en docs/coordination y valores de test intencionales)
- **DB:** `migrate:status` limpio (210000, 220000, 221000 = Ran); conexión con timezone América/Bogota

**Conclusión:** **≈78/100 — ALTO.** Remedición Auditoría #3 confirmada: todos los tasks verdes, suite completa, sin dependencias vulnerables, secretos fuera del árbol. Única acción manual pendiente: rotar la contraseña comprometida en el historial remoto antes de cualquier push/deploy público.