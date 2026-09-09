# AGENTS.md — Contexto Compartido para Agentes de IA

## Proyecto
**Sushixpress** — Sistema de gestión integral para restaurante de sushi.

## Stack
- Backend: Laravel 13 + PHP 8.3
- DB: PostgreSQL 18
- Frontend: Blade + Livewire (mobile-first, táctil)
- Ubicación: `D:\Proyectos\sushixpress`

## Agentes Activos
| Agente | Rol | Herramienta |
|--------|-----|-------------|
| OpenCode | Desarrollo principal, setup, código | OpenCode CLI |
| Antigravity | Scripts, migraciones, automatización | Antigravity IDE |

## Reglas Generales
1. **Leer este archivo** antes de empezar cualquier tarea
2. **Consultar `coordination.md`** para ver qué está haciendo el otro agente
3. **Crear lock file** antes de modificar módulos compartidos
4. **No editar archivos .lock** — son del otro agente
5. **Registrar cambios** en coordination.md al terminar

## Estructura del Proyecto
```
sushixpress/
├── AGENTS.md              ← Este archivo (contexto compartido)
├── coordination.md        ← Estado de trabajo entre agentes
├── .ai/rules/             ← Reglas de trabajo
├── .locks/                ← Lock files de módulos en uso
├── docs/                  ← Documentación existente
│   ├── arquitectura/
│   ├── fases/
│   ├── modelo-datos/
│   ├── modulos/
│   └── pantallas/
└── [código Laravel aquí]
```

## Fases de Desarrollo
- **Fase 0:** Cimientos (setup, auth, estructura)
- **Fase 1:** Núcleo (POS, mesas, pedidos, cocina)
- **Fase 2:** Caja y Contabilidad
- **Fase 3:** Inventario y Recetas
- **Fase 4:** Clientes y Delivery
- **Fase 5:** Reservas y Reportes
- **Fase 6:** Robustez y Pulido

## Documentación
- Ver `docs/` para requerimientos, arquitectura, modelo de datos
- Cada módulo tiene su doc en `docs/modulos/`
- Fases en `docs/fases/fases.md`

## Skills Compartidas (seguridad + calidad de código)
> Disponibles para OpenCode (`.opencode/skills/`) y Antigravity (`C:\Users\PC-0001\.gemini\config\skills\`).

| Skill | Uso |
|-------|-----|
| `secrets-scan` | Detectar credenciales/tokens hardcodeados antes de commit/deploy |
| `laravel-security-review` | Auditoría: broken access control, inyección, XSS, CSRF, mass assignment, dinero |
| `authz-rbac-check` | Verificar autorización server-side por rol (nunca solo en UI) |
| `dependency-audit` | Validar paquetes Composer/npm: hallucinations, CVEs, mantenimiento |
| `skill-mcp-hygiene` | Vetar skills/plugins/MCP antes de instalarlos (prompt injection) |
| `config-env-guard` | Proteger `.env`, config y credenciales fuera del repo |
| `laravel-best-practices` | Estructura idiomática Laravel: Services, Form Requests, Policies, PSR-12 |
| `performance-audit` | N+1, eager loading, paginación, índices, `#[Computed]` |
| `safe-refactoring` | Refactor sin romper comportamiento (tests antes/después, lonchas) |
| `code-review-gate` | Puerta de revisión pre-merge: tests, seguridad, diff ≤400 líneas |

**Aplicación obligatoria en:** implementación de features críticas (dinero, auth, inventario), revisión de PRs y antes de merge. En OpenCode: herramienta `skill`. En Antigravity: `@<skill-name>`.

## Contacto entre Agentes
- Usar `coordination.md` para communicate
- No asumir qué hizo el otro — verificar
- Si hay conflicto, priorizar el último cambio registrado
