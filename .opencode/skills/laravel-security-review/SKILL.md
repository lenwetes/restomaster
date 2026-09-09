---
name: laravel-security-review
description: Use when reviewing code, PRs, or diffs in a Laravel/Livewire app before merge or deploy — to find broken access control, SQL injection, XSS, CSRF, mass assignment, and other vulnerability classes that AI-generated code commonly introduces.
---

# Laravel Security Review

## Overview

El código generado por agentes IA falla rutinariamente en controles de autorización, validación de entrada y manejo de secretos. Esta revisión es obligatoria **después** de implementar cualquier feature, antes de mergear.

## When to Use

- Al terminar una feature (Form Request, controlador, Livewire component, relación)
- Antes de un merge/PR
- Al revisar código de otro agente
- Al tocar rutas que manejan dinero (POS, caja), datos de clientes o inventario

## Checklist de auditoría (por orden de gravedad)

### 1. Broken Access Control (la clase #1 en código vibecoded)
- [ ] Toda mutación tiene `authorize()` o policy (`$this->authorize('update', $pedido)`)
- [ ] Middleware de ruta: `auth` + `role/can` en rutas sensibles (POS, Caja, Inventario)
- [ ] Livewire: `authorize()` dentro de métodos que mutan estado, NO solo en `mount()`
- [ ] Las rutas web NO dependen solo de ocultar el botón en la UI
- [ ] Un rol `mesero` NO puede: ver reportes, anular pagos, abrir caja, ajustar stock
- [ ] `->pluck()`, exportaciones y reportes no filtran por sucursal sin permiso

### 2. SQL Injection / Query
- [ ] Sin concatenación de SQL crudo; usar Query Builder/Eloquent siempre
- [ ] `whereRaw`/`DB::raw` con datos del usuario → validados o parametrizados
- [ ] Ordenamientos dinámicos (`$request->get('sort')`) pasan por allowlist de columnas

### 3. XSS (Blade/Livewire)
- [ ] Datos del usuario renderizados con `{{ }}` (escapado), NUNCA `{!! !!}` salvo contenido confiable
- [ ] Livewire: no inyectar HTML de usuario sin `htmlspecialchars` o componente sanitizado
- [ ] Notas de pedido, direcciones, nombres de clientes → escapados por defecto

### 4. CSRF
- [ ] Toda ruta POST/PUT/PATCH/DELETE usa el middleware `web` (que incluye CSRF)
- [ ] Livewire maneja CSRF automáticamente, pero no deshabilitarlo
- [ ] No exponer rutas sin CSRF con `except`

### 5. Mass Assignment
- [ ] Modelos tienen `$fillable` explícito (no usar `$guarded = []` salvo justificado)
- [ ] En `update`, pasar solo campos permitidos (Form Request + `validated()`)
- [ ] Campo `total`, `descuento`, `estado`, `role_id` NUNCA llegable con mass assignment desde request

### 6. Money & Business Logic (crítico en este proyecto)
- [ ] Cálculos de total/descuento/cambio hechos en el SERVIDOR, no confiar en valores del cliente
- [ ] Descuentos: verificar permiso (`can('aplicar-descuento')`) y límite máximo (config)
- [ ] Arqueo de caja: cálculos idempotentes, sin mutar al re-ejecutar
- [ ] Estados de pedidos/mesas: transiciones validadas (no permitir `pagado → creado`)
- [ ] Comparaciones de dinero usar centavos/integer o decimal inseguro en DB (NUNCA float)

### 7. Validación de entrada
- [ ] Form Requests para toda entrada de más de 1 campo
- [ ] Reglas de validación incluyen tipos, rangos y longitudes
- [ ] UUIDs/IDs numéricos validados (`integer`, `exists`)
- [ ] Uploads con `mimes` + tamaño + no ejecutables

### 8. Sesiones / Auth
- [ ] Password con hash (`Hash::make` / bcrypt)
- [ ] Login rate-limited
- [ ] `remember me` no permite escalar privilegios

## Comandos de verificación rápidos

```bash
# Rutas registradas — revisar si falta middleware en las sensibles
php artisan route:list | Select-String "pos|caja|reportes|inventory"

# Modelos con guarded vacío (mass assignment abierto)
rg -n "guarded = \[\]" app/Models

# Algo de {!! !!} sin comprobar origen
rg -n "!!" resources/views

# Autorización ausente en mutaciones Livewire
rg -n "function (save|update|store|create|pag|anular|cancelar)" app/Livewire
```

## Red Flags — STOP

- "Solo es admin quien lo ve" — la UI no es seguridad; verificar en el servidor
- "Agrego la política después" — la autorización se escribe con la feature
- "El request ya viene validado del front" — nunca confiar en el cliente
- "Es un valor interno, no importa" — los campos money/estado sí importan
- "No lo detectó el agente" — esa es exactamente la brecha que buscamos cerrar

## Prioridad de hallazgos

| Severidad | Visión | Bloquea merge |
|-----------|--------|---------------|
| Critical | Bypass auth, inyección ejecutable, fuga de credenciales, lógica de dinero | Sí |
| High | XSS almacenado, mass assignment alcanzable, CSRF en mutación | Sí |
| Medium | Validación incompleta, falta rate limit | No (agendar) |

## Salida requerida

Reporte por hallazgo: `archivo:línea → clase (OWASP/CWE) → severidad → fix sugerido`. Todo Critical/High bloquea el merge o deploy.