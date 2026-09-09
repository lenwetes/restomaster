# Fase 5 — Reservas, Reportes Avanzados y Configuración (incl. parametrización DIAN)

Fecha: 2026-09-09
Autor: OpenCode (diseño aprobado por usuario)
Proyecto: Sushixpress (D:\Proyectos\sushixpress)
Estado: APROBADO — listo para plan de implementación

---

## 1. Contexto y objetivo

El sistema ya tiene operativo F0–F4 (102/102 tests, 315 assertions). La Fase 5 añade:

1. **Reservas** (gestión interna + formulario público + webhook para n8n/WhatsApp).
2. **Tablero de KPIs en vivo** en el Dashboard (información superficial), y todo el detalle en Reportes.
3. **Centro de Reportes avanzados** en `/reportes` (pestañas).
4. **Exportaciones** a PDF (generado en servidor) y CSV.
5. **Área de Configuraciones** (`/configuracion`) donde el usuario parametriza manualmente el emisor y la resolución **DIAN** (sin integración con proveedor externo en esta fase) y otros datos del negocio.

## 2. Alcance

### Incluye
- Subsistema de Reservas completo (modelo, servicio, vista interna, formulario público, webhook).
- KPIs reales en Dashboard (reemplazan los valores mock actuales).
- Reportes avanzados con pestañas en `/reportes`.
- Exportación PDF servidor (paquete `barryvdh/laravel-dompdf`, a auditar) y CSV `;` UTF-8.
- Página de configuración con secciones DIAN/Facturación y Empresa/Impresión/Webhook.
- Seeders y migraciones necesarios.
- Tests TDD por subsistema.

### Excluye (para fases posteriores)
- Integración activa con proveedor DIAN (dataico/habitac/otro): firma XML, envío, recepción de eventos.
- Emails/notificaciones a clientes.
- Jobs/queue (Fase 6).
- Soporte multi-sucursal restringido: las reservas se modelan con `sucursal_id` nullable para futuro, pero la UI opera sobre la sede actual (primera sucursal).

## 3. Arquitectura

Sigue los patrones existentes del proyecto:
- **Modelo/migración/service/vista Volt Livewire** (como `MenuService`, `ReporteService`, `CajaService`).
- RBAC por middleware `role:` en rutas (`EnsureUserHasRole`).
- Design system Aura Gastro Expressive OS (tokens `surface-container-lowest`, `rounded-3xl`, `material-symbols-outlined`, badges de módulo `RES-01`, `CFG-01`, `REP-01`).

### 3.1 Módulo Reservas (RES-01)

**Migración `reservas`** (convención timestamps, FKs nullable):
- `id`, `sucursal_id` (nullable, FK set null)
- `cliente_id` (nullable, FK set null) — si es cliente registrado
- `nombre_contacto`, `telefono_contacto`, `email_contacto` (nullable)
- `fecha` (date), `hora_llegada` (time), `duracion_min` (int, default 120)
- `personas` (int > 0)
- `estado` (enum string): `solicitada`, `confirmada`, `llego`, `finalizada`, `cancelada`, `no_mostro`
- `origen` (string): `sistema`, `publico`, `webhook`
- `notas`, `anticipo` (decimal(12,2) nullable), `confirmado_por` (nullable FK users)
- `token_publico` (string 64, unique, generado automáticamente) — para que el cliente pueda consultar/visible sin login
- `created_by` (nullable FK users), `created_at`, `updated_at`
- Índices: `(fecha, estado)`, `token_publico` unique, `(cliente_id)`

**Migración pivote `reserva_mesa`**: `reserva_id` (FK cascade), `mesa_id` (FK cascade), PK compuesta.

**Reglas de negocio** (en `ReservaService`):
- `personas >= 1` y `fecha >= hoy` para creación interna; público solo fechas futuras.
- Duplicados: al confirmar, la mesa debe estar `libre` o `reservada` por otra reserva cuyo bloque no solape (`hora_llegada` ± `duracion_min`). `verificarDisponibilidad(fecha, horaInicio, personas)` devuelve mesas libres con capacidad suficiente.
- Ciclo: `solicitada → confirmada → llego → finalizada`; ramas `cancelada` / `no_mostro`.
  - `confirmar`: mesas → `reservada`.
  - `llego`: mesas → `ocupada`; opcionalmente crea pedido de tipo `mesa` (hook `crearPedidoDesdeReserva` opcional, NO se auto-crea por defecto).
  - `finalizar / cancelar / no_mostro`: libera mesas (vuelven a `libre`).
- `reservasDelDia(fecha)`: agenda ordenada, con mesas asignadas eager-loaded.
- Auditoría: acciones de mutación registradas con `AuditoriaService` (acciones `reserva.creada`, `reserva.confirmada`, etc.).

**Vista interna `/reservas`** (roles: `mesero,cajero,gerente` por estar operativo; incluye admin por defecto):
- RES-01: agenda del día (lista por franja), filtros por estado, modales crear (elegir fecha/hora/personas → disponibilidad → mesas) y detalle con ciclo de vida, botón "Exportar agenda PDF/CSV".

**Formulario público `/reservas/crear`** (guest, con rate limiting `throttle:5,1` + honeypot):
- Paso 1: fecha + personas → franjas disponibles (mesas libres).
- Paso 2: nombre, teléfono, email (opcional), mesas elegidas, notas.
- Creación con estado `solicitada`, origen `publico`.
- Página de confirmación muestra `token_publico` (para future consulta/cancelación).

**Webhook `POST /api/reservas`** (guest, `throttle:20,1`):
- Header `X-Webhook-Token` obligatorio; se compara con config `reservas.webhook_token` (constant-time). Si `reservas.webhook_activo` es false → 403.
- Payload JSON: `nombre`, `telefono`, `email?`, `fecha` (Y-m-d), `hora` (H:i), `personas`, `mesa_ids[]?`, `notas?`.
- Valida igual que el servicio; crea reserva origen `webhook`, estado `solicitada`.
- Respuestas: `201` con `{ reserva_id, token_publico }`, `422` con errores, `401`/`403` token inválido/desactivado.
- Documentación del contrato en `docs/modulos/reservas.md` (actualizar existente).

### 3.2 Configuraciones (CFG-01)

**Migración `configuraciones`**: `id`, `grupo` (string), `clave` (string unique), `valor` (json), timestamps.

**Seeder `ConfiguracionSeeder`** crea:
- `general.razon_social` = 'SushiXpress S.A.S.', `general.nit`, `general.direccion`, `general.telefono`, `general.regimen` = 'Común'
- `dian.envio_activo` = false, `dian.ambiente` = 'habilitacion', `dian.tipo_documento` = '01'
- `dian.resolucion_numero`, `dian.resolucion_fecha`, `dian.prefijo` = 'MP', `dian.desde`, `dian.hasta`, `dian.vigente` = true
- `reservas.webhook_token` = Str::random(48) (solo si no existe), `reservas.webhook_activo` = false
- `impresion.pie_ticket` = '¡Gracias por preferir SushiXpress!'

**Modelo `Configuracion`**: `valor` cast array, scope helpers (`Configuracion::obtener(grupo, clave, default)`).

**Servicio `ConfiguracionService`**: `obtener(grupo, clave, default)`, `guardar(grupo, clave, valor)`, `regenerarWebhookToken()`.

**Vista `/configuracion`** (rol: `admin`): CFG-01 con pestañas:
1. **DIAN / Facturación**: razón social, NIT, régimen, tipo documento, ambiente (habilitación/producción), resolución (número/fecha/prefijo/desde/hasta/vigente), toggle "Facturación electrónica activa" (`dian.envio_activo`). Guardado con validación. Nota visible: "Esta parametrización habilita la configuración; la integración con el proveedor DIAN se conectará en una fase posterior."
2. **Empresa / Impresión**: nombre, dirección, teléfono, pie de ticket.
3. **Reservas / Webhook**: token (mostrado con reveal/copiar, botón regenerar) y toggle activo.

### 3.3 Dashboard KPIs en vivo (DASH-01)

`ReporteService::kpisRealtime()` devuelve:
- `ventas_dia` (suma `total` de pedidos `pagado` con `created_at` de hoy), `transacciones_dia`, `ticket_promedio`, `food_cost_porcentaje` (suma costo de items vendidos hoy / ventas), `mesas_ocupadas` (estado `ocupada`), `comandas_cocina_activas`, `picos_por_hora` (últimas 6h, counts por hora de creación)., `transacciones_dia`, `ticket_promedio`, `food_cost_porcentaje` (suma costo de items vendidos hoy / ventas), `mesas_ocupadas` (estado `ocupada`), `comandas_cocina_activas`, `picos_por_hora` (últimas 6h, counts por hora creato).
- Nota: el dashboard actual muestra valores mock en USD — la sección KPI se reemplaza por datos reales COP manteniendo el diseño de tarjetas.

Edición de `resources/views/dashboard.blade.php` (componente volt/livewire para datos en vivo o vista con inyección de servicio). **Requiere lock + coordinación** con Antigravity (es su módulo DASH-01).

### 3.4 Centro de Reportes (REP-01 → pestañas)

`ReporteService` se extiende con:
- `ventasPorPeriodo(desde, hasta)`: serie por día (ventas, transacciones, ticket prom).
- `ventasPorTipo(desde, hasta)`: por canal `mesa/mostrador/delivery`.
- `ventasPorProducto(desde, hasta, limite=10)`.
- `ventasPorTrabajador(desde, hasta)` (por user que cobró/creó el pedido).
- `comparativaPeriodos(desde, hasta)`: período actual vs anterior (ventas, transacciones, ticket, % variación).
- `topClientes(desde, hasta, limite=10)`, `tiemposEntrega(desde, hasta)` (promedio/min/max minutos), `resumenReservas(desde, hasta)` (confirmadas, canceladas, no-shows, cumplimiento %).
- Usa datos de `Pedido` (+`items_pedido`, `productos.costo`), `Cliente`, `Delivery`, `Reserva`. Eager-loading y `groupBy` en BD.

`/reportes` pasa a pestañas: **Estado de Resultados** (existente), **Ventas**, **Clientes/Delivery**, **Reservas**. Barra de rango de fechas compartida (persistida en estado del componente). Rol: `gerente` (ya definido); admin incluido por default en middleware.

### 3.5 Exportaciones

- **PDF servidor**: `barryvdh/laravel-dompdf`. Ruta GET `reportes/exportar-pdf` (authz gerente) recibe `reporte`, `desde`, `hasta` → renderiza blade `resources/views/pdf/reporte.blade.php` (hoja Letter/Carta A4, `@page` 216×279mm, cabecera con razón social/NIT de configuración, tablas, pie con fecha de emisión) → `DomPDF` download. Auditar paquete (dependency-audit) antes de instalar.
- **CSV**: generado en memoria y servido como descarga, con `;` como separador, BOM UTF-8 (`\xEF\xBB\xBF`), content-type `text/csv; charset=utf-8`. Botón en cada pestaña.
- Tickets 80mm: sin cambio (siguen por `window.print()`).

## 4. Rutas y RBAC

```
GET  /reservas              role:mesero,cajero,gerente   name:reservas        (interna RES-01)
GET  /reservas/crear        guest (throttle)             name:reservas.publico
POST /api/reservas          guest (throttle + token)     name:reservas.webhook
GET  /configuracion         role:admin                   name:configuracion   (CFG-01)
GET  /reportes/exportar-pdf role:gerente                 name:reportes.pdf
GET  /reportes/exportar-csv role:gerente                 name:reportes.csv
```
Navbar (layout/navigation) + Dashboard: activar tarjeta REP-01 y añadir accesos Reservas y Configuración.

## 5. Testing (TDD)

- `Fase5ReservasTest`: creación interna, disponibilidad/solapamiento, ciclo de vida completo con bloqueo/liberación de mesa, validaciones, auditoría, RBAC de `/reservas`.
- `Fase5PublicoReservasTest`: formulario público (crea `solicitada`), honeypot, rate limit; webhook (token correcto/incorrecto/inactivo, payload válido/422, respuestas).
- `Fase5ReportesTest`: `kpisRealtime`, `ventasPorPeriodo/Tipo/Producto/Trabajador`, `comparativaPeriodos`, `topClientes`, `tiemposEntrega`, `resumenReservas` con datos controlados (fechas fijas), export PDF (status 200 + content-type) y CSV.
- `Fase5ConfiguracionTest`: seeder inicial, guardar/obtener, regenerar token, RBAC `/configuracion` (admin vs otros).
- Suite final y `migrate:fresh --seed`.

## 6. Criterios de éxito

- Un cliente puede reservar por el formulario público o por webhook (n8n/WhatsApp) y el gerente la confirma desde RES-01 con bloqueo real de la mesa.
- El dashboard muestra KPIs reales del día (COP), no mock.
- Reportes avanzados exportables (PDF servidor en hoja carta + CSV) para gerente.
- Configuración DIAN y webhook parametrizable desde `/configuracion` sin tocar código.
- Suite completa ≥102 tests 100% verde; `coordination.md` actualizado; locks liberados.

## 7. Riesgos / notas de coordinación

- **dashboard.blade.php y navigation** son mantenidos por Antigravity (DASH-01): levantar `.locks/fase5-reservas-reportes.lock` antes de editar y registrar en `coordination.md`.
- Añadir paquete `barryvdh/laravel-dompdf`: ejecutar skill `dependency-audit` previo; en su defecto verificar packagist/última release y fijar el constraint de versión a una release estable.
- Volt: datos al servicio vía `with()` (no exponer métodos privados ni Computed a la vista); `constrained('tabla')` explícito en FKs con nombres no convencionales.
- Vista KPI del dashboard: si el dashboard es blade puro (no Volt), conectarlo con un pequeño componente Volt `dashboard.kpis` embebido.