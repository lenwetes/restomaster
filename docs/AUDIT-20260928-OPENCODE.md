<!-- CODIGO-INVOCACION-ANTIGRAVITY: AUDIT-20260928-OPENCODE -->
<!-- Antigravity: LEER ESTE REPORTE INMEDIATAMENTE. Código: AUDIT-20260928-OPENCODE -->

# AUDIT-20260928-OPENCODE — Reporte de Auditoría Completa del Sistema
**De:** OpenCode (Muse Spark) → **Para:** Antigravity
**Fecha:** 2026-09-28 | **Proyecto:** Sushixpress/RestoMaster (`D:\Proyectos\restomaster`)
**Stack:** Laravel 13.33 + PHP 8.3 + PostgreSQL 18 + Livewire 4.4/Volt 1.11 + Tailwind 3.4
**Skills:** laravel-security-review, authz-rbac-check, secrets-scan, config-env-guard, laravel-best-practices, performance-audit, dependency-audit, code-review-gate

## Métricas verificadas
48 modelos · 9 controladores · 36 Volt + 2 Livewire · 76 migraciones · 0 Form Requests · 32 Services · 11 Policies · 3 Enums · 18 seeders · 117 tests (105F/8U/3I) · 64 vistas · `composer audit` limpio · `pint` passed

## Calificaciones
| Área | Nota | Estado |
|---|---|---|
| Seguridad | 6.8/10 | Aceptable, fixes High pendientes |
| Código/Best practices | 7.0/10 | Bueno |
| Base de datos | 8.5/10 | Muy bueno |
| Performance/caching/rate-limit | 6.2/10 | Aceptable, N+1 reales |
| UI/UX táctil | 7.8/10 | Bueno |
| Dependencias/deploy | 8.8/10 | Excelente |
| **GLOBAL** | **7.5/10** | **Bueno — piloto con fixes, no prod desatendida** |

## Hallazgos críticos (High) — HACER YA
1. **H-01** `app/Models/Pedido.php:18-55` fillable con dinero/estado → reducir fillable, asignar en Service.
2. **H-02** `app/Models/TurnoCaja.php:16-35` + `User.php:16` fillable `totales/estado/role_id` → sacar de fillable.
3. **H-03** `livewire/caja/control.blade.php:89,106,134,151` previsualizar/reimprimir/editar ticket sin authorize ni scoping sucursal (IDOR) → `authorize('view')` + `abort_if(sucursal)`.
4. **H-05** `livewire/admin/copilot-drawer.blade.php:308` `{!! Str::markdown($msg) !!}` XSS vía LLM → sanitizar (HTMLPurifier/strip).
5. **H-08** `AuthClienteController.php:148,150` magic-link en logs/flash → eliminar.
6. **H-11** `docker-compose.yml:28,40,79,100` APP_KEY/DB_PASSWORD demo hardcodeados → vaciar + rotar.
7. **PERF-1** `pos/terminal:599,614,620` `Producto::find` en loop carrito → `whereIn()->keyBy()`.
8. **PERF-2** `pos/terminal:1327` productos `->get()` sin paginar por keystroke → `paginate(48)` + debounce.
9. **PERF-3** `caja/control:535-537`, `pos:1321` `ConfiguracionService::obtener` en loop (8-12 q/render) → `obtenerGrupo()` + `Cache::remember(300)`.
10. **PERF-4** `CrmEstadisticasService:99-116` loop query por mesero → `GROUP BY`.
11. **KDS** `cocina/kds:172-178` falta `items.producto` (N+1) → `with(items.producto.categoria)`.
12. **AUTH** `routes/auth.php:11` login sin `throttle` HTTP; `magic-verify` sin throttle → `throttle:5,1` / `10,1`.
13. **A11Y** 4 layouts con `user-scalable=no` → quitar (WCAG 1.4.4).

## Medium (esta semana)
- 0 Form Requests → crear `app/Http/Requests/`; `CrmWebhookController:44` `$request->all()` sin HMAC.
- Totales repetidos: `Pedido:155-170` vs `PedidoService:87-153` vs `Fidelizacion:125,160` vs `Caja:452,467` → unificar.
- Queries `@php` en Blade (`pos:2350`, `reservas:1476`, `mesas:1810,2227`, `proveedores:863`) → mover a `with()`.
- Índices faltantes: `productos(categoria_id,activo)`, `clientes(activo,tier,total_gastado)`, `pedidos(created_at,codigo)`.
- Policies faltantes: Producto, MovimientoCaja, MovimientoInventario, Promocion, User, Sucursal.
- Chips <44px (~35 POS) + 547 `text-[10px]` → min 44px/12px; contraste `#a89086`; `CACHE_STORE=database` → redis/file en prod.

## Benchmark mercado 2026
Toast $69+extras (contrato 24m) · Square $0/49/149+$30 KDS · Lightspeed €89-249. Propios: $0 licencia, inventario/recetas + reservas/delivery/CRM/fidelización/WhatsApp/IA nativos (ventaja), hardware agnóstico (ventaja). Gaps: sin offline mode, sin pasarela de pagos, sin soporte 24/7.

## Orden sugerido a Antigravity
1. H-01/H-02/H-03/H-05/H-08/H-11 (seguridad). 2. PERF-1/2/3/4 + KDS eager. 3. Form Requests + unificar totales + índices. 4. A11Y + throttle HTTP.
<!-- FIN-INVOCACION: AUDIT-20260928-OPENCODE -->
