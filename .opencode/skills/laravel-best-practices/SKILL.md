---
name: laravel-best-practices
description: Use when creating or modifying any Laravel feature, controller, model, Livewire component, migration, or file in this project — to follow the idomatic PHP/Laravel structure, correct directory conventions, PSR-12 style, and the project's coding rules (.ai/rules/code.md).
---

# Laravel Best Practices

## Overview

El código óptimo para este proyecto: estructura idiomática Laravel (video 11+), `app/` bien organizado, controladores delgados, validación con Form Requests, lógica en Services y autorización con Policies. Reglas técnicas del proyecto en `.ai/rules/code.md` y acuerdos en `AGENTS.md` (`roles como string`, TDD de verificación).

## Estructura de `app/` en este proyecto

```
app/
├── Models/          → Eloquent (+ casts, $fillable, relaciones, scopes)
├── Http/
│   ├── Controllers/ → flujos HTTP, delgados
│   └── Requests/    → validación centralizada
├── Livewire/        → componentes (POS, cocina, mesas, caja…)
├── Services/        → lógica de negocio reutilizable (caja, inventario, pedidos)
├── Policies/        → autorización por modelo
└── Enums/           → estados como string (ver regla project)
```

## Reglas de estilo y patrón

### 1. Controlador
- DELGADO: recibir request → validar → llamar servicio/modelo → responder
- NO lógica de negocio en el controlador (calcular totales, estados, descuentos → Services)
- Acciones REST: `index/create/store/show/edit/update/destroy` + verbos de negocio (`pagar`, `anular` → ruta custom veya método propio)

### 2. Validación
- Form Request por operación (`StorePedidoRequest`, `UpdateMesaRequest`)
- Usar `$request->validated()` — no extraer datos crudos
- Rules explícitas (tipos, rangos, `exists:mesas,id`)

### 3. Eloquent
- `$fillable` explícito (NUNCA `$guarded = []` salvo justificación)
- Casts tipados (`money`, `decimal`, `enum`)
- Relaciones nombradas en naturaleza plural (relación `pedidos`, `items`)
- Scopes para filtros (`scopeActivos`, `scopePorSucursal`)
- NUNCA into N+1 (ver performance-audit)

### 4. Services
- Lógica de negocio con side effects en Services, no en controllers/models
- Métodos públicos pequeños con nombre de acción (`recalcularTotal()`, `aplicarDescuento()`, `arqueoCaja()`)
- Recibir parámetros tipados; devolver resultado/estado claro

### 5. Livewire
- Componentes centrados en el dominio (uno por pantalla del módulo)
- `wire:model` para entrada; `wire:click` para acciones
- Props con `#[Modelable]`, `#[Locked]`, `#[\Livewire\Attributes\Url]` según convenga
- `#[Computed]` para propiedades derivadas (evita re-ejecutar queries)
- autorización dentro de los métodos (ver authz-rbac-check)

### 6. Migraciones y esquema
- Naming: `sufijo` tabla plural, timestamps en todas
- Foreigned keys con `foreignId(...)->constrained()->cascadeOnDelete()`
- Índices para FKs y campos de búsqueda frecuente
- Estados de negocio como colonna `string` (ver `.ai/rules/code.md`) o `enum` nativo si es estable

### 7. Style / formato
- PSR-12 (4 espacios, ranuras `{` en nueva línea, tipos declarados)
- PHP 8.3: tipos estrictos, readonly, nullsafe cuando aplique
- NO comentarios salvo que aporten contexto (regla del proyecto: sin comentarios redundantes)

## Verificación

```bash
# Lint del proyecto (si existe configurado)
composer run pint            # o: php artisan pint --test

# PHPStan si está instalado
composer run phpstan

# Migraciones y seed limpio
php artisan migrate:fresh --seed
```

## Anti-patrones

| Anti-pattern | Fix |
|--------------|-----|
| Controlador de 200 líneas | Mover a Service |
| Validación inline en el método | Form Request |
| `Model::all()` sin filtro | scope + paginación |
| `update()` sin validated() | `$request->validated()` |
| Repetir cálculo de total en 3 lugares | Service único |
| `DB::raw` de user input | Query Builder / parametrizar |
| Eager load a medias | `with()` completo (ver performance-audit) |

## Salida requerida

Al terminar una feature: confirmar estructura (`app/` coherente), Form Request presente, Service donde haya lógica, autorización cubierta y `pint`/`phpstan` en verde. No dejar TODO ni bloques comentados innecesarios.