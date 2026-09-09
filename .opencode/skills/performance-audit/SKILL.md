---
name: performance-audit
description: Use when query logic appears in controllers, Livewire components, or reports — or after implementing list screens and reports — to find and fix N+1 queries, missing eager loading, repeated queries, missing indexes, and expensive queries before they hurt the POS/cocina/caja in production.
---

# Performance Audit

## Overview

El código generado por agentes IA suele consultar la DB de forma ineficiente: N+1, `::all()` sin paginar, queries repetidas en loops, y `where()` sin índices. En un POS con turnos reales, la latencia se nota. Auditar tras cada pantalla/reactiva.

## Detectores de problemas clásicos

### 1. N+1
```php
// MAL — 1+n queries
foreach ($pedidos as $p) { echo $p->mesa->nombre; }

// BIEN
Pedido::with('mesa', 'items.plato')->get();
```
Detectar: relaciones usadas en Blade/Livewire que no están en `with()`.

### 2. `::all()` o listas sin paginar
- Pantallas de productos, pedidos, clientes NO cargan todo
- `->paginate(20)` o `->simplePaginate()` en listas

### 3. Queries en loops
- Violación de N+1 disfrazada: cálculo dentro de `@foreach` (total, subtotal, margen)
- Precalcular/sumar en la query (`->withSum`, `->withCount`)

### 4. Filtros sin índice
- FK sin índice → joins lentos
- `where('estado', ...)` frecuente en tabla grande → índice parcial si aplica

### 5. `livewire` re-computación
- Llamar query en el render (property) que se re-ejecuta en cada tecla escrita
- Usar `#[Computed]` para queries ligeras; cachear listas que no cambian por petición

## Comandos de diagnóstico

```bash
# Ver las queries reales de una petición (log)
php artisan tinker --execute="DB::enableQueryLog(); ...; dump(DB::getQueryLog());"

# (Mejor) Habilitar lazy loading violation detect: NUNCA en producción, sí en dev
# config/database.php → 'strict' en true; en dev:'options' => ['lazy' => true]
```

## Checks de velocidad para pantallas core del proyecto

| Pantalla | Query esperado |
|----------|----------------|
| POS items | Productos activos por sucursal, cacheados corto plazo |
| Mesas | Mesa + pedido activo, `with('pedidoActivo')` |
| Cocina | Pedidos con estado en proceso, `with('items.plato')` con índice en `estado` |
| Arqueo caja | Suma agregada `->whereDate(...)->sum(...)` (índice fecha) |
| Reportes | Aggreggates en SQL, no materializar filas en PHP |

## Checklist final

- [ ] Sin N+1 (todas las relaciones usadas cargadas `with()`)
- [ ] Listas paginadas
- [ ] `withCount`/`withSum` en vez de loops
- [ ] Índices en FKs, `estado`, `fecha`
- [ ] No queries dentro de `@foreach`
- [ ] Livewire: `#[Computed]` donde se reutiliza la misma query
- [ ] Básicos: `cached` config en producción, `php artisan optimize`

## Red Flags — STOP

- "Solo son 100 registros" → mañana serán 10,000
- "La query corre rápido local" → local ≠ producción con datos reales
- "El agente lo generó en un solo archivo" → verificar queries de todos los métodos, no solo el primero
- "Ya le puse with() a una" → verificar TODAS las páginas del flujo (listado + detalle + export)

## Salida requerida

Reporte: `archivo → método → problema (N+1 | sin paginar | loop query | falta índice) → queries por request antes/después si medible`. N+1 o query en loop sobre tablas grandes = bloqueante para merge.