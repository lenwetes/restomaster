# Reglas de Coordinación entre Agentes

> Estas reglas son vinculantes para TODOS los agentes que trabajen en este proyecto.

## 1. Lectura obligatoria
- Leer **AGENTS.md** y **coordination.md** antes de comenzar cualquier tarea.
- Si `coordination.md` muestra un lock activo sobre un módulo, NO tocar ese módulo.

## 2. Antes de trabajar en un módulo compartido
1. Crear lock file en `.locks/<modulo>.lock`
2. Registrar en `coordination.md` la tarea + módulos + fecha
3. Trabajar
4. Al terminar: eliminar lock file y actualizar `coordination.md`

## 3. Nunca editar archivos `.lock`
Los lock files son del otro agente. Si ves uno, espera o coordina por coordination.md.

## 4. Formato de lock file
```json
{
  "modulo": "inventario",
  "agente": "opencode",
  "desde": "2026-09-09T12:00:00",
  "tarea": "Crear migraciones de inventario",
  "estado": "en_progreso"
}
```

## 5. Modificación de archivos
- No modificar archivos de código **sin registrar primero** en coordination.md.
- Si hay conflicto, priorizar el último cambio registrado.
- Actualizar coordination.md al fin de cada tarea.

## 6. Tabla de módulos compartidos
| Módulo | Archivos típicos | Zona libre |
|--------|-----------------|------------|
| Migraciones | `database/migrations/*` | ⚠️ COMPARTIDO |
| Modelos | `app/Models/*` | ⚠️ COMPARTIDO |
| Seeders | `database/seeders/*` | ⚠️ COMPARTIDO |
| Rutas | `routes/*` | ⚠️ COMPARTIDO |
| Config | `config/*`, `.env` | ⚠️ COMPARTIDO |
| Docs | `docs/*` | ✅ Puede editar cualquiera |
| Assets | `resources/` | ⚠️ COMPARTIDO |
| Scripts | `scripts/*` | ✅ Escritos por Antigravity |