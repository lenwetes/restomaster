# Reporte de Remediación — Sushixpress (para Antigravity)

- **Autor:** OpenCode · **Fecha:** 2026-09-10
- **Fuente:** Auditoría integral 2ª pasada (solo lectura) — `docs/auditoria/auditoria-2026-09-10.md`
- **Objetivo:** Que Antigravity corrija los hallazgos pendientes en orden de prioridad, sin romper las 213 pruebas actuales.
- **Suite actual:** 213 tests / 724 assertions (verde). **Regla:** correr suite en secuencia y **sin `php artisan serve` activo** (colisión de compilación Volt en Windows).

## Reglas de coordinación

1. **Crear lock** en `.locks/` si tocas módulos compartidos (`pos`, `caja`, `mensajes`, `navigation`). El lock de `/pos` está **liberado** (solo `README.md` presente).
2. Actualizar `coordination.md` al iniciar y terminar.
3. Seguir `.ai/rules/code.md` y PSR-12 (Pint `--test` debe salir 0).
4. Hacer **TDD**: cada fix con su test nuevo/actualizado (los tests de regresión ya existen para C1/C2/H5).
5. Aplicar skills: `authz-rbac-check`, `laravel-security-review`, `performance-audit`.
6. **No corregir** C1/C5/H5/M2/H9/H10/L1/L3 — ya están FIXED y verificados.

---

## P0 — Bloqueantes de producción (corrección obligatoria)

### P0-01 — RBAC server-side: `authorize()`/Policies en TODA mutación de dinero y estado (CRITICAL C3)

**Problema:** No existe `app/Policies`. `grep -r "authorize(Gate::|Policies" app/` → **0 resultados**. Las mutaciones volátiles solo dependen del middleware de ruta y de ocultar botones en la UI: cualquier usuario con rol lo degradado puede invocar el método Volt por red.

**Ubicaciones verificadas (métodos Volt, ws seguro):**
| Archivo | Método | Línea |
|---|---|---|
| `resources/views/livewire/pos/terminal.blade.php` | `cobrar`, `enviarCocina`, `aplicarDescuento`, canje puntos | ~215, ~279 |
| `resources/views/livewire/caja/control.blade.php` | `guardarMovimiento`, `cerrarTurno`, `arqueo`, `guardarNuevaCaja` | ~101, ~136, ~177, **65** |
| `resources/views/livewire/clientes/index.blade.php` | `ajustarPuntos` (suma/resta), `guardarCliente` | ~107, ~169 |
| `resources/views/livewire/inventario/index.blade.php` | `registrarCompra`, `registrarMerma`, `ajusteFisico` | ~135 |
| `resources/views/livewire/cxp/index.blade.php` | `guardarCuenta`, `registrarPago` | ~60 |
| `resources/views/livewire/reservas/index.blade.php` | `guardarReserva`, `confirmar`, `cancelar` | (bloque mutaciones) |
| `resources/views/livewire/mesas/index.blade.php` | `guardarMesa`, `eliminarMesa`, `cambiarEstado` | **58**, **87**, **103** |
| `resources/views/livewire/configuracion/index.blade.php` | `guardarConexionDb`, `restaurarBackup` | **192**, **218** |

**Esperado:**
- Crear `app/Policies` (o `Gate::define()`) por recurso: `Pedido`, `Caja/TurnoCaja`, `Cliente`, `Insumo`, `Cxp`, `Reserva`, `Mesa`. Ruta global `Gate::before()` para `admin`.
- `guardarNuevaCaja` (caja:65): solo `admin`/`gerente`. Hoy lo invoca un `cajero` con la ruta `/caja` (`role:cajero,gerente`).
- `guardarMesa/eliminarMesa/cambiarEstado` (mesas:58/87/103): solo `gerente`/`admin`. Hoy la ruta permite `mesero,cajero,gerente` (web.php:41).
- `cambiarEstado` (mesas:103): además **validar `$nuevoEstado` contra `MesaEstado`** (hoy acepta cualquier string).
- `guardarConexionDb` (config:192): escribe credenciales DB en `configuraciones` en texto plano; requieren **cifrado** (o campo `password`) y debe ser `admin` + cuenta propia.

**Criterio de aceptación:** nuevos tests `Policies*` (usuario sin permiso → 403, no muta); `grep authorize` en app/ > 0.

---

### P0-02 — FKs `cascadeOnDelete` sobre histórico (CRITICAL C4 / NEW N1)

**Problema:** `DELETE` físico de una sucursal/caja/turno/usuario/cliente/impresora arrasa el historial financiero/kardex/impresión. Los soft deletes ya cubren catálogo (productos/insumos/clientes), pero **traición de histórico no**.

**Cascades verificados (18):**
| Migración | FK | Línea |
|---|---|---|
| `2026_09_09_180030_create_items_pedido_table` | `items_pedido.producto_id` | 17 |
| `2026_09_09_180010_create_productos_table` | `productos.categoria_id` | 16 |
| `2026_09_09_174736_create_mesas_table` | `mesas.sucursal_id` | 16 |
| `2026_09_09_190000_create_cajas_table` | `cajas.sucursal_id` | 16 |
| `2026_09_09_190010_create_turnos_caja_table` | `turnos_caja.caja_id`, `turnos_caja.user_id` | 16-17 |
| `2026_09_09_190020_create_movimientos_caja_table` | `movimientos_caja.turno_caja_id`, `.user_id` | 16-17 |
| `2026_09_09_191020_create_movimientos_inventario_table` | `movimientos_inventario.insumo_id` | 16 |
| `2026_09_09_191010_create_recetas_table` | `recetas.producto_id`, `.insumo_id` | 16-17 |
| `2026_09_09_193510_create_pagos_cxps_table` | `pagos_cxps.cuenta_por_pagar_id` | 13 |
| `2026_09_09_194010_create_direcciones_cliente_table` | `direcciones_cliente.cliente_id` | 16 |
| `2026_09_09_194020_create_movimientos_puntos_table` | `movimientos_puntos.cliente_id` | 16 |
| `2026_09_09_200020_create_reserva_mesa_table` | `reserva_mesa.reserva_id`, `.mesa_id` | 12-13 |
| `2026_09_09_210010_create_trabajos_impresion_table` | `trabajos_impresion.impresora_id` (histórico fiscal) | **19** |

**Esperado:**
- Migración nueva (estilo `harden`) que cambie a `restrictOnDelete()` (o `nullOnDelete` donde el modelo no exista) las FK de **histórico**: `items_pedido.producto_id`, `cajas.sucursal_id`, `turnos_caja.*`, `movimientos_caja.*`, `movimientos_inventario.insumo_id`, `pagos_cxps.cuenta_por_pagar_id`, `movimientos_puntos.cliente_id`, `reserva_mesa.*`, `trabajos_impresion.impresora_id`.
- `restricciones de catálogo` (productos.categoria, recetas.*, direcciones_cliente, mesas.sucursal) pueden quedarse en null/soft según semántica; **decisión por FK documentada en la migración**.
- **No tocar** migraciones anteriores (`¡no editar los archivos originales!`): usar migración nueva con `dropForeign` + `foreign` + `restrict` (SQLite = rebuild, verificar con suite `:memory:`).

**Criterio de aceptación:** test que intente `DELETE` una caja/turno/usuario/impresora con histórico → `QueryException` constraint violada, historial intacto.

---

### P0-03 — Credenciales por defecto (HIGH H1 / H2)

- `database/seeders/AdminUserSeeder.php:28` → `Hash::make('123456')` (7 usuarios demo). Cambiar a contraseña generada aleatoria + aviso en consola (o `.env`, NUNCA hardcodeada).
- `app/Services/TrabajadorService.php:44` → `'password' => Hash::make($datos['password'] ?? 'secret')`. **Remover el fallback**: validar `password` requerida o usar `resetearPassword()` (ya existe en `:150`, genera `Str::password(10)` temporal auditado).

**Criterio:** tests no deben depender de `123456`/`'secret'`; seeder imprime clave temporal.

---

### P0-04 — Allowlists de caja y puntos (HIGH H4 / H7)

- **H4** `caja/control.blade.php:689` + `CajaService.php:133`: `tipoMovimiento`/`autorizadoPor` libres. Esperado: `in:ingreso,egreso,retiro` en `tipoMovimiento`; `autorizadoPor` requerido para `retiro/egreso` y **no igual al usuario autenticado** (H4 permitía self-attestation).
- **H7** `ClienteService.php:34` (fuera de `validate()`), `clientes/index.blade.php:175` (`$this->puntosAjuste` sin `in:suma,resta`, sin tope): `puntos_fidelidad` int ≥ 0 con validación; `tipoAjuste` allowlist `suma,resta`; `ajustarPuntos` solo `cajero,gerente,admin`.

**Criterio:** tests de allowlist (cambio rejected), descuento/acumulación consistente.

---

### P0-05 — Throttle/honeypot en rutas públicas (HIGH H6)

`routes/web.php:34,37,38` (`mesa/{numero}/menu`, `delivery/pedir`, `carta`) sin throttle ni honeypot; `pedido-publico` puede crear pedidos ilimitados por bot.

**Esperado:** `->middleware('throttle:10,1')` en creación de pedido (ya existe patrón en `reservas/crear` con honeypot `empresa`); reconsiderar un honeypot en el form público.

**Criterio:** 11ª petición en 1 min → 429.

---

### P0-06 — `descuento_puntos` sin acotar (C2 PARCIAL)

`PedidoService.php:42` solo aplica `max(0, ...)`. Falta: `$descuentoPuntos = min($descuentoPuntos, $subtotal)` y verificar disponibilidad de puntos del cliente (canje via `FidelizacionService`). El total ya se unifica en `:67-68`.

**Criterio:** test: descuento > subtotal → descuento = subtotal; puntos > saldo → rechazado.

---

## P1 — Rendimiento (HIGH F1–F5, NUEVOS)

| ID | Hallazgo | Ubicación | Solución esperada |
|---|---|---|---|
| P1-01 | Reportes agregan en PHP | `ReporteService.php:21-23`, `reportes/index.blade.php:24-41` | `sum()` en SQL / `withSum`, no traer colecciones completas |
| P1-02 | `estadoResultados` incondicional | `reportes/index.blade.php:40` | Calcular solo en pestaña activa |
| P1-03 | Delivery +2N queries | `delivery/index.blade.php:566-575` | `with('repartidor')` / `withCount` en el query |
| P1-04 | KDS por ítem | `cocina/kds.blade.php:44-64` | Batch por estación, índices ya existen (`estado_cocina,area_cocina`) |
| P1-05 | Delivery sin paginar | `delivery/index.blade.php:184` | `->paginate(30)` + paginador |
| P1-06 | `wire:poll.4s` recalcula todo `with()` | `mesa/menu-publico.blade.php:214` | Usar acción específica `wire:poll.4s="refrescarEstado"` (1 query) o Livewire entangle con `#Computed` |
| P1-07 | Categorías sin filtro activo | `mesa/menu-publico.blade.php:166` | `where('activa', true)` |
| P1-08 | POS carga todos los clientes | `pos/terminal.blade.php:392` | Búsqueda remota (debounce) con `#[Computed]` |
| P1-09 | Índices faltantes | Reportes:187,299 / 190010 / 190020 / 193000 | `(estado,pagado_en)`, `(caja_id,estado)`, `(turno_caja_id,tipo)`, `auditorias.created_at`, `(estado,estado_delivery)` |

---

## P2 — Calidad (MEDIUM/LOW)

- **P2-01 Enums activos:** `app/Enums/{PedidoEstado,TurnoCajaEstado}.php` sin usos; `Pedido.php:115` usa `'en_proceso'` fuera del enum; falta estado `CANCELADO`. Unificar dominio de estados + validación.
- **P2-02 Dinero en `float`:** ~50 casts `(float)` en dinero (CajaService:124-283, PedidoService:40-48,162, TurnoCaja:88-91, FidelizacionService:31-109). Migrar progresivo a enteros/céntimos o `bcmath` (no romper suite).
- **P2-03 Paginación listas:** clientes (`clientes/index.blade.php:230`), cxp pendientes (`cxp:33-51`), pedidos turno (`caja:223`).
- **P2-04 Uniques:** `productos.slug`, `mesas(numero,sucursal_id)`, `clientes(telefono/email)` (migraciones 180010:18, 174736, 194000:17-18).
- **P2-05 Stack Tailwind único:** `package.json:11` v3 (activo) + `:16` v4 sin registrar → remover v4; fijar `bacon/bacon-qr-code` ^3.1 (`composer.json:10`, hoy `"*"`); eliminar `@laravel/multiplex` (`package.json:20`, sin uso).
- **P2-06 Volt monstruosos:** dividir `pos` (1592), `configuracion` (1167), `inventario` (990), `caja` (905), `carta-publica`/`menu-publico`.
- **P2-07 `@` suppression:** `ImpresionService.php:190,327`, `Impresora.php:164` → manejo de errores real.
- **P2-08 `down()` con `dropForeign`** en varias migraciones (194030:36-38) — limpiar `dropForeign` para SQLite.
- **P2-09 Backups:** `configuracion/index.blade.php:218` `restaurarBackup` usa `DB::unprepared` solo validando `file`. **P2-P0** → validar extensión `.sql` server-side (MIME+mime) aunque sea admin.
- **P2-10 Limpieza:** `Insumo::casts` propiedad (L9), `Reserva.hora_llegada` sin cast (L8), comentarios en inglés fuera de `.ai/rules/code.md` (L10), `findOrFail` sin filtro `activo` en `pedido-publico:114`/`menu-publico:50` (NEW).

---

## Orden de ejecución sugerido

```
[1] P0-02 cascades (migración + test)        → cerramos el riesgo de pérdida de histórico
[2] P0-01 Policies/authorize + validate      → cierre del broken access control (más amplio)
[3] P0-03 credenciales  → [4] P0-04 allowlists  → [5] P0-05 throttle  → [6] P0-06 descuento
[7] P1 rendimiento (F3, F4, F5 fáciles; F1/F2 reportes)
[8] P2 calidad (empezar por P2-09 backups, P2-01 enums, P2-05 deps)
```

**Cada lote:** suite completa verde antes de liberar lock + `coordination.md` actualizado.

---

## No tocar (ya FIXED con tests)

C1 precio desde DB · C5/H5 `lockForUpdate`+`abort_if('pagado')` · M2/C2 fórmula total · H9/H10 SoftDeletes+FK insumo · L1 test `activo` · L3 `.gitignore`.