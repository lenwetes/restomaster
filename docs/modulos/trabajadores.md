# Módulo: Trabajadores

## Objetivo
Gestionar el personal del restaurante, sus roles, permisos de acceso y registro de actividad/horarios.

## Roles propuestos

| Rol | Permisos principales |
|-----|----------------------|
| `admin` | Todo, incluida configuración y contabilidad |
| `gerente` | Reportes, ajustes de inventario, descuentos |
| `cajero` | POS, pedidos, cobros, clientes |
| `mesero` | Tomar pedidos, mesas, entregar platos |
| `cocina` | KDS, marcar producción |
| `barra` | KDS de área de bar/bebidas |
| `delivery` | Ver/actualizar entregas asignadas |
| `reposteria` (opcional) | Área de postres |

## Datos del trabajador
- Datos personales (nombre, teléfono, email)
- Rol y área asignada
- Credenciales de acceso (username/password)
- Estado (activo/inactivo)
- Horarios de turno
- Fecha de ingreso

## Gestión de acceso
- Autenticación con contraseña (y opcional 2FA).
- Permisos **por rol** (RBAC): cada acción del sistema exige un permiso.
- Cada usuario solo ve los módulos que su rol permite (ej. cocina ve KDS, cajero ve POS).

## Registro de actividad (auditoría)
- Cada acción sensible (cobro, cancelación, ajuste, descuento) se registra con: usuario, fecha, detalle.

## Reglas de negocio
- No se puede eliminar un trabajador con historial: solo `inactivo`.
- Dos usuarios no comparten credenciales.
- Los permisos se otorgan por rol; el rol `admin` no se puede eliminar.

## Interacciones
- **Pedidos/POS:** registra quién cobra/toma cada pedido.
- **Cocina:** registra quién prepara cada comanda.
- **Contabilidad:** registra gastos/ventas por responsable.
- **Reportes:** desempeño por trabajador (ventas por cajero, tiempos por cocinero).
