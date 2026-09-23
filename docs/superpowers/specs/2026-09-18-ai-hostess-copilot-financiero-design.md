# Spec: Suite de Agentes IA — Hostess 24/7 (WhatsApp) & Copilot Financiero

**Fecha:** 2026-09-18 · **Estado:** Planificado a futuro (Spec de Diseño) · **Fase:** Expansión IA (Plan 1)

---

## 1. Objetivo y Alcance

Potenciar **RestoMaster** mediante la incorporación de dos agentes de Inteligencia Artificial especializados y desacoplados:
1. **Hostess Omnicanal 24/7 (WhatsApp):** Atiende clientes automáticamente, responde consultas del menú/horarios, verifica disponibilidad real de mesas y agenda reservas con confirmación instantánea.
2. **Copilot Financiero & Operativo (Panel Administrativo):** Asistente consultivo para dueños y administradores dentro de Livewire, capaz de responder preguntas en lenguaje natural sobre ventas, KPIs en tiempo real, platos estrella y food cost.

**Principio de diseño cardinal:** El LLM **nunca** interactúa de forma directa con la base de datos ni emite SQL. Toda acción u obtención de datos se realiza a través de **Tool Calling (Function Calling)** tipado contra los servicios de dominio existentes en Laravel (`ReservaService`, `MenuService`, `ReporteService`).

---

## 2. Decisiones de Arquitectura

1. **Abstracción del Proveedor LLM:**
   - Se utilizará `prism-php/prism` (estándar moderno agnóstico para Laravel) o una capa de servicios `AiProviderInterface`.
   - Modelo por defecto en producción: **Gemini 2.5 Flash** (ultra-bajo costo, ventana de contexto extendida y respuesta < 1s). Alternativa configurable vía `.env`: `gpt-4o-mini`.
2. **Canal WhatsApp Oficial y Asíncrono:**
   - Integración con **Meta WhatsApp Cloud API**.
   - El webhook `POST /api/webhooks/whatsapp` valida la firma `X-Hub-Signature-256`, responde HTTP 200 inmediatamente (< 50ms) y despacha un Job `ProcesarMensajeWhatsAppJob` a la cola (`QUEUE_CONNECTION=database`).
3. **Memoria de Conversación (Context Window):**
   - Caché con TTL de 2 horas indexado por número de teléfono (`whatsapp:chat:{telefono}`) para retener los últimos 6 mensajes del hilo de conversación sin sobrecargar tokens.
4. **Seguridad y Control de Acceso (OWASP Top 10 LLM):**
   - **Hostess:** Solo permisos de lectura sobre menú público y escritura exclusiva sobre creación de reservas (`ReservaService::crear()`).
   - **Copilot Financiero:** Estrictamente protegido por middleware de autenticación y verificación de roles/abilities (`Gate::allows('ver-reportes')`).
   - **Defensa contra Prompt Injection:** Parámetros de usuario aislados en rol `user`; instrucciones de rol del sistema inmutables con barreras semánticas explícitas.

---

## 3. Modelo de Datos y Esquemas

No se requieren migraciones destructivas ni cambios a tablas existentes. Se contempla una tabla ligera opcional para observabilidad y trazabilidad de costos:

### Tabla `ia_conversaciones_log` (Opcional para auditoría)
- `id`: bigint PK.
- `canal`: string (whatsapp, panel_admin).
- `identificador_usuario`: string (número de teléfono o `user_id`).
- `mensaje_usuario`: text.
- `respuesta_ia`: text.
- `tools_invocadas`: jsonb (nombre de herramientas y argumentos ejecutados).
- `tokens_prompt`: integer.
- `tokens_completion`: integer.
- `costo_estimado_usd`: decimal(8, 6).
- `duracion_ms`: integer.
- `created_at`: timestamptz.

---

## 4. Definición de Herramientas (Tool Calling)

### Para la Hostess (WhatsApp)
1. `verificar_disponibilidad`:
   - Argumentos: `fecha` (YYYY-MM-DD), `hora` (HH:mm), `personas` (int), `sucursal_id` (opcional int).
   - Servicio: `ReservaService::verificarDisponibilidad()`.
2. `crear_reserva`:
   - Argumentos: `nombre` (string), `telefono` (string), `fecha` (YYYY-MM-DD), `hora` (HH:mm), `personas` (int), `notas` (opcional string).
   - Servicio: `ReservaService::crear($datos, 'whatsapp')`. Retorna código y link público con `token_publico`.
3. `consultar_menu`:
   - Argumentos: `categoria` (opcional string), `busqueda` (opcional string).
   - Servicio: `MenuService::obtenerMenuPublico()`.

### Para el Copilot Financiero (Dashboard)
1. `kpis_tiempo_real`:
   - Argumentos: `sucursal_id` (opcional int).
   - Servicio: `ReporteService::kpisRealtime()`.
2. `ventas_por_periodo`:
   - Argumentos: `desde` (YYYY-MM-DD), `hasta` (YYYY-MM-DD).
   - Servicio: `ReporteService::ventasPorPeriodo()`.
3. `ranking_productos_margen`:
   - Argumentos: `desde` (YYYY-MM-DD), `hasta` (YYYY-MM-DD), `limite` (int).
   - Servicio: `ReporteService::ventasPorProducto()`.
4. `estado_resultados`:
   - Argumentos: `desde` (YYYY-MM-DD), `hasta` (YYYY-MM-DD).
   - Servicio: `ReporteService::estadoResultados()`.

---

## 5. UI / UX

1. **Hostess:** Mensajes formateados para WhatsApp con emojis, viñetas legibles y links directos a la reserva pública (`/reservas/{token_publico}`).
2. **Copilot Financiero:**
   - Componente Livewire `app/Livewire/Admin/AsistenteReportesChat.php`.
   - Interfaz en cajón lateral tipo "Offcanvas" o botón flotante accesible en `/reportes`.
   - Respuestas formateadas con Markdown, badges de KPIs, comparativas porcentuales y botones de acción rápida ("Ver detalle de hoy", "Comparar con semana pasada").

---

## 6. Estrategia de Pruebas (TDD & Mocks)

- Pruebas automatizadas en `tests/Feature/Ai/`:
  - `WhatsAppWebhookSecurityTest`: Valida que requests sin firma HMAC o con firma inválida sean rechazados con HTTP 401/403.
  - `HostessAgentToolCallingTest`: Mock del cliente LLM para verificar que cuando el modelo decide llamar a `verificar_disponibilidad` o `crear_reserva`, se invoque con los tipos de datos correctos y se cree el registro en la BD.
  - `CopilotFinancieroAuthTest`: Valida que meseros o cajeros no puedan interactuar con el agente financiero (403 Forbidden).
- Cero costo de API durante la ejecución de `php artisan test` (todo mediante mocks o fakes de Prism/LLM).
