# Coordination.md — Estado de Trabajo entre Agentes

> **Instrucción:** Cada agente actualiza esta sección al INICIAR y FINALIZAR una tarea.
> Formato: `[FECHA] [AGENTE] [ACCIÓN] [ARCHIVOS]`

---

## Última Actualización
2026-09-09 23:00 | OpenCode | Inicio Fase 5: Reservas, Reportes, Configuración/DIAN y KPIs Dashboard. Lock `.locks/fase5-reservas-reportes.lock` creado.
2026-09-09 23:59 | OpenCode | Fase 5 completada: Reservas + webhook + Reportes avanzados + export PDF/CSV + KPIs dashboard + navegación. Suite 147/147 OK (425 aserciones). Lock liberado.

---

## Trabajo en Progreso
**Fase 5 (OpenCode, COMPLETADA):** Fase 5 finalizada el 2026-09-09. Suite completa 147/147 tests (425 assertions). Lock `.locks/fase5-reservas-reportes.lock` eliminado.

## Tareas Completadas (Historial)

| Fecha | Agente | Tarea | Archivos modificados |
|-------|--------|-------|---------------------|
| 2026-09-09 | OpenCode | Lectura documentación completa | ninguno |
| 2026-09-09 | OpenCode | Instalación Superpowers plugin | ~/.config/opencode/opencode.jsonc |
| 2026-09-09 | OpenCode | Corrección script Antigravity | create_laravel.ps1 |
| 2026-09-09 | OpenCode | Sistema coordinación agentes | AGENTS.md, coordination.md, .ai/rules/coordination.md, .ai/rules/code.md, .locks/README.md |
| 2026-09-09 | OpenCode | Actualizar doc: Laravel 12 → 13 | AGENTS.md, .ai/rules/code.md, docs/runbook-setup.md, docs/requerimientos.md |
| 2026-09-09 | OpenCode | Habilitar pdo_sqlite en php.ini (pruebas SQLite) | D:\Proyectos\tools\php83\php.ini |
| 2026-09-09 | Antigravity | Fase 0: Montaje Volt, RBAC middleware, layout táctil, enums y servicios base | app/Providers/AppServiceProvider.php, app/Http/Middleware/EnsureUserHasRole.php, bootstrap/app.php, app/Enums/*, app/Services/MesaService.php, resources/views/layouts/app.blade.php, resources/views/livewire/layout/navigation.blade.php, resources/views/dashboard.blade.php, tests/Feature/RoleMiddlewareTest.php |
| 2026-09-09 | OpenCode | Crear 10 skills de seguridad y calidad de código (compartidas OpenCode + Antigravity) | .opencode/skills/*/, ~/.gemini/config/skills/*/ |
| 2026-09-09 | Antigravity | Fase 1: Catálogo sushi, mapa de mesas, POS táctil, pedidos, KDS cocina, cobro y tickets 80mm | database/migrations/*, app/Models/*, app/Services/PedidoService.php, database/seeders/MenuSeeder.php, resources/views/livewire/mesas/*, resources/views/livewire/pos/*, resources/views/livewire/cocina/*, routes/web.php, tests/Feature/Fase1OperacionesTest.php |
| 2026-09-09 | Antigravity | Fase 2: Control de Caja, Turnos independientes, Arqueo ciego, Reporte fiscal Z y Contabilidad automática (CAJ-01, CAJ-04/05) | database/migrations/*, app/Models/*, app/Services/CajaService.php, database/seeders/CajaSeeder.php, resources/views/livewire/caja/*, routes/web.php, tests/Feature/Fase2CajaTest.php |
| 2026-09-09 | OpenCode | Verificación F1+F2 (auditoría de código, migraciones y suite: 42/42 OK). Fase 1 operable completa; Fase 2 cuadrable y auditada | ninguno (solo lectura) |
| 2026-09-09 | Antigravity | Fase 3: Inventario y Recetas / Escandallos (Insumos, Kardex, Costeo NIIF, Mermas, Deducción KDS y pantalla Stitch INV-01) | database/migrations/*, app/Models/*, app/Services/InventarioService.php, app/Services/PedidoService.php, database/seeders/InventarioSeeder.php, resources/views/livewire/inventario/*, routes/web.php, tests/Feature/Fase3InventarioTest.php |
| 2026-09-09 | Antigravity | Rediseño y alineación integral al Design System oficial de Stitch "Aura Gastro Expressive OS" (DASH-01, MES-01, POS-01, COC-01, CAJ-01, INV-01, TRB-01, paleta Porcelana/Terracota/Verde Palma/Panela Dorada, 64/64 tests OK) | tailwind.config.js, resources/views/layouts/app.blade.php, resources/views/livewire/*, tests/Feature/* |
| 2026-09-09 | OpenCode | Cierre brechas F0–F3: RBAC por rol en rutas F1/F2 + middleware `EnsureUserHasRole` | routes/web.php, tests/Feature/Fase0RbacRutasTest.php |
| 2026-09-09 | OpenCode | Cierre brechas F0–F3: CRUD Trabajadores (admin) alineado al rediseño Aura Gastro | resources/views/livewire/trabajadores/index.blade.php, tests/Feature/Fase0TrabajadoresTest.php |
| 2026-09-09 | OpenCode | Cierre brechas F0–F3: CRUD Menú (categorías y productos, gerente) + MenuService | app/Services/MenuService.php, resources/views/livewire/menu/index.blade.php, tests/Feature/Fase1MenuCrudTest.php |
| 2026-09-09 | OpenCode | Cierre brechas F0–F3: Comanda por área en KDS (impresión 80mm filtrada por `areaSeleccionada`) | resources/views/livewire/cocina/kds.blade.php, tests/Feature/Fase1ComandaAreaTest.php |
| 2026-09-09 | OpenCode | Cierre brechas F0–F3: Estado de Resultados /reportes (REP-01) desde AsientoContable + ReporteService | app/Services/ReporteService.php, resources/views/livewire/reportes/index.blade.php, tests/Feature/Fase2ReportesTest.php |
| 2026-09-09 | OpenCode | Cierre brechas F0–F3: Audit log (`auditorias` + AuditoriaService, trazabilidad de acciones sensibles en menú y trabajadores) | database/migrations/2026_09_09_193000_*.php, app/Models/Auditoria.php, app/Services/AuditoriaService.php, app/Services/{MenuService,TrabajadorService}.php, tests/Feature/Fase2AuditoriaTest.php |
| 2026-09-09 | OpenCode | Cierre brechas F0–F3: Cuentas por Pagar CXP-01 (cuentas, pagos/abonos, saldos por proveedor) | database/migrations/2026_09_09_1935*.php, app/Models/{CuentaPorPagar,PagoCxp}.php, app/Services/CuentasPorPagarService.php, resources/views/livewire/cxp/index.blade.php, routes/web.php, tests/Feature/Fase2CxpTest.php |
| 2026-09-09 | Antigravity | Fase 4: Clientes VIP, Fidelización (Puntos & Tiers) + Delivery & Flota de Despacho (CLI-01, PED-04, Liquidación en Caja, POS integrado, 102/102 tests OK) | database/migrations/*, app/Models/{Cliente,DireccionCliente,MovimientoPuntos,Pedido}.php, app/Services/{ClienteService,FidelizacionService,DeliveryService}.php, resources/views/livewire/{clientes,delivery,pos}/*, resources/views/dashboard.blade.php, routes/web.php, tests/Feature/Fase4ClientesDeliveryTest.php |
| 2026-09-09 | OpenCode | Fase 5: Reservas internas RES-01 (CRUD + confirmación de mesas + estados) y reserva pública + webhook con token | app/Models/Mesa.php, app/Services/ReservaService.php, app/Http/Controllers/{ReservaPublicaController,ReservaWebhookController}.php, resources/views/livewire/reservas/index.blade.php, resources/views/reservas/crear.blade.php, routes/web.php, tests/Feature/{Fase5ReservasTest,Fase5PublicoReservasTest}.php |
| 2026-09-09 | OpenCode | Fase 5: Reportes avanzados REP-01 por pestañas + exportación PDF (dompdf) y CSV restringida a gerente | app/Services/ReporteService.php, app/Http/Controllers/ReporteExportController.php, resources/views/livewire/reportes/index.blade.php, resources/views/pdf/reporte.blade.php, composer.json (barryvdh/laravel-dompdf ^3.1), routes/web.php, tests/Feature/Fase5ReportesTest.php |
| 2026-09-09 | OpenCode | Fase 5: KPIs reales en Dashboard DASH-01 (kpisRealtime) + tarjetas REP-01/RES-01 activas + navegación Reservas/Reportes/Configuración | resources/views/dashboard.blade.php, resources/views/livewire/layout/navigation.blade.php, tests/Feature/Fase5DashboardTest.php |

## Pendientes

- [x] Fase 0: Setup Laravel + PostgreSQL (Laravel 13.31 + PostgreSQL 18 activo)
- [x] Fase 0: Migraciones base (users, roles, sucursales, mesas)
- [x] Fase 0: Auth con Breeze + seeders + middleware de roles RBAC
- [x] Fase 0: Layout responsive base táctil con menú lateral persistente y drawer móvil
- [x] Fase 1: Catálogo de categorías y productos (5 categorías, 18 productos de sushi)
- [x] Fase 1: Mapa visual de mesas y estados en tiempo real (Salón, Barra, Terraza)
- [x] Fase 1: POS táctil (comandas, notas, modificadores, selección mesa/mostrador/delivery)
- [x] Fase 1: Pantalla de Cocina KDS (cola de preparación FIFO, estaciones de cocina, marcado listo)
- [x] Fase 1: Cobro y cierre de pedidos (efectivo con cálculo de cambio, tarjeta, mixto)
- [x] Fase 1: Impresión y simulación de tickets térmicos (80mm con detalle completo)
- [x] Fase 2: Apertura y cierre de caja con fondo inicial
- [x] Fase 2: Movimientos de caja (ingresos, egresos, retiros) y arqueo ciego
- [x] Fase 2: Reporte Z / Corte de caja por turno y cajero
- [x] Fase 2: Contabilidad básica (asientos automáticos de ventas)
- [x] Fase 3: Inventario y Recetas (insumos, recetas/escandallo, deducción automática de stock)
- [x] Fase 4: Clientes y Fidelización + Delivery (clientes, puntos, repartidores, pedidos a domicilio)
- [x] Fase 5: Reservas y Reportes Avanzados / Facturación Electrónica DIAN

## Notas para el otro agente

> **Fase 5 finalizada (OpenCode, 2026-09-09 23:59) — Suite completa 147/147 tests (425 assertions).**
> - **Reservas (RES-01):** `ReservaService` (crear/confirmar/marcarLlego/finalizar/cancelar/marcarNoShow/reservasDelDia/verificarDisponibilidad) con relación pivot `reserva_mesa`. **Gotcha SQLite:** columna `date` se guarda como `YYYY-MM-DD 00:00:00` → comparar con `whereDate('fecha', ...)` (NO `where('fecha', $dia)`). Pantalla `/reservas` (mesero,cajero,gerente).
> - **Reserva pública y webhook:** `GET/POST /reservas/crear` (throttle 10/1, honeypot `empresa`) y `POST /api/reservas` (throttle 20/1, header `X-Webhook-Token` comparado con `hash_equals`; 401/403/422/201). Registrada en `routes/web.php` FUERA del grupo auth (bootstrap no carga `routes/api.php`). Contrato en `docs/modulos/reservas.md`.
> - **Reportes (REP-01):** `/reportes` por pestañas estado/ventas/clientes/reservas + exportaciones `GET /reportes/exportar-pdf` y `GET /reportes/exportar-csv` (name `reportes.pdf`/`reportes.csv`, role:gerente). Nuevo paquete `barryvdh/laravel-dompdf ^3.1` (auditado: compatible Laravel 13, sin CVEs, mantenido — `composer audit` limpio). Vista PDF en `resources/views/pdf/reporte.blade.php` (letter portrait, usa `DejaVu Sans`).
> - **Dashboard (DASH-01) y navegación MODIFICADOS por OpenCode:** `resources/views/dashboard.blade.php` ahora consume `ReporteService::kpisRealtime()` reales (ventas_dia, ticket_promedio, mesas_ocupadas, comandas_cocina_activas, food_cost_porcentaje, top_productos_hoy) y activó tarjetas REP-01 (`route('reportes')`) y RES-01 (`route('reservas')`). `resources/views/livewire/layout/navigation.blade.php` ganó enlaces Reservas/Reportes/Configuración (admin) en sidebar y drawer. **Verificar diff antes de la Fase 6.**
> - **Configuración (CFG-01):** `/configuracion` (role:admin) con `ConfiguracionService::obtener(grupo, clave, default)` / `guardar(grupo, clave, valor)`; seeder `ConfiguracionSeeder` (datos DIAN: razon_social, nit, regimen, resolución, prefijo, rango, token_webhook, zona_horaria, moneda).
> - **Volt:** recuerda pasar datos por `with()` (métodos privados/`#[Computed]` no se exponen). <x-layouts.guest> NO existe → vistas públicas usan `@component('layouts.guest')`.

> **Fase 4 finalizada con éxito (Antigravity, 2026-09-09 20:52):**
> - **Base de datos & Migraciones:** Creadas `clientes`, `direcciones_cliente`, `movimientos_puntos` y enriquecido `pedidos` con campos de delivery (`costo_envio`, `canal_entrega`, `estado_delivery`, `repartidor_id`, `direccion_cliente_id`, `despachado_at`, `entregado_at`, `recaudo_liquidado`, `puntos_ganados`, `puntos_canjeados`, `descuento_puntos`).
> - **Modelos Eloquent:** `Cliente` (con `esVip()`, badges, scopes), `DireccionCliente`, `MovimientoPuntos`, y relaciones bidireccionales en `Pedido`.
> - **Servicios:**
>   - `ClienteService`: creación con validación de teléfono único, edición, libreta de direcciones y búsqueda multi-campo.
>   - `FidelizacionService`: acumulación por consumo ($10.000 COP = 1 pt), canje ($10 COP/pt), ajuste manual auditado y progresión de tiers (Regular -> Gold -> VIP -> Black).
>   - `DeliveryService`: despacho de pedidos, asignación de motorizados, transiciones a ruta y entrega, métricas operativas flash y liquidación en bloque del recaudo en efectivo del motorizado hacia el turno de caja activo.
> - **Pantallas Stitch Livewire Volt (Aura Gastro Expressive OS):**
>   - `CLI-01` (`/clientes`): Bento KPIs, búsqueda instantánea debounce, filtros por tier/alergias, split 7/5 directorio + inspector 360° con historial de pedidos y movimientos de puntos, modales de nuevo/editar/puntos/dirección.
>   - `PED-04` (`/delivery`): 5 KPIs flash, filtros de estado/canal, cola de despacho con asignación rápida y modales de cobro contra entrega / nuevo pedido manual, y panel lateral de gestión de flota de motorizados con liquidación de efectivo.
>   - `POS Terminal` (`/pos`): buscador rápido de clientes por teléfono/nombre, selección de dirección guardada, visor de saldo de puntos y botón de canje inmediato de descuento.
>   - `Dashboard` (`/dashboard`): agregadas tarjetas operativas de acceso directo para `CLI-01` y `PED-04`.
> - **Suite de Pruebas Automatizadas:** `Fase4ClientesDeliveryTest.php` creada con 7 tests (46 assertions). Suite completa del proyecto: **102 de 102 tests pasando (315 assertions), 100% verde.**
> - **Lock liberado:** `.locks/fase4-clientes-delivery.lock` eliminado. El proyecto queda listo para abordar la Fase 5.

> **Fase 3 finalizada con éxito.**
> - Migraciones creadas y ejecutadas: `insumos`, `recetas` (escandallos), `movimientos_inventario` (Kardex completo con trazabilidad y costeo NIIF) y `inventario_descontado` en `items_pedido` para salvaguarda de idempotencia.
> - Modelos creados: `Insumo`, `Receta`, `MovimientoInventario` y relaciones bidireccionales en `Producto` (`recetas`, `insumos`, `costo_receta`).
> - Servicio transaccional `InventarioService.php`: deducción automática por receta al expedir en cocina (`COC-01`) o cobrar en POS, compras con recálculo de costo promedio ponderado NIIF, mermas con motivo/usuario y ajustes físicos.
> - Seeder gastronómico de sushi `InventarioSeeder.php`: 15 materias primas reales (Salmón, Atún Maguro, Shari, Nori, etc.) vinculadas con recetas exactas para los 18 productos de la carta y movimientos históricos iniciales.
> - Pantalla interactiva Stitch `INV-01` en Livewire Volt (`/inventario`): Bento KPIs, alertas de stock crítico, filtros por categoría/estado, panel lateral de trazabilidad en vivo con consumo KDS, recetas vinculadas, proveedor preferente con WhatsApp y modales operativos de merma, compra y conteo físico.
> - Enlaces activos y unificados en sidebar de escritorio, cajón móvil y dashboard principal.
> - Suite de pruebas automatizadas al 100% verde: **52 de 52 tests pasando** (168 assertions totales en la aplicación).
> - Listo para arrancar la **Fase 4: Clientes y Fidelización + Delivery**.

> **Verificación OpenCode F1+F2 (2026-09-09, tras finalización):**
> - Revisados migraciones, models, Services (+ `CajaService`, `PedidoService`), vistas Volt y tests. Suite ejecutada: 42/42 PASS.
> - **Fase 1:** flujo completo operable y probado (mesa→orden→cocina→cobro→ticket). Brecha menor: no hay CRUD admin de menú (el catálogo se siembra y el POS lo consume; gestionar productos requiere seeds o SQL) y la impresión activa es ticket de venta, no comanda por área.
> - **Fase 2:** turno completo cuadrable (apertura, ventas, movimientos, arqueo ciego con sobrante/faltante, Reporte Z, asientos contables trazables). Sin brechas funcionales detectadas.
> - **Recomendación de robustez (post Fase 3):** añadir middleware de rol a las rutas de F1/F2 (`mesas`, `pos`, `cocina`, `caja` hoy solo usan `auth`); aplicar skills `authz-rbac-check` + `laravel-security-review` al cerrar cada fase.
> - Nota: los tests de Fase 2 usan `codigo`/`activa` en Sucursal y role seed... ya quedaron alineados (suite verde).
> **Cierre brechas F0–F3 (OpenCode, 2026-09-09):**
> - **RBAC en rutas:** `mesas`/`pos` (mesero,pajero,gerente), `cocina` (cocina,barra,gerente), `caja` (cajero,gerente), `inventario`/`menu`/`reportes`/`cxp` (gerente), `trabajadores` (admin). Sujetos cubiertos por `EnsureUserHasRole`. **42→95 tests, 269 assertions, todos verdes.**
> - **CRUD Trabajadores (TRB-01):** gestión completa de trabajadores para admin, alineado al rediseño Aura Gastro (7/7 tests).
> - **CRUD Menú (MEN-01):** categorías (slug único, icono, orden) y productos (precio>0, área cocina, costo) con desactivación no destructiva (10/10 tests).
> - **Comanda por área (COC-01):** KDS imprime comanda de 80mm filtrada por estación activa (`cocina`/`barra`), con abrir/cerrar modal (4/4 tests).
> - **Estado de Resultados (REP-01):** `/reportes` filtra por rango, suma ingresos/gastos de AsientoContable y muestra resultado neto + movimientos recientes (4/4 tests).
> - **Audit log:** tabla `auditorias` (usuario, acción, entidad, datos JSON, IP) con `AuditoriaService` integrado en MenuService y TrabajadorService (6/6 tests).
> - **Cuentas por Pagar (CXP-01):** `/cxp` con cuentas, abonos/pagos (validación: no supera saldo), cierre automático a `pagada` y saldos agrupados por proveedor (7/7 tests).
> - Nota técnica Volt: métodos privados y `#Computed` NO se exponen a la vista → pasar datos por `with()`. `constrained()` sin tabla explícita infiere el plural por defecto (usar `constrained('cuentas_por_pagar')`).

---
*Ultima edicion: 2026-09-09 20:30*
