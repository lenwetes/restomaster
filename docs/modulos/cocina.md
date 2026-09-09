# Módulo: Cocina (KDS - Kitchen Display System)

## Objetivo
Mostrar y gestionar las comandas en la cocina para producir los platos en orden, con una pantalla táctica dedicada.

## Datos de una comanda
- Número de comanda / pedido
- Mesa o método (mostrador/delivery)
- Hora de entrada
- Ítems a producir con **área de producción** (sushi, nigiri, tempura, cocina caliente, ensaladas, etc.)
- Notas especiales del cliente (aliegenos, cocción)
- Prioridad / cola

## Flujo de la comanda

```
Entra de POS → Planificada en la cola del área → En_Producción → Lista → Confirmada/Entregada
   └────────────────────────────► Cancelada
```

| Estado del ítem | Descripción |
|-----------------|-------------|
| `pendiente` | Recibida, espera en la cola |
| `en_preparacion` | El cocinero la tomó |
| `lista` | Plato terminado, listo para entregar |
| `entregada` | El mesero/commander la retiró |
| `cancelada` | Ítem cancelado (motivo obligatorio) |

## Vista principal (KDS)
- Columnas por **área** (Sushi Bar, Cocina, Frituras/Globo).
- Ordenadas por hora de llegada (FIFO) para evitar atrasos.
- Colores por tiempo de espera (normal → amarillo → rojo si excede umbral).
- Botón táctil para **tomar**, **listo** y **entregar** cada ítem.

## Reglas de negocio
- El tiempo de preparación estimado se calcula por ítem y se compara con el real.
- Un ítem `lista` permanece visible hasta que el mesero confirme entrega.
- Si un ítem se cancela después de iniciar producción, el cocinero lo ve para detenerse.
- Alarmas visuales/sonoras si un ítem supera el tiempo máximo.

## Interacciones
- **Pedidos:** recibe las comandas y le devuelve el estado de producción.
- **Inventario:** al confirmar `lista` se descuenta materia prima.
- **Impresión:** opción de imprimir tiquetes de comanda por área.
- **Reportes:** tiempos promedio de preparación por plato.
