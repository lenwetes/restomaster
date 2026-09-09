# Módulo: Reservas

## Objetivo
Permitir reservar mesas con anticipación y gestionar el calendario de reservas del salón.

## Datos de una reserva
- Cliente (o datos de contacto si no está registrado)
- Mesa(s) o zona reservada
- Fecha y hora de llegada
- Número de personas
- Estado
- Notas (evento, cumpleaños, requisitos)
- Anticipo/señal (opcional)

## Ciclo de vida de una reserva

```
Solicitada → Confirmada → Llegó (ocupa mesa) → Finalizada
   └────────► Cancelada / No_mostró
```

| Estado | Descripción |
|--------|-------------|
| `solicitada` | Pendiente de confirmar |
| `confirmada` | Mesa bloqueada para esa hora |
| `llegó` | El cliente se presentó y se asigna la mesa |
| `finalizada` | Reserva completada |
| `cancelada` | Cancelada por el cliente |
| `no_mostró` | No se presentó (política configurable) |

## Flujos

### 1. Reserva en línea (opcional, fase avanzada)
- Formulario público con disponibilidad en tiempo real.
- Confirmación automática o por administrador.

### 2. Reserva por mesero/gerente
- Busca cliente, elige mesa/zona, hora y personas.
- Verifica disponibilidad para evitar doble reserva.

## Calendario / mapa
- Vista de agenda del día con franjas horarias.
- Mapa de mesas que colorea las reservadas.

## Reglas de negocio
- No se confirman dos reservas sobre la misma mesa en horarios que se solapan.
- Se puede configurar anticipo obligatorio para grupos grandes.
- `no_mostró` puede facturar señal (si se configuró).
- Al confirmar, la mesa pasa a estado `reservada`.

## Interacciones
- **Mesas:** bloquea/libera la mesa según estado.
- **Clientes:** liga la reserva al cliente.
- **Pedidos:** al llegar, se convierte en pedido de mesa.
- **Reportes:** tasa de cumplimiento y no-shows.
