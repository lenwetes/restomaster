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

## Webhook (n8n/WhatsApp)

Permite crear reservas desde bots (WhatsApp, Instagram, páginas externas) sin sesión.

### Endpoint
```
POST /api/reservas
```

### Autenticación
Header obligatorio `X-Webhook-Token` con el valor configurado en **Configuración → Reservas → Token webhook** (clave `reservas.webhook_token`). La comparación usa `hash_equals` (constante en tiempo). Si el webhook está desactivado (`reservas.webhook_activo` = `false`) responde `403`.

### Payload
```json
{
  "nombre": "Cliente WhatsApp",
  "telefono": "3200000001",
  "email": "cliente@mail.com",
  "fecha": "2026-09-26",
  "hora": "19:00",
  "personas": 3,
  "notas": "Sin soja"
}
```
Solo `nombre`, `telefono`, `fecha`, `hora` y `personas` son obligatorios. `email` y `notas` son opcionales. `fecha` debe ser hoy o posterior y `hora` en formato `H:i`.

### Respuestas
| Código | Situación |
|--------|-----------|
| `201` | Reserva creada (`{"reserva_id": 1, "token_publico": "..."}`) |
| `401` | Token ausente/incorrecto |
| `403` | Webhook desactivado |
| `422` | Payload inválido o reserva rechazada (solo se crea en estado `solicitada`) |

Al crearse, la reserva queda en estado `solicitada` con `origen = webhook`; el equipo la confirma desde **Reservas** (RES-01). Toggle y token se gestionan en CFG-01.
