# Módulo: Mesas

## Objetivo
Gestionar el salón, el estado de cada mesa y la asignación de comensales.

## Ciclo de vida de una mesa

```
Libre → Ocupada → (cuenta pedida) → Por_limpiar → Libre
```

| Estado | Descripción |
|--------|-------------|
| `libre` | Disponible para asignar |
| `ocupada` | Tiene clientes con pedido en curso |
| `por_limpiar` | Los clientes se fueron, requiere limpieza |
| `reservada` | Asignada a una reserva futura |

## Datos de la mesa
- Número / nombre visual
- Zona / área (ej. "salón terracota", "barra", "terraza")
- Capacidad (número de personas)
- Estado actual
- Ubicación en el mapa del salón (coordenadas para vista gráfica)

## Flujo funcional
1. Un mesero/caja toma una mesa `libre` (o libera una `reservada` para reserva).
2. La mesa pasa a `ocupada` y se vincula al pedido en curso.
3. Cuando el cliente paga, la mesa pasa a `por_limpiar`.
4. El personal de limpieza la marca como `libre`.

## Reglas de negocio
- No se pueden asignar dos pedidos activos a la misma mesa.
- El mapa de mesas muestra en tiempo real el estado de cada una (vista en pantallas de salón).
- Si una mesa tiene `reserva` cercana, el sistema avisa para evitar doble asignación.
- Cambio de mesa: permite mover un pedido activo de una mesa a otra (con registro).

## Interacciones
- **Pedidos:** estado de la mesa refleja el pedido activo.
- **Reservas:** una reserva confirmada pre-ocupa la mesa.
- **POS/Reportes:** tiempos de rotación de mesas para métricas.
