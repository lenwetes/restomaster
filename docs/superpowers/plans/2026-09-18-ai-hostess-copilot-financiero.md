# Plan de Implementación: Suite de Agentes IA (Hostess WhatsApp & Copilot Financiero)

> **Documento de Referencia:** `docs/superpowers/specs/2026-09-18-ai-hostess-copilot-financiero-design.md`  
> **Estado:** Listo para ejecución futura (Plan 1)  
> **Dependencias Previas:** Laravel 13, PostgreSQL 18, `ReservaService`, `MenuService`, `ReporteService`.

---

## Resumen Ejecutivo

Este plan detalla la construcción paso a paso de la automatización por IA para **RestoMaster**:
1. **Fase 1: Infraestructura Base y Contratos de IA** (SDK, variables `.env`, wrappers de Tools).
2. **Fase 2: Copilot Financiero en Dashboard** (Herramientas analíticas, Livewire Chat, RBAC).
3. **Fase 3: Hostess Omnicanal por WhatsApp** (Webhook Meta, Job en cola, Tool calling de reservas).
4. **Fase 4: Observabilidad, Seguridad y Suite de Pruebas**.

---

## Fase 1: Infraestructura Base & Configuración de IA

### Paso 1.1: Instalación de Dependencias
- Instalar paquete agnóstico de IA para Laravel:
  ```bash
  composer require prism-php/prism
  ```
- Publicar configuración de Prism:
  ```bash
  php artisan vendor:publish --tag=prism-config
  ```

### Paso 1.2: Variables de Entorno y Configuración
- Actualizar `.env.example` con las claves requeridas (sin valores reales):
  ```env
  # Proveedor LLM
  PRISM_DEFAULT_PROVIDER=gemini
  GEMINI_API_KEY=
  OPENAI_API_KEY=

  # Meta WhatsApp Cloud API
  WHATSAPP_TOKEN=
  WHATSAPP_PHONE_NUMBER_ID=
  WHATSAPP_VERIFY_TOKEN=
  WHATSAPP_APP_SECRET=
  ```
- Crear archivo de configuración específico: `config/ia.php` para definir prompts base, modelos a utilizar, límites de tokens y umbrales de rate limit.

### Paso 1.3: Definición de Tools en Laravel
- Crear directorio `app/Services/Ai/Tools/`:
  - `ConsultarDisponibilidadTool.php`: Envoltorio tipado de `ReservaService::verificarDisponibilidad`.
  - `CrearReservaTool.php`: Envoltorio tipado de `ReservaService::crear`.
  - `ConsultarMenuTool.php`: Envoltorio tipado de `MenuService::obtenerMenuPublico`.
  - `ConsultarKpisHoyTool.php`: Envoltorio tipado de `ReporteService::kpisRealtime`.
  - `ConsultarVentasPeriodoTool.php`: Envoltorio tipado de `ReporteService::ventasPorPeriodo`.
  - `ConsultarTopProductosTool.php`: Envoltorio tipado de `ReporteService::ventasPorProducto`.

---

## Fase 2: Copilot Financiero & Operativo (Panel Administrativo)

### Paso 2.1: Servicio del Agente Financiero
- Crear `app/Services/Ai/FinancialCopilotService.php`:
  - Configura el system prompt analítico (experto financiero gastronómico, análisis de márgenes, ticket promedio y food cost).
  - Registra las herramientas de lectura financiera (`ConsultarKpisHoyTool`, `ConsultarVentasPeriodoTool`, etc.).
  - Método `preguntar(string $pregunta, User $usuario): string`.

### Paso 2.2: Componente Livewire y Vista Blade
- Crear componente Livewire: `app/Livewire/Admin/AsistenteReportesChat.php`.
- Autorización estricta: Verificación de `Gate::authorize('ver-reportes')` en `mount()` y en el método de envío.
- Interfaz en `resources/views/livewire/admin/asistente-reportes-chat.blade.php`:
  - Botón flotante o pestaña en `/reportes`.
  - Historial de chat interactivo con estados de carga ("Analizando ventas...", "Calculando food cost...").
  - Renderizado de Markdown para tablas y métricas formateadas en pesos colombianos (`COP`).

### Paso 2.3: Tests del Copilot Financiero
- Crear `tests/Feature/Ai/FinancialCopilotTest.php`:
  - Test de autorización: Usuarios no autorizados (meseros/cajeros) reciben 403.
  - Test de respuesta: Simulación de respuesta con Mock de Prism para verificar que invoque `ReporteService` adecuadamente y devuelva la estructura esperada.

---

## Fase 3: Hostess Omnicanal por WhatsApp

### Paso 3.1: Servicio y Webhook de WhatsApp
- Crear `app/Services/WhatsApp/WhatsAppService.php`:
  - Métodos para enviar mensajes de texto y plantillas interactivas a la API de Meta Graph (`https://graph.facebook.com/v21.0/{phone_number_id}/messages`).
- Crear `app/Http/Controllers/Api/WhatsAppWebhookController.php`:
  - `GET /api/webhooks/whatsapp`: Endpoint de verificación del webhook requerido por Meta (`hub_challenge`, `hub_verify_token`).
  - `POST /api/webhooks/whatsapp`: Recepción de eventos. Verificación estricta de la firma HMAC (`X-Hub-Signature-256`) contra `WHATSAPP_APP_SECRET`.
  - Despacho inmediato de `ProcesarMensajeWhatsAppJob::dispatch($datosMensaje)`.

### Paso 3.2: Job de Procesamiento y Memoria de Conversación
- Crear `app/Jobs/ProcesarMensajeWhatsAppJob.php`:
  - Manejo de colas con reintentos y timeouts.
  - Recuperación y guardado del historial de los últimos 6 mensajes en `Cache::remember("whatsapp:chat:{$from}", ...)`.
  - Orquestación del `HostessAgentService`:
    - Ejecuta el LLM con las tools de disponibilidad, reserva y menú.
    - Envía la respuesta generada vía `WhatsAppService::enviarMensaje()`.

### Paso 3.3: Tests de Integración de WhatsApp
- Crear `tests/Feature/Ai/WhatsAppWebhookTest.php`:
  - Valida el handshake de verificación de Meta (`hub.challenge`).
  - Valida el rechazo de requests con firmas HMAC falsificadas o ausentes.
  - Valida que mensajes válidos despachen el Job a la cola sin ejecutar código síncrono pesado en el controlador.

---

## Fase 4: Observabilidad, Seguridad y Pulido

### Paso 4.1: Registro de Auditoría y Métricas (Opcional)
- Migración `create_ia_conversaciones_log_table` para registrar tiempos de respuesta, tokens utilizados y herramientas ejecutadas.
- Monitoreo de costos estimados en el panel de administración.

### Paso 4.2: Rate Limiting & Resiliencia
- Aplicar middleware `throttle:30,1` en la ruta del webhook para prevenir spam.
- Fallback elegante: Si el proveedor LLM arroja timeout o error de servicio, la Hostess responde con un mensaje predeterminado:
  *"En este momento presento intermitencia en mi sistema. Para reservas urgentes, por favor comunícate directamente a nuestra línea de atención."*

---

## Criterios de Aceptación para Dar por Concluido el Feature

1. [ ] **Copilot Financiero:** Un administrador puede consultar "¿Cómo van las ventas de hoy?" y obtener un desglose exacto de ventas, órdenes y ticket promedio proveniente de `ReporteService`.
2. [ ] **Hostess WhatsApp:** Un cliente puede escribir por WhatsApp consultando mesa para una fecha específica; el bot verifica la disponibilidad real en RestoMaster y puede agendar la reserva retornando el token público.
3. [ ] **Seguridad:** Ni el cliente de WhatsApp ni usuarios no autorizados pueden ejecutar acciones destructivas o ver datos ajenos.
4. [ ] **Estabilidad:** La suite completa de pruebas unitarias y de feature pasa al 100% con cero llamadas a APIs externas en los tests.
