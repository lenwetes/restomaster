# Auditoría Integral — Sushixpress (2ª pasada)

- **Fecha:** 2026-09-10 (segunda pasada de re-verificación)
- **Agente:** OpenCode
- **Modo:** Solo lectura. 4 auditores paralelos re-verificaron todos los hallazgos de la 1ª pasada contra el código actual (~6.000 líneas cambiadas desde entonces).
- **Código relevado a v1 reporte:** se aplicó remediación parcial (ver §2). Suite actual reportada por Antigravity: **213 tests / 724 assertions** (match histórico del audit, no re-ejecutada por no interferir el entorno).
- **Stack:** Laravel 13.31 · PHP 8.3 · Livewire 4.4.4 · Volt 1.11.2 · PostgreSQL 18 (dev)

---

## 1. Calificativos (0–10) — comparativa entre pasadas

| Dominio | 1ª pasada | **2ª pasada** | Cambio |
|---|---|---|---|
| Seguridad | 4/10 | **5.5/10** | C1, H5 y M2 corregidos con tests; C3 (authorize) y H1–H4/H6–H7 persisten |
| Integridad BD | 7/10 | **7.5/10** | H9/H10 y soft deletes corregidos; cascades de dinero siguen (C4 parcial) |
| Performance | 6/10 | **6/10** | Sin remediación; nuevo punto caliente `wire:poll.4s` en menú QR |
| Calidad de código | 8/10 | **8.3/10** | `is_active` corregido, pruebas de dinero nuevas, pint 0 |
| Dependencias | 9/10 | **9/10** | Sin cambios |
| Tests | 8/10 | **8.5/10** | `SeguridadDineroAuditoriaTest` + suites nuevas (38 archivos) |
| **Global** | **≈6.8/10** | **≈7.2/10** | **Sigue sin apto para producción** hasta cerrar P0 restante |

---

## 2. Remediación confirmada entre pasadas (FIXED)

| Hallazgo | Evidencia |
|---|---|
| **C1** Precio unitario desde DB (ignora payload cliente) | `app/Services/PedidoService.php:48` + test `SeguridadDineroAuditoriaTest::test_c1_precio...` |
| **H5** Idempotencia de cobro (`lockForUpdate` + `abort_if('pagado')`) | `PedidoService.php:159-160` + test `test_h5_idempotencia` |
| **M2** Fórmula total unificada `subtotal + envío − descuento − puntos` | `PedidoService.php:67-68` + test `test_c2_y_m2_formula...` |
| **H9** FK real `cuentas_por_pagar.insumo_id` (`nullOnDelete` + índice) | `2026_09_10_200000_harden_db_integrity_audit_fixes.php:34-38` |
| **H10** SoftDeletes en `productos`/`insumos`/`clientes` (migración + `use SoftDeletes` en los 3 modelos) | `harden:15-31`, `Producto.php:14`, `Insumo.php:13`, `Cliente.php:12` |
| **L1** Test `'is_active'` → `'activo'` | `NotificacionesRbacTest.php:61,70,79,88,103,133` |
| **L3** `.gitignore` cubre `*.sqlite/*.sql/*.bak` | `.gitignore:28-31` |
| Índices de rendimiento para POS/KDS/delivery | `2026_09_10_120000_add_performance_indexes`: `(mesa_id,estado)`, `(estado,created_at)`, `(tipo,estado_delivery)`, `(estado_cocina,area_cocina)`, `(pedido_id,inventario_descontado)` |
| `configuraciones` unique compuesto `(grupo, clave)` | `200000` correcto semánticamente |

---

## 3. Hallazgos vigentes por severidad

### 🔴 CRITICAL (bloquean deploy)

| # | Hallazgo | Ubicación actual | Estado |
|---|---|---|---|
| C3 | **Cero `authorize()`/policies en mutaciones de dinero/estado** (cobro, caja, inventario, cxp, puntos, reservas). No existe `app/Policies`; la defensa sigue siendo middleware de ruta + ocultar botón en UI | `caja/control.blade.php:55,65,101,136,177`, `pos/terminal.blade.php:215,279`, `clientes/index.blade.php:107,169`, `inventario:135`, `cxp:60` | **STILL PRESENT** |
| C4 | **12 FKs `cascadeOnDelete` intactas sobre histórico**: `items_pedido.producto_id`, `movimientos_inventario.insumo_id`, `cajas.sucursal_id`, `turnos_caja.caja_id/user_id`, `movimientos_caja.turno_caja_id/user_id`, `recetas.producto_id/insumo_id`, `pagos_cxps.cuenta_por_pagar_id`, `direcciones_cliente.cliente_id`, `movimientos_puntos.cliente_id`, `reserva_mesa.*`. `forceDelete()` o DELETE a nivel DB de **sucursal/caja/turno/usuario** sigue arrasando historial financiero (esos modelos NO tienen soft deletes) | migraciones 174736:16, 180010:16, 180030:16-17, 191010:16-17, 191020:16, 190000:16, 190010:16-17, 190020:16-17, 193510:13, 194010:16, 194020:16, 200020:12-13 | **PARTIAL** (solo mitigado: catálogo con soft deletes) |

### 🟠 HIGH

| # | Hallazgo | Ubicación | Estado |
|---|---|---|---|
| C2 | Descanso PARCIAL: descuento acotado a subtotal y total unificado ✓; **`descuento_puntos` sin acotar a subtotal y sin permiso/`authorize`** en flujo descuento/puntos | `PedidoService.php:67-68` | **PARTIAL** |
| H1 | Seeder admin `Hash::make('123456')` en 7 usuarios | `AdminUserSeeder.php:28` | **STILL PRESENT** |
| H2 | Password por defecto `'secret'` en alta de trabajador | `TrabajadorService.php:44` | **STILL PRESENT** |
| H3 | Ruta mesas `role:mesero,cajero,gerente`; `guardarMesa`/`eliminarMesa` sin authorize server-side | `routes/web.php:41`, `mesas/index.blade.php:58,87` | **STILL PRESENT** |
| H4 | `autorizadoPor` editable (self-attestation) y `tipo` sin allowlist en caja (`'ingreso'` permite inflar la gaveta) | `caja/control.blade.php:689`, `CajaService.php:133` | **STILL PRESENT** |
| H6 | Rutas públicas (`mesa/{numero}/menu`, `/delivery/pedir`, pedido público) **sin throttle/honeypot** — bot puede generar pedidos; precios ya recomputados de DB (bien) | `routes/web.php:34,37,38` | **PARTIAL** |
| H7 | `puntos_fidelidad` fuera del `validate()` y `tipoAjuste` sin `in:` → cajero regala puntos arbitrarios | `clientes/index.blade.php:107,169`, `ClienteService.php:33-34` | **PARTIAL** |
| H8 | `pagos_cxps.cuenta_por_pagar_id` cascade | `193510:13` | **STILL PRESENT** |
| H11 | `movimientos_puntos.cliente_id` cascade (mitigado con SoftDeletes de Cliente; `forceDelete` aún arrasa) | `194020:16` | **PARTIAL** |
| N1-NUEVO | **`trabajos_impresion.impresora_id` cascadeOnDelete**: borrar una impresora destruye histórico de impresión fiscal (tickets, reportes Z, `contenido_raw`) | `210010_create_trabajos_impresion_table.php:19` | **NEW** |
| N1-NUEVO | `guardarNuevaCaja()` sin authorize (cajero crea terminales de caja) | `caja/control.blade.php:55,65` | **NEW** |
| N1-NUEVO | `guardarConexionDb` escribe credenciales DB en `.env` en runtime sin cifrado | `configuracion/index.blade.php` (≈192) | **NEW** |
| F1/F2/F9 | Reportes: agregación en PHP (`AsientoContable::get()` + sum en PHP) y ~12-15 queries masivas por render, `estadoResultados` incondicional | `ReporteService.php:21-23`, `reportes/index.blade.php:24-41,40` | **STILL PRESENT** |
| F3 | Delivery: 2 queries por motorizado en `@forelse` (+2N queries/render) | `delivery/index.blade.php:566-575` | **STILL PRESENT** |
| F4 | KDS: batch por ítem (~6-10 queries/ítem) | `cocina/kds.blade.php:44-64` | **STILL PRESENT** |
| F5 | Delivery sin paginar (`latest()->get()`) — meses de historial por render | `delivery/index.blade.php:184` | **STILL PRESENT** |

### 🟡 MEDIUM

| # | Hallazgo | Ubicación | Estado |
|---|---|---|---|
| M1 | Dinero en `float` (~50 casts `(float)` en servicios de dinero) | `CajaService.php:124-283`, `PedidoService.php:40-48,162`, `TurnoCaja.php:88-91`, `FidelizacionService.php:31-109`, `Pedido.php:132` | **STILL PRESENT** |
| M3 | Enums dead code: `PedidoEstado`/`TurnoCajaEstado` 0 usos; `'en_proceso'` en `Pedido.php:115` fuera del enum; sin `CANCELADO` | `app/Enums/*`, `Pedido.php:115` | **STILL PRESENT** (buena noticia: `MesaEstado` ahora 12 usos) |
| M4/F11/F13/F15 | Listas sin paginar: clientes, cxp pendientes, pedidos del turno completos | `clientes/index.blade.php:230`, `cxp/index.blade.php:33-51`, `caja/control.blade.php:223` | **STILL PRESENT** |
| M6 | Doble stack Tailwind v3 (activo) + v4 instalado sin registrar | `package.json:11,16` | **STILL PRESENT** |
| M7 | `bacon/bacon-qr-code: "*"` | `composer.json:10` | **STILL PRESENT** |
| M8/M9/M10 | Sin unique: `productos.slug`, `mesas(numero,sucursal_id)`, `clientes` (tel/email) | migraciones 180010:18, 174736, 194000:17-18 | **STILL PRESENT** |
| M11 | `clientes.total_gastado` denormalizado | `FidelizacionService.php:68` | **STILL PRESENT** |
| M12/L12 | Índices faltantes: `(estado,pagado_en)`, `(caja_id,estado)`, `movimientos_caja (turno_caja_id,tipo)`, `auditorias.created_at` | ReporteService:187,299 / 190010 / 190020 / 193000 | **STILL PRESENT** |
| M13 | `reserva_mesa` cascade | `200020:12-13` | **STILL PRESENT** |
| M14 | Solapamiento de reservas en PHP | `ReservaService.php:20-37` | **STILL PRESENT** |
| M15/F8 | Inventario: KPIs + todos los movimientos del insumo sin límite por render | `inventario/index.blade.php:203-211` | **STILL PRESENT** |
| M16 | Queries en `@php` dentro de plantillas (POS `:1099`, reservas `:225`) | `pos/terminal.blade.php`, `reservas/index.blade.php` | **STILL PRESENT** |
| M17 | Volt monstruosos: pos 1592, configuracion 1167, inventario 990, caja 905, navigation 823, delivery/pedido-publico 559 (nuevo) | `resources/views/livewire/**` | **STILL PRESENT** |
| M18 | Costos/stock teórico como `float` | `Producto.php:65-68`, `Insumo.php:93-96` | **STILL PRESENT** |
| N2-NUEVO | `menu-publico:166` categorías **sin filtro `activo`** (muestra inactivas; incoherente con POS/carta) | `mesa/menu-publico.blade.php:166` | **NEW** |
| N2-NUEVO | `wire:poll.4s` en `menu-publico:214` re-ejecuta TODO el `with()` (3-4 q × 15/min **por mesa abierta**) — punto caliente real | `mesa/menu-publico.blade.php:214` | **NEW** |
| N3-NUEVO | POS carga **todos los clientes activos** en cada render | `pos/terminal.blade.php:392` | **NEW** |
| N2-NUEVO | `impresoras.tipo_conexion`/`driver_nombre`/área strings sin constraint; typo cae en socket IP | `Impresora.php:73-82`, harden | **NEW** |

### 🟢 LOW

| # | Hallazgo | Ubicación | Estado |
|---|---|---|---|
| L2 | `Cliente::tier` `'imperial'` vs vocabulario del esquema (ahora mapeado a 'Imperial VIP': parcial/intencional) | `Cliente.php:60` | **PARTIAL** |
| L4 | CSRF exento para todo `api/*` | `bootstrap/app.php:19` | **STILL PRESENT** |
| L5 | Slugs de rol inline en 2-3 sitios | `routes/web.php:41-56`, `NotificacionService.php:21-24` | **PARTIAL** |
| L6 | `@` suppression en red/archivos de impresión | `ImpresionService.php:190,327`, `Impresora.php:164` | **STILL PRESENT** |
| L7 | `down()` con `dropForeign` (varias) | `194030:36-38`, harden down | **STILL PRESENT** |
| L8 | `reservas.hora_llegada` sin cast | `Reserva.php:23-31` | **STILL PRESENT** |
| L9 | `Insumo::$casts` propiedad vs método | `Insumo.php:35` | **STILL PRESENT** |
| L10 | Comentarios redundantes en inglés (violan `.ai/rules/code.md`) | varios Volt | **STILL PRESENT** |
| L11 | `@laravel/multiplex` unmet sin uso | `package.json:20` | **STILL PRESENT** |
| NUEVO | `DD` visual`imagen/texto` SCSS duplicados `@media` en Volt | pos/caja/inventario | **NEW** |
| NUEVO | `restaurarBackup`: `DB::unprepared` sobre archivo subido (admin) sin validación de extensión server-side | configuracion | **NEW (solo admin)** |
| NUEVO | `delivery/pedido-publico:114` y `mesa/menu-publico:50` `findOrFail` sin filtro `activo` | delivery/mesa | **NEW** |
| N4-NUEVO | `enviarPedidoDelivery`: ~2N queries en write path (aceptable en carritos pequeños) | `pedido-publico.blade.php:113-174` | **NEW (opcional)** |
| N4-NUEVO | Operativo: el harden agrega FK sobre tabla existente (si hay `cuentas_por_pagar.insumo_id` huérfano, la migración **falla en producción**) | harden:34-38 | **NEW (risk ops)** |

---

## 4. Bugs concretos vigentes

| Bug | Impacto |
|---|---|
| `descuento_puntos` sin acotar a subtotal (C2 PARCIAL) | Total nunca negativo (por `max(0,...)`), pero descuento persistido puede superar subtotal |
| `tipoMovimiento` en caja sin allowlist (`'ingreso'` libre) | Cajero puede auto-inflar arqueo/gaveta (H4) |
| `puntos_fidelidad`/`tipoAjuste` sin validación estricta | Puntos regalables (H7) |
| `cambiarEstado` en mesas escribe cualquier string sin validar `MesaEstado` | Estados inválidos en DB (N-Medio) |
| `'en_proceso'` usado fuera del enum `PedidoEstado` | Dominio de estados inconsistente (M3) |
| `cambiarEstado`/`guardarMesa`/`eliminarMesa`/`guardarNuevaCaja` sin authorize | Control de acceso roto (C3/H3) |

---

## 5. Plan de remediación actualizado

**P0 — antes de producción (bloquean deploy):**
1. `authorize()`/Policies en TODAS las mutaciones de dinero/estado (C3) — prioridad #1.
2. `restrictOnDelete()` en las 12 FKs cascade sobre histórico (C4) + SoftDeletes en sucursales/cajas/turnos y prohibir `forceDelete` de catálogo; incluir `trabajos_impresion.impresora_id` (N1).
3. Credenciales por env: eliminar `123456`/`'secret'` (H1/H2).
4. Permitir roles: CRUD mesas solo gerente/admin (H3); `autorizadoPor` verificada + allowlist de `tipo` y `tipoAjuste` (H4/H7); acotar `descuento_puntos` (C2).
5. Throttle/honeypot en rutas públicas de QR/delivery (H6).

**P1:**
1. Agregación SQL en reportes (F1/F2/F9), batch en KDS (F4), `withCount/withSum` en flota delivery (F3), paginación delivery/clientes/cxp (F5/F11/F13).
2. `refrescarEstado()` 1 query en `wire:poll.4s` del menú QR (N2) y filtrar categorías `activo` (N1-menu).
3. Índices `(estado,pagado_en)`, `(caja_id,estado)`, `(turno_caja_id,tipo)`, `auditorias.created_at`, `(estado,estado_delivery)`.
4. Uniques: `productos.slug`, `mesas(numero,sucursal_id)`, `clientes(telefono/email)`.
5. Activar enums de estado; validar `MesaEstado` en `cambiarEstado`.

**P2:**
1. Dinero en enteros/`bcmath` (M1).
2. Stack Tailwind único (M6); fijar `bacon/bacon-qr-code ^3.1` (M7); remover `@laravel/multiplex` (L11).
3. Cache de catálogo + `#[Computed]` en POS y búsqueda remota de clientes (N3/F6).
4. Dividir Volt monstruosos; unificar casts/estilos; limpiar comentarios.
5. Revisar `guardarConexionDb` (cifrado + saneo) y `restaurarBackup` (validación de extensión).

---

## 6. Lo que quedó bien (2ª pasada)

- **Pint 0 violaciones** tras +6.000 líneas nuevas.
- **Seguridad de dinero real con tests:** precio desde DB, `lockForUpdate`, `abort_if('pagado')`, fórmula unificada — 3 tests de regresión dedicados (`SeguridadDineroAuditoriaTest`).
- **SoftDeletes correctos** (trait en los 3 modelos + migración idempotente con `Schema::hasColumn`) y FK `insumo_id` con `nullOnDelete`.
- **Sin** `dd/dump/var_dump/ray`, sin SQLi, sin XSS, sin `$guarded=[]`, sin secrets commiteados.
- Rutas con middleware `role:` centralizado; webhook con `hash_equals`; `NotificacionService` filtra por rol.
- Eager loading correcto en los `with()` nuevos (carta-publica, pedido-publico, InventarioService, ImpresionService) y comandas despachadas por job (sin bloquear el request).
- `MesaEstado` ahora con 12 usos.
- Índices de rendimiento 120000 bien dirigidos.
- 38 archivos de test (~210 métodos test + suites Antigravity), cobertura de dinero y RBAC robusta.

---

## 7. Pendiente de verificar (dinámico, no estático)

- Ejecutar la suite completa de forma controlada (213/724) en un momento sin `php artisan serve` activo, para evitar la colisión de compilación Volt en Windows detectada ayer.
- Smoke-test del rate limiting de login bajo cache `database`.
- Validar en producción que `harden` no falle por filas huérfanas de `cuentas_por_pagar.insumo_id` antes de aplicar.
- Confirmar si `descuento_puntos` debe acumular por pedido (hoy solo `max(0, ...)`).

---

*Generado por OpenCode · 2026-09-10 · Segunda pasada de auditoría, solo lectura, sin modificar código.*