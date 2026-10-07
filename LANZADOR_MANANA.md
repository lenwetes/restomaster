# 🚀 LANZADOR MAESTRO — SPRINT FINAL
**Fecha programada:** Próxima sesión  
**Plan de referencia:** `PLAN_MAESTRO_SPRINT_FINAL.md`  
**Estado:** ✅ Aprobado por el usuario — Listo para ejecutar

---

## 📌 Instrucción de Arranque para el Agente

> **Si eres un agente (Antigravity / OpenCode):** Lee `PLAN_MAESTRO_SPRINT_FINAL.md` completo antes de empezar.  
> Ejecuta las **9 fases en orden estricto**. No omitas ninguna fase ni la declares completada sin tests en verde.

---

## 🗂️ RESUMEN DE LAS 9 FASES

### BLOQUE I — Motor IA & Turnos Base

| # | Fase | Tarea principal | Archivos clave |
|---|------|-----------------|----------------|
| 1 | ⚡ Hotfix Copiloto IA | Corregir crash `$p->total_ventas`, soporte egresos de caja, anti-bucle saludo | `AdminAiCopilotService.php` |
| 2 | 🧠 Function Calling Gemini | 5 tools reales + pestaña Gemini API Key en `/configuracion` | `AdminAiCopilotService.php`, `configuracion/index.blade.php` |
| 3 | 📅 Turnos Semanales Base | 3 migraciones + `TurnoSemanalService` + matriz semanal Livewire | `turnos/index.blade.php` *(nuevo)* |
| 4 | 🔔 Aviso Login Mesero | Modal táctil 7 días al entrar al POS + confirmación registrada | `modal-turno-semanal.blade.php` *(nuevo)* |
| 5 | 🧪 Tests Bloque I | 5 test classes + `npm run build` | `tests/Feature/` |

### BLOQUE II — Frontend, RRHH, Caja & Reportes

| # | Fase | Tarea principal | Archivos clave |
|---|------|-----------------|----------------|
| 6 | 📱 Frontend Móvil | Fix header cortado + botones compartir WA/FB/IG + grid responsivo | `layouts/app.blade.php`, `promociones/publico.blade.php` |
| 7 | 👥 Autorrotación RRHH | No-repetición de zona + ciclo equitativo descansos 4 semanas | `TurnoSemanalService.php` *(extensión)* |
| 8 | 💳 Centralización Cobros | Mesero solicita → caja procesa → notifica mesero + NC obligatoria | `SolicitudCobroEnviada.php`, `PagoProcesadoPorCaja.php`, `NotaCredito.php` |
| 9 | 📊 Reportes + IA | Filtros Semana/Trimestre/Semestre + modo comparativo + PDF ejecutivo IA | `ReportesComparativosService.php` *(nuevo)* |

---

## ✅ CHECKLIST DE EJECUCIÓN

### BLOQUE I
- [ ] **FASE 1** — Hotfix crash ventas + egresos de caja + anti-bucle saludo
- [ ] **FASE 2** — Function Calling: 5 tools + pestaña configuración Gemini
- [ ] **FASE 3** — Migraciones + TurnoSemanalService + vista matriz semanal
- [ ] **FASE 4** — Modal login mesero + confirmación + notificación campana
- [ ] **FASE 5** — Tests Bloque I (5 clases) + `npm run build` ✓

### BLOQUE II
- [ ] **FASE 6** — Fix header móvil + botones compartir redes + grid responsivo
- [ ] **FASE 7** — Algoritmo no-repetición zona + ciclo descansos 4 semanas
- [ ] **FASE 8** — Centralizar cobros + notif. WebSocket mesero + Nota de Crédito
- [ ] **FASE 9** — Reportes comparativos + análisis IA + PDF ejecutivo

### Cierre
- [ ] Suite completa de 13 test classes en verde
- [ ] `npm run build` sin errores
- [ ] Actualizar `coordination.md`

---

## ⚙️ CONSTRAINTS DE DISEÑO (Recordatorio rápido)

1. **UI acorde al sistema** — Heredar `dark:` si la vista lo usa; no imponer modo claro ni oscuro.
2. **Contraste WCAG AA en ambos modos** — `text-gray-900 dark:text-gray-100`, nunca gris sobre gris.
3. **Fuentes** — `text-sm` mínimo en móvil, `text-base` en párrafos.
4. **Táctil-first** — `min-h-[44px] min-w-[44px]` en POS/móvil, spacing 8px entre botones.
5. **Sin CDN nuevos** — Material Symbols (`<span class="material-symbols-outlined">`) o SVG inline.
6. **Seguridad server-side** — `Policy` / `Gate` / `authorize()` en backend siempre.
7. **Tests antes de cerrar** — Fase no completada sin tests en verde + build limpio.

---

## ⚡ CÓMO ARRANCAR

Cuando abras el chat en la próxima sesión, escribe exactamente:

```
Ejecuta el PLAN_MAESTRO_SPRINT_FINAL.md de RESTOMASTER — implementa las 9 fases
en orden estricto sin detenerte hasta que todos los tests estén en verde
y npm run build compile sin errores.
```

O para un bloque específico:
```
Implementa el Bloque I del PLAN_MAESTRO_SPRINT_FINAL.md (Fases 1 a 5)
```

---

## 📁 Archivos de Referencia

| Archivo | Propósito |
|---------|-----------|
| [`PLAN_MAESTRO_SPRINT_FINAL.md`](file:///d:/Proyectos/restomaster/PLAN_MAESTRO_SPRINT_FINAL.md) | Especificación técnica completa de las 9 fases |
| [`coordination.md`](file:///d:/Proyectos/restomaster/coordination.md) | Estado de trabajo entre agentes |
| [`AGENTS.md`](file:///d:/Proyectos/restomaster/AGENTS.md) | Contexto compartido del proyecto |
