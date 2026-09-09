# Módulo: POS Táctil (Punto de Venta)

## Objetivo
Permitir crear pedidos y cobrar de forma rápida con interfaz táctil, pensada para tablets/pantallas táctiles y también usable en móvil.

## Vista principal (táctil)
- **Botones grandes** para el menú y los ítems (acorde a pantalla táctil).
- **Categorías** por pestaña (Sushi, Nigiri, Makis, Rolls, Entradas, Bebidas, Postres).
- **Panel de carrito/pedido** a un lado (o abajo en móvil).
- Botón de **cobrar** al finalizar.

## Flujo de cobro

```
Seleccionar destino (mesa / mostrador / delivery)
  → Agregar ítems al carrito
  → Aplicar descuento (permiso requerido) / notas
  → Enviar a cocina (si aplica)
  → Cobrar: efectivo | tarjeta | mixto
  → Confirmar pago → cierre del pedido / impresión de ticket
```

## Métodos de pago
- **Efectivo:** entrada de monto y cálculo de cambio automático.
- **Tarjeta:** registro del monto (pasarela opcional / captura manual).
- **Mixto:** división de monto entre varios métodos.
- **Cuenta dividida:** dividir una mesa entre varias tarjetas/personas.

## Reglas de negocio
- El POS solo muestra productos `activos` y en `stock` configurado.
- Descuentos bloqueados para roles sin permiso.
- El cobro requiere autorización para reabrir/cancelar pedidos pagados.
- Funciona offline básico: puede registrar venta y sincronizar (opcional, fase avanzada).

## Interacciones
- **Pedidos:** crea y cierra la orden.
- **Mesas:** al cobrar libera/limpia la mesa.
- **Impresión:** emite ticket/factura al confirmar.
- **Inventario:** descuenta al producirse.
- **Clientes:** permite asociar y acumular puntos.
- **Contabilidad/Reportes:** registra el ingreso.
