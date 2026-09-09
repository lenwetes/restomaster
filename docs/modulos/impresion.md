# Módulo: Impresión de Tickets

## Objetivo
Imprimir de forma rápida y confiable los documentos del restaurante: comandas de cocina, tickets de venta (recibos) y facturas.

## Tipos de impresión

| Tipo | Contenido | Destino |
|------|-----------|---------|
| **Comanda de cocina** | Ítems a preparar por área | Impresoras térmicas de cocina/bar |
| **Ticket/Recibo** | Resumen de la venta, ítems, total | Impresora de caja (80mm térmica) |
| **Factura** (fiscal, opcional) | Datos fiscales | Impresora de caja |
| **Cuenta de mesa** | Detalle para que el cliente revise | Impresora de caja |

## Formatos
- **Térmico 80mm:** ancho de papel estándar para tickets y comandas.
- **A4 / PDF:** para facturas y reportes exportables.

## Preferencias configurables
- Pie de impresión (dirección, teléfono, NIT/RUC, mensaje).
- Número de copias de comanda por área.
- Fuente/logo del restaurante.

## Colas de impresión
- Cada área de producción puede tener su **propia cola** (comanda de sushi va a la impresora de sushi, la de tempura a otra).
- Se puede reimprimir un ticket/comanda desde el historial del pedido.

## Conexión de impresoras
- Impresoras locales (USB / red) conectadas a la caja.
- En red: impresión a dispositivos IP/Bluetooth según configuración.

## Reglas de negocio
- La comanda se imprime/despacha al momento de `enviar_a_cocina`.
- No se puede cerrar un pedido sin registrar la impresión (regla configurable).
- Reimpresiones quedan registradas en auditoría.

## Interacciones
- **Pedidos/Cocina:** disparan comandas.
- **POS:** dispara ticket/factura al cobrar.
- **Reportes:** exporta a PDF.
