# RestoMaster

## Sistema de Gestión Integral para Restaurantes

**Documento único:** propuesta comercial · módulos del sistema · manual de uso · ventajas frente a la forma actual

---

| | |
|---|---|
| **Producto** | RestoMaster |
| **Restaurante** | [Nombre del restaurante] |
| **Fecha** | [Fecha] |
| **Preparado por** | [Nombre de tu empresa] |
| **Contacto** | [Teléfono] · [Correo] · [Web] |
| **Versión** | 1.0 |

---

> *Una sola plataforma que conecta el salón, la cocina, la caja, el inventario y la contabilidad de su restaurante — en tiempo real y sin dobles registros.*

---

## Contenido

1. [Carta de presentación](#1-carta-de-presentación)
2. [El desafío de su restaurante hoy](#2-el-desafío-de-su-restaurante-hoy)
3. [Propuesta de valor](#3-propuesta-de-valor)
4. [Cómo funciona el día a día](#4-cómo-funciona-el-día-a-día)
5. [Módulos del sistema](#5-módulos-del-sistema)
6. [Manual de uso por rol](#6-manual-de-uso-por-rol)
7. [Ventajas frente a la forma actual](#7-ventajas-frente-a-la-forma-actual)
8. [Tecnología y seguridad](#8-tecnología-y-seguridad)
9. [Plan de implementación](#9-plan-de-implementación)
10. [Inversión](#10-inversión)
11. [Soporte y garantía](#11-soporte-y-garantía)
12. [Próximos pasos](#12-próximos-pasos)

---

## 1. Carta de presentación

Estimado(a) [Nombre del cliente / Gerente]:

Su restaurante maneja un alto volumen de operaciones diarias: mesas que rotan, pedidos de mostrador, domicilios, una cocina en ritmo continuo y un control financiero que no admite errores.

RestoMaster nace con un objetivo claro: **convertir la operación de su restaurante en un proceso digital, rápido y sin fricción**, donde cada plato que sale de la cocina quede registrado en la caja, en el inventario y en la contabilidad — automáticamente y en tiempo real.

En las siguientes páginas encontrará una visión completa de la solución: los módulos que la conforman, cómo se usa cada uno, qué ventajas tiene frente a la forma de trabajar actual y cómo se implementa en su negocio.

RestoMaster no es solo una caja registradora ni un cuaderno digital: es el **sistema nervioso de su operación**, la herramienta que le entrega información para crecer.

Atentamente,

[Nombre del vendedor / empresa]
[Teléfono] · [Correo] · [Web]

---

## 2. El desafío de su restaurante hoy

| Situación común | Consecuencia |
|---|---|
| Comandas en papel o en la memoria | Errores en la cocina, platos perdidos, meseros que anotan mal |
| Cuentas calculadas a mano o en calculadora | Errores de cobro, descuadres de caja al final del día |
| Inventario sin control real | Falta insumos el fin de semana, se desperdicia mercancía que nadie registra |
| Sin historial de clientes | Ventas que dependen solo de la presencia, sin fidelización |
| Caja sin arqueo confiable | Pérdidas de dinero que nadie detecta ni explica |
| Reportes tardíos o manuales en Excel | Decisiones tomadas sin datos reales, horas perdidas armando tablas |
| Cada cosa en una herramienta distinta | Cuaderno + Excel + canales de domicilio + caja manual: nadie tiene el panorama completo |

**¿Cuánto le cuesta hoy no tener esta visibilidad?** Cada descuadre, cada insumo vencido y cada cliente que no regresa es dinero que se va de su operación. Y lo más costoso: se entera cuando ya no hay cómo recuperarlo.

---

## 3. Propuesta de valor

Entregamos un **aplicativo web completo** — accesible desde tablets táctiles, computadoras y celulares — que centraliza toda la operación del restaurante en un solo sistema.

### Lo que su restaurante ganará

1. **Velocidad en la operación** — Pantallas táctiles diseñadas para el ritmo del servicio: el mesero toma el pedido tocando la pantalla, la cocina recibe la comanda al instante y la caja cobra sin filas.
2. **Cero descuadres** — Control de caja con apertura, arqueo y cierre de turno, conectado a la contabilidad. Al final del día el arqueo coincide.
3. **Control de costos real** — Recetas que descuentan inventario automáticamente: sabrá exactamente cuánto gasta cada plato en materia prima.
4. **Clientes que regresan** — Base de datos de clientes, historial de compras y programa de fidelización con puntos.
5. **Decisiones con datos** — Reportes en tiempo real: ventas por producto, por mesero, por horario, márgenes y costo de comida.
6. **Una sola plataforma** — Del salón al domicilio, del tiquete a la contabilidad, todo integrado y sin dobles registros.

---

## 4. Cómo funciona el día a día

```
Cliente llega o llama (o reserva por WhatsApp)
      │
      ▼
Mesero/Cajero toma el pedido en tablet (POS táctil)
      │
      ▼
Cocina recibe la comanda al instante (pantalla KDS + impresión térmica)
      │
      ▼
Plato listo → el stock se descuenta automáticamente (recetas)
      │
      ▼
Entrega → Caja cobra (efectivo / tarjeta / mixto) → Ticket impreso
      │
      ▼
La venta alimenta: caja del turno · inventario · contabilidad · reportes
```

**Resultado:** al cierre del día, el arqueo coincide, el inventario refleja la realidad y el gerente recibe su reporte sin hacer ninguna cuenta manual.

---

## 5. Módulos del sistema

RestoMaster se compone de **16 módulos integrados** en una sola base de datos. A continuación, cada módulo con lo que resuelve, sus funciones clave y cómo se usa en el día a día.

---

### 5.1 POS Táctil (Punto de Venta)

**Qué resuelve:** tomar pedidos y cobrar de forma rápida desde una tablet o pantalla táctil.

**Funciones clave:**
- Menú por categorías con tarjetas visuales grandes (perfectas para pantalla táctil).
- Destinos de pedido: **mesa**, **mostrador** o **delivery**.
- Carrito con cantidades, notas por plato (ej. "sin cebolla", "poco término") y notas generales.
- Envío de comandas a cocina con un solo toque.
- Métodos de pago: **efectivo** (con cálculo de cambio automático), **tarjeta** y **pago mixto**.
- Propinas (monto o porcentaje) y descuentos (solo con permiso).
- Bloqueo inteligente: no se puede cobrar un plato que aún está en cocina.

**Cómo se usa (flujo rápido):**
1. Ingresar al módulo POS desde la tablet o caja.
2. Elegir el destino (mesa / mostrador / delivery).
3. Tocar los productos que el cliente pidió (se agregan al carrito).
4. Tocar **Enviar a Cocina** → la comanda llega a la cocina al instante.
5. Cuando la cocina termina, el sistema avisa; se cobra tocando **Cobrar**.
6. Elegir el método de pago, confirmar e imprimir el ticket.

---

### 5.2 Mesas (Mapa del Salón)

**Qué resuelve:** saber en todo momento qué mesas están libres, ocupadas, reservadas o por limpiar, sin preguntar ni anotar.

**Funciones clave:**
- Mapa visual del salón con el estado de cada mesa en tiempo real.
- Estados: `libre`, `ocupada`, `reservada`, `por_limpiar`.
- Zonas configurables (salón, terraza, barra, VIP) y capacidad de personas.
- Cambiar un pedido de mesa sin perder el registro.
- Aviso visual cuando la cocina tiene el plato listo (campanita en la mesa).

**Cómo se usa:**
1. Abrir el mapa de mesas.
2. Tocar una mesa libre → se crea el pedido directamente.
3. La mesa pasa a `ocupada` mientras hay pedido.
4. Al cobrar, la mesa pasa a `por_limpiar`; el personal la marca como `libre` cuando la deja lista.

---

### 5.3 Cocina (KDS — Pantalla de Comandas)

**Qué resuelve:** que la cocina reciba y produzca las comandas en orden, sin papeles que se pierdan ni gritos entre el salón y la cocina.

**Funciones clave:**
- Cola de comandas organizada **por área de producción** (cocina caliente, cocina fría, barra, postres).
- Orden FIFO por hora de llegada: nunca se atrasa un plato por descuido.
- Semáforo de tiempos: verde → amarillo → rojo si un plato se demora.
- Estados por plato: `pendiente` → `en_preparación` → `lista` → `entregado`.
- Timers por plato y cumplimiento de tiempos de preparación (SLA).
- **Historial de comandas** con filtros por fecha, hora, mes, área y estado, para revisar tiempos y reimprimir.
- Botones táctiles gigantes: *Tomar*, *Listo*, *Entregar*.

**Cómo se usa:**
1. La comanda llega sola desde el POS (pantalla + impresión térmica).
2. Cada cocinero o bar tender *toma* los platos de su área.
3. Al terminar, toca **Listo** → el mesero ve la notificación en su tablet.
4. Al retirar el plato, toca **Entregado** y la comanda sale de la cola.

---

### 5.4 Control de Caja

**Qué resuelve:** el dinero manejado con el mismo rigor que un banco: todo queda registrado y cierra a fin de turno sin sorpresas.

**Funciones clave:**
- **Apertura de caja** con fondo inicial (efectivo con el que inicia el turno).
- Movimientos de caja: ingresos (ventas), egresos (gastos de caja chica) y retiros a banco.
- **Arqueo:** compara el efectivo que *debería* haber contra el efectivo físico contado, y registra sobrantes/faltantes con nota.
- **Cierre de turno** con Reporte Z (ventas por método de pago, transacciones, ticket promedio).
- Historial de turnos por cajero y por período.
- El POS **bloquea cobros** si no hay una caja abierta.

**Cómo se usa (turno completo):**
1. El cajero abre la caja indicando su fondo inicial.
2. Cobra todo el turno desde el POS (cada venta alimenta la caja automáticamente).
3. Si necesita retirar efectivo para depositar al banco, registra un retiro.
4. Al cerrar, digita el **efectivo físico** que tiene y el sistema calcula sobrante/faltante.
5. Confirma el arqueo → caja cerrada + Reporte Z impreso, listo para contabilidad.

---

### 5.5 Inventario y Recetas

**Qué resuelve:** saber siempre qué mercancía hay, cuánto cuesta y qué se consumió — para no quedarse sin insumos ni perder dinero por desperdicio.

**Funciones clave:**
- Insumos con unidad de medida, stock actual, stock mínimo y **alertas de stock bajo**.
- Categorías personalizadas con color e ícono (carnes, lácteos, barra, etc.).
- **Recetas (escandallos):** cada plato del menú indica exactamente qué insumos consume y en qué cantidad.
- Consumo automático de stock al confirmar un plato en cocina.
- Registro de mermas (vencimiento, rotura) y ajustes por conteo físico, con motivo y usuario.
- Valorización del inventario y costo de ventas.

**Cómo se usa:**
1. Registrar cada compra o alta de insumo con su costo.
2. Definir la receta de cada plato (ej. "Bife de Chorizo" = 350 g de carne + guarnición + aderezo).
3. El sistema descuenta stock automáticamente cada vez que el plato se confirma listo en cocina.
4. Cuando algo cae bajo el mínimo, el sistema lo alerta para recomprar a tiempo.

---

### 5.6 Proveedores y Compras

**Qué resuelve:** ordenar el abastecimiento: quién le vende, a qué precio y cuánto le debe.

**Funciones clave:**
- Registro de proveedores con datos de contacto.
- Compras multi-insumo: una factura puede traer varios insumos a la vez, y el stock aumenta automáticamente.
- Kardex por insumo: historial completo de entradas, salidas y ajustes.
- **Comparador de precios** entre proveedores para comprar más barato.
- Compras generan **Cuentas por Pagar (CxP)** automáticamente.

**Cómo se usa:**
1. Registrar cada proveedor (carnes, bebidas, lácteos...).
2. Al recibir mercancía, registrar la compra con los insumos y cantidades → el stock sube solo.
3. Comparar precios entre proveedores antes de pedir.
4. Desde CxP, registrar el pago parcial o total de cada factura.

---

### 5.7 Cuentas por Pagar (CxP)

**Qué resuelve:** saber cuánto le debe a cada proveedor y cuándo, sin papeles sueltos.

**Funciones clave:**
- Deudas generadas automáticamente al registrar compras.
- Estados: pendiente, parcial, pagada.
- Registro de pagos parciales o totales con fecha y método.
- Reportes de deuda por proveedor.

**Cómo se usa:**
1. Al registrar una compra, el sistema crea la cuenta por pagar al proveedor.
2. Al pagar (parcial o total), registrar el abono.
3. Consultar en cualquier momento cuánto se debe y a quién.

---

### 5.8 Clientes y Fidelización

**Qué resuelve:** convertir a cada comensal en un cliente conocido que vuelve.

**Funciones clave:**
- Base de datos de clientes con búsqueda rápida por teléfono.
- Direcciones de entrega guardadas (una o varias por cliente).
- Historial de compras por cliente.
- Notas por cliente (preferencias, alergias, fechas especiales).
- **Programa de puntos:** acumulan en cada venta y canjean por descuentos.
- Cumplimiento de normativa de protección de datos (consentimiento Habeas Data).

**Cómo se usa:**
1. Al registrar un pedido de mostrador o domicilio, buscar al cliente por teléfono (o crearlo al instante).
2. El sistema acumula sus puntos y guarda su historial automáticamente.
3. En el cobro, el cajero puede canjear puntos del cliente.
4. Consultar el cliente para saber su última visita, total gastado y preferencias.

---

### 5.9 Delivery y Domicilios

**Qué resuelve:** que los domicilios lleguen a tiempo, con repartidor asignado y seguimiento del estado.

**Funciones clave:**
- Pedidos delivery con cliente, dirección y teléfono.
- Asignación de repartidor por pedido.
- Estados: asignado → salió → entregado, actualizados desde el celular del repartidor.
- Vista de cola de entregas para el repartidor.
- **Pedido en línea público** (el cliente pide sin llamar).

**Cómo se usa (flujo delivery):**
1. El cliente pide por teléfono o por el pedido en línea.
2. El cajero registra el pedido, selecciona el cliente y su dirección, y asigna un repartidor.
3. La cocina prepara; cuando está listo, el repartidor sale y marca `salió`.
4. Al entregar, marca `entregado` y se cobra.

---

### 5.10 Reservas

**Qué resuelve:** organizar las mesas reservadas, sin dobles reservas ni mesas perdidas.

**Funciones clave:**
- Agenda de reservas por día con franjas horarias.
- Selección de mesa/zona, número de personas y notas (cumpleaños, eventos).
- Anticipo/señal opcional para grupos grandes.
- Control de no-shows (clientes que no llegan).
- Estados: solicitada → confirmada → llegó → finalizada (o cancelada).
- **Webhook de reservas:** los clientes pueden reservar desde WhatsApp, Instagram o una página web, sin sesión.
- La mesa pasa automáticamente a `reservada` en el mapa del salón.

**Cómo se usa:**
1. El equipo (o el cliente por WhatsApp/Instagram) crea la reserva.
2. El sistema valida disponibilidad: evita doble reserva en la misma mesa y horario.
3. El equipo confirma la reserva → la mesa queda `reservada` en el mapa.
4. Al llegar el cliente, el mesero marca **Llegó** → se crea el pedido en esa mesa.
5. Si no se presentó, se registra `no_mostró` y la mesa se libera.

---

### 5.11 Menú Digital por QR y Carta Pública

**Qué resuelve:** que el cliente vea el menú desde su celular, sin meseros recitando la carta ni cartas impresas desactualizadas.

**Funciones clave:**
- **Menú por mesa:** cada mesa tiene su código QR; el cliente escanea y ve el menú en su celular (autoservicio visual).
- **Carta pública:** menú en línea con categorías, fotos, descripciones y precios.
- **Pedido de domicilio en línea:** el cliente arma su pedido y la cocina lo recibe.

**Cómo se usa:**
1. Imprimir el código QR de cada mesa (el sistema lo genera).
2. El cliente lo escanea y navega el menú desde su celular.
3. Para domicilios, el cliente entra al enlace, arma el pedido y el restaurante lo recibe.

---

### 5.12 Impresión de Tickets

**Qué resuelve:** imprimir todo lo necesario sin peleas con impresoras: comandas, tickets y reimpresiones.

**Funciones clave:**
- **Comandas de cocina** impresas por área (cocina caliente, barra, postres).
- **Ticket/recibo de venta** (formato térmico 80 mm) al confirmar el cobro.
- Preferencias configurables: pie de ticket (dirección, teléfono, NIT/RUC, mensaje).
- Reimpresión de comandas y tickets desde el historial.
- Registro en auditoría de cada reimpresión.

**Cómo se usa:**
1. Al enviar a cocina, la comanda se imprime automáticamente en el área correspondiente (y/o se muestra en la pantalla KDS).
2. Al confirmar el pago, se imprime el ticket.
3. Si se necesita una copia, se reimprime desde el historial del pedido.

---

### 5.13 Trabajadores, Roles y Permisos (RBAC)

**Qué resuelve:** que cada persona del equipo vea y haga solo lo que le corresponde.

**Funciones clave:**
- Usuarios con rol y área asignada (administrador, gerente, cajero, mesero, cocina, barra, repartidor).
- **Permisos por rol**: cada acción sensible exige autorización (descuentos, ajustes de inventario, cobros).
- Cada rol entra directo a su pantalla (el mesero al mapa de mesas, el cocinero al KDS, el cajero al POS).
- Usuarios activos/inactivos (no se eliminan: conservan historial).
- **Auditoría:** cada acción sensible queda registrada con usuario, fecha y detalle.

**Cómo se usa:**
1. El administrador crea los usuarios del equipo con su rol.
2. Cada persona inicia sesión y ve solo su área de trabajo.
3. Cualquier descuadre o error se rastrea: el sistema sabe quién hizo qué y cuándo.

---

### 5.14 Catálogo y Menú (Configuración del Restaurante)

**Qué resuelve:** administrar la carta y las reglas del negocio sin tocar código.

**Funciones clave:**
- CRUD de **categorías** (con color e ícono para que el POS sea visual) y **productos** (precio, descripción, disponibilidad, área de cocina).
- Productos inactivos no aparecen en el POS.
- Configuración general: datos del restaurante, sucursales, políticas (descuento máximo, propina por defecto), moneda y numeración de tickets.
- Asociación de recetas desde el menú.

**Cómo se usa:**
1. Ingresar al módulo de Menú/Configuración.
2. Crear o editar categorías y platos (el cambio se refleja al instante en el POS y el menú digital).
3. Configurar las reglas del negocio según su operación.

---

### 5.15 Reportes y KPIs (Dashboard)

**Qué resuelve:** que el gerente tome decisiones con datos reales y en tiempo real, sin armar tablas en Excel.

**Funciones clave:**
- **Dashboard ejecutivo:** ventas de hoy, ticket promedio, pedidos, mesas ocupadas, pedidos en cocina.
- Reportes por: **ventas** (por producto, por mesero, por tipo, por período), **operación** (tiempos de cocina, rotación de mesas), **inventario** (stock, mermas, consumo), **clientes** (top por gasto, frecuencia), **delivery** (tiempos y repartidores) y **financiero** (ingresos vs gastos, margen).
- **Costo de comida (food cost)** y margen bruto.
- Exportación a **PDF** y **CSV/Excel**.

**Cómo se usa:**
1. Abrir el Dashboard para ver el resumen del día en vivo.
2. Consultar el reporte del área que necesita (ventas, cocina, inventario, clientes, delivery, financiero).
3. Filtrar por fechas y exportar a PDF o Excel para compartir con socios o contador.

---

### 5.16 Contabilidad

**Qué resuelve:** que la contabilidad de la operación se registre sola, día a día, sin dobles tipeos.

**Funciones clave:**
- **Ingresos automáticos** desde cada pedido pagado (desglosados por método de pago).
- Registro de **gastos** (por compras a proveedores y gastos operativos) con categoría, monto y comprobante.
- **Cuentas por pagar** a proveedores con registro de pagos parciales.
- Estado de resultados del período (ingresos − costos − gastos = resultado).
- Todo movimiento queda registrado y se anula dejando rastro (nunca se borra).

**Cómo se usa:**
1. Cada venta se registra sola en contabilidad al cobrar.
2. Cada compra a proveedor crea su gasto/cuenta por pagar.
3. El gerente consulta el estado de resultados del día, semana o mes sin hacer cuentas manuales.

---

### Resumen de módulos

| # | Módulo | Lo que cuida |
|---|--------|--------------|
| 1 | POS Táctil | Velocidad en el cobro |
| 2 | Mesas | Control del salón |
| 3 | Cocina (KDS) | Orden y tiempos en producción |
| 4 | Caja | Control del dinero |
| 5 | Inventario y Recetas | Control de costos y stock |
| 6 | Proveedores y Compras | Abastecimiento inteligente |
| 7 | Cuentas por Pagar | Deudas bajo control |
| 8 | Clientes y Fidelización | Clientes que vuelven |
| 9 | Delivery | Domicilios a tiempo |
| 10 | Reservas | Mesas organizadas |
| 11 | Menú digital QR y carta | Cliente empoderado |
| 12 | Impresión | Tickets y comandas sin fallas |
| 13 | Trabajadores y Permisos | Cada quien en su área |
| 14 | Catálogo y Configuración | La carta siempre al día |
| 15 | Reportes y KPIs | Decisiones con datos |
| 16 | Contabilidad | Números que cuadran solos |

---

## 6. Manual de uso por rol

Cada perfil entra directo a la pantalla que necesita y usa solo los módulos de su área.

### 6.1 Mesero

| Paso | Pantalla | Acción |
|---|---|---|
| 1 | Mesas | Identifica la mesa y su estado en el mapa del salón |
| 2 | POS | Toca la mesa libre → toma el pedido tocando los productos |
| 3 | POS | Toca **Enviar a Cocina** |
| 4 | Mesas | Ve en su tablet cuando la cocina marca el plato **Listo** |
| 5 | Mesas | Sirve el plato y la mesa continúa su ciclo; al cobrar pasa a `por_limpiar` |

*Además:* registra notas del cliente (sin cebolla, poco término), ve reservas del día y puede consultar clientes.

### 6.2 Cajero

| Paso | Pantalla | Acción |
|---|---|---|
| 1 | Caja | Abre la caja del turno con su fondo inicial |
| 2 | POS | Cobra pedidos: efectivo (cambio automático), tarjeta o mixto |
| 3 | Caja | Registra egresos y retiros a banco durante el turno |
| 4 | Caja | Al cerrar, digita el efectivo físico → sistema calcula sobrante/faltante |
| 5 | Caja | Confirma arqueo → Reporte Z impreso y turno cerrado |

*Además:* consulta el detalle de pedidos, entrega pedidos de mostrador, ve clientes y puntos.

### 6.3 Cocina / Barra

| Paso | Pantalla | Acción |
|---|---|---|
| 1 | Cocina (KDS) | Ve las comandas que llegan solas, ordenadas por área y por hora |
| 2 | KDS | Toca **Tomar** para iniciar un plato (arranca el timer) |
| 3 | KDS | Toca **Listo** al terminar → el mesero recibe el aviso |
| 4 | KDS | Toca **Entregado** cuando el mesero retira el plato |
| 5 | KDS | Consulta el historial del día para revisar tiempos y demoras |

*Además:* barra ve solo su área (cócteles y bebidas); cocina ve todas las áreas.

### 6.4 Gerente

| Paso | Pantalla | Acción |
|---|---|---|
| 1 | Dashboard | Revisa ventas de hoy, ticket promedio y mesas ocupadas en vivo |
| 2 | Reportes | Consulta ventas, food cost, márgenes y exporta a PDF/Excel |
| 3 | Inventario | Registra compras, revisa stock y alertas de recompra |
| 4 | Proveedores | Compara precios y registra facturas de proveedores |
| 5 | Caja | Supervisa turnos de cajeros y sobrantes/faltantes acumulados |

*Además:* puede ver cocina (KDS), clientes, reservas, CxP y contabilidad.

### 6.5 Administrador

| Paso | Pantalla | Acción |
|---|---|---|
| 1 | Trabajadores | Crea los usuarios del equipo con su rol y sus permisos |
| 2 | Menú | Administra la carta: categorías, platos, precios y disponibilidad |
| 3 | Configuración | Define reglas del negocio, datos del restaurante y sucursales |
| 4 | Impresión | Configura impresoras, formatos y pie de ticket |
| 5 | Todo | Acceso completo a todas las áreas para supervisión y soporte |

### 6.6 Repartidor (Delivery)

| Paso | Pantalla | Acción |
|---|---|---|
| 1 | Delivery | Ve la cola de pedidos de domicilio asignados |
| 2 | Delivery | Revisa la dirección y el teléfono del cliente (enlaces a mapa y llamada) |
| 3 | Delivery | Marca **Salió** cuando deja el restaurante |
| 4 | Delivery | Marca **Entregado** al completar el domicilio |

### Flujo de un turno completo (visión de conjunto)

```
Apertura de caja → servicio (pedido → cocina → entrega → cobro)
     → arqueo y cierre de turno → Reporte Z → contabilidad y reportes del día
```

---

## 7. Ventajas frente a la forma actual

### 7.1 Operación diaria

| Actividad | Con cuaderno, papel y Excel | Con RestoMaster |
|---|---|---|
| Tomar pedido | Se anota a mano; puede perder hojas o malinterpretarse | Se toca en la tablet; la cocina lo recibe igual, siempre |
| Llegar a la cocina | Un mesero va con el papel, otros conductores de golosos lo interrumpen | La comanda llega al instante a la pantalla e impresora del área |
| Saber si un plato está listo | Se grita "¿qué pasó con la mesa 4?" | Notificación visual en la tablet del mesero |
| Calcular la cuenta | A mano, con calculadora | El sistema lo hace; cambio automático |
| Cuadrar la caja | Se cuentan billetes contra papeles sueltos | Arqueo automático: `esperado vs físico`, sobrante/faltante registrado |
| Registrar gastos | Facturas en una carpeta; nadie sabe cuánto se debió | CxP genera la deuda sola al registrar la compra |
| Hacer inventario | Se cuenta por memoria el fin de semana | Stock siempre al día; alertas de mínimo |
| Reporte para el dueño | Excel armado a mano con horas de trabajo | Un clic: ventas, costos, margen, food cost |
| Contestar "¿cuándo fue la última vez que vino X?" | No se sabe | Historial completo de cada cliente |

### 7.2 Costos ocultos de la forma actual

| Cosa que hoy "no cuesta" | Lo que realmente cuesta |
|---|---|
| Pedido perdido o mal anotado | Insatisfacción, plato rehecho, mesa que no vuelve |
| Error de cuenta a mano | Fila en la caja, reclamos, descuadre |
| Descuadre de caja al cierre | Horas revisando, y si no se explica: plata perdida |
| Insumo que falta el sábado | Cliente que pide y no hay → venta perdida |
| Excel manual del dueño | Error humano, datos viejos, decisiones a ciegas |
| Cliente sin registro | Sin forma de avisarle, sin fidelización, sin recompra |

### 7.3 Lo que ningún cuaderno ni Excel le dará

1. **Un solo registro** para todo: una venta alimenta caja, inventario, contabilidad y reportes **al mismo tiempo** — cero dobles registros.
2. **Trazabilidad total** del dinero: quién cobró, qué turno, qué diferencia. El descuadre se detecta el mismo día.
3. **Costo real por plato**: las recetas dicen cuánto gasta cada plato; el food cost se calcula solo.
4. **Comunicación salón–cocina instantánea** sin papeles ni gritos.
5. **Fidelización con datos**: puntos, historial y campañas para que el cliente vuelva.
6. **Decisiones en tiempo real**: ventas, márgenes y desempeño en la palma de la mano.
7. **Escalabilidad**: una sola plataforma acompaña su crecimiento — varias cajas, varias sucursales.

---

## 8. Tecnología y seguridad

| Aspecto | Especificación |
|---|---|
| Plataforma | Aplicativo web (funciona en cualquier navegador: tablet, computadora, celular) |
| Backend | Laravel (PHP) — marco robusto, seguro y ampliamente usado |
| Base de datos | PostgreSQL — fuerte, confiable y escalable |
| Diseño | Mobile-first y táctil: la pantalla táctil es la experiencia principal, no una versión reducida |
| Acceso | Inicio de sesión por usuario con **roles y permisos** (RBAC) |
| Autorización | Validada siempre en el servidor (nunca solo en la interfaz): un rol sin permiso no puede ejecutar la acción aunque manipule la pantalla |
| Auditoría | Cada cobro, arqueo, ajuste y anulación queda registrado con usuario y fecha |
| Integridad de datos | Reglas de negocio y restricciones en la base de datos (montos y cantidades siempre positivas, números únicos de mesa, cuentas que no se eliminan: se anulan dejando rastro) |
| Protección del cliente | Consentimiento de tratamiento de datos (Habeas Data) para la base de clientes |
| Respaldos | Respaldo de base de datos configurable y verificable |
| Integraciones | Webhook de reservas (WhatsApp/Instagram/página web), menú digital por QR |

---

## 9. Plan de implementación

Implementación por fases. Cada fase es **usable e independiente**, permitiendo empezar a operar con el núcleo y sumar módulos progresivamente.

| Fase | Alcance | Resultado |
|------|---------|-----------|
| **Fase 0 — Cimientos** | Instalación, usuarios, roles y sucursal | Sistema instalado y accesible |
| **Fase 1 — Núcleo operativo** | POS táctil, mesas, pedidos, cocina (KDS), impresión, menú digital | El restaurante opera 100% digital |
| **Fase 2 — Caja y Contabilidad** | Apertura/arqueo/cierre de caja, reporte Z, contabilidad | Control financiero diario sin descuadres |
| **Fase 3 — Inventario y Recetas** | Insumos, recetas, compras, proveedores, CxP, mermas | Costos reales por plato |
| **Fase 4 — Clientes y Delivery** | Base de clientes, fidelización, domicilios, pedido en línea | Más ventas y clientes recurrentes |
| **Fase 5 — Reservas y Reportes** | Reservas + webhook, dashboards, exportaciones | Decisiones con datos |
| **Fase 6 — Robustez** | Notificaciones, respaldos, pulido y soporte | Estabilidad operativa total |

**Tiempo estimado:** [X] semanas hasta la operación completa.

**Puesta en marcha incluye:** carga del menú, configuración de mesas y zonas, usuarios del equipo, impresoras, capacitación del personal por área y acompañamiento en los primeros días reales.

---

## 10. Inversión

### Opciones de adquisición

| Modalidad | Alcance | Inversión |
|-----------|---------|-----------|
| **Núcleo (Fases 0–2)** | POS, mesas, cocina, caja, contabilidad y tickets | [Monto] |
| **Completo (Fases 0–6)** | Todos los módulos del sistema | [Monto] |
| **Mantenimiento mensual** | Soporte, actualizaciones y respaldos | [Monto/mes] |

### ¿Qué incluye la inversión?

- Licencia de uso del sistema (perpetua en modalidad núcleo/completo).
- Instalación y puesta en marcha.
- Configuración inicial: menú, mesas, usuarios e impresoras.
- Capacitación del personal (salón, cocina, caja, gerencia).
- Manual de uso en línea.
- Soporte técnico durante el periodo contratado.

**Nota:** los montos se definen según el alcance y los dispositivos a conectar (impresoras, tablets, sucursales). Los valores se detallan en la cotización adjunta.

---

## 11. Soporte y garantía

| Servicio | Descripción |
|----------|-------------|
| Capacitación | Sesiones con el personal de cada área (salón, cocina, caja, gerencia) |
| Soporte | Atención para preguntas e incidencias en horario acordado |
| Actualizaciones | Mejoras del sistema incluidas en el mantenimiento |
| Respaldos | Copias de seguridad configuradas y verificables |
| Acompañamiento | Seguimiento en los primeros días de operación real |

---

## 12. Próximos pasos

1. **Validación de alcance** — Revisión conjunta de los módulos y prioridades del restaurante.
2. **Cotización final** — Detalle de montos según alcance, dispositivos y sucursales.
3. **Agenda de implementación** — Definición de fechas de inicio y capacitación.
4. **Puesta en marcha** — Instalación, carga de datos y arranque operativo.

> Estamos listos para acompañarlos desde el primer día de la operación digital.

---

## Contacto

| | |
|---|---|
| **Empresa** | [Nombre de tu empresa] |
| **Responsable** | [Nombre] |
| **Teléfono** | [Teléfono] |
| **Correo** | [Correo] |
| **Web** | [Sitio web] |
| **Dirección** | [Dirección] |

---

*Gracias por su tiempo. Esperamos construir juntos el sistema que impulse su restaurante.*

**[Nombre del vendedor]**