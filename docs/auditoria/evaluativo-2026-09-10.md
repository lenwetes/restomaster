# Auditoría Integral + Evaluativo — Sushixpress (post fase-2 cierre)

- **Fecha:** 2026-09-10
- **Autor:** OpenCode (solo lectura, 4 auditores paralelos)
- **Skills aplicadas (10):** secrets-scan · laravel-security-review · authz-rbac-check · dependency-audit · skill-mcp-hygiene · config-env-guard · laravel-best-practices · performance-audit · safe-refactoring · code-review-gate
- **Objeto:** commit `6a27c7e` "cierre definitivo fase 2" (Antigravity) sobre HEAD
- **Suite verificada por auditor independiente:** **247/247 tests, 774 assertions** ✅ · **Pint 0** ✅ · `composer audit` 0 · `npm audit --omit=dev` 0

---

## 🏆 EVALUATIVO GLOBAL

| Dominio | Verificación fase-2 (prev.) | **Actual** | Justificación |
|---|---|---|---|
| Seguridad & RBAC | 6.5/10 | **7.5/10** | Acceso server-side CERRADO en $76 mutaciones de dinero/estado; remainder menores (caja `autorizadoPor` texto libre, `puntos_fidelidad` al crear, `monto>=total` no en service) |
| Integridad BD | 8.5/10 | **8.8/10** | 17/18 cascades endurecidos; 1 cascade histórico queda (`items_pedido.pedido_id`) + `eliminarMesa` roto con reservas (Alto) |
| Performance | 7/10 | **7.2/10** | POS N+1 móvil des-resuelto (:711), SQL en `ventasPorTipo`/`topClientes` ✓; 8 métodos ReporteService aún PHP; poll QR hotel |
| Calidad código | 8.5/10 | **8.6/10** | `$fillable` 100% ✓, dinero 100% `decimal:2` ✓, servicio→Volt OK; Form Requests inexistentes (regla rota), Volt gordos |
| Dependencias | 9/10 | **9/10** | 0 CVEs; `package-lock` desync (Tailwind v4/uplexhuerto) — no bloqueante |
| **Skills/MCP hygiene** | — | **9/10** | 3 skills de Terceros (semgrep r2c) seguras; MCP solo laravel-boost local; hashes lock no reproducibles |
| Tests | 9/10 | **9/10** | 247/774 verde; tests delivery/KDS/FKs genuinos; SIN tests de performance; flujo "Nuevo Pedido Manual" sin cobertura |
| **Calificación GLOBAL** | ≈7.8/10 | **≈8.0/10** | **Apto para UAT/producción piloto** tras corregir 1 bug de flujo (500) y 2 hallazgos Altos |

**Veredicto gate (code-review-gate): ⚠️ APROBADO CON CONDICIONES.** El gate de Antigravity ("LISTO PARA MERGE") es correcto en el sentido técnico (autorización end-to-end, FKs, tests, pint), pero hay **1 bug funcional bloqueante** (500 en "Nuevo Pedido Manual") y 2 hallazgos High que conviene corregir antes de producción real.

---

## 1. Afirmaciones de Antigravity (6a27c7e) — verificación

| Afirmación | Estado | Evidencia |
|---|---|---|
| `authorize('gestionarDelivery')` en mutaciones delivery | ✅ FIXED | `delivery/index.blade.php:57,66,81,93,110,141,158` |
| `authorize('liquidarRepartidor')` acotado a repartidor propio | ✅ FIXED | `delivery/index:119`; `PedidoPolicy::liquidarRepartidor:60-71`; test 403 repartidor ajeno |
| `authorize('cocinar',[Pedido,$area])` en KDS con segregación de estación | ✅ FIXED | `kds:27,42,55,67`; `PedidoPolicy::cocinar:73-88`; test barra≠sushi / cocina=sushi |
| Migración 240000 `restrictOnDelete` en 3 FKs | ✅ FIXED | `direcciones_cliente.cliente_id`, `recetas.producto_id`, `recetas.insumo_id`; down() coerente |
| Índice `pedidos(estado, estado_delivery)` | ✅ FIXED | presente en runtime PG |
| `withCount('productos')` eliminó N+1 POS | ⚠️ PARCIAL | Fijo en :952/:1275; **reabierto en :711** (píldora móvil) |
| Paginación clientes 25/pág | ✅ FIXED | `paginate(25)` + links + resetPage (falta reset en `updatedFiltroAlergias`) |
| Agregación SQL `ventasPorTipo`, `topClientes` | ✅ FIXED | `selectRaw+sum+groupBy` (contrato intacto) |
| Locks liberados | ✅ | `.locks/` solo README |

---

## 2. Hallazgos por severidad

### 🔴 BLOQUEANTE (bug funcional → 500 en producción)

| # | Hallazgo | Ubicación |
|---|---|---|
| **B1** | `guardarNuevoPedido` invoca `PedidoService::agregarItem()` que **NO EXISTE** → `Call to undefined method` (Error 500). Solo existen `crearPedido`, `crearPedidoDesdeQr`, y `DeliveryService::crearPedidoDelivery`. El flujo "Nuevo Pedido Manual" de delivery está **roto** y SIN test de cobertura. | `delivery/index.blade.php:173` vs `PedidoService.php` (sin método) |

### 🟠 ALTO

| # | Hallazgo | Ubicación |
|---|---|---|
| **A1** | `MesaService::eliminarMesa` valida solo pedidos activos y luego `$mesa->delete()` → **QueryException con cualquier reserva histórica** (`reserva_mesa` ahora RESTRICT). Además audita "eliminada" ANTES del delete → registro falso en fallo. | `app/Services/MesaService.php:96-117` |
| **A2** | `items_pedido.pedido_id` sigue **CASCADE** (única cascadeOnDelete original sin endurecer; `pedidos` NO tiene SoftDeletes → DELETE físico del pedido destruye el detalle de venta). | `180030:16` (no tocada por 220000/240000) |
| **A3** | POS **N+1 en móvil**: píldora de categorías `$cat->productos->...->count()` con categorías SIN `with('productos')` → 1 query por categoría por render. El commit afirma haberlo eliminado; solo quedó en 2 de 3 lugares. | `pos/terminal.blade.php:711` (query `:423-424` solo `withCount`) |

### 🟡 MEDIO

| # | Hallazgo | Ubicación | Fix sugerido |
|---|---|---|---|
| **M1** | `autorizadoPor` en caja es texto libre: cae un cajero oral de auto-aprobar egresos/retiros escribiendo otro nombre | `caja/control.blade.php:136,151,175`, `CajaService:104-111` | `autorizado_por_user_id` con rol gerente/admin |
| **M2** | `ClienteService::crear` acepta `puntos_fidelidad` del form (Livewire `$set` anida) → fraude de fidelización | `ClienteService.php:34` | Forzar `puntos_fidelidad=0`/`total_gastado=0` al crear |
| **M3** | `DeliveryService::marcarEntregado` no valida `montoRecibido >= total` (C.O.D. parcial); `costo_envio` sin clamp | `DeliveryService:111-138,48` | Validación en server + `max(0, costo_envio)` |
| **M4** | `PedidoService::cobrarPedido` no valida `montoPagado >= total` (solo UI lo hace) | `PedidoService:167-209` | Validación en el service |
| **M5** | 11 FKs con RESTRICT/SET NULL **sin índice** → seq-scan en cada DELETE del padre (hotspot: cliente_id, producto_id, impresora_id, turno_caja.user_id) | varias | Índices en FKs restrictivas |
| **M6** | `wire:poll.6s` del menú QR re-ejecuta `with()` categorías+productos+pedido (multiplicador con N mesas abiertas) | `mesa/menu-publico.blade.php:163-199,221` | Guard por estado / `with()` condicional |
| **M7** | ReporteService: 8 métodos materializan colecciones y suman en PHP (kpis, ventasPorPeriodo/Producto/Trabajador, comparativa, resumenPeriodo, tiemposEntrega, resumenReservas) | `ReporteService.php:86-96,138-168,173-205,248-298` | SQL agregado (`sum`/`count`/`EXTRACT`) |
| **M8** | Listas sin paginar que crecerán: insumos, cxp pendientes | `inventario:211-213`, `cxp:39-42` | `paginate`/`take` |
| **M9** | `login.blade.php:12` default `$password='123456'` + hint demo en :182 obsoleto tras H1 | `resources/views/livewire/pages/auth/login.blade.php` | Quitar default, hint dinámico |

### 🟢 BAJO

- `package-lock.json` desync (Tailwind v4 + multiplex en lock/node_modules, extraneous) → correr `npm install` (P2-05a heredado; **no bloquea** porque activo es v3 y bundle bundle correcto).
- Form Requests inexistentes (`app/Http/Requests` vacía); validación inline en 3 controllers y en Volt → incumple regla `.ai/rules/code.md`. Aceptable para módulos legacy, pendiente en nuevos.
- `#[Computed]` solo 1 uso (cxp:33); reportes/kpis/detalle inventario re-ejecutan por render → candidatos.
- Volt gordos: terminal 1745, menu 1272, inventario 1081, caja 1013, clientes 981 — mantenibilidad.
- `skills-lock.json` hashes no coinciden con SHA256 local (informativo; no evidencia de manipulación).
- `updatedFiltroAlergias` no resetea página (clientes).
- KDS conteo por área sin restringir estado del pedido (semántica difusa).
- 220000 doc dice "13 FKs" pero endurece 14 (cosmético).

---

## 3. Integridad BD — estado final de FKs (resumen)

- **Durado:** items_pedido.pedido_id → **CASCADE** ⚠️ (última heredada) · pedidos→SET NULL · el resto RESTRICT/null.
- **Endurecidas (17):** cajas, turnos_caja(×2), movimientos_caja(×2), kardex(×2), pagos_cxps, movimientos_puntos, reserva_mesa(×2), trabajos_impresion, direcciones_cliente, recetas(×2), productos.categoria(SET NULL), mesas.sucursal.

**Ingeniería:** patrones `dropForeign`+recreate corren bien en SQLite (table-rebuild nativo L13) y PG (ALTER). `IntegridadHistoricoFkTest` 12/12 verificado. **Gaps de test:** `mesas.sucursal_id`, `reserva_mesa.*`, `items_pedido.pedido_id`, SET NULLs, y `eliminarMesa` con reservas.

**SoftDeletes:** Producto/Insumo/Cliente correctos y respetados en servicios; doble mecanismo (`deleted_at`+`activo`) redundante pero sin conflicto.

---

## 4. Skills de terceros & MCP (skill-mcp-hygiene)

| Item | Procedencia | Veredicto |
|---|---|---|
| `.agents/skills/code-security/` | semgrep/r2c (oficial) | ✅ CONSERVAR — defensivo OWASP/CWE, sin exfiltración |
| `.agents/skills/llm-security/` | semgrep/r2c (oficial) | ✅ CONSERVAR — OWASP Top 10 LLM 2025, segura |
| `.agents/skills/semgrep/` | semgrep/r2c (oficial) | ✅ CONSERVAR — instala semgrep CLI, docs oficiales |
| MCP `laravel-boost` | local, sin credenciales en config | ✅ OK |
| grep exfiltración/manipulación | 100+ matches **todos defensivos** | ✅ Limpio |

---

## 5. Prioridad de acción (puesta en producción piloto)

**Inmediato (bloqueante):**
1. **B1**: Implementar `PedidoService::agregarItem` (precio desde `$producto->precio` + `max(1,cantidad)` + recalcular) o refactor a `DeliveryService::crearPedidoDelivery` con items — + test del flujo "Nuevo Pedido Manual".
2. **A1**: `eliminarMesa` con manejo de reserva histórica (bloqueo informativo o borrado lógico) + auditoría POST-éxito.
3. **A2**: `items_pedido.pedido_id → RESTRICT` + test.
4. **A3**: `pos/terminal:711` → usar `$cat->productos_count`.

**Antes de escalar volumen:**
5. **M5/M6/M7**: índices de FKs restrictivas, poll QR con guard, reportes SQL.

**Deuda técnica (siguiente sprint):**
6. M1-M4, M8-M9, desync npm, Form Requests, `#[Computed]`, dividir Volt gordos.

---

## 6. Lo positivo del estado actual

- **Control de acceso server-side real y probado** en todo flujo de dinero/estado ($76 mutaciones autorizadas; policies 8 + PedidoPolicy extendida + bypass admin).
- **Histórico financiero protegido y persistente** (17/18 endpoints de borrado endurecidos, SoftDeletes en catálogo).
- **Fórmulas de dinero unificadas** (precio desde DB, `lockForUpdate`, `abort_if pagado`, descuento puntos ≤ remanente) — sin invalidación por lado cliente.
- **Suite robusta**: 247/774, tests nuevos genuinos (authorize por rol/estación, integrity FK reales).
- **Sin secretos en repo**, `.env` ignorado, credencial DB cifrada con `Crypt`, backups validados y fuera de git.
- **Índices compuestos correctos** para POS/KDS/delivery y monetarios.

---

*Generado por OpenCode · 2026-09-10 · Auditoría con las 10 skills del proyecto · solo lectura · Lock de Antigravity liberado (`.locks/` limpio).*