# RestoMaster — Sistema Integral de Gestión para Restaurantes

Aplicativo web integral de alto rendimiento para gestión de restaurantes gastronómicos, parrilla y bares, con soporte táctil para punto de venta (POS), KDS de cocina en tiempo real, CRM multicanal (WhatsApp y Email), control de caja multi-turno y reservas. **Stack: Laravel 13 + PHP 8.3 + PostgreSQL 18 + Livewire.**

## Módulos del Sistema

| Módulo | Descripción |
|--------|-------------|
| [Pedidos](docs/modulos/pedidos.md) | Flujo de órdenes para salón, mostrador, QR en mesa y delivery |
| [Mesas](docs/modulos/mesas.md) | Gestión del salón, zonas visuales y asignación inteligente con rotación de meseros |
| [Cocina (KDS)](docs/modulos/cocina.md) | Pantalla de cocina en tiempo real y producción de platos |
| [Inventario y Recetas](docs/modulos/inventario.md) | Materia prima, insumos, escandallo, proveedores y stock automático |
| [Clientes y Fidelización](docs/modulos/clientes.md) | Base de clientes VIP, programa de puntos, cashback y portal social |
| [CRM y Automatizaciones](docs/modulos/clientes.md) | Campañas WhatsApp Cloud API, encuestas CSAT/NPS y correos automáticos |
| [Trabajadores y Roles](docs/modulos/trabajadores.md) | Personal, RBAC, permisos granulares y turnos |
| [POS Táctil](docs/modulos/pos.md) | Terminal de punto de venta táctil ultra-rápido |
| [Impresión de Tickets](docs/modulos/impresion.md) | Impresión ESC/POS de comandas, facturas y auditoría visual de tickets |
| [Reportes y Analítica](docs/modulos/reportes.md) | KPIs en tiempo real, ventas por canal y gráficos interactivos |
| [Reservas](docs/modulos/reservas.md) | Agenda de reservaciones con auto-asignación de mesa y mesero |
| [Control de Caja](docs/modulos/caja.md) | Aperturas, turnos simultáneos, arqueos ciegos, cortes Z y egresos |
| [Contabilidad](docs/modulos/contabilidad.md) | Libro diario contable, balance de ingresos/egresos y cuentas por pagar |

## Documentación general

- [Arquitectura del sistema](docs/arquitectura/arquitectura.md)
- [Modelo de datos](docs/modelo-datos/modelo-datos.md)
- [Fases de desarrollo](docs/fases/fases.md)
- [Catálogo de pantallas e interacciones (prototipo Stitch)](docs/pantallas/pantallas.md)
- [Guía de inicio rápido](docs/inicio-rapido.md)

## Flujo general de un pedido

```
Cliente llega/llama → Se registra pedido
     → Caja/POS asigna (mesa | mostrador | delivery)
     → Cocina recibe la comanda y prepara
     → Mojito/Bar y cocina confirman producción
     → Mesero entrega / repartidor sale
     → Se cobra en caja
     → Se imprime ticket/factura
     → La venta alimenta inventario, reportes y contabilidad
```
