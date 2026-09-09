# Módulo: Contabilidad

## Objetivo
Registrar y conciliar los movimientos financieros del restaurante, tanto los que nacen de la operación (ventas, compras) como otros gastos e ingresos.

## Componentes

### 1. Cuentas (CBC - Cuentas Bancarias / Caja)
- Caja chica (efectivo)
- Cuentas bancarias
- Saldo por método de pago
- Conciliación de caja al cierre del día

### 2. Ingresos
- Provienen **automáticamente** de los pedidos pagados.
- Desglose por método de pago (efectivo, tarjeta, mixto).
- Propinas (opcional).

### 3. Egresos / Gastos
- Compras a proveedores (materia prima).
- Gastos operativos (sueldos, renta, servicios, mantenimiento).
- Requieren categoría, monto y comprobante.

### 4. Cuentas por pagar / gastos de proveedores
- Compra registrada en inventario → genera deuda al proveedor.
- Registro de pagos parciales o totales.

### 5. Arqueo de caja
- Cierre diario: cuadrar efectivo físico vs. ventas registradas.
- Detecta faltantes/sobrantes con registro.

## Flujos

### Ventas → Contabilidad
```
Pedido pagado → se crea movimiento de ingreso automático
```

### Compra → Contabilidad
```
Compra de insumos (inventario) → gasto/cuenta por pagar al proveedor
```

## Reportes financieros
- Estado de resultados simple (ingresos - gastos = resultado del período).
- Resumen por método de pago y por caja.
- Flujo de caja.
- Historial de todas las transacciones (auditable).

## Reglas de negocio
- Cualquier movimiento de caja queda registrado con usuario, fecha y motivo.
- El arqueo diario debe cerrar antes de iniciar el siguiente turno (configurable).
- Solo accesible para `admin` y `gerente` (con permisos finos).
- Nunca se elimina un movimiento: se anula/reversa dejando rastro.

## Interacciones
- **Pedidos/POS:** generan ingresos automáticos.
- **Inventario:** genera gastos por compra.
- **Trabajadores:** registra quién hace cada movimiento.
- **Reportes:** estado de resultados y flujo de caja.
