# Plan de Implementación: Agente IA en CRM con Switch Manual de Encendido/Apagado, Plantilla de Privilegios Editable y Guardrails de Seguridad

> **Proyecto:** RestoMaster  
> **Fecha:** 2026-09-26  
> **Estado:** Listo para revisión e implementación  
> **Módulo Integrado:** CRM & Automatizaciones (`resources/views/livewire/crm/index.blade.php`, `app/Models/CrmConfiguracion.php`)  
> **Documentos Relacionados:** `docs/superpowers/specs/2026-09-18-ai-hostess-copilot-financiero-design.md`, `docs/superpowers/plans/2026-09-18-ai-hostess-copilot-financiero.md`

---

## 1. Visión General del Requerimiento

El restaurante requiere control total, transparente e inmediato sobre la Inteligencia Artificial:

1. **Botón Manual de Encendido / Apagado Inmediato (Kill-Switch en Vivo):**
   * Un interruptor destacado en la cabecera de la configuración de IA.
   * Si el administrador pulsa "Apagar IA", **inmediatamente** el sistema deja de procesar mensajes con LLM y responde con un mensaje predeterminado de atención humana, protegiendo al restaurante ante cualquier contingencia, mantenimiento o evento especial.
   * Sin reiniciar servidores ni modificar archivos `.env`.

2. **Sistema de Privilegios basado en "Plantilla de Políticas de la IA" (Editable desde la UI):**
   * Un gestor donde el administrador puede elegir entre **Plantillas Predefinidas** (*Presets*) o **personalizar su propia plantilla de privilegios** directamente desde la pantalla.
   * La plantilla define qué acciones tiene autorizadas la IA y cuáles tiene terminantemente bloqueadas.
   * Permite editar tanto los **permisos binarios (toggles)** como las **reglas de conducta y límites de negocio** en lenguaje claro.

3. **Configuración de API Keys Cifradas:**
   * Carga de credenciales de Google Gemini u OpenAI directo desde la interfaz, guardadas cifradas en base de datos.

4. **Blindaje contra Fugas de Información y Prompt Injection:**
   * **Tool Gatekeeper Server-Side:** Las herramientas deshabilitadas en la plantilla activa **no se envían al LLM**. No existe posibilidad técnica de que el modelo acceda a lo que tiene prohibido.
   * Las herramientas financieras, de recetas y costos internos están **permanentemente vetadas del canal de chat público**.

---

## 2. Arquitectura del Sistema de Privilegios y Plantillas

### Matriz de Privilegios de la Plantilla

Cada plantilla de privilegios agrupa los permisos en 4 niveles de seguridad:

```
┌────────────────────────────────────────────────────────────────────────┐
│                      PLANTILLA DE PRIVILEGIOS IA                       │
├────────────────────────────────────────────────────────────────────────┤
│ 1. PRIVILEGIOS DE LECTURA PÚBLICA (Consultas):                         │
│    • Consultar Carta y Platos [✓ Permitido]                            │
│    • Mostrar Precios de la Carta [✓ Permitido]                         │
│    • Informar Alérgenos e Ingredientes [✓ Permitido]                   │
│    • Consultar Horarios y Ubicación de Sucursal [✓ Permitido]          │
│                                                                        │
│ 2. PRIVILEGIOS TRANSACCIONALES (Acciones en Base de Datos):            │
│    • Verificar Disponibilidad de Mesas en Vivo [✓ Permitido]           │
│    • Agendar Nuevas Reservas [✓ Permitido / ✗ Bloqueado]               │
│    • Modificar / Cancelar Reservas Existentes [✗ Bloqueado por Defecto]│
│    • Límite Máximo de Comensales por Reserva: [ 6 personas ]           │
│                                                                        │
│ 3. PRIVILEGIOS DE MARKETING Y CRM:                                     │
│    • Informar Promociones y Cupones Activos [✓ Permitido]              │
│    • Consultar Saldo de Puntos VIP [✗ Bloqueado / Requiere Auth]       │
│                                                                        │
│ 4. PRIVILEGIOS FINANCIEROS Y ADMINISTRATIVOS:                          │
│    • Consultar Ventas, Caja, Costos o Recetas [⛔ BLOQUEO PERMANENTE]   │
│      (Incluso si el usuario intenta prompt-injection, no hay tool)     │
└────────────────────────────────────────────────────────────────────────┘
```

### Presets de Plantillas Rápidas (1 Clic)
1. **Plantilla "Hostess Completa" (Recomendada):** Lectura de carta, verificación de mesas y creación de reservas hasta 6 comensales.
2. **Plantilla "Solo Informativa / Menú":** Responde dudas, carta, alérgenos y horarios; **no agenda reservas** (envía link web o teléfono).
3. **Plantilla "Estricta / Modo Silencioso":** Solo responde preguntas exactas con respuestas breves; no interactúa con transacciones ni datos de clientes.
4. **Plantilla "Personalizada":** El administrador activa/desactiva cada privilegio individualmente y redacta sus directivas específicas.

---

## 3. Modelo de Datos (`crm_configuraciones` & `crm_ia_plantillas_privilegios`)

Para garantizar flexibilidad sin romper la base de datos actual, implementamos:

### Tabla `crm_ia_plantillas_privilegios` (Catálogo de Plantillas)
* `id`: bigint PK.
* `sucursal_id`: bigint FK nullable (permite plantillas globales del sistema y personalizadas por local).
* `nombre`: string (ej. *"Hostess Completa Estándar"*, *"Eventos Fin de Año"*).
* `descripcion`: string.
* `es_sistema`: boolean (true para las 3 plantillas predeterminadas de fábrica).
* `permitir_menu`: boolean (default: true).
* `permitir_precios`: boolean (default: true).
* `permitir_alergenos`: boolean (default: true).
* `permitir_verificar_mesas`: boolean (default: true).
* `permitir_crear_reservas`: boolean (default: true).
* `max_personas_reserva`: integer (default: 6).
* `permitir_cancelar_reservas`: boolean (default: false).
* `permitir_promociones`: boolean (default: true).
* `permitir_puntos_vip`: boolean (default: false).
* `directivas_sistema`: text (instrucciones de tono, reglas de la casa: *"Mascotas solo en terraza, descorche $30.000"*).
* `timestamps`.

### Campos en `crm_configuraciones`:
* `ia_activo`: boolean (default: false) ➔ **El Kill-Switch manual**.
* `ia_plantilla_privilegio_id`: bigint FK ➔ Enlace a la plantilla activa seleccionada.
* `ia_proveedor`: string (default: `'gemini'`).
* `ia_modelo`: string (default: `'gemini-2.5-flash'`).
* `ia_api_key`: text nullable (cifrada con `Crypt::encryptString`).
* `ia_limite_mensajes_por_cliente_dia`: integer (default: 15).
* `ia_mensaje_apagado`: text (default: *"En este momento nuestro asistente virtual está descansando. Para reservas o consultas inmediatas, comunícate al {telefono}."*).

---

## 4. Diseño de la Interfaz en Livewire CRM (`crm.index`)

En `resources/views/livewire/crm/index.blade.php`, la nueva pestaña **"🧠 Agente IA & Privilegios"** se organiza en 3 bloques Bento interactivos:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ Pestañas: [Satisfacción] [Automatizaciones] [Plantillas] [Logs] [Config API] [🧠 Agente IA] │
└────────────────────────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────────────────────────┐
│ 🔴 BARRA DE ESTADO Y BOTÓN KILL-SWITCH MANUAL                                           │
│  Estado Actual: [ ● IA ACTIVA Y ATENDIENDO 24/7 ]                                       │
│  [  ⚡ APAGAR IA MANUALMENTE  ] (Botón reactivo instantáneo con toggle On/Off)          │
│  Mensaje de fuera de servicio: "En este momento nuestro asistente está en pausa..."     │
└────────────────────────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────┐ ┌──────────────────────────────────────────────┐
│ 🔑 1. Credenciales & Motor LLM        │ │ 🛡️ 2. Plantilla de Privilegios Activa        │
├───────────────────────────────────────┤ ├──────────────────────────────────────────────┤
│ Proveedor: [ Google Gemini 2.5 Flash▼]│ │ Seleccionar Plantilla:                       │
│ API Key:   [••••••••••••••••3x9Z    ] │ │ [ Hostess Completa (Recomendada)          ▼] │
│ [ Probar Conexión con Proveedor ]     │ │                                              │
│                                       │ │ ⚙️ Editar Privilegios de esta Plantilla:     │
│ Consumo & Cuota:                      │ │ • [✓] Consultar Carta y Precios              │
│ Límite msg/día por comensal: [ 15 ]   │ │ • [✓] Verificar Disponibilidad de Mesas      │
│                                       │ │ • [✓] Agendar Reservas (Máx [ 6 ] personas)  │
│                                       │ │ • [✗] Cancelar/Modificar Reservas            │
│                                       │ │ • [✓] Informar Promociones CRM               │
│                                       │ │ • [✗] Consultar Puntos VIP                   │
│                                       │ │                                              │
│                                       │ │ 📝 Directivas y Reglas de la Casa:           │
│                                       │ │ [ Terraza es pet-friendly. Estacionamiento..]│
│                                       │ │ [ Guardar Cambios en Plantilla ]             │
└───────────────────────────────────────┘ └──────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────────────────────────┐
│ 🧪 3. Simulador Interactivo de Auditoría (Sandbox en Tiempo Real)                      │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ Permite al administrador chatear con el bot en esta misma pantalla para verificar      │
│ que respete los privilegios configurados antes de activar el switch a clientes reales. │
│ Ejemplos de prueba con un clic:                                                        │
│ [ "¿Tienen mesa para 15 personas?" ] ➔ Verifica aplicación del límite de comensales   │
│ [ "¿Cuánto dinero vendieron ayer?" ] ➔ Verifica el bloqueo de seguridad financiera    │
│ [ "¿Qué platos tienen salmón?" ]     ➔ Verifica la lectura del menú de la carta        │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Implementación Técnica Paso a Paso

### Fase 1: Base de Datos, Modelo y Seeders de Plantillas
- **Paso 1.1:** Migración `create_crm_ia_plantillas_privilegios_table` con las columnas de privilegios y directivas.
- **Paso 1.2:** Migración `add_ia_fields_to_crm_configuraciones_table` con el switch `ia_activo`, FK de plantilla, API Key cifrada y mensaje de fallback.
- **Paso 1.3:** `CrmIaPlantillaSeeder`: Registra las 3 plantillas iniciales de fábrica (*Hostess Completa*, *Solo Menú*, *Estricta*).
- **Paso 1.4:** Modelos `CrmIaPlantillaPrivilegio` y actualización de `CrmConfiguracion`:
  - `Crypt::encryptString` en el mutator de `ia_api_key`.
  - Método `toggleIaActiva(): bool`.

### Fase 2: Tool Gatekeeper Dinámico según la Plantilla Activa
- **Paso 2.1: `app/Services/Ai/AiToolGatekeeper.php`:**
  - Lee la plantilla asociada a la sucursal activa.
  - Ensambla la lista de Tools autorizadas:
    * Si `permitir_menu` → Carga `ConsultarMenuTool`.
    * Si `permitir_verificar_mesas` → Carga `ConsultarDisponibilidadTool`.
    * Si `permitir_crear_reservas` → Carga `CrearReservaTool(maxPersonas: $plantilla->max_personas_reserva)`.
    * Si un privilegio está en `false`, la tool **no existe** en la petición al modelo.
  - En canal WhatsApp público, **exclusión total** de herramientas de contabilidad, reportes y costos.

### Fase 3: Integración en la Interfaz Volt CRM (`crm/index.blade.php`)
- **Paso 3.1:** Agregar la pestaña `ia` al selector de tabs superior.
- **Paso 3.2:** Botón reactivo **Kill-Switch**:
  - `toggleIaManual()`: Alterna `$ia_activo` inmediatamente con toast de confirmación y cambio visual de verde a rojo/gris.
- **Paso 3.3:** Selector y editor de plantillas:
  - Cambiar de plantilla en el dropdown recarga instantáneamente los checkboxes y las directivas en pantalla.
  - Botón *Guardar Plantilla* actualiza los permisos y directivas en la BD.
- **Paso 3.4:** Simulador Sandbox de chat en vivo con selector de preguntas rápidas de auditoría.

### Fase 4: Orquestación del Webhook de WhatsApp con Kill-Switch
- **Paso 4.1: [`CrmWebhookController.php`](file:///d:/Proyectos/restomaster/app/Http/Controllers/CrmWebhookController.php) / `ProcesarMensajeWhatsAppJob.php`:**
  - Si `CrmConfiguracion::activa()->ia_activo === false`:
    - El bot **no llama al LLM**.
    - Envía inmediatamente el `ia_mensaje_apagado` por WhatsApp al cliente y finaliza el proceso con costo cero de API.
  - Si `ia_activo === true`:
    - Verifica cuota de mensajes por cliente.
    - Aplica la plantilla activa y las tools autorizadas.
    - Envía la respuesta generada.

---

## 6. Suite de Pruebas de Calidad y Seguridad (TDD)

Archivo de pruebas: `tests/Feature/CrmAiPrivilegiosTest.php`:
1. **Test Kill-Switch Manual:** Al desactivar `ia_activo = false`, un mensaje entrante de WhatsApp recibe la respuesta de contingencia sin invocar al proveedor LLM.
2. **Test Cifrado de API Key:** Verifica que la API Key se guarde cifrada en base de datos (`$config->getRawOriginal('ia_api_key') !== 'mi-clave-real'`).
3. **Test Plantilla Solo Menú (Gatekeeper):** Con la plantilla "Solo Menú", la herramienta `crear_reserva` no está presente en el schema de tools y el bot no agenda reservas.
4. **Test Límite de Personas:** Si una plantilla tiene tope de 6 comensales, una solicitud de 10 personas es rechazada por el gatekeeper de la tool.
5. **Test Anti-Fuga de Datos Financieros:** Ante preguntas sobre costos o ingresos del restaurante, el bot responde con denegación de privilegios y 0 datos internos expuestos.
6. **Mocks de LLM:** 100% de la suite corre en verde sin llamadas a APIs externas de pago.

---

## 7. Criterios de Aceptación

- [ ] Existe un botón manual claramente visible en la interfaz para apagar o encender la IA con 1 solo clic.
- [ ] Si la IA está apagada, responde con el mensaje configurable de fuera de servicio y no consume tokens.
- [ ] El administrador puede seleccionar o editar plantillas de privilegios directamente en la pantalla de CRM.
- [ ] Las herramientas prohibidas en la plantilla no se transmiten al LLM (seguridad server-side).
- [ ] La API Key se guarda cifrada y funciona sin necesidad de editar archivos del servidor.
- [ ] La suite de pruebas pasa al 100% y el estilo cumple con Laravel Pint (0 violaciones).
