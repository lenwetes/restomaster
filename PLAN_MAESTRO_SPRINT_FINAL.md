# 🚀 Plan Maestro de Implementación — RESTOMASTER
**Versión:** Final Unificada (LANZADOR_MANANA + Sprint 6)  
**Fecha:** 2026-09-29  
**Estado:** ✅ Aprobado — Listo para ejecutar

---

## Resumen Ejecutivo

Este plan consolida **todas** las funcionalidades pendientes en un único documento ejecutable. Organiza **9 fases** con **25 sub-tareas** agrupadas en dos grandes bloques que comparten dependencias y deben ejecutarse en orden.

| Bloque | Fases | Contenido |
|--------|-------|-----------|
| **Bloque I — Motor IA & Turnos Base** | Fases 1–5 | Hotfix Copiloto, Function Calling, Turnos Semanales, Aviso Login |
| **Bloque II — Frontend, RRHH, Caja & Reportes** | Fases 6–9 | Móvil, Autorrotación, Cobros centralizados, Reportes IA |

---

## 🗺️ Mapa de Dependencias

```
FASE 1 (Hotfix Copiloto IA)
  ├── FASE 2 (Function Calling Gemini)
  └── FASE 3 (Turnos Semanales Base)
         └── FASE 4 (Aviso Login Mesero)

FASE 2 + FASE 4 ──► FASE 5 (Tests Bloque I + npm run build)

FASE 5 ──► FASE 6 (Frontend Móvil + Compartir Redes)
       ├──► FASE 7 (Autorrotación Equitativa RRHH)
       └──► FASE 8 (Centralización Cobros + Notif. + NC)

FASE 6 + FASE 7 + FASE 8 ──► FASE 9 (Reportes Comparativos + IA PDF)
```

---

## ─────────────────────────────────────
## BLOQUE I — Motor IA & Turnos Base
## ─────────────────────────────────────

### 🟢 FASE 1: Hotfix Inmediato del Copiloto IA

**Objetivo:** Eliminar el crash en ventas y mejorar la comprensión del lenguaje natural.

**Archivo principal:** `app/Services/Ai/AdminAiCopilotService.php`

#### 1.1 — Corregir crash en ventas (línea ~1071)
```php
// ANTES (falla si $p es array):
$p->total_ventas

// DESPUÉS (acceso seguro):
$p['total_ventas'] ?? ($p->total_ventas ?? 0)
```
- Validar que `"ventas de hoy?"` responda con KPIs y gráfico de barras por hora sin excepción.

#### 1.2 — Soporte de Salidas / Egresos de Base de Cajas
- Detectar intenciones: `"cuánto dinero de la base ha salido"`, `"retiros de caja"`, `"egresos"`.
- Consultar `TurnoCaja` + `MovimientoCaja` donde `tipo IN ('egreso', 'retiro')`.
- Responder con desglose: retiros por turno, motivos, saldo restante de la base vs `monto_inicial`.

#### 1.3 — Reemplazar Fallback Repetitivo del Saludo
- Si la consulta no coincide con ninguna intención conocida: **no** reenviar el saludo de bienvenida.
- Devolver mensaje analítico con sugerencias guiadas:
  > *"No encontré datos sobre eso. Puedo ayudarte con: ventas del día, movimientos de caja, inventario crítico, top platos, rendimiento de meseros."*

---

### 🟢 FASE 2: Arquitectura Agéntica (Gemini Function Calling)

**Objetivo:** El copiloto usa herramientas reales en lugar de reglas frágiles de texto.

**Archivos:**
- `app/Services/Ai/AdminAiCopilotService.php`
- `resources/views/livewire/configuracion/index.blade.php`

#### 2.1 — Catálogo de Herramientas (Tools)

| Tool | Parámetros | Consulta DB |
|------|-----------|-------------|
| `consultar_ventas` | `periodo`, `agrupacion` | `pedidos` + `turno_caja` |
| `consultar_movimientos_caja` | `tipo`, `caja_id`, `fecha` | `movimientos_caja` |
| `consultar_inventario` | `insumo`, `solo_alertas` | `inventario_insumos` |
| `consultar_rendimiento_meseros` | `periodo` | `pedidos GROUP BY mesero_id` |
| `consultar_platos_estrella` | `limite`, `categoria` | `items_pedido GROUP BY producto_id` |

#### 2.2 — Integración con Gemini Function Calling API
- Flujo: Pregunta natural → LLM selecciona tool → ejecutar SQL real → LLM genera respuesta ejecutiva.
- Soporte multi-turn: si el LLM necesita más contexto, hace segunda llamada a tool.
- Fallback determinista offline si la API no responde.

#### 2.3 — Pestaña "IA & Copiloto" en `/configuracion`
- Campo: Gemini API Key (enmascarado, guardado con `Crypt::encrypt()`).
- Botón "Probar conexión" → latencia + modelo activo.
- Indicador semáforo: 🟢 Conectado / 🔴 Error / ⚫ No configurado.

---

### 🟢 FASE 3: Programación Semanal de Turnos y Mesas

**Objetivo:** Crear el módulo base de programación semanal que será extendido por las Fases 7 y 8.

#### 3.1 — Migraciones y Modelos Eloquent

**Tabla `plantillas_turnos`:**
```sql
id, nombre (Almuerzo / Cena / Partido), hora_inicio, hora_fin,
zona_default_id, sucursal_id, activo, timestamps
```

**Tabla `programaciones_semanales`:**
```sql
id, semana_iso (INT), anio (INT), sucursal_id,
estado (borrador | publicado | archivado),
publicado_por (user_id), publicado_en, timestamps
```

**Tabla `turnos_meseros_semana`:**
```sql
id, programacion_semanal_id, user_id, fecha (DATE),
zona_id, plantilla_turno_id, mesas_especificas (jsonb),
es_descanso (BOOL), notificado_login_en, confirmado_por_mesero_en,
timestamps
```

#### 3.2 — Servicio `TurnoSemanalService.php`

**Motor de Auto-Programación Equitativa:**
- Distribuir turnos y zonas balanceadamente entre meseros activos de la sucursal.
- Respetar días de descanso del ciclo configurado (ver Fase 7).
- **Modo Respetar Hoy:** No tocar días ya transcurridos ni la rotación activa actual; solo completar días restantes de la semana (lunes a domingo ISO).
- `copiarSemanaAnterior(sucursal_id, semana_destino)`.
- `publicarSemana(programacion_id)` → estado `publicado` + evento `HorarioSemanalPublicado`.
- Sincronización automática con `RotacionMeseroService` al publicar.

#### 3.3 — Matriz Semanal para el Administrador

**Vista:** `resources/views/livewire/turnos/index.blade.php`

```
┌──────────┬────────┬────────┬────────┬────────┬────────┬────────┬────────┐
│  Mesero  │  Lun   │  Mar   │  Mié   │  Jue   │  Vie   │  Sáb   │  Dom   │
├──────────┼────────┼────────┼────────┼────────┼────────┼────────┼────────┤
│ Juan     │🍽️Salón │🍽️Barra │😴 DESC │🍽️Terr │🍽️Salón│🍽️ VIP │🍽️Salón│
│ María    │😴 DESC │🍽️ VIP │🍽️Salón │🍽️Barra │😴 DESC │🍽️Terr │🍽️Barra│
└──────────┴────────┴────────┴────────┴────────┴────────┴────────┴────────┘
```

- Acciones: `⚡ Auto-Programar` · `📋 Copiar Anterior` · `✅ Publicar Horarios`.
- Celdas editables por tap/click (cambiar zona o marcar descanso).
- Selector de semana ISO con navegación anterior/siguiente.
- Badge de estado: Borrador / Publicado.

---

### 🟢 FASE 4: Aviso Interactivo al Login del Mesero

**Objetivo:** Notificar al mesero su itinerario semanal en el primer acceso tras la publicación.

**Archivos:**
- `resources/views/livewire/pos/terminal.blade.php`
- `resources/views/components/modal-turno-semanal.blade.php` *(nuevo)*

#### 4.1 — Detección al Ingresar al POS
- En `mount()` del POS: verificar si hay programación publicada para la semana ISO actual con `confirmado_por_mesero_en = NULL`.
- Si aplica: emitir evento Alpine para mostrar el modal.

#### 4.2 — Modal Táctil de Alta Visibilidad

```
╔═══════════════════════════════════════╗
║  📅 TU HORARIO — SEMANA 40 / 2026    ║
╠═══════════════════════════════════════╣
║  Lun  │ Mar │ Mié │ Jue │ Vie │Sáb│Dom ║
║ Salón │Barra│ 😴  │Terr │ VIP │Sal│Barra║
╠═══════════════════════════════════════╣
║   [ ✅ He leído y acepto mi turno ]  ║
╚═══════════════════════════════════════╝
```

- Registra `confirmado_por_mesero_en = now()` al confirmar.
- No se puede cerrar sin confirmar (posponerse 1 vez con "Ver más tarde").

#### 4.3 — Notificación de Respaldo
- Registrar en `notificaciones_usuario` con `tipo = 'turno_semanal'`.
- Aparece en campana de avisos durante todo el turno hasta confirmar.

---

### 🟢 FASE 5: Tests Automatizados — Bloque I

| Test | Valida |
|------|--------|
| `AdminAiCopilotHotfixTest` | Sin crash en ventas, egresos de caja, no-repetición de saludo |
| `AdminAiCopilotFunctionCallingTest` | 5 tools seleccionadas correctamente, fallback offline |
| `TurnoSemanalCRUDTest` | CRUD de programaciones, copiar semana, publicar |
| `TurnoSemanalAutoProgramarTest` | Auto-programación equitativa, modo respetar hoy |
| `TurnoSemanalLoginMeseroTest` | Modal aparece solo si publicado+no confirmado; registro de confirmación |
| **Build** | `npm run build` — 0 errores, 0 warnings críticos |

---

## ─────────────────────────────────────
## BLOQUE II — Frontend, RRHH, Caja & Reportes
## ─────────────────────────────────────

### 🟢 FASE 6: Frontend & Optimización Móvil

**Objetivo:** Corregir el header comercial cortado, diseño responsivo de promociones y botones de compartir en redes sociales.

#### 6.1 — Corrección del Encabezado Comercial en Móvil

**Archivos:** `resources/views/layouts/app.blade.php`, `resources/css/app.css`

```
Problemas a corregir:
  ✗ Título cortado por el navbar fijo
  ✗ Falta de safe-area para notch iOS
  ✗ Textos con bajo contraste en modo claro/oscuro

Soluciones:
  ✓ padding-top = height(navbar) + env(safe-area-inset-top)
  ✓ viewport-fit=cover en el meta viewport
  ✓ text-gray-900 dark:text-gray-100 — contraste ≥ 4.5:1 en ambos modos
  ✓ font-size mínimo 16px (text-base) en títulos de sección
  ✓ UI coherente con el estilo del sistema (hereda dark: si la vista lo usa)
```

#### 6.2 — Botones de Compartir en Promociones

**Archivos:**
- `resources/views/livewire/promociones/publico.blade.php`
- `resources/views/livewire/promociones/detalle.blade.php`

```
Plataforma   │ Mecanismo
─────────────┼──────────────────────────────────────────────────────
WhatsApp     │ https://wa.me/?text={titulo}%20{url}
Facebook     │ https://www.facebook.com/sharer/sharer.php?u={url}
Instagram    │ navigator.clipboard.writeText(url) + toast
             │ "¡Link copiado! Pégalo en tu historia de Instagram 📲"
─────────────┼──────────────────────────────────────────────────────
Móvil (PWA)  │ navigator.share({title, url}) si está disponible
             │ Fallback: botones individuales
```

- Íconos SVG inline (sin CDN): WA verde, FB azul, IG gradiente púrpura/naranja.
- Registrar cada compartida en `promocion_difusiones` con `canal = 'social_[plataforma]'`.
- Botones táctiles: `min-h-[44px]`, separación mínima 8px.

#### 6.3 — Grid Responsivo de Tarjetas de Promociones

```
Breakpoint   │ Columnas │ Imagen      │ Texto
─────────────┼──────────┼─────────────┼────────────────
< 375px      │ 1        │ aspect 16:9 │ text-sm (14px)
375–767px    │ 1        │ aspect 16:9 │ text-[15px]
768–1023px   │ 2        │ aspect 16:9 │ text-base (16px)
≥ 1024px     │ 3        │ aspect 16:9 │ text-base (16px)
```

- `overflow-wrap: break-word` en título y subtítulo.
- Badge de tipo de beneficio con z-index correcto.
- Colores de badge adaptados a modo claro y oscuro.

---

### 🟢 FASE 7: Autorrotación Semanal Equitativa (RRHH)

**Objetivo:** Extender el módulo de turnos (Fase 3) con dos algoritmos de equidad.

**Archivos:**
- `app/Services/TurnoSemanalService.php` *(extensión)*
- `resources/views/livewire/turnos/index.blade.php` *(extensión)*

#### 7.1 — Motor de No-Repetición Consecutiva de Zonas

```
Regla: Si el mesero estuvo en Zona X en la semana N,
       NO puede estar en Zona X en la semana N+1.

Algoritmo:
  1. Cargar historial de últimas N semanas (N = ciclo configurado).
  2. Para cada slot (fecha, turno) a asignar:
     a. Obtener zonas usadas por el mesero en las últimas N semanas.
     b. Excluir esas zonas del pool disponible.
     c. Asignar zona disponible con menor carga total.
  3. Si todas las zonas han sido usadas → reiniciar ciclo (rotación completa).

Parámetros configurables (sección "Reglas de Rotación" en vista turnos):
  - Toggle: "Impedir zona repetida semana consecutiva" (ON por defecto)
  - Selector: "Longitud del ciclo" (2–8 semanas, por defecto: nº de zonas)
  - Vista previa: proyección de rotación a 4 semanas por mesero
```

#### 7.2 — Algoritmo de Ciclo de Descansos Equitativos (4 Semanas)

```
Clasificación de días por nivel de demanda:
  🔴 Alta:   Viernes, Sábado, Domingo
  🟡 Media:  Miércoles, Jueves
  🟢 Baja:   Lunes, Martes

Ciclo de 4 semanas por mesero (con offset rotativo):
  Semana 1: descanso día de demanda BAJA   (ej. Lunes)
  Semana 2: descanso día de demanda MEDIA  (ej. Miércoles)
  Semana 3: descanso día de demanda ALTA   (ej. Domingo)
  Semana 4: descanso día de demanda ALTA   (ej. Sábado)
  → Ciclo repite con offset +1 entre meseros para garantizar equidad.

Parámetros configurables:
  - Checkboxes de días "alta demanda" (por defecto: Vie/Sáb/Dom)
  - Número de semanas del ciclo (por defecto: 4)
```

**Panel visual "Proyección de Descansos — 4 semanas":**

```
Mesero  │  S40        │  S41        │  S42        │  S43
────────┼─────────────┼─────────────┼─────────────┼─────────────
Juan    │ 😴 Lun 🟢  │ 😴 Mié 🟡  │ 😴 Dom 🔴  │ 😴 Sáb 🔴
María   │ 😴 Mar 🟢  │ 😴 Jue 🟡  │ 😴 Sáb 🔴  │ 😴 Dom 🔴
Pedro   │ 😴 Mié 🟡  │ 😴 Vie 🔴  │ 😴 Lun 🟢  │ 😴 Mié 🟡
```

- Botón "Equilibrar descansos": redistribuye si algún mesero acumula más descansos en días de alta que el promedio.

---

### 🟢 FASE 8: Centralización de Cobros & Notificaciones de Caja

**Objetivo:** Revocar el cobro directo del mesero, centralizar en caja y notificar en ambas direcciones.

#### 8.1 — Revocar Cobro Directo del Mesero

**Archivos:**
- `resources/views/livewire/pos/terminal.blade.php`
- `resources/views/livewire/pos/partials/modal-cobro.blade.php`
- `app/Services/PedidoService.php`
- `app/Events/SolicitudCobroEnviada.php` *(nuevo)*

```
ANTES (mesero):   Carrito → [Cobrar] → modal de cobro → cobra directamente
DESPUÉS (mesero): Carrito → [📲 Solicitar cobro a Caja] → notificación a cajero

Implementación:
  1. En terminal.blade.php:
     - Si rol = 'mesero': reemplazar botón "Cobrar" por "📲 Solicitar cobro a Caja".
     - Llama a wire:click="solicitarCobroCaja".

  2. En PedidoService::solicitarCobroCaja(pedido_id):
     - pedido.estado = 'pendiente_cobro'.
     - Broadcast SolicitudCobroEnviada → canal privado 'caja.{sucursal_id}'.
     - Insert en notificaciones_usuario para cajeros activos.
     - Toast al mesero: "✅ Solicitud enviada a caja. Espera confirmación."

  3. En caja/control.blade.php:
     - Badge contador "Cobros Pendientes" (WebSocket tiempo real).
     - Lista de pedidos 'pendiente_cobro': mesa, mesero, total, tiempo espera.
     - Botón "Procesar cobro" → modal de cobro del cajero.
     - Al cobrar exitoso: estado → 'pagado', genera ticket → dispara Fase 8.2.
```

#### 8.2 — Notificación al Mesero: Pago Procesado por Caja

**Archivos:**
- `app/Events/PagoProcesadoPorCaja.php` *(nuevo)*
- `resources/views/components/alerta-cocina-mesero.blade.php`
- `routes/channels.php`

```
Flujo:
  1. Cajero confirma pago → PedidoService::cobrarPedido() dispara:
     broadcast(new PagoProcesadoPorCaja(pedido, mesero_id))
     → canal privado 'mesero.{mesero_id}'

  2. Payload del evento:
     {
       mesa:        "Mesa 5",
       total:       "$85.000 COP",
       metodo_pago: "Tarjeta Débito",
       ticket_url:  "/tickets/{pedido_id}",
       mensaje:     "💳 Caja procesó el pago. Entrega la factura al cliente."
     }

  3. En el dispositivo del mesero:
     - Toast táctil de alta visibilidad (banner superior, fondo verde oscuro).
     - Variante dark: si la vista del POS usa modo oscuro.
     - navigator.vibrate([200, 100, 200]) si disponible.
     - Enlace directo al ticket para mostrarlo al cliente.
     - Fallback: polling /notificaciones/pendientes cada 20s si WebSocket inactivo.
```

#### 8.3 — Auditoría de Devoluciones con Nota de Crédito Obligatoria

**Archivos:**
- `app/Models/NotaCredito.php` *(nuevo)*
- `database/migrations/..._create_notas_credito_table.php` *(nuevo)*
- `resources/views/livewire/pos/partials/modal-ticket-preview.blade.php`
- `resources/views/livewire/caja/control.blade.php`

```
Migración tabla notas_credito:
  id, numero_nc UNIQUE (NC-{anio}-{sucursal}-{seq}),
  pedido_id, pedido_devolucion_id,
  motivo ENUM(error_cargo, producto_defectuoso, cambio_pedido, otro),
  descripcion TEXT NULL,
  autorizado_por (user_id FK),
  sucursal_id, monto NUMERIC(10,2), timestamps

Flujo del modal de Nota de Crédito:
  1. Intento de anular/devolver ítem → sistema abre modal NC obligatorio.
  2. Campos obligatorios: motivo + autorización (cajero sesión o admin PIN).
  3. Al confirmar:
     a. Genera número NC automático y guarda en notas_credito.
     b. Ejecuta PedidoService::devolverItemPedido() (ya implementado S-ant).
     c. Imprime comprobante NC al cajero.
  4. Sin NC completado: opción de anular bloqueada en UI Y backend (Policy).

Policy: solo cajeros y admins pueden crear NCs.
```

---

### 🟢 FASE 9: Reportes Comparativos & Análisis con IA

**Objetivo:** Filtros temporales avanzados, comparativas de períodos e informes ejecutivos con IA.

**Archivos:**
- `resources/views/livewire/reportes/index.blade.php`
- `app/Services/ReportesComparativosService.php` *(nuevo)*

#### 9.1 — Filtros de Rango y Modo Comparativo

```
Selector de período:
  [ Hoy ] [ Semana ] [ Mes ] [ Trimestre ] [ Semestre ] [ Custom: ━━ a ━━ ]

Agrupación automática:
  Rango ≤ 7 días   → por día
  Rango ≤ 31 días  → por semana
  Rango ≤ 90 días  → por quincena
  Rango > 90 días  → por mes

Modo Comparativo (toggle):
  Período A (barras azules) vs Período B (barras grises)

KPIs diferenciales:
  Δ% ventas brutas │ Δ% comandas │ Δ ticket promedio │ Δ% devoluciones

Opciones de período de comparación:
  → "Mismo período anterior" (automático)
  → "Período personalizado" (2 date pickers adicionales)
```

**Gráficos adicionales:**
- Línea acumulativa de ventas (día a día del período).
- Heatmap de ventas (día de semana × hora del día).
- Top 5 productos del período seleccionado.
- Exportar comparativa en CSV y PDF.

#### 9.2 — Análisis con IA & Informe PDF Ejecutivo

```
Botón "🧠 Analizar con IA" (visible para admin/gerente):
  → Envía al AdminAiCopilotService:
    - Período activo + datos agregados.
    - Período de comparación si está activo.
    - Contexto del restaurante (sucursal, fecha).

  → El copiloto responde con:
    1. Resumen ejecutivo en español (2-3 párrafos).
    2. Observación de tendencia (crecimiento / meseta / caída).
    3. Top 3 recomendaciones operativas.
    4. Tarjeta infográfica ejecutiva (formato ya soportado en copilot-drawer).

Informe PDF Ejecutivo (GET /reportes/informe-ejecutivo):
  - Membrete con logo del restaurante.
  - KPIs clave con variación Δ%.
  - Gráfico comparativo visual.
  - Análisis generado por IA.
  - Pie: "Generado por RESTOMASTER IA · {fecha}"
  - Acceso: solo admin y gerente.
```

---

## 📋 Archivos Nuevos a Crear

| Archivo | Fase | Tipo |
|---------|------|------|
| `app/Events/SolicitudCobroEnviada.php` | 8.1 | Evento |
| `app/Events/PagoProcesadoPorCaja.php` | 8.2 | Evento |
| `app/Models/NotaCredito.php` | 8.3 | Modelo |
| `app/Services/TurnoSemanalService.php` | 3.2 | Servicio |
| `app/Services/ReportesComparativosService.php` | 9.1 | Servicio |
| `database/migrations/..._create_plantillas_turnos_table.php` | 3.1 | Migración |
| `database/migrations/..._create_programaciones_semanales_table.php` | 3.1 | Migración |
| `database/migrations/..._create_turnos_meseros_semana_table.php` | 3.1 | Migración |
| `database/migrations/..._create_notas_credito_table.php` | 8.3 | Migración |
| `resources/views/livewire/turnos/index.blade.php` | 3.3 | Vista |
| `resources/views/components/modal-turno-semanal.blade.php` | 4.2 | Componente |

---

## 🧪 Tests Requeridos por Fase

| Test | Fase | Aserciones clave |
|------|------|-----------------|
| `AdminAiCopilotHotfixTest` | 1 | Sin crash en ventas, respuesta de egresos, no-bucle saludo |
| `AdminAiCopilotFunctionCallingTest` | 2 | 5 tools resueltas, fallback offline |
| `TurnoSemanalCRUDTest` | 3 | CRUD, copiar semana, publicar, sync RotacionMeseroService |
| `TurnoSemanalAutoProgramarTest` | 3 | Distribución equitativa, modo respetar hoy |
| `TurnoSemanalLoginMeseroTest` | 4 | Modal visible solo si publicado+no confirmado |
| `TurnoSemanalRotacionZonasTest` | 7.1 | Zona no repetida en semana consecutiva |
| `TurnoSemanalDescansoEquitativoTest` | 7.2 | Descansos en días de demanda rotativa en 4 semanas |
| `PromocionesCompartirSocialTest` | 6.2 | URLs correctos, registro en promocion_difusiones |
| `CentralizacionCobrosTest` | 8.1 | Mesero bloqueado de cobrar; solicitud llega a caja |
| `NotificacionPagoProcesadoTest` | 8.2 | Evento emitido, canal correcto, payload completo |
| `NotaCreditoAuditoriaTest` | 8.3 | Sin NC no hay devolución; número NC único en BD |
| `ReportesComparativosTest` | 9.1 | Filtros de período, agrupación dinámica, JSON correcto |
| `CopilotoReportesIntegracionTest` | 9.2 | Respuesta IA estructurada, PDF accesible solo admin/gerente |

---

## ⚙️ Constraints de Diseño (Inviolables)

> **Aplican a TODAS las fases sin excepción:**

1. **UI acorde al sistema existente** — Toda UI nueva debe coincidir visualmente con el estilo actual del sistema. Si la vista donde se inserta ya usa `dark:`, la nueva UI **debe incluir sus variantes `dark:` correspondientes**. No se introduce un esquema de colores propio; se hereda la paleta y tokens del `tailwind.config.js` del proyecto.

2. **Contraste mínimo en ambos modos** — Ratio WCAG AA (4.5:1) garantizado en modo claro **y** oscuro. Prohibido `gray-400 / bg-white` y `gray-600 / bg-gray-900`. Usar siempre pares de contraste opuesto: `text-gray-900 dark:text-gray-100`.

3. **Fuentes legibles** — Mínimo `text-sm` (14px) en móvil; `text-base` (16px) en párrafos. Nunca reducir tipografía para ajustar un diseño: ajustar el layout.

4. **Táctil-first en POS y móvil** — Área táctil mínima `min-h-[44px] min-w-[44px]` en todos los botones e inputs del POS, cocina, caja y vistas móvil. Spacing entre botones adyacentes: mínimo 8px.

5. **Sin dependencias CDN nuevas** — Íconos con Material Symbols ya cargados (`<span class="material-symbols-outlined">`) o SVG inline. No agregar nuevas fuentes, icon-sets ni librerías UI externas.

6. **Seguridad server-side** — Toda autorización verificada en backend mediante `Policy`, `Gate` o `authorize()`. Ocultar en UI es UX, no seguridad: el backend siempre valida independientemente de lo que muestre la interfaz.

7. **Tests antes de cerrar fase** — Ninguna fase se considera completada sin su batería de tests en verde **y** `npm run build` sin errores ni warnings críticos.

---

## ▶️ Comandos de Arranque

**Plan completo (autónomo):**
```
"Ejecuta el PLAN_MAESTRO_SPRINT_FINAL.md de RESTOMASTER — implementa las 9 fases
en orden estricto sin detenerte hasta que todos los tests estén en verde
y npm run build compile sin errores."
```

**Por bloques:**
```
"Implementa el Bloque I del PLAN_MAESTRO_SPRINT_FINAL.md (Fases 1 a 5)"
"Implementa el Bloque II del PLAN_MAESTRO_SPRINT_FINAL.md (Fases 6 a 9)"
```

**Por fase específica:**
```
"Implementa la Fase [N] del PLAN_MAESTRO_SPRINT_FINAL.md"
```
