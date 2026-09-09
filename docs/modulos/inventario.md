# Módulo: Inventario

## Objetivo
Controlar la materia prima e insumos del restaurante, permitiendo recetas que descuentan stock automáticamente al producir.

## Conceptos clave

### Materia prima / Insumos
- Nombre, unidad de medida (kg, l, u), stock actual, stock mínimo.
- Proveedor asociado.
- Categoría (pescados, arroz, mariscos, verduras, salsas, empaques...).

### Recetas
- Un producto del menú (ej. "California Roll") se compone de insumos con cantidades.
- Ejemplo: `California Roll` → 120g arroz, 50g salmón, 1 alga, etc.

## Ciclo de vida de stock

```
Compra/Ingreso → Stock disponible → (venta/producción descuenta) → Stock bajo → Reorden/Compra
```

## Flujos

### 1. Ingreso (compra a proveedor)
1. Se registra una compra con insumos y cantidades.
2. Aumenta el stock disponible.

### 2. Consumo (por venta)
1. Al confirmar un ítem `lista` en cocina se ejecuta su receta.
2. Cada insumo de la receta descuenta la cantidad requerida.
3. Si no hay stock suficiente para la receta, se **advierte** (puede permitirse por política).

### 3. Merma / ajuste
- Registrar pérdida (vencimiento, rotura, preparación) con motivo.
- Permite stock manual por conteo físico.

## Alertas y control
- **Stock mínimos:** avisa cuando un insumo cae bajo su mínimo → sugerencia de recompra.
- **Reporte de merma.**
- **Valorización de inventario** (costo de los insumos consumidos).

## Reglas de negocio
- Un insumo no se puede eliminar si tiene movimientos (solo desactivar).
- La venta descontará inventario **solo si el producto tiene receta**.
- Variaciones/ajustes requieren registro con motivo y usuario.

## Interacciones
- **Cocina/Pedidos:** confirma producción → descuenta.
- **POS:** solo vende ítems `activos` con stock configurado.
- **Reportes:** costo de ventas = consumo de materia prima.
- **Contabilidad:** las compras generan cuentas por pagar a proveedores.
