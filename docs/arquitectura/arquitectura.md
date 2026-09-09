# Arquitectura del Sistema

## Stack
- **Backend / Aplicación:** Laravel (PHP 8.2+)
- **Base de datos:** PostgreSQL
- **Frontend:** Blade + Livewire o Inertia (Vue/React) — para buen soporte táctil y móvil
- **Autenticación:** Laravel Breeze/Fortify (roles y permisos)
- **Imprenta:** librería de impresión térmica (por ejemplo Laravel + conexión a impresoras de red/USB)

## Arquitectura general (monolito modular)

```
┌──────────────────────────────────────────────┐
│              Interfaz (UI)                   │
│  POS táctil | KDS cocina | Móvil | Admin     │
└───────────────────┬──────────────────────────┘
                    │  HTTP / JSON / Livewire
┌───────────────────▼──────────────────────────┐
│            Capa de Aplicación (Laravel)      │
│  Rutas · Controladores · Servicios · Jobs    │
│  Autenticación · Políticas (RBAC)            │
└───────────────────┬──────────────────────────┘
                    │  Eloquent / Query Builder
┌───────────────────▼──────────────────────────┐
│                PostgreSQL                     │
│  Tablas · Relaciones · Migraciones · Seeds   │
└──────────────────────────────────────────────┘
```

## Capas dentro de Laravel
1. **Rutas** (`routes/web.php` y `routes/api.php`): definen accesos por módulo.
2. **Controladores:** orquestan las peticiones (poco lógica de negocio).
3. **Servicios:** lógica de negocio reutilizable (ej. `PedidoService`, `InventarioService`).
4. **Modelos (Eloquent):** representan entidades y relaciones.
5. **Migraciones/Seeders:** estructura de BD y datos iniciales (roles, menú, mesas).
6. **Políticas/Gates:** controlan permisos por rol.
7. **Jobs/Events:** tareas asíncronas (envío a cocina, impresión, notificaciones).

## Principios de diseño
- **Monolito modular:** separado por dominio (cada módulo es un "dominio"), conexo pero ordenado.
- **Transaccional:** los flujos críticos (pedido→cocina→pago→inventario) se envuelven en transacciones de BD para consistencia.
- **RBAC:** permisos centralizados por rol; cada acción se verifica.
- **Auditoría:** movimientos sensibles quedan logueados.
- **Mobile-first / táctil:** las pantallas de POS, mesas y cocina son responsive y optimizadas para toque.

## Flujo de datos de un pedido (end-to-end)
1. **POS** crea el pedido → estado `creado`.
2. Al **enviar a cocina**, se genera la comanda → cola de cocina.
3. **Cocina** marca ítems → al quedar `lista`, descuenta **inventario** (receta).
4. Mesero **entrega** → estado `entregado`.
5. **Cobro** en POS → estado `pagado` → actualiza la **caja** del turno y genera ingreso **contable** → mesa **por_limpiar**.
6. Al cierre del turno se realiza **arqueo de caja** (cuadre efectivo / reporte Z).
6. Todo lo anterior alimenta **reportes** en tiempo real.

## Requisitos de despliegue
- Servidor con PHP 8.2+, PostgreSQL.
- Opcional: cola de trabajos (Redis/DB queue) para impresión y notificaciones.
- Impresoras térmicas: conexión por impresora local o de red.
