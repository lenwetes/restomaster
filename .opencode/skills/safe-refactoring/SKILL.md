---
name: safe-refactoring
description: Use when improving, renaming, extracting, or restructuring existing code without changing visible behavior — to refactor with the minimize-risk loop (run tests, change in slices, verify again) and avoid breaking working features in the POS/cocina/caja.
---

# Safe Refactoring

## Overview

La refactorización genera más bugs que los cambios de features cuando se hace de golpe. El estándar del proyecto (`.ai/rules/code.md`): **TDD de verificación** — cada refactor corre primero los tests y vuelve a correrlos al final. Si no hay cobertura, crearla para el área antes de tocar nada.

## Principio

**Comportamiento idéntico, estructura mejor.** No mezclar refactor con features: si en el camino aparece un fix, anotarlo y hacerlo aparte.

## Loop seguro (por unidad pequeña)

1. **Baseline**: `composer test` / `php artisan test` — debe pasar
2. **Cobertura del área objetivo**: si no hay tests para el método a tocar, crear test del comportamiento actual (test rojo → CODE hace lo mismo → verde) usando acceptación funcional si es Livewire (spec/laravel pest o phpunit)
3. **Refactor en lonchas**: extraer/mover UN paso a la vez (p. ej. sacar cálculo a Service, sin cambiar inputs ni outputs)
4. **Verificar**: correr tests + `pint` (estilo) y `phpstan` si configurado
5. **Repetir** con la siguiente loncha
6. Al final: diff total limitado (una responsabilidad por PR)

## Reglas del refactor

- Cada cambio que toque lógica de negocio (totales, estados, pagos, stock) requiere test de la transición antes y después
- No cambiar comportamiento, naming externo (rutas, nombres de métodos públicos de API, blade partials) y los `env()` en un mismo paso
- Renombrar con hallazgo de usos: `rg` de TODAS las referencias (Blade `@livewire`, `wire:`, Livewire components, policies, routes)
- Migraciones: añadir columnas con `->nullable()`/`->default()` si renombre; nunca borrar sin rollback pensado
- Livewire: renombrar property púbica rompe `wire:model` → cambiar Vista y clase en el mismo commit

## Fraudes de "refactor" que no lo son

| Pasa | NO |
|------|-----|
| Extraer método conservando firma | Cambiar la firma + extraer en el mismo paso |
| Mover código a Service con misa I/O | Mover y además reintroducir cálculo |
| Renombrar con `rg` completo | Renombrar y quedarse con referencias `{{ $nombreViejo }}` |
| Optimizar query manteniendo resultado | Optimizar query y esperar el mismo resultado sin test |

## Verificación final obligatoria

```bash
composer test   # (o php artisan test)
composer run pint -- --test   # si está configurado
php artisan migrate:fresh --seed   # solo en dev, valida esquema
```

## Salida requerida

Documentar: qué se extrajo/movió, qué tests se escribieron antes del refactor, resultado de `test` antes/después. Si un refactor no tiene verificación de que el comportamiento se conserva → no considerar terminado.