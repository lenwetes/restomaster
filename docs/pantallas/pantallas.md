# Catálogo de Pantallas e Interacciones

Catálogo único de todas las pantallas del sistema con su flujo de interacción. Formato pensado para modelar el prototipo en **Stitch**.

## Contenido

- [Nomenclatura de pantallas](#nomenclatura-de-pantallas)
- [Acceso según rol](#acceso-según-rol)
- [Mapa de navegación maestro](#mapa-de-navegación-maestro)
- [Convenciones](#convenciones-de-la-documentación)
- [Login y Dashboard](#1-login-y-dashboard)
- [Mesas y Pedidos](#2-mesas-y-pedidos)
- [POS (Punto de Venta)](#3-pos-punto-de-venta-táctil)
- [Cocina (KDS)](#4-cocina-kds)
- [Caja y Contabilidad](#5-caja-y-contabilidad)
- [Inventario](#6-inventario)
- [Clientes y Reservas](#7-clientes-y-reservas)
- [Trabajadores, Impresión y Configuración](#8-trabajadores-impresión-y-configuración)
- [Reportes](#9-reportes)
- [Público (Cliente)](#10-público-cliente)

---

## Nomenclatura de pantallas

| Prefijo | Módulo |
|---------|--------|
| `LOG` | Login / Acceso |
| `DASH` | Dashboard / Inicio |
| `MES` | Mesas |
| `PED` | Pedidos |
| `POS` | Punto de Venta (cobro) |
| `CAJ` | Control de Caja |
| `COC` | Cocina (KDS) |
| `INV` | Inventario |
| `CLI` | Clientes |
| `TRA` | Trabajadores |
| `RES` | Reservas |
| `REP` | Reportes |
| `CON` | Contabilidad |
| `IMP` | Impresión / Config de impresoras |
| `CFG` | Configuración general |
| `PUB` | Vista pública (cliente) |

## Acceso según rol

| Rol | Login entra directo a | Puede navegar a |
|-----|-----------------------|-----------------|
| admin | DASH completo | Todo |
| gerente | DASH completo | Todo excepto seguridad de usuarios |
| cajero | POS (PED activos) | POS, Caja, Clientes, Mesas (ver), Pedidos |
| mesero | Mapa de mesas | Mesas, Pedidos, Cocina (ver estado entrega) |
| cocina | KDS | KDS, Pedidos (ver comandas) |
| barra | KDS (solo su área) | KDS (filtrado por área) |
| delivery | Cola de entregas | Delivery (pedidos tipo delivery) |

## Mapa de navegación maestro

```
LOG-login
  │ (rol)
  ▼
DASH (admin/gerente) ──┬──► REP · CON · CFG · TRA · INV · RES
                       │
POS (cajero) ──────────┬──► PED · CAJ · CLI
                       │
MES-mapa (mesero) ─────┴──► PED · COC(estado)
                       │
KDS (cocina/barra) ────┴──► PED (solo lectura de comandas)
```

## Convenciones de la documentación

- Cada pantalla se describe con: **uso** (rol), **propósito**, **elementos**, **acciones** y **navegación** (desde → hacia).
- Las transiciones usan el formato `ACCIÓN → DESTINO`.
- `[lat]` = botón grande táctil (dispositivo móvil/tablet).
- `[cmd]` = acción con teclado/ratón (PC).

---

## 1. Login y Dashboard

### LOG-01 · Login

- **Uso:** todos los roles.
- **Propósito:** autenticar y dirigir a la pantalla según rol.
- **Elementos:**
  - Campo usuario (email/nombre de usuario)
  - Campo contraseña
  - Botón `Entrar [lat]`
  - Enlace "¿Olvidaste tu contraseña?" (opcional)
- **Validaciones:**
  - Usuario inactivo → error "usuario desactivado".
  - Credenciales incorrectas → error rojo.
- **Navegación:**
  - `Entrar` → redirige por rol:
    - admin/gerente → `DASH-01`
    - cajero → `POS-01`
    - mesero → `MES-01`
    - cocina/barra → `COC-01`
    - delivery → `PED-04` (cola de entregas)
  - `¿Olvidaste tu contraseña?` → flujo de recuperación (email).

### DASH-01 · Dashboard (Inicio / KPIs)

- **Uso:** admin, gerente.
- **Propósito:** resumen ejecutivo en tiempo real con los indicadores clave.
- **Elementos:**
  - **Encabezado:** nombre del restaurante, fecha, estado de caja del día, selector de sucursal.
  - **Métricas en vivo:** ventas de hoy, ticket promedio, # pedidos, # mesas ocupadas, # pedidos en cocina.
  - **Panel de mesas:** mini-mapa del salón con estado de cada mesa (táctil).
  - **Gráfico de ventas por hora** (últimas 8h).
  - **Alertas:** stock bajo pendiente, caja abierta sin arqueo, reservas próximas.
  - **Atajos [lat]:** Nueva venta (POS), Abrir/Arquear caja, Ver reservas de hoy, Reporte de ventas.
- **Acciones de usuario:**
  - Tocar una mesa del mini-mapa → `MES-02` (detalle mesa).
  - Tocar una alerta de stock → `INV-04` (alertas).
  - Tocar "Nueva venta" → `POS-01`.
- **Navegación (menú superior/lateral):**
  - `POS / Venta` → `POS-01`
  - `Mesas` → `MES-01`
  - `Pedidos` → `PED-01`
  - `Caja` → `CAJ-01`
  - `Inventario` → `INV-01`
  - `Clientes` → `CLI-01`
  - `Reservas` → `RES-01`
  - `Trabajadores` → `TRA-01`
  - `Reportes` → `REP-01`
  - `Contabilidad` → `CON-01`
  - `Configuración` → `CFG-01`
  - `Cerrar sesión` → `LOG-01`

```
LOG-01 ──rol──► DASH-01 ──► (menú) ──► cualquier módulo
              └─ atajos ──► POS-01 / CAJ-01 / RES-01 / REP-01
```

---

## 2. Mesas y Pedidos

### MES-01 · Mapa de Mesas (salón)

- **Uso:** mesero, cajero, gerente (ver), admin (ver).
- **Propósito:** vista general del salón con el estado de todas las mesas, en tiempo real.
- **Elementos:**
  - Mapa gráfico del salón (coordenadas por mesa).
  - Cada mesa se dibuja como botón `[lat]` con color de estado:
    - verde `libre` / amarillo `ocupada` / gris `por_limpiar` / azul `reservada`.
  - Número de mesa + comensales.
  - Filtros: por zona (salón, barra, terraza).
  - Indicador de pedidos en cocina (campanita en la mesa con ítems en proceso).
- **Acciones:**
  - Tocar mesa **libre** → crear pedido → `POS-01` (pre-cargado con esa mesa).
  - Tocar mesa **ocupada** → `MES-02` (detalle/pedido).
  - Tocar mesa **por_limpiar** → botón `Marcar limpia [lat]`.
  - Tocar mesa **reservada** → `RES-02`.
- **Navegación:**
  - `POS / Nueva venta` → `POS-01`
  - `Ver todos los pedidos` → `PED-01`

```
MES-01 ── mesa libre ──► POS-01 (nuevo pedido en esa mesa)
MES-01 ── mesa ocupada ─► MES-02
MES-01 ── mesa reservada ► RES-02
```

### MES-02 · Detalle de Mesa (pedido en curso)

- **Uso:** mesero, cajero, gerente/admin (ver).
- **Propósito:** ver y gestionar el pedido activo de una mesa.
- **Elementos:**
  - Cabecera: mesa #, zona, comensales, tiempo transcurrido.
  - Líneas del pedido con estado de producción (en cola / en proceso / listo / entregado).
  - Totales: subtotal, descuento, total.
  - Botones:
    - `Agregar ítems [lat]` → `POS-01` (añade a este pedido).
    - `Enviar a cocina [lat]` (si hay pendientes).
    - `Marcar entregado [lat]` (por ítem o por todo lo listo).
    - `Pedir cuenta [lat]` → avisa a caja (flujo cajero).
    - `Cambiar mesa [lat]` → selector de mesa.
    - `Pagar [lat]` → `POS-02` (si el rol tiene permiso).
- **Navegación:**
  - `Editar / corregir ítem` → modal de detalle del ítem.
  - `Pagar` → `POS-02` (pantalla de cobro).
  - Volver → `MES-01`.

### PED-01 · Lista de Pedidos (panel general)

- **Uso:** cajero, gerente, admin; mesero (solo puede ver los suyos).
- **Propósito:** ver todos los pedidos del día con su estado y filtrarlos.
- **Elementos:**
  - Filtros: tipo (mesa/mostrador/delivery), estado, fecha, "solo míos".
  - Lista en formato tarjetas: #pedido, tipo, cliente/mesa, total, estado, hora.
  - Estado con color: creado / en cocina / listo / entregado / pagado / cancelado.
- **Acciones:**
  - Tocar tarjeta → `PED-02` (detalle).
  - Botón flotante `+ Nuevo [lat]` → `POS-01`.
- **Navegación:**
  - `Ver caja del día` → `CAJ-01`.

### PED-02 · Detalle de Pedido

- **Uso:** los que pueden ver PED-01.
- **Propósito:** inspección completa de un pedido y acciones según estado.
- **Elementos:**
  - Cabecera: #, tipo, hora, cliente (si aplica), usuario que lo tomó.
  - Items con notas y estado de producción.
  - Historial de estados (timeline).
  - Botones según rol/estado:
    - `Reimprimir comanda / ticket [lat]` → flujo IMP.
    - `Cancelar ítem [lat]` (motivo obligatorio, permiso).
    - `Editar cantidad` → modal.
    - `Marcar pagado` (si el pedido está entregado y no cobrado) → `POS-02`.
  - `Cancelar pedido [lat]` → confirmación → requiere permiso.
- **Navegación:**
  - `Ir a mesa` → `MES-02`.
  - `Cobrar` → `POS-02`.
  - Volver → `PED-01`.

### PED-03 · Entrega (Mostrador) — llamada al cliente

- **Uso:** cajero.
- **Propósito:** gestionar pedidos para llevar cuando están listos.
- **Elementos:**
  - Lista de pedidos mostrador `listos` por orden de llegada.
  - Al marcar `Entregado` el pedido queda listo para cobrar.
- **Navegación:**
  - `Cobrar` → `POS-02`.

### PED-04 · Cola de Delivery (repartidor)

- **Uso:** delivery, cajero (asignar), gerente (ver).
- **Propósito:** que el repartidor vea sus pedidos asignados y actualice el estado.
- **Elementos:**
  - Lista de pedidos delivery del repartidor: cliente, dirección, estado, tiempo.
  - Estados: `asignado` → `salió` → `entregado`.
- **Acciones:**
  - Tocar pedido → detalle con dirección completa y teléfono → llamada (`tel:`) y navegación (`maps:`).
  - `Marcar salió [lat]` / `Marcar entregado [lat]` (actualiza MES/PED en tiempo real).
- **Navegación:**
  - `Ver pendientes de pago contra entrega` → `POS-02` (si cobra en destino).

### Flujos clave (Mesas y Pedidos)

#### Pedido en mesa (flujo completo de pantallas)
```
MES-01 → (mesa libre) POS-01 → (agregar ítems) → enviar a cocina
      → COC-01 (cocina ve comanda)
      → MES-02 (mesero ve estado, entrega)
      → POS-02 (cajero cobra)
      → MES-01 (mesa queda por_limpiar → libre)
```

#### Pedido mostrador
```
POS-01 (destino=mostrador) → enviar cocina → COC-01
      → PED-03 (listo → entregado) → POS-02 (cobro)
```

#### Pedido delivery
```
POS-01 (destino=delivery, cliente + dirección) → enviar cocina
      → COC-01 → PED-04 (repartidor: salió → entregado)
      → POS-02 (cobro, en local o contra entrega)
```

---

## 3. POS (Punto de Venta Táctil)

### POS-01 · Crear / Editar Pedido (carrito táctil)

- **Uso:** cajero (principal), mesero (para mesas), gerente/admin.
- **Propósito:** construir el pedido con botones táctiles y enviarlo a cocina.
- **Elementos:**
  - **Cabecera de destino [lat]:** selector de tipo: `Mesa` / `Mostrador` / `Delivery`.
    - Mesa → selector de mesa (vuelve de MES-01 si vino de ahí).
    - Delivery → grupo de campos: cliente + dirección (o crea cliente rápido).
  - **Pestañas de categorías [lat]:** Sushi, Rolls, Nigiri, Entradas, Bebidas, Postres.
  - **Grilla de productos [lat]:** tarjetas grandes con nombre, precio, imagen; visibles solo productos activos.
  - **Panel de carrito:** líneas con cantidad (+/−) y notas; precio unitario y subtotal.
  - **Botones de acción [lat]:**
    - `Nota general del pedido`
    - `Descuento %` (permiso)
    - `Enviar a cocina` (confirma y crea comandas)
    - `Cobrar` → `POS-02`
    - `Cancelar pedido` (confirmación, permiso)
- **Comportamiento:**
  - Tocar producto → agrega línea; tocar de nuevo → +1.
  - Tocar línea → popup de opciones: cantidad, selección/modificadores (p. ej. tendencia sin wasabi), nota.
  - `Enviar a cocina` marca ítems pendientes → comanda a KDS e impresora.
  - Si el destino es mesa, se refleja en MES-01 (ocupada).
- **Navegación:**
  - `Cobrar` → `POS-02`
  - `Enviar a cocina` → se queda (puede seguir agregando) o propone `POS-02`
  - `Volver` → origen (MES-01 / DASH-01).

### POS-02 · Cobro (pantalla de pago)

- **Uso:** cajero.
- **Propósito:** cobrar el pedido, registrar el pago y cerrarlo.
- **Elementos:**
  - **Resumen:** total, método de pago.
  - **Métodos [lat]:** `Efectivo` | `Tarjeta` | `Pago mixto`.
    - Efectivo: teclado numérico para monto recibido → cambio calculado automático.
    - Tarjeta: botón confirmar (monto = total) con datos opcionales de tarjeta.
    - Mixto: división de monto (efectivo + tarjeta).
  - **Botones extra [lat]:** `Dividir cuenta` (monto entre personas/tarjetas), `Propina` (monto o %).
  - **Cliente (opcional):** buscar por teléfono → acumula puntos.
  - Botón final `Confirmar pago [lat]`.
- **Comportamiento:**
  - Solo disponible si existe **caja abierta** del usuario (si no, redirige a CAJ-01 apertura).
  - Al confirmar: pedido → `pagado`, se imprime **ticket/factura**, mesa → `por_limpiar`, se registra ingreso contable y totales de caja.
- **Navegación:**
  - `Confirmar pago` → confirmación visual de éxito → `PED-02`/`MES-01`/`POS-01` (nuevo pedido).
  - `Reimprimir último ticket` (si no salió la impresión) → menú IMP.

### POS-03 · Anulación (modal)

- **Uso:** cajero (permiso).
- **Propósito:** anular un pedido pagado (reembolso) con motivo obligatorio.
- **Elementos:** selector de pedido, motivo, método de devolución (efectivo/tarjeta), confirmación.
- **Comportamiento:** registra anulación en auditoría, revierte ingreso contable y totales de caja (efectivo sale de la caja).
- **Navegación:** desde `PED-02` / `CON-01` / historial de caja.

### Flujo (POS)

```
POS-01 ── Cobrar ──► POS-02 ── Confirmar pago ──► [éxito] ──► MES-01 / POS-01
   ▲                     │
   └── Enviar cocina ◄───┘ (vuelve al carrito)
```

#### Validación crítica: caja abierta
```
POS-02 ── ¿caja abierta? ── no ──► CAJ-01 (apertura) ──► regresa a POS-02
```

---

## 4. Cocina (KDS)

### COC-01 · Tablero de Comandas (Kitchen Display)

- **Uso:** cocina, barra (solo su área), gerente (ver).
- **Propósito:** ver y gestionar las comandas entrantes por área de producción.
- **Elementos:**
  - **Filtro de área [lat]:** Sushi / Cocina caliente / Frituras / Barra (el rol de cocina ve todas, barra solo la suya).
  - **Columnas (estado):**
    - `Pendiente` → comandas que entran y esperan.
    - `En producción` → el cocinero las tomó.
    - `Listas` → platos listos, esperando al mesero.
  - **Tarjeta de comanda:** #pedido/comanda, destino (mesa/mostrador/delivery), hora, ítems con cantidades y notas (reseñas de alérgenos destacadas), color de urgencia por tiempo (normal/amarillo/rojo).
  - Timer por ítem desde que entra.
- **Acciones [lat] (cada tarjeta):**
  - Tocar/tomar → mueve de `Pendiente` a `En producción` (start timer).
  - Botón `Listo` → cuando termina el plato → pasa a `Listo`.
  - Botón `Entregado` → cuando el mesero retiró → la comanda resuelta sale de la cola visual.
  - `Cancelar ítem` → solo si el ítem fue cancelado en pedido; se confirma y detiene la preparación.
- **Comportamiento:**
  - Al entrar una comanda hay alerta visual (y opcional sonora).
  - Orden FIFO por hora de llegada dentro de cada columna.
  - Si un ítem pasa el tiempo máximo → resaltado rojo.
- **Navegación:**
  - `Historial del día` (comandas resueltas) → modal `COC-02`.
  - `Ver detalle de producto` (receta/alérgenos) → modal desde la tarjeta.

### COC-02 · Historial / Resumen del turno (KDS)

- **Uso:** cocina, gerente.
- **Propósito:** consultar comandas cerradas, tiempos de preparación y pendientes atrasados.
- **Elementos:** lista por estado + filtros por área/hora; tiempos promedio por plato; lista de ítems que superaron el tiempo.
- **Acciones:** `Exportar resumen` → `REP-02`.

### Flujo (Cocina)

```
POS-01 ── Enviar a cocina ──► COC-01 (Pendiente)
  COC-01: tomar ──► En producción ── Listo ──► Entregado (sale)
    │
    ▼ (si requiere)
INV-01 (consumo de receta al confirmar "Listo")
```

#### Comunicación en vivo
- El mesero en `MES-02` ve la campanita cuando pasa a `Listo` en COC-01.
- El cajero en `PED-01` ve el estado actualizado de producción.

---

## 5. Caja y Contabilidad

### CAJ-01 · Panel de Caja (sesión del turno)

- **Uso:** cajero, gerente/admin (ver).
- **Propósito:** abrir, operar, arquear y cerrar la caja del turno; ver su estado.
- **Elementos (sin caja abierta):**
  - Botón `Abrir caja [lat]` → modal CAJ-02.
- **Elementos (con caja abierta):**
  - **Cabecera:** fondo inicial, estado `abierta/cerrada`, hora de apertura, cajero.
  - **Totales en vivo:** ventas del turno, por método (efectivo/tarjeta/mixto), efectivo esperado (fondo + ingresos − egresos − retiros).
  - **Lista de movimientos del turno** (hora, tipo, monto, motivo, usuario).
  - **Botones [lat]:** `Registrar egreso`, `Retiro a banco`, `Arquear y cerrar`.
- **Comportamiento:**
  - Sin caja abierta, el POS bloquea cobros y redirige aquí.
- **Navegación:**
  - `Abrir caja` → modal `CAJ-02`.
  - `Registrar egreso/retiro` → modal `CAJ-03`.
  - `Arquear y cerrar` → `CAJ-04`.
  - `Reporte de caja (Z)` → `CAJ-05`.
  - `Historial de turnos/cajeros` → `CAJ-06`.

### CAJ-02 · Modal Apertura de Caja

- **Uso:** cajero.
- **Elementos:**
  - Fondo inicial (teclado numérico).
  - Confirmación.
- **Comportamiento:** crea la sesión de caja `abierta`; vuelve a `CAJ-01` y desbloquea el POS.
- **Validación:** no se puede abrir una segunda caja mientras la anterior no se cierre (por usuario; configurable por sucursal).

### CAJ-03 · Modal Registrar Movimiento (egreso/retiro/ingreso extra)

- **Uso:** cajero (permiso para retiros).
- **Elementos:** tipo (`Egreso`, `Retiro a banco`, `Ingreso`), monto, motivo (obligatorio, con lista + texto libre), método.
- **Comportamiento:** actualiza el efectivo esperado y queda en auditoría.
- **Navegación:** vuelve a `CAJ-01`.

### CAJ-04 · Arqueo de Caja (corte)

- **Uso:** cajero, gerente.
- **Propósito:** cuadrar el efectivo físico vs. el esperado y cerrar el turno.
- **Elementos:**
  - **Efectivo esperado** (calculado automático).
  - Campo `Efectivo físico contado` (teclado numérico).
  - Cálculo en vivo de `Sobrante / Faltante`.
  - Nota (obligatorio si hay diferencia).
  - Botón `Confirmar arqueo y cerrar caja [lat]`.
- **Comportamiento:**
  - Al confirmar: caja `cerrada`, se genera `CAJ-05` (reporte Z) y se registra en contabilidad.
  - El turno queda cerrado; el siguiente turno requiere nueva apertura.
- **Navegación:**
  - `Confirmar` → `CAJ-05` → `POS-01`/`DASH-01`.

### CAJ-05 · Reporte Z (corte de turno)

- **Uso:** cajero (ver), gerente/admin.
- **Propósito:** resumen oficial del turno para archivo y contabilidad.
- **Elementos:**
  - Fecha/hora, cajero, número de turno.
  - Totales: ventas por método, número de transacciones, ticket promedio.
  - Detalle de movimientos (egresos, retiros, ingresos).
  - Efectivo esperado, efectivo físico, sobrante/faltante.
  - Firma: cajero, (revisión gerente).
- **Acciones:** `Imprimir [lat]`, `Enviar email`, `Exportar PDF` (→ REP).
- **Navegación:** volver → `CAJ-06`.

### CAJ-06 · Historial de Cajas (turnos y cajeros)

- **Uso:** gerente, admin.
- **Propósito:** consultar cierres históricos: por fecha, cajero, sucursal; comparar sobrantes/faltantes.
- **Elementos:** tabla con filtros (fecha, cajero, estado) + exportación.
- **Acciones:** tocar turno → `CAJ-05` (detalle Z).

### CON-01 · Movimientos Contables

- **Uso:** gerente, admin.
- **Propósito:** ver y registrar movimientos financieros (ingresos automáticos + gastos + ajustes).
- **Elementos:**
  - Filtros: tipo (ingreso/gasto), categoría, método, fecha, caja/turno.
  - Lista de movimientos con referencia (pedido #, caja #, compra #).
  - Botón `Nuevo gasto [lat]` → modal `CON-02`.
  - Botón `Nuevo ingreso manual [lat]` → modal (permiso).
- **Navegación:**
  - `Estado de resultados` → `CON-03`.
  - `Cuentas por pagar (proveedores)` → `CON-04`.
  - `Ir a gasto` → modal edición/anulación.

### CON-02 · Modal Registro de Gasto

- **Uso:** gerente, admin.
- **Elementos:** categoría (sueldos, renta, servicios, insumos, marketing, otros), monto, método, fecha, descripción, comprobante (adjunto opcional).
- **Comportamiento:** registra gasto; si es compra de insumos, puede vincularse a `INV-05` (compra).

### CON-03 · Estado de Resultados (período)

- **Uso:** gerente, admin.
- **Elementos:**
  - Período (fechas).
  - **Ingresos:** ventas (por tipo y método).
  - **Costo de ventas:** consumo de inventario (food cost).
  - **Gastos operativos:** por categoría.
  - **Resultado del período** (utilidad/pérdida) y margen %.
- **Acciones:** exportar PDF/Excel.

### CON-04 · Cuentas por Pagar (proveedores)

- **Uso:** gerente, admin.
- **Propósito:** deudas a proveedores por compras pendientes de pago.
- **Elementos:** lista por proveedor y estado (pendiente/parcial/pagada); botón `Registrar pago`.
- **Navegación:** `Ver compra origen` → `INV-05`.

### Flujo (Caja y Contabilidad)

```
POS-02 (pago) ──► actualiza CAJ-01 (totales) ──► al cerrar turno: CAJ-04 → CAJ-05 → CON-01
DASH-01 ──► CAJ-01 ──► (arqueo) CAJ-04 ──► CAJ-05 ──► CON-01
```

---

## 6. Inventario

### INV-01 · Lista de Insumos

- **Uso:** gerente, admin.
- **Propósito:** administrar la materia prima e insumos.
- **Elementos:**
  - Búsqueda + filtros (categoría, proveedor, estado stock).
  - Tabla/tarjetas: nombre, unidad, stock actual, stock mínimo, estado (verde/amarillo/rojo según nivel).
  - Botón flotante `+ Nuevo insumo [lat]`.
- **Acciones:**
  - Tocar insumo → `INV-02` (detalle).
- **Navegación:**
  - `Alertas (stock bajo)` → `INV-04`
  - `Recetas` → `INV-03`
  - `Compras` → `INV-05`
  - `Movimientos` → `INV-06`

### INV-02 · Detalle de Insumo

- **Uso:** admin/gerente.
- **Elementos:**
  - Datos: nombre, unidad, proveedor, costo, stock actual/min, categoría.
  - Historial de movimientos del insumo.
  - Botones:
    - `Ajustar stock [lat]` → modal (conteo físico / merma) con motivo.
    - `Registrar compra [lat]` → `INV-05`.
    - `Editar` / `Desactivar`.
- **Comportamiento:** desactivar conserva historial (no se elimina físicamente).

### INV-03 · Recetas (composición de productos)

- **Uso:** admin (responsable del menú).
- **Propósito:** definir de qué insumos se compone cada producto del menú.
- **Elementos:**
  - Lista de productos del menú con su condición (tiene receta / no).
  - Botón `Editar receta [lat]` → formulario: agregar insumos + cantidad, con aviso de costo unitario calculado.
- **Comportamiento:**
  - Al guardar una venta que usa este producto, `COC-01` (confirmar listo) dispara el consumo automático.
  - Si no hay stock suficiente: advertencia (¿permitir venta sí/no, configurable).

### INV-04 · Alertas de Stock

- **Uso:** gerente, admin, cajero (ver).
- **Propósito:** visibilidad de insumos por debajo del mínimo.
- **Elementos:** lista priorizada por nivel de urgencia + botón `Generar orden de compra [lat]` (sugerida).
- **Navegación:** `Crear compra` → `INV-05`.

### INV-05 · Compras (a proveedor)

- **Uso:** admin/gerente.
- **Propósito:** registrar compras de insumos → aumenta stock y genera gasto/cuenta por pagar.
- **Elementos:**
  - Cabecera: proveedor, fecha, estado (borrador/recibida/pagada).
  - Líneas: insumo (buscar/add), cantidad, costo unitario.
  - Total de la compra.
  - Botones: `Guardar borrador`, `Marcar recibida` (aumenta stock), `Registrar pago` (→ CON-04).
- **Navegación:**
  - `Ver pagos` → `CON-04`.

### INV-06 · Movimientos de Stock

- **Uso:** admin/gerente (ver), auditoría.
- **Propósito:** historial completo y trazable de los movimientos de inventario.
- **Elementos:**
  - Filtros: tipo (ingreso/consumo/merma/ajuste), rango de fecha, insumo.
  - Lista con referencia (pedido #, compra #, ajuste), usuario, motivo.
- **Acciones:** exportar.

### Flujo (Inventario)

```
INV-01 ──► INV-02 (detalle/ajuste) ──► INV-06 (movimiento registrado)
INV-03 (receta) ──► COC-01 "Listo" ──► consumo automático de stock
INV-04 (alerta) ──► INV-05 (compra) ──► INV-06 (ingreso) + CON-04 (por pagar)
```

#### Punto clave de integración
El descuento de stock **no** ocurre al cobrar, **sí** al confirmar en cocina que el producto quedó `listo` (`COC-01`). Así el stock refleja platos realmente producidos.

---

## 7. Clientes y Reservas

### CLI-01 · Lista de Clientes

- **Uso:** cajero, mesero (ver/buscar), gerente/admin.
- **Propósito:** buscar y gestionar clientes.
- **Elementos:**
  - Búsqueda rápida por teléfono (predeterminada) o nombre [con teclado numérico/ABC].
  - Resultados como tarjetas: nombre, teléfono, puntos, # pedidos.
  - Botón flotante `+ Nuevo cliente [lat]`.
- **Acciones:**
  - Tocar resultado → `CLI-02`.
  - `+ Nuevo` → modal rápido (nombre, teléfono).
- **Navegación:**
  - `Ver histórico de pedidos` → `PED-01` filtrado por cliente.
  - `Nueva reserva` → `RES-02` (desde cliente).

### CLI-02 · Detalle de Cliente

- **Uso:** cajero, gerente/admin.
- **Elementos:**
  - Datos: nombre, teléfono, email, notas/preferencias, fecha de registro.
  - **Direcciones de entrega** (lista con principal, agregar/editar).
  - **Saldo de puntos** + historial.
  - Resumen: total gastado, # pedidos, última visita.
  - Historial de pedidos (top: las 10 últimas).
- **Acciones [lat]:**
  - `Editar`, `Agregar dirección`.
  - `Nuevo pedido para este cliente` → `POS-01` (destino mostrador/delivery precargado).
  - `Nueva reserva` → `RES-02`.
- **Navegación:** volver → `CLI-01`.

### CLI-03 · Modal Puntos / Fidelización

- **Uso:** cajero.
- **Propósito:** canjear puntos del cliente en el cobro.
- **Elementos:** saldo disponible, regla de canje, campo de puntos a usar → descuento equivalente.
- **Comportamiento:** al confirmar se aplica al total en `POS-02` y descuenta puntos.
- **Navegación:** `POS-02` (aplica) ↔ `CLI-02`.

### RES-01 · Calendario de Reservas

- **Uso:** gerente, admin, mesero (consulta), cajero.
- **Propósito:** agenda de reservas por día con el mapa de disponibilidad.
- **Elementos:**
  - Selector de fecha y vista (día/semana).
  - Línea de tiempo con franjas horarias; cada reserva como bloque (cliente, personas, mesa).
  - Mapa de mesas al lado con color de `reservada`.
  - Botón `+ Nueva reserva [lat]`.
- **Acciones:**
  - Tocar bloque → `RES-03` (detalle).
  - Tocar mesa reservada en mapa → `RES-02` (pre-cargada) o `RES-03`.
- **Navegación:**
  - `Nueva reserva` → `RES-02`.

### RES-02 · Nueva / Editar Reserva

- **Uso:** gerente, admin, mesero, cajero.
- **Elementos:**
  - Cliente (buscar o crear rápido).
  - Fecha/hora; personas.
  - Mesa/zona (selector con disponibilidad).
  - Notas (evento, cumpleaños).
  - Anticipo/señal (monto opcional, pagado/por pagar).
  - Botón `Reservar [lat]`.
- **Comportamiento:**
  - Valida conflicto: no solapar mesa+horario → aviso y propone alternativa.
  - Al confirmar: mesa → `reservada` en `MES-01`.
- **Navegación:**
  - `Confirmar` → `RES-01` (vuelve a agenda).

### RES-03 · Detalle de Reserva

- **Uso:** todos los de RES-01.
- **Elementos:** datos completos, estado, historial de cambios.
- **Acciones [lat]:**
  - `Confirmar` (pasa a confirmada).
  - `Llegó el cliente` → reserva `llegó` → mesa `ocupada` → crea pedido `POS-01`.
  - `Cancelar` (motivo) → mesa libre.
  - `No se mostró` → registra no-show (política de anticipo).
- **Navegación:**
  - `Llegó` → `POS-01` (nuevo pedido en la mesa reservada).
  - Volver → `RES-01`.

### Flujos (Clientes y Reservas)

```
RES-01 ──+Nueva──► RES-02 ──Confirmar──► RES-01 (mesa = reservada)
RES-01 ──bloque──► RES-03 ──Llegó──► POS-01 (mesa ocupada + pedido)
                                     └──► (el flujo continúa como mesa normal → MES-02)
CLI-02 ──Nueva reserva──► RES-02
```

#### Caso sin show
```
RES-03 ── No se mostró ──► se libera la mesa + cierra la reserva (no-show registrado)
```

---

## 8. Trabajadores, Impresión y Configuración

### TRA-01 · Lista de Trabajadores

- **Uso:** admin (íntegro), gerente (ver).
- **Propósito:** gestionar el personal y sus accesos.
- **Elementos:**
  - Filtros: rol, estado (activo/inactivo), área.
  - Tarjetas/tabla: nombre, rol, área, estado, última conexión.
  - Botón `+ Nuevo trabajador [lat]`.
- **Navegación:**
  - Tocar trabajador → `TRA-02`.
  - `Roles y permisos` → `TRA-03`.

### TRA-02 · Detalle / Editar Trabajador

- **Uso:** admin.
- **Elementos:**
  - Datos personales y de contacto.
  - Credenciales (usuario/reset contraseña).
  - Rol, área, horario de turno.
  - Estado activo/inactivo.
  - Historial de actividad (últimos eventos: cobros, ajustes).
  - Botón `Guardar [lat]`.
- **Comportamiento:** no se elimina; solo `inactivo` si tiene historial.

### TRA-03 · Roles y Permisos (RBAC)

- **Uso:** admin.
- **Propósito:** definir qué puede hacer cada rol.
- **Elementos:**
  - Matriz: módulos/acciones × roles, con checkboxes.
  - CRUD de roles (no eliminar `admin`).
- **Navegación:** aplicación inmediata sobre todas las pantallas.

### IMP-01 · Configuración de Impresoras

- **Uso:** admin.
- **Propósito:** registrar impresoras y asignarlas por área/tipo.
- **Elementos:**
  - Lista de impresoras: nombre, tipo (ticket/comanda), área (sushi/cocina/barra/caja), conexión (USB/Red IP).
  - Botón `+ Nueva impresora [lat]`, `Probar impresión [lat]`.
- **Navegación:**
  - `Preferencias de ticket` → `IMP-02`.

### IMP-02 · Preferencias de Impresión

- **Uso:** admin.
- **Elementos:** pie de ticket (dirección, teléfono, NIT/RUC, mensaje), logo, nº copias por comanda/área, ancho de papel.
- **Comportamiento:** aplica a `POS-02` (ticket) y `COC-01` (comandas).

### IMP-03 · Cola / Reimpresión

- **Uso:** cajero (reimprimir), admin.
- **Elementos:** historial de impresiones con estado; botones `Reimprimir` por documento; estado de la cola por impresora.
- **Navegación:** desde `PED-02` (reimprimir pedido) y `CAJ-05` (reimprimir Z).

### CFG-01 · Configuración General

- **Uso:** admin.
- **Elementos (agrupados):**
  - **Restaurante:** nombre, dirección, teléfono, NIT/RUC, logos.
  - **Sucursales:** CRUD de sucursales.
  - **Reglas del negocio:** políticas de cancelación, descuento máximo, alérgenos obligatorios, propina por defecto, puntos por venta, min stock, tiempos máximos de preparación.
  - **Caja:** ¿puede abrir varias cajas? ¿cierre obligatorio al cambiar turno?
  - **Moneda / numeración de tickets.**
  - **Respaldo de base de datos** (botón ejecutar respaldo).
- **Navegación:** aplicación de cambios global (refleja en POS, cocina, caja, etc.).

### CFG-02 · Menú / Productos (Catálogo)

- **Uso:** admin (y gerente con permiso).
- **Propósito:** gestionar el menú que consume el POS.
- **Elementos:**
  - CRUD de categorías (orden, visibilidad).
  - CRUD de productos: nombre, precio, imagen, categoría, activo, alérgenos, tiempo estimado de preparación, selecciones/modificadores.
  - Asociar receta → `INV-03`.
- **Comportamiento:** solo productos activos aparecen en `POS-01`.
- **Navegación:** `Editar receta` → `INV-03`.

### Flujo (Trabajadores, Impresión y Configuración)

```
TRA-01 ──► TRA-02 / TRA-03 (permisos) ──► afecta TODAS las pantallas según rol
CFG-02 (menú) ──► POS-01 (grilla de productos)
IMP-01/02 (impresoras) ──► COC-01 (comandas) · POS-02 (tickets)
```

---

## 9. Reportes

### REP-01 · Dashboard / Centro de Reportes

- **Uso:** gerente, admin.
- **Propósito:** acceder a todos los reportes y KPIs.
- **Elementos:**
  - **KPIs de hoy:** ventas del día, # transacciones, ticket promedio, mesas ocupadas, pedidos en cocina.
  - **Gráficos:** ventas por hora (últimas 8h), ventas por tipo (salón/mostrador/delivery), top productos.
  - **Accesos por categoría de reporte [lat]:**
    - Ventas (tipo, producto, vendedor) → `REP-02`
    - Operación (tiempos de cocina, rotación de mesas) → `REP-03`
    - Inventario (stock, mermas, consumo) → `REP-04`
    - Clientes (top, frecuencia) → `REP-05`
    - Delivery (tiempos, repartidores, zonas) → `REP-06`
    - Financiero (ingresos vs gastos, food cost) → `REP-07`
    - Caja (cortes Z, cajeros) → `REP-08`
- **Navegación:**
  - `Ver todos los pedidos` → `PED-01`
  - `Exportar` → PDF/Excel según módulo.

### REP-02 · Reporte de Ventas

- **Uso:** gerente, admin.
- **Elementos:** filtros (rango de fecha, tipo de pedido, método de pago, cajero).
  - Tabla resumen + gráficos (barras por día, donas por tipo/método).
- **Acciones:** `Exportar PDF/Excel`, `Comparar con período anterior [lat]`.

### REP-03 · Reporte Operativo

- **Uso:** gerente, admin.
- **Elementos:** tiempo promedio de preparación por plato/área; rotación de mesas; promedio de espera.
- **Acciones:** exportar; ver detalle por plato → modal.

### REP-04 · Reporte de Inventario

- **Uso:** gerente, admin.
- **Elementos:** niveles de stock, valorización, movimientos/mermas del período, consumo por insumo.
- **Acciones:** `Ir a alertas` → `INV-04`; exportar.

### REP-05 · Reporte de Clientes

- **Uso:** gerente, admin.
- **Elementos:** top por gasto, frecuencia, nuevos vs recurrentes; segmentación.
- **Acciones:** tocar cliente → `CLI-02`; exportar.

### REP-06 · Reporte de Delivery

- **Uso:** gerente, admin.
- **Elementos:** pedidos por repartidor, tiempos de entrega, zonas más pedidas, tasa de entregas a tiempo.
- **Acciones:** exportar.

### REP-07 · Reporte Financiero (resumen)

- **Uso:** gerente, admin.
- **Elementos:** ingresos vs gastos del período, food cost %, margen bruto; desglose por categoría.
- **Navegación:** `Ver detalle contable` → `CON-03`.

### REP-08 · Reporte de Caja

- **Uso:** gerente, admin.
- **Elementos:** consolidado de cajas del período (por cajero/turno/fecha), totales por método, sobrantes/faltantes acumulados.
- **Acciones:** tocar turno → `CAJ-05` (Z detallado); exportar.

### REP-09 · Programación / Exportación

- **Uso:** gerente/admin.
- **Elementos:** agendar resumen diario por email (destinatarios), historial de exportaciones.
- **Comportamiento:** genera informe al cierre del día (fase avanzada).

### Flujo (Reportes)

```
DASH-01 ── "Reportes" ──► REP-01 ──► REP-02..08 (según categoría)
                            │ exportar → PDF/Excel / email programado
REP-01 ── cruces con: POS (ventas en vivo) · CAJ (cortes) · INV (stock/costo)
```

#### Nota de datos en vivo
Los KPIs de `REP-01` y `DASH-01` se alimentan en tiempo real con eventos de `POS-02`, `COC-01`, `CAJ-01`.

---

## 10. Público (Cliente)

Vistas del lado del cliente. Sin login (o cuenta simple opcional). Fases 5–6.

### PUB-01 · Página Inicial / Menú Digital

- **Uso:** cliente, sin cuenta.
- **Propósito:** mostrar el restaurante y su menú al cliente.
- **Elementos:**
  - Portada: nombre, ubicación, horarios, contacto, botón reservar.
  - Menú digital por categorías con fotos, descripciones, precios y alérgenos.
- **Acciones:**
  - `Reservar mesa [lat]` → `PUB-02`.
  - `Ver menú` → scroll/pestañas.
- **Navegación:** solo hacia las secciones públicas (no accede al panel de trabajadores).

### PUB-02 · Reservar en Línea

- **Uso:** cliente.
- **Elementos:** fecha/hora, personas, nombre y teléfono/email (captura de datos), notas.
- **Comportamiento:**
  - Muestra disponibilidad en vivo.
  - Al enviar: reserva en estado `solicitada` → llega al panel `RES-01` (admin confirma).
- **Navegación:** confirmación → `PUB-03`.

### PUB-03 · Confirmación / Seguimiento

- **Uso:** cliente.
- **Elementos:**
  - Confirmación de reserva (con código) y estado: `solicitada / confirmada / cancelada`.
  - Para pedidos (si hay self-service opcional): estado del pedido en tiempo real.
- **Navegación:** consulta por teléfono/código → panel `RES-01` / `PED-02` del lado administración.

### Flujo (Público)

```
PUB-01 ── Reservar ──► PUB-02 ── Enviar ──► PUB-03 (solicitada)
                                                │
RES-01 (admin ve solicitud) ── Confirmar ──► actualiza estado visible en PUB-03
```

#### Nota de alcance
- La vista pública **no** comparte sesión ni permisos con el panel de trabajadores.
- `PUB-03` solo expone información mínima (estado), nunca datos sensibles del negocio.

---

## Inventario de pantallas (resumen)

| Pantalla | Nombre | Rol principal |
|----------|--------|---------------|
| LOG-01 | Login | Todos |
| DASH-01 | Dashboard / KPIs | admin, gerente |
| MES-01 | Mapa de Mesas | mesero, cajero |
| MES-02 | Detalle de Mesa | mesero, cajero |
| PED-01 | Lista de Pedidos | cajero, gerente |
| PED-02 | Detalle de Pedido | cajero, gerente |
| PED-03 | Entrega Mostrador | cajero |
| PED-04 | Cola de Delivery | delivery |
| POS-01 | Crear/Editar Pedido | cajero, mesero |
| POS-02 | Cobro | cajero |
| POS-03 | Anulación | cajero (permiso) |
| COC-01 | Tablero KDS | cocina, barra |
| COC-02 | Historial KDS | cocina, gerente |
| CAJ-01 | Panel de Caja | cajero |
| CAJ-02 | Apertura de Caja | cajero |
| CAJ-03 | Movimiento de Caja | cajero |
| CAJ-04 | Arqueo de Caja | cajero, gerente |
| CAJ-05 | Reporte Z | cajero, gerente |
| CAJ-06 | Historial de Cajas | gerente, admin |
| CON-01 | Movimientos Contables | gerente, admin |
| CON-02 | Registro de Gasto | gerente, admin |
| CON-03 | Estado de Resultados | gerente, admin |
| CON-04 | Cuentas por Pagar | gerente, admin |
| INV-01 | Lista de Insumos | gerente, admin |
| INV-02 | Detalle de Insumo | admin |
| INV-03 | Recetas | admin |
| INV-04 | Alertas de Stock | gerente, admin |
| INV-05 | Compras | admin, gerente |
| INV-06 | Movimientos de Stock | admin, gerente |
| CLI-01 | Lista de Clientes | cajero, mesero |
| CLI-02 | Detalle de Cliente | cajero |
| CLI-03 | Puntos / Fidelización | cajero |
| RES-01 | Calendario de Reservas | gerente, admin |
| RES-02 | Nueva Reserva | gerente, admin |
| RES-03 | Detalle de Reserva | gerente, admin |
| TRA-01 | Lista de Trabajadores | admin |
| TRA-02 | Detalle de Trabajador | admin |
| TRA-03 | Roles y Permisos | admin |
| IMP-01 | Config. de Impresoras | admin |
| IMP-02 | Preferencias de Impresión | admin |
| IMP-03 | Cola / Reimpresión | cajero, admin |
| CFG-01 | Configuración General | admin |
| CFG-02 | Menú / Productos | admin |
| REP-01 | Centro de Reportes | gerente, admin |
| REP-02..08 | Reportes por área | gerente, admin |
| REP-09 | Programar Exportaciones | gerente, admin |
| PUB-01 | Menú Digital | cliente |
| PUB-02 | Reservar en Línea | cliente |
| PUB-03 | Seguimiento de Reserva | cliente |