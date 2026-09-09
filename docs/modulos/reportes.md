# Módulo: Reportes

## Objetivo
Transformar los datos operativos en información accionable con indicadores clave (KPIs) del restaurante.

## Reportes principales

### Ventas
- Ventas por día/semana/mes.
- Ventas por tipo (salón, mostrador, delivery).
- Ventas por producto (top productos).
- Ventas por trabajador/cajero.
- Ticket promedio y número de transacciones.

### Cocina / Operación
- Tiempo promedio de preparación por plato.
- Platos más rentables vs. más vendidos.
- Tiempo de rotación de mesas.

### Inventario
- Stock bajo / por agotarse.
- Mermas por período.
- Consumo de insumos.
- Valorización de inventario.

### Clientes
- Top clientes por gasto.
- Frecuencia de compra.
- Nuevos vs. recurrentes.

### Delivery
- Pedidos por repartidor.
- Tiempos de entrega.
- Zonas con más pedidos.

### Financiero
- Ingresos vs. gastos del período.
- Costo de materias primas.
- Margen bruto (%).

## KPIs clave (Dashboards)
- **Ventas del día** (en tiempo real).
- **Ticket promedio**.
- **% margen bruto**.
- **Costo de comida (food cost %)**: `costo materia prima / ventas`.
- **Pedidos por hora** (picos de demanda).
- **Mesas ocupadas en tiempo real**.

## Exportaciones
- Todos los reportes exportables a **PDF** y **Excel (CSV)**.
- Reportes programados por email (resumen diario) — fase avanzada.

## Reglas de negocio
- Solo accesible para `admin` y `gerente`.
- Los datos provienen de pedidos pagados + gastos, no de entradas manuales.
- Rangos de fecha configurables con comparación entre períodos.

## Interacciones
- Alimenta de **Pedidos, Inventario, Clientes, Delivery, Contabilidad, Trabajadores**.
