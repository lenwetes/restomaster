# Módulo: Clientes

## Objetivo
Almacenar datos de los clientes, su historial de compras y un programa de fidelización para mostrador y delivery.

## Datos del cliente
- Nombre completo
- Teléfono / WhatsApp
- Email (opcional)
- Direcciones de entrega (para delivery) — pueden ser varias
- Fecha de registro
- Notas (preferencias, alergias)
- Puntos de fidelidad / saldo

## Flujos

### 1. Registro
- Al hacer un pedido de mostrador/delivery se puede crear o seleccionar el cliente.
- Búsqueda rápida por teléfono (el campo más útil en un restaurante).

### 2. Pedido a domicilio
- Se selecciona el cliente, se elige una dirección de entrega guardada.
- El pedido guarda el histórico del cliente.

### 3. Fidelización (puntos)
- Por cada venta se acumulan puntos (ej. 1 punto por cada 10 unidades de moneda).
- Los puntos se canjean por descuentos o ítems gratis.
- Historial de puntos ganados/canjeados.

## Reglas de negocio
- El teléfono es el identificador principal para búsqueda rápida.
- Un cliente **inactivo** no puede tener pedidos activos nuevos pero mantiene su historial.
- Los puntos tienen vencimiento/reglas configurables.

## Interacciones
- **Pedidos:** se asocia cliente en mostrador/delivery.
- **POS/Reportes:** sumatoria de compras por cliente, mejores clientes.
- **Reservas:** se usa el cliente para ligar reservas.
- **Reportes:** segmentación y frecuencia de compra.
