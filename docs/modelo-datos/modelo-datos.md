# Modelo de Datos (borrador)

Base PostgreSQL. Diagrama de las entidades principales y sus relaciones.

## Entidades y relaciones

```
usuarios ─┬─ roles (pivot)
          └─ turnos/cajas (abre cajas)

clientes ──1:N── direcciones
clientes ──1:N── reservas
clientes ──1:N── puntos_fidelidad

restaurantes/config ── sucursales ──1:N── mesas
sucursales ──1:N── impresoras

categorias ──1:N── productos (menú)
productos ──1:N── selecciones (modificadores/opciones)
productos ──1:N── items_receta
insumos ──1:N── items_receta
insumos ──1:N── movimientos_inventario

pedidos ──1:1── mesa (opcional)
pedidos ──1:N── items_pedido
pedidos ──1:N── pagos
pedidos ──1:N── estados_historial
pedidos ──1:N── reservas (origen)
pedidos ──1:N── entregas (repartidor)

items_pedido ──1:1── item_cocina (KDS item, estado producción)
items_pedido ──N:M── selecciones (valores aplicados)

pagos ──N:1── cajas (turno)
cajas ──1:N── movimientos_caja
cajas ──1:N── arqueos

movimientos: stock (insumos) | caja (ingresos/egresos/retiros)

compras ──1:N── items_compra (insumos) → cuentas_por_pagar
pedidos_pagados / cierre_caja ──> movimientos_contables (ingresos)
```

## Tablas principales (borrador de columnas)

### Catálogo y negocio
| Tabla | Columnas clave |
|-------|----------------|
| `sucursales` | id, nombre, direccion, telefono |
| `mesas` | id, sucursal_id, numero, zona, capacidad, estado |
| `categorias` | id, nombre, orden, visible |
| `productos` | id, categoria_id, nombre, precio, costo, estado (activo/inactivo), imagen |
| `selecciones` | id, producto_id, nombre, precio_extra, tipo (radio/check) |
| `insumos` | id, nombre, unidad, stock, stock_minimo, proveedor, costo |
| `items_receta` | id, producto_id, insumo_id, cantidad |

### Operación
| Tabla | Columnas clave |
|-------|----------------|
| `pedidos` | id, tipo (mesa/mostrador/delivery), estado, mesa_id, cliente_id, total, descuento, notas, usuario_id |
| `items_pedido` | id, pedido_id, producto_id, cantidad, precio, notas, estado_produccion |
| `items_pedido_seleccion` | id, item_pedido_id, seleccion_id, precio_extra |
| `pagos` | id, pedido_id, caja_id, metodo (efectivo/tarjeta/mixto), monto, fecha |
| `estados_historial` | id, pedido_id, estado_anterior, estado_nuevo, usuario_id, fecha |

### Cocina / KDS
| Tabla | Columnas clave |
|-------|----------------|
| `comandas` | id, pedido_id, area, estado, hora_recibida |
| `items_cocina` | id, item_pedido_id, area, estado, tiempo_inicio, tiempo_fin |

### Clientes / Reservas
| Tabla | Columnas clave |
|-------|----------------|
| `clientes` | id, nombre, telefono, email, puntos, estado |
| `direcciones` | id, cliente_id, direccion, referencia, principal |
| `reservas` | id, cliente_id, mesa_id, fecha_hora, personas, estado, notas, anticipo |

### Inventario
| Tabla | Columnas clave |
|-------|----------------|
| `movimientos_stock` | id, insumo_id, tipo (ingreso/consumo/merma/ajuste), cantidad, motivo, usuario_id, referencia (pedido/compra) |
| `compras` | id, proveedor, fecha, total, estado |
| `items_compra` | id, compra_id, insumo_id, cantidad, costo_unitario |

### Caja / Contabilidad
| Tabla | Columnas clave |
|-------|----------------|
| `cajas` | id, sucursal_id, usuario_id, estado (abierta/cerrada/cancelada), fondo_inicial, abierta_en, cerrada_en |
| `movimientos_caja` | id, caja_id, tipo (ingreso/egreso/retiro), monto, motivo, metodo, usuario_id, referencia |
| `arqueos` | id, caja_id, efectivo_esperado, efectivo_real, diferencia, estado, notas |
| `movimientos_contables` | id, tipo (ingreso/gasto), categoria, monto, metodo, fecha, referencia, usuario_id |

### Personal y seguridad
| Tabla | Columnas clave |
|-------|----------------|
| `users` | id, nombre, email/login, password, rol, estado, area |
| `roles` / `permissions` | RBAC (Sanctum/abilities o Spatie) |

### Impresión
| Tabla | Columnas clave |
|-------|----------------|
| `impresoras` | id, sucursal_id, nombre, tipo (ticket/comanda), area, conexion (usb/red/ip) |
| `jobs_impresion` | id, tipo, destino, contenido, estado, reimpresion, usuario_id |

## Notas de diseño
- Todos los estados se manejan como ENUM/cadenas controladas en Laravel.
- Las tablas de historial (`estados_historial`, `auditoria`) son de solo inserción.
- PostgreSQL se aprovecha con índices, transacciones y constraints de unicidad (ej. una mesa no puede tener 2 pedidos activos).
- Los movimientos de stock/caja siempre tienen `referencia` (pedido/compra/cedula) para trazabilidad.