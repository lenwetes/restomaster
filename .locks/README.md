# Lock Files — Sistema de Exclusión Mutua entre Agentes

## ¿Qué son?
Archivos que marcan qué módulo está siendo modificado por quién, para evitar que dos agentes trabajen sobre el mismo código simultáneamente.

## Cómo funciona
1. **Antes de trabajar** en un módulo, crear un archivo en esta carpeta:
   - Nombre: `<modulo>.lock`
   - Ejemplo: `pedidos.lock`

2. **Al terminar**, eliminar el archivo `.lock`.

## Formato del lock file
```json
{
  "modulo": "pedidos",
  "agente": "opencode",
  "desde": "2026-09-09T12:00:00",
  "tarea": "Crear modelos y migraciones de pedidos",
  "estado": "en_progreso"
}
```

## Reglas
- **Nunca editar** un `.lock` que no sea tuyo
- **Verificar** siempre si existe un `.lock` antes de empezar
- Si existe un `.lock` para el módulo que necesitas, **esperar** o coordinar en `coordination.md`
- Los archivos `.lock` se pueden borrar solo cuando la tarea terminó

## Módulos compartidos sensibles
- Migraciones
- Modelos
- Seeders
- Rutas
- Config

## Checklist
```powershell
# Ver bloqueos activos
Get-ChildItem "D:\Proyectos\sushixpress\.locks"

# Crear lock
Set-Content "D:\Proyectos\sushixpress\.locks\pedidos.lock" '{"modulo":"pedidos","agente":"opencode"}'

# Eliminar lock al terminar
Remove-Item "D:\Proyectos\sushixpress\.locks\pedidos.lock"
```