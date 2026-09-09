# Módulo: Pedidos

## Objetivo
Registrar, rastrear y cobrar todas las órdenes del restaurante sin importar su origen (mesa, mostrador o delivery).

## Tipos de pedido
1. **En salón (mesa):** el cliente está sentado, se asigna a una mesa.
2. **Mostrador / para llevar:** el cliente retira su pedido en el local.
3. **Delivery:** el pedido se envía a un domicilio, asignado a un repartidor.

## Ciclo de vida de un pedido (estados)

```
Creado → Enviado_a_cocina → En_Proceso → Listo → Entregado/Retirado → Pagado/Cerrado
   └──────────► Cancelado (en cualquier momento, antes de pagar)
```

| Estado | Descripción |
|--------|-------------|
| `creado` | El pedido se registró en caja/POS, aún no se envía a producción |
| `enviado_a_cocina` | Las comandas se imprimieron/enviaron a cocina |
| `en_proceso` | Al menos un ítem está en preparación |
| `listo` | Todos los ítems están preparados, listos para entregar |
| `entregado` | Se entregó al cliente / salió el repartidor (delivery) |
| `pagado` | Se cobró y el pedido se cierra |
| `cancelado` | Se canceló (queda registrado para auditoría, sin afectar ingresos) |

## Componentes del pedido
- **Cabecera:** tipo, fecha/hora, mesa o repartidor, estado, total, descuento.
- **Líneas (ítems):** producto, cantidad, precio, notas, estado individual de producción.
- **Pagos:** uno o varios métodos de pago asociados a la cabecera.

## Flujo funcional

### 1. En salón (mesa)
1. Caja/POS abre o selecciona la mesa.
2. Agrega ítems del menú al pedido.
3. Envía a cocina (comandero de cada área).
4. Cuando el mesero confirma entrega, el pedido pasa a `entregado`.
5. El cliente paga → `pagado` y la mesa queda `libre`.

### 2. Mostrador / para llevar
1. Caja registra el pedido sin mesa.
2. Se envía a cocina.
3. Cuando está `listo`, se llama al cliente; al retirar pasa a `entregado` y `pagado`.

### 3. Delivery
1. Caja registra el pedido con datos del cliente y dirección.
2. Se asigna repartidor.
3. Cocina prepara; cuando `listo`, el repartidor sale → `entregado`.
4. Al cobrar (efectivo contra entrega o tarjeta en línea) → `pagado`.

## Reglas de negocio
- Un pedido en mesa solo se cobra cuando el cliente solicita la cuenta.
- Un pedido cancelado no afecta ingresos, solo queda en auditoría.
- Cada ítem puede cancelarse por separado (motivo obligatorio).
- Los descuentos requieren rol con permiso (`chef`, `gerente`).
- Nota de cocina por ítem (ej. "sin sésamo", "bien cocido").

## Interacciones con otros módulos
- **Mesas:** ocupación/liberación automática según estado del pedido.
- **Cocina:** envía comandas y recibe estado de producción.
- **Inventario:** al confirmar un ítem preparado se descuenta materia prima.
- **Clientes:** en delivery/mostrador se asocia el cliente.
- **Pagos/Contabilidad:** al cerrar el pedido se registra la venta.
- **Reportes:** cada transición alimenta historial para métricas.
