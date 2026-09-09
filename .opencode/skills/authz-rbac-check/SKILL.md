---
name: authz-rbac-check
description: Use when implementing or reviewing any route, Livewire component method, or action that mutates data or exposes sensitive info — to verify authorization and role-based access control is enforced server-side, never only in the UI.
---

# Authorization / RBAC Check

## Overview

En comparación con el backend de Laravel, el código generado por agentes IA omite la autorización o la implementa solo ocultando botones. Validar que cada acción esté protegida en el servidor.

## Roles del proyecto Sushixpress

- `admin` — todo
- `gerente` — supervisión, reportes, ajustes de inventario (según reglas del módulo)
- `cajero` — POS, caja, pagos, arqueos
- `cocinero` — pedidos, cocina, estado
- `mesero` — mesas, pedidos, alta de pedidos

## Regla de oro

**La UI no es seguridad.** El botón oculto no es un control de acceso. Toda mutación se verifica con policy o `->can()`.

## Checklist por capa

### 1. Rutas web (`routes/web.php`)
- [ ] Grupo con middleware `auth`
- [ ] Rutas de módulo sensible (pos, caja, reportes, inventario, reservas) con middleware `can:<ability>` o `role:<rol>`
- [ ] Un rol inferior NO accede con URL directa aunque el menú lo oculte

### 2. Livewire components (app/Livewire)
- [ ] Métodos que mutan (`guardar`, `pagar`, `anular`, `abrirCaja`, `ajustarStock`, `completar`) llaman `$this->authorize('...', $model)` o `abort_unless(auth()->user()->can(...))`
- [ ] Checks de permiso en el **método**, no solo en `mount()`

### 3. Policies (app/Policies)
- [ ] Existe policy por modelo sensible: `Pedido`, `Mesa`, `AperturaCaja`, `MovimientoInventario`, `Cliente`
- [ ] Abilities nombradas por acción real: `viewAny`, `view`, `create`, `update`, `delete`, `anular`, `aplicarDescuento`, `ajustarStock`
- [ ] Registro en `AuthServiceProvider` o auto-discovery (Laravel 11+ lo hace solo si sigue convención)

### 4. Datos sensibles leídos sin mutar
- [ ] Reportes de caja/ventas solo para admin/gerente/cajero
- [ ] Token o credenciales de otro — delegado al backend, jamás en front
- [ ] Exportaciones filtradas por sucursal si el usuario no es admin global

## Verificación rápida

```bash
# Rutas sin middleware de auth — sospechoso
php artisan route:list | Select-String "   " -NotMatch | Measure-Object

# Métodos Livewire que mutan sin authorize visible
rg -n "function (guardar|pagar|anular|actualizar|completar|eliminar|abrir|cerrar|cancelar)" app/Livewire

# Policies existentes
Get-ChildItem app/Policies
```

## Anti-patrones

| Anti-pattern | Fix |
|--------------|-----|
| `@if(auth()->user()->role == 'admin')` en Blade como único control | Sumar policy/middleware en servidor |
| `authorize` solo en `mount()` | Mover al método que muta |
| `authorize` en todo menos la acción destructiva | Cubrir TODAS las mutaciones |
| Comparar roles con strings mágicos en toda la app | `$user->can('aplicar-descuento')` centralizado |
| `Auth::user()` nullable sin `abort_unless` | `abort_unless($user->can(...), 403)` |

## Red Flags — STOP

- "Nadie navegaría a esa URL" → **la seguridad no asume navegación**
- "Es una vista de solo lectura" → si muestra datos sensibles requiere autorización
- "El agente ya puso el botón" → el botón no es la defensa
- "Solo es admin" → verificar en servidor, no en la condición del menú

## Salida requerida

Listar por ruta/método: `nombre → permiso exigido → rol/es permitido → ¿cubierto?`. Cualquier acción sensible sin autorización server-side se marca como bloqueante (Critical).