# Re-auditoría Verificada — Seguridad, Rendimiento y Robustez (2026-09-15 16:55)

- **Fecha:** 2026-09-15 (segunda pasada, tras remediación L1-L3 de Antigravity + rebranding RestoMaster)
- **Autor:** OpenCode (3 subagentes paralelos solo lectura + suite completa ejecutada)
- **Objeto:** working tree actual (rebranding RestoMaster aplicado; remediación R1-R34 en curso por Antigravity)
- **Suite ejecutada por auditor:** **274/274 tests · 881 assertions · 100.9s — VERDE**
- **Tests RED entregados:** `RemediacionInfraSeguridadTest` (4/4 PASS, R1-R3 verificados), `RemediacionPosCocinaTest` + `RemediacionReporteZTest` (5/5 PASS, R5-R8 verificados)

---

## Veredicto

Los **6 bloqueantes** de la auditoría del 15-09 fueron **corregidos y verificados**:

| Hallazgo | Estado | Evidencia |
|---|---|---|
| R1 — Secretos en docker-compose | ✅ **FIXED** | `docker-compose.yml:14,26,37,59` sin valores reales, `APP_DEBUG:-false`, `AUTO_SEED:-false`; duplicados (R28) eliminados |
| R2 — AUTO_SEED resetea passwords | ✅ **FIXED** | `AdminUserSeeder.php:110-116` hace `unset(password)` si usuario existe; `autoseed` default false |
| R3 — Login sin validar `activo` | ✅ **FIXED** | `LoginForm.php:33` `'activo' => true` en `Auth::attempt` + middleware **`EnsureUserIsActive`** en stack web (`bootstrap/app.php:17-19`); también bloquea rutas sin `role:` |
| R5 — Duplicación de comanda | ✅ **FIXED** | `terminal.blade.php:270-296` reutiliza pedido activo y agrega solo el diff de items |
| R6 — Cobro ignora carrito | ✅ **FIXED** | `terminal.blade.php:366-408` mergea items antes de cobrar y valida contra el total real |
| R7 — Tarjeta con cambio residual | ✅ **FIXED** | `terminal.blade.php:248-253,410-412` resetea `montoPagado = total` al cambiar a medios no-efectivo |
| R8 — Reporte Z con campos inexistentes | ✅ **FIXED** | `ImpresionService.php:621-642` usa `monto_inicial`, `total_ventas_*`, `monto_real_efectivo`; migración `total_ingresos` creada |

---

## Hallazgos NUEVOS / pendientes que siguen abiertos

### Seguridad (verificación `laravel-security-review` + `secrets-scan`)

1. **🔴 HIGH — Cluster de password demo `restomaster2026`**
   - `AdminUserSeeder.php:28` `env('DEMO_USERS_PASSWORD') ?: 'restomaster2026'`
   - `resources/views/livewire/pages/auth/login.blade.php:15` `config('auth.demo_password', 'restomaster2026')` (botón "rellenar demo" pre-llena la clave)
   - `.env.example:66` `DEMO_USERS_PASSWORD=restomaster2026`
   - Fix: fallback a `Str::password(12)` o `null` + arranque estricto; quitar fallback de `config()`; `.env.example` con variable vacía; ocultar autocompletado en prod.

2. **🔴 HIGH — IDOR por sucursal (R24 sigue abierto)**
   - `PedidoPolicy.php:35-53`, `TurnoCajaPolicy.php:20-38`, `InsumoPolicy.php:20-48`, `CajaPolicy.php:20-34` validan solo rol, nunca `entity->sucursal_id === user->sucursal_id`.
   - `caja/control.blade.php:91-94` primer turno/caja del sistema sin filtrar por sucursal del operador.
   - `ReporteService.php:19,138,241,281` + `ReporteExportController.php:79-84` reportes/exportaciones globales (sin `sucursal_id`).
   - Fix: criterio de sucursal en policies y en queries de caja/reportes.

3. **🟡 MEDIUM — CSRF `api/*` demasiado amplio** (`bootstrap/app.php:23-25`): exceptar solo `api/reservas` (hoy la única) o lista explícita.

4. **🟡 MEDIUM — `costo_envio` sin authorize/tope** (`terminal.blade.php:299-313`, `PedidoService.php:36,42,47`): solo `max(0,...)`, un mesero puede inflar el flete. Fix: tope desde config.

5. **🟡 MEDIUM — `autorizadoPor` texto libre** (`caja/control.blade.php:151`): egreso facturable sin evidencia real (R27 parcial). Fix: `autorizado_por_user_id`.

6. **🟢 LOW — `{!! $qrSvg !!}`** (`mesas/index.blade.php:689`): `numero` valida solo `string|max:10` sin `regex` → `regex:/^[0-9A-Za-z -]+$/`.

7. **🟢 LOW — Varios menores:** `LOG_LEVEL: debug` en contenedor prod (`docker-compose.yml:18`); entrypoint.sh no valida `DB_PASSWORD` vacío ni escribe `DB_SSLMODE` (`docker/entrypoint.sh:43-54`); `.env` generado a `644`; volcado fallback de backup sin sequences/constraints (`BackupDatabaseCommand.php:108-130`); arqueo "ciego" prellenado (`caja/control.blade.php:94`).

### Lógica POS / dinero (verificación de debugging de flujo de caja)

8. **🔴 HIGH — Bug R7 mutado a `mixto`** (`terminal.blade.php:248-252,410-412`): `mixto` no está en la lista de reset; efectivo residual queda como cambio fantasma; además el modal no captura split efectivo/tarjeta del pago mixto.

9. **🔴 HIGH — Clasificación de money en `vincularCobroPedido`** (`CajaService.php:178-186`): solo distingue `efectivo` vs `tarjeta*`; **`mixto`, `datafono`, `datáfono` caen en `total_ventas_transferencia`** → Reporte Z desglose miente y el efectivo del mixto nunca suma al arqueo (sobrante falso).

10. **🟡 MEDIUM — Descuento perdido en merge de pedido existente** (`terminal.blade.php:274-296,370-391`): `$this->descuento`, `descuentoPuntos`, `puntosCanjeados`, `costo_envio` NO se escriben en el pedido existente → se cobra de más al comensal.

11. **🟡 MEDIUM — Race/merge sin `lockForUpdate`** (`terminal.blade.php:270-272,366-368` + `PedidoService.php:275`): búsqueda de pedido activo y diff/merge fuera de transacción → dos terminales pueden duplicar items/crear dos pedidos. `crearPedido` (`:20-39`) tampoco verifica pedido activo de la mesa.

12. **🟡 MEDIUM — Delivery no vincula cobro al turno** (`DeliveryService.php:110-143`): `marcarEntregado` marca `pagado` pero nunca llama `vincularCobroPedido` → ventas contra entrega sin `total_ventas_*`; además pisa `estado='entregado'` sobre `pagado` → ventas ausentes del Reporte Z.

13. **🟡 MEDIUM — Cobro sin turno abierto contabilizado como invisible** (`PedidoService.php:197-200`): si no hay `$turnoActivo`, el cobro se procesa igual y el dinero no entra a ningún reporte. Fix: exigir turno abierto antes de vender.

14. **🟢 LOW — `monto_esperado_efectivo = max(0,...)`** (`CajaService.php:216`) enmascara saldos negativos en el arqueo.

### Rendimiento (verificación `performance-audit`)

15. **🟠 ALTO — Dashboard `kpisRealtime()` materializa en PHP** (`ReporteService.php:82-134`): `Pedido::with('items.producto')->whereDate(...)->get()` + `ItemPedido->get()` + agregados PHP, se ejecuta **en cada carga del dashboard** sin caché (R18 incompleto). Fix: agregados SQL + `Cache::remember(30-60s)`.

16. **🟠 ALTO — TTL de notificaciones < intervalo poll** (R19 incompleto): `NotificacionService.php:25` `Cache::remember(8s)` vs poll 15s → casi 0 cache hits. Fix: TTL ≥ 30s o polling condicional.

17. **🟡 MEDIO — Catálogo público sin caché parcial** (R20 incompleto): `mesa/menu-publico` (`:190`) y `delivery/pedido-publico` (`:153-163`) consultan por render/tecleado sin MenuService; `delivery/index.blade.php:208` `Producto::where('activo',true)->get()` por render. POS ya cachea categorías/mesas (`terminal.blade.php:508,515`).

18. **🟢 BAJO — Índices faltantes:** `productos(categoria_id, activo)`, `movimientos_inventario(tipo, created_at)`; `reservas(estado)` standalone si se mantiene el filtro sin fecha. `ConfiguracionService::obtener()` sin caché (se llama 2× en export de reportes).

---

## Lo que quedó BIEN (verificado)

- **Sin `$guarded = []`** (0 modelos); ninguna `request()->all()` → `create/update`.
- Precios SIEMPRE de la BD (`PedidoService:58`), inventario con flag atómico dentro de la tx (`InventarioService:20-22,41`).
- `cobrarPedido` idempotente contra doble cobro: `lockForUpdate` + `abort_if(pagado)` (`PedidoService:171-172`).
- Fórmula del saldo esperado (efectivo + ingresos − egresos − retiros) consistente entre `recalcularEsperado` y Reporte Z (el arqueo coteja efectivo físico, tarjeta/transferencia no entran al cajón).
- `.env` correctamente en `.gitignore` (verificado con `git check-ignore`).
- Backup: primer intento `pg_dump -Fc -Z 9` streaming a disco + fallback cursor SQL correcto + rotación `--keep 14` + scheduler `dailyAt('03:00')->withoutOverlapping(120)` (`routes/console.php:10-14`).
- 0 N+1 severos en pantallas paginadas; `preventLazyLoading` activo fuera de producción.

## Próximos pasos recomendados (para Antigravity)

1. Cerrar los 2 🔴 HIGH de dinero (mixto residual + clasificación mixto/datafono).
2. Cerrar IDOR sucursal R24 (policies + caja + reportes) antes de despliegue multi-sucursal.
3. Eliminar cluster `restomaster2026` (seeder + login UI + `.env.example`).
4. Los alinear con lote L2-L3 restantes y re-ejecutar suite + Pint.