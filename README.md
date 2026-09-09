# Sistema de Gestión para Restaurante de Sushi

Aplicativo web completo para gestionar un restaurante de sushi con soporte táctil para punto de venta (POS) y visualización móvil. **Stack: Laravel + PostgreSQL.**

## Módulos

| Módulo | Descripción |
|--------|-------------|
| [Pedidos](docs/modulos/pedidos.md) | Flujo de órdenes para mesas, mostrador y delivery |
| [Mesas](docs/modulos/mesas.md) | Gestión del salón y asignación de mesas |
| [Cocina](docs/modulos/cocina.md) | Pantalla de cocina y producción de platos |
| [Inventario](docs/modulos/inventario.md) | Materia prima, insumos y stock |
| [Clientes](docs/modulos/clientes.md) | Base de datos de clientes y fidelización |
| [Trabajadores](docs/modulos/trabajadores.md) | Personal, roles y permisos |
| [POS Táctil](docs/modulos/pos.md) | Punto de venta táctil |
| [Impresión de Tickets](docs/modulos/impresion.md) | Impresión de comandas, facturas y recibo |
| [Reportes](docs/modulos/reportes.md) | Estadísticas e indicadores (KPIs) |
| [Reservas](docs/modulos/reservas.md) | Reservaciones de mesas |
| [Control de Caja](docs/modulos/caja.md) | Apertura, arqueo, cierre y reportes de caja |
| [Contabilidad](docs/modulos/contabilidad.md) | Registros financieros y CBC |

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
