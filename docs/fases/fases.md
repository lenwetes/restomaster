# Fases de Desarrollo

Estrategia: **entregar una versión utilizable desde la Fase 1** (el restaurante puede operar), y luego ir agregando módulos. Cada fase termina con algo probado y utilizable, no con código "a medias".

---

## Fase 0 — Cimientos (configuración base)
**Objetivo:** tener el proyecto Laravel + PostgreSQL corriendo con autenticación y estructura de carpetas por módulo.

### Entregables
- [ ] Proyecto Laravel instalado y conectado a PostgreSQL.
- [ ] Migraciones base de tablas esenciales (`users`, `roles`, `sucursales`, `mesas`).
- [ ] Autenticación con login y permisos por rol (admin/gerente/cajero/mesero/cocina/delivery).
- [ ] Seeders básicos: roles, usuario admin, sucursal inicial, mesas de ejemplo.
- [ ] Layout responsive base (móvil-first) y estructura de navegación por módulo.

### Criterio de salida
- [ ] Se puede iniciar sesión como admin y la base navega con menú lateral/responsive.

---

## Fase 1 — Núcleo operativo (pedidos, cocina, POS, mesas)
**Objetivo:** el restaurante puede tomar pedidos, prepararlos y cobrarlos. Es el corazón del sistema.

### Entregables
- [ ] **Catálogo:** categorías y productos del menú CRUD (POS los consume).
- [ ] **Mesas:** mapa del salón, estados, asignación.
- [ ] **POS táctil:** crear pedido (mesa/mostrador/delivery), carrito, notas, envío a cocina.
- [ ] **Cocina (KDS):** cola de comandas por área, marcar producción/listo/entregado.
- [ ] **Pagos:** efectivo, tarjeta, mixto; cálculo de cambio; cierre de pedido.
- [ ] **Imprenta:** comandas por área + ticket de venta (térmico 80mm).

### Criterio de salida
- [ ] Flujo completo operando: mesa → orden → cocina → cobro → ticket. El restaurante puede trabajar con esto.

---

## Fase 2 — Control de Caja y Contabilidad
**Objetivo:** control financiero diario, con el cruce caja-operación.

### Entregables
- [ ] **Caja:** apertura con fondo inicial, movimientos (egreso/retiro/ingreso), arqueo, cierre de turno.
- [ ] **Reporte Z / corte de caja** por turno y por cajero.
- [ ] **Contabilidad:** registro automático de ingresos desde pedidos pagados; registro de gastos; reporte estado de resultados simple.
- [ ] **Auditoría:** movimientos sensibles quedan logueados.

### Criterio de salida
- [ ] Un turno completo queda cuadrable: ventas, caja y contabilidad coinciden y son auditables.

---

## Fase 3 — Inventario y Recetas
**Objetivo:** cerrar el ciclo de costo: lo que se vende, descuenta materia prima.

### Entregables
- [ ] **Insumos** CRUD con stock y unidades.
- [ ] **Recetas:** productos del menú compuestos por insumos.
- [ ] **Movimientos de stock:** ingreso por compra, consumo por venta, merma, ajuste.
- [ ] **Compras a proveedores** (género cuentas por pagar).
- [ ] **Alertas de stock bajo** y valorización de inventario.

### Criterio de salida
- [ ] Al cerrar un pedido el stock baja según las recetas; reporte de stock y mermas confiable.

---

## Fase 4 — Clientes y Fidelización + Delivery
**Objetivo:** fidelizar clientes y gestionar entregas a domicilio.

### Entregables
- [ ] **Clientes** CRUD con búsqueda por teléfono, direcciones guardadas.
- [ ] **Puntos de fidelidad:** ganar/canjear en POS.
- [ ] **Delivery:** pedidos con repartidor asignado, estados de entrega, tiempos.
- [ ] **Reporte de clientes y delivery** (top clientes, tiempos de entrega).

### Criterio de salida
- [ ] Un delivery completo: cliente → dirección → repartidor → entrega → pago, con puntos acumulados.

---

## Fase 5 — Reservas y Reportes Completos
**Objetivo:** reservas y tableros de indicadores (KPIs).

### Entregables
- [ ] **Reservas:** gestión, bloqueo de mesa, confirmación, no-shows, (opcional) formulario público.
- [ ] **Dashboard de KPIs:** ventas del día en vivo, ticket promedio, margen, food cost, mesas ocupadas, picos por hora.
- [ ] **Reportes avanzados:** ventas por producto/vendedor/tipo, comparativas de períodos.
- [ ] **Exportaciones** a PDF y Excel.

### Criterio de salida
- [ ] El gerente decide con datos en tiempo real y exporta reportes para revisión.

---

## Fase 6 — Cola de trabajos, impresión en red y Pulido
**Objetivo:** robustez, escalabilidad y refinamiento de UX.

### Entregables
- [ ] Jobs/queue para impresión y notificaciones (Redis o DB queue).
- [ ] Reimpresión de tickets/comandas desde historial.
- [ ] Mejoras de flujo: modos offline básico de caja (opcional), 2FA, respaldos de BD.
- [ ] Pruebas integrales y corrección de bugs acumulados.

### Criterio de salida
- [ ] Sistema estable bajo uso real en horario pico.

---

## Priorización sugerida
> **Valor para el negocio por fase:**
> F1 (operar) > F2 (control de dinero) > F3 (costos) > F4 (ventas/canales) > F5 (análisis) > F6 (robustez).

Si el tiempo es limitado, lo crítico es terminar F1 y F2 antes de cualquier cosa adicional.