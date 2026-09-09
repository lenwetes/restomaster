# Módulo: Control de Caja

## Objetivo
Gestionar cada caja de forma independiente: apertura, movimientos de efectivo durante el turno, arqueo y cierre, con total control y auditoría del dinero.

## Conceptos clave

### Caja (sesión/turno de caja)
- Una caja se abre por **usuario/turno** (ej. cajero turno mañana).
- Cada caja tiene un fondo inicial de dinero en efectivo.

### Apertura de caja
- Antes de operar, el cajero registra el **fondo inicial** (efectivo con que inicia).
- La caja queda `abierta` y solo se puede operar mientras esté abierta.

### Movimientos de caja
En una caja abierta pueden registrarse movimientos de efectivo:

| Movimiento | Efecto | Ejemplo |
|------------|--------|---------|
| **Ingreso** | Aumenta el efectivo en caja | Ventas cobradas en efectivo, depósito recibido |
| **Egreso** | Disminuye el efectivo en caja | Gasto de caja chica, retiro |
| **Retiro/Depósito a banco** | Saca efectivo de la caja | Se deposita el excedente para no acumular efectivo |

### Arqueo de caja (corte)
- Al cerrar el turno, el sistema calcula cuánto efectivo **debería haber**:
  `efectivo esperado = fondo inicial + ingresos en efectivo − egresos − retiros`
- El cajero cuenta el **efectivo físico** real.
- Se comparan ambos y se registra sobrante/faltante.

### Cierre de caja
- Cuando el cajero termina, se cierra la caja quedando `cerrada`.
- No se pueden hacer nuevas ventas/movimientos en esa caja mientras esté cerrada.
- Respalda la auditoría completa del turno.

## Ciclo de vida de una caja

```
Abierta (fondo inicial) → Operativa (ingresos/egresos) → Arqueada (corte) → Cerrada
   └──────────────► Cancelada (si se abre por error sin operar)
```

| Estado | Descripción |
|--------|-------------|
| `abierta` | En operación, acepta ventas y movimientos |
| `cerrada` | Turno finalizado, ya no acepta operaciones |
| `cancelada` | Abierta por error y anulada sin movimiento |

## Reportes de caja

### 1. Reporte de caja por turno (Z)
- Resumen de la caja al cierre: fondo inicial, ventas totales, total por método de pago, egresos, retiros, efectivo esperado, efectivo físico, sobrante/faltante.

### 2. Corte/cuadre de caja
- Detalle de todos los movimientos del turno (ingresos, egresos, retiros) con hora y usuario.

### 3. Reporte de cajeros
- Comparativo de ventas y cuadre por cajero/turno/sucursal.

### 4. Reporte de caja por período
- Consolidado de todas las cajas del día/semana/mes: totales por método de pago, faltantes, sobrantes.

## Reglas de negocio
- Solo se puede cobrar si existe una **caja abierta** del usuario actual.
- No se puede cerrar/arquear una caja con pedidos pagados pendientes de registrar.
- El faltante/sobrante se registra y se reporta; requiere nota.
- Solo `admin`/`gerente` puede abrir, cerrar o cancelar una caja ajena.
- El cierre de un turno es **necesario** para el reporte Z y la contabilidad del día.

## Ejemplo de flujo del día
1. **Mañana:** cajero abre caja con fondo $200 → estado `abierta`.
2. **Durante el día:** venta en efectivo +$500, venta en tarjeta (no toca efectivo), gasto de caja -$20, retiro a banco -$300.
3. **Sobrantes esperados:** `200 + 500 − 20 − 300 = 380` efectivo esperado.
4. **Cajero cuenta $385** → sobrante $5 (se registra).
5. **Cierre:** caja `cerrada`, se imprime reporte Z, se envía a contabilidad.

## Interacciones
- **POS:** cada cobro en efectivo/tarjeta alimenta los totales de la caja.
- **Contabilidad:** el arqueo y el reporte Z alimentan los ingresos del día.
- **Trabajadores:** registra el cajero responsable de cada turno.
- **Reportes:** genera cortes Z, de cajeros y consolidados.
