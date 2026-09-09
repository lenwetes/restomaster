# Propuesta Comercial

## Sistema de Gestión Integral para Restaurante de Sushi

**Restaurante:** [Nombre del restaurante]
**Fecha:** [Fecha]
**Preparado por:** [Nombre de tu empresa]
**Versión:** 1.0

---

> *"Una plataforma única que conecta el salón, la cocina, la caja y la contabilidad de tu restaurante — diseñada para el sushi, pensada para cada comensal."*

---

## 1. Carta de Presentación

Estimado [Nombre del cliente / Gerente]:

Su restaurante maneja un alto volumen de operaciones diarias: mesas que rotan, pedidos de mostrador, entregas a domicilio, cocina en ritmo continuo y un control financiero que no admite errores.

Este proyecto nace con un objetivo claro: **convertir la operación de su restaurante en un proceso digital, rápido y sin fricción**, donde cada plato que sale de la cocina quede registrado en la caja, en el inventario y en la contabilidad — automáticamente y en tiempo real.

En las siguientes páginas encontrará una visión completa de la solución, sus módulos, el plan de implementación y la inversión necesaria. Estamos seguros de que esta herramienta no solo organizará su operación, sino que le entregará **información para crecer**.

Atentamente,
[Nombre del vendedor / empresa]
[Teléfono] · [Correo] · [Web]

---

## 2. El Desafío de un Restaurante de Sushi

| Situación común | Consecuencia |
|---|---|
| Comandas en papel o memoria | Errores en la cocina, platos perdidos |
| Cuentas calculadas a mano | Errores de cobro, descuadres de caja |
| Inventario sin control real | Desperdicio de pescado, falta de insumos el fin de semana |
| Sin historial de clientes | Ventas que dependen solo de la presencia, sin fidelización |
| Caja sin arqueo confiable | Pérdidas de dinero que nadie detecta |
| Reportes tardíos o manuales | Decisiones tomadas sin datos reales |

**¿Cuánto le cuesta hoy no tener esta visibilidad?** Cada descuadre, cada insumo vencido y cada cliente que no regresa es dinero que se va de su operación.

---

## 3. Nuestra Propuesta de Valor

Entregamos un **aplicativo web completo** — accesible desde tablets táctiles, computadoras y celulares — que centraliza toda la operación del restaurante en un solo sistema, con **Laravel + PostgreSQL** como base tecnológica sólida y escalable.

### Lo que su restaurante ganará

1. **Velocidad en la operación** — Pantallas táctiles diseñadas para el ritmo del servicio: el mozo toma el pedido, la cocina recibe la comanda al instante, la caja cobra sin archivo.
2. **Cero descuadres** — Control de caja con apertura, arqueo y reportes Z por turno, conectado a la contabilidad.
3. **Control de costos real** — Recetas que descuentan inventario automáticamente: sabrá exactamente cuánto pescado, arroz y alga consume cada plato.
4. **Clientes que regresan** — Base de datos de clientes, historial y programa de fidelización con puntos.
5. **Decisiones con datos** — Reportes en tiempo real: ventas por producto, por mozo, por horario, márgenes y food cost.
6. **Una sola plataforma** — Del salón al delivery, del tiquete a la contabilidad, todo integrado y sin dobles registros.

---

## 4. Módulos del Sistema

| # | Módulo | ¿Qué resuelve? |
|---|--------|----------------|
| 1 | **POS Táctil** | Pedidos y cobros rápidos: mesa, mostrador y delivery. Cambio automático, pago mixto, dividir cuenta. |
| 2 | **Mesas** | Mapa del salón en tiempo real: libre, ocupada, reservada o por limpiar. |
| 3 | **Cocina (KDS)** | Pantalla de cocina con comandas por área, tiempos y alarmas de demora. |
| 4 | **Control de Caja** | Apertura con fondo inicial, movimientos, arqueo y cierre de turno con reporte Z. |
| 5 | **Inventario + Recetas** | Insumos, stock mínimo, mermas, compras a proveedores y consumo automático por receta. |
| 6 | **Clientes + Fidelización** | Historial, direcciones de entrega y puntos canjeables. |
| 7 | **Delivery** | Asignación de repartidores, estados de entrega y tiempos. |
| 8 | **Reservas** | Agenda de reservas, bloqueo de mesas y control de no-shows. |
| 9 | **Impresión de Tickets** | Comandas por área, tiquetes de venta y facturas. Reimpresión desde historial. |
| 10 | **Trabajadores y Permisos** | Roles (administrador, gerente, cajero, mozo, cocina, delivery) con control fino de accesos. |
| 11 | **Reportes y KPIs** | Dashboard ejecutivo: ventas del día, ticket promedio, food cost, margen y más. |
| 12 | **Contabilidad** | Ingresos automáticos desde ventas, gastos, cuentas por pagar y estado de resultados. |

**Valor agregado:** Todo unificado en una sola base de datos — sin archivos Excel dispersos, sin cuadernos, sin dobles registros.

---

## 5. Cómo Funciona el Día a Día

```
Cliente llega o llama
      │
      ▼
Mozo/Repartidor toma el pedido en tablet
      │
      ▼
Cocina recibe la comanda al instante (pantalla + tiquete)
      │
      ▼
Plato listo → el stock se descuenta automáticamente (receta)
      │
      ▼
Entrega → Caja cobra (efectivo / tarjeta / mixto) → Ticket e impresión
      │
      ▼
La venta alimenta: caja del turno · inventario · contabilidad · reportes
```

**Resultado:** al cierre del día, el arqueo coincide, el inventario refleja la realidad y el gerente recibe su reporte sin hacer ninguna cuenta manual.

---

## 6. Experiencia por Perfil de Usuario

| Perfil | Dispositivo | Su experiencia |
|--------|-------------|----------------|
| **Cliente** | Celular | Menú digital, reservas en línea, confirmación al instante |
| **Mozo** | Tablet | Toma pedidos tocando la pantalla, ve cuando el plato está listo |
| **Cocina** | Tablet montada | Comandas ordenadas por área y por orden de llegada |
| **Cajero** | Pantalla táctil | Cobra rápido, arquea su caja y cierra turno con su reporte |
| **Gerente** | PC / Tablet | Ve las ventas en vivo, controla costos y toma decisiones con datos |
| **Repartidor** | Celular | Recibe entregas asignadas con dirección y navegación |

---

## 7. Tecnología y Seguridad

| Aspecto | Especificación |
|---------|----------------|
| Plataforma | Aplicativo web (funciona en cualquier navegador) |
| Backend | Laravel (PHP 8.2+) — robusto y ampliamente probado |
| Base de datos | PostgreSQL — fuerte, segura y escalable |
| Acceso | Inicio de sesión por usuario con roles y permisos |
| Auditoría | Cada cobro, arqueo y ajuste queda registrado con usuario y fecha |
| Dispositivos | Tablets táctiles, computadoras y celulares (diseño adaptativo) |
| Respaldos | Respaldo de base de datos configurable |

**Adaptabilidad móvil:** el sistema fue diseñado *mobile-first*: la interfaz táctil es la experiencia principal, no una versión reducida.

---

## 8. Plan de Implementación

Implementación por fases. Cada fase es **usable e independiente**, lo que permite empezar a operar con el núcleo y sumar módulos progresivamente.

| Fase | Alcance | Resultado |
|------|---------|-----------|
| **Fase 0 — Cimientos** | Configuración inicial, usuarios y roles | Sistema instalado y accesible |
| **Fase 1 — Núcleo operativo** | POS, mesas, pedidos, cocina, impresión | El restaurante puede operar 100% digital |
| **Fase 2 — Caja y Contabilidad** | Apertura/arqueo de caja, reportes Z, contabilidad | Control financiero diario sin descuadres |
| **Fase 3 — Inventario y Recetas** | Stock, recetas, compras, mermas | Costos reales por plato |
| **Fase 4 — Clientes y Delivery** | Base de clientes, fidelización, entregas | Más ventas y clientes recurrentes |
| **Fase 5 — Reservas y Reportes** | Reservas, dashboards, exportaciones | Decisiones con datos |
| **Fase 6 — Robustez** | Notificaciones, respaldos, pulido | Estabilidad operativa total |

**Tiempo estimado:** [X] semanas desde el inicio hasta la operación completa.

---

## 9. Inversión

### Opciones de adquisición

| Modalidad | Alcance | Inversión |
|-----------|---------|-----------|
| **Núcleo (Fases 0–2)** | POS, mesas, cocina, caja, contabilidad y tickets | [Monto] |
| **Completo (Fases 0–6)** | Todos los módulos del sistema | [Monto] |
| **Mantenimiento mensual** | Soporte, actualizaciones y respaldos | [Monto/mes] |

### ¿Qué incluye la inversión?
- Licencia de uso del sistema (perpetua en modalidad núcleo/completo).
- Instalación y puesta en marcha.
- Configuración inicial del menú, mesas y usuarios.
- Capacitación del personal (mozo, cocina, caja, gerencia).
- Manual de uso en línea.
- Soporte técnico durante el periodo contratado.

**Nota:** los montos se definen según el alcance y los dispositivos a conectar (impresoras, tablets, sucursales). Los valores se detallan en la cotización adjunta.

---

## 10. Soporte y Garantía

| Servicio | Descripción |
|----------|-------------|
| Capacitación | Sesiones con el personal de cada área (salón, cocina, caja, gerencia) |
| Soporte | Atención para dudas e incidencias en horario acordado |
| Actualizaciones | Mejoras del sistema incluidas en el mantenimiento |
| Respaldos | Copias de seguridad configuradas y verificables |
| Acompañamiento | Seguimiento en los primeros días de operación real |

---

## 11. Próximos Pasos

1. **Validación de alcance** — Revisión conjunta de los módulos y prioridades del restaurante.
2. **Cotización final** — Detalle de montos según alcance, dispositivos y sucursales.
3. **Agenda de implementación** — Definición de fechas de inicio y capacitación.
4. **Puesta en marcha** — Instalación, carga de datos y arranque operativo.

> Estamos listos para acompañarlos desde el primer día de la operación digital.

---

## 12. Datos de Contacto

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