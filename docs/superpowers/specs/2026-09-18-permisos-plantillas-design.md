# Spec: Permisos por usuario + plantillas por rol (3 estados, admin blindado)

Fecha: 2026-09-18 · Estado: diseño aprobado en chat (modelo híbrido 2026-09-18), pendiente plan (writing-plans).

## 1. Objetivo

El administrador otorga o quita permisos a cada usuario que crea: aplica una **plantilla pre-diseñada por rol** (rápido) o marca **checks individuales de 3 estados** (fino) y guarda. El admin **no puede editar sus propios permisos** (blindado por construcción).

## 2. Decisiones aprobadas

- Semántica de 3 estados por permiso: **heredar** (manda el rol, default) / **otorgar** (extra) / **quitar** (resta quirúrgica).
- Admin blindado: bypass de superusuario por slug `admin` siempre + UI prohíbe auto-edición.
- Plantillas pre-diseñadas por rol + edición check-a-check.
- Enfoque: catálogo en código + pivot con `tipo` + `Gate::before`, sin dependencias nuevas, policies intactas.
- Migración sin cambio de conducta el día 1 (usuarios sin filas → 100% legacy).

## 3. Catálogo de permisos

Fuente: `config/permisos.php`, agrupado por módulo para la UI. Claves `modulo.ability`:

- POS/pedidos: `pedidos.ver/crear/actualizar/eliminar/cobrar/enviar_cocina/aplicar_descuento/canjear_puntos/gestionar_delivery/liquidar_repartidor/cocinar` (derivado de `PedidoPolicy`: 11 abilities).
- Mesas: `mesas.ver/crear/actualizar/eliminar/cambiar_estado` (`MesaPolicy`, 6).
- Caja: `caja.ver/crear/actualizar/eliminar` (`CajaPolicy`, 5).
- Turnos: `turnos.ver/abrir/cerrar/arqueo/guardar_movimiento` (`TurnoCajaPolicy`, 6).
- Clientes: `clientes.ver/crear/actualizar/eliminar/ajustar_puntos` (`ClientePolicy`, 6).
- Reservas: `reservas.ver/crear/actualizar/eliminar/confirmar/cancelar` (`ReservaPolicy`, 7).
- Inventario: `insumos.ver/crear/actualizar/eliminar/registrar_compra/registrar_merma/ajuste_fisico` (`InsumoPolicy`, 8).
- CxP: `cxp.ver/crear/actualizar/eliminar/registrar_pago` (`CuentaPorPagarPolicy`, 6).
- Acceso a módulos (pseudo-permisos para menús): `modulos.pos/mesas/caja/cocina/clientes/delivery/reservas/inventario/cxp/reportes/trabajadores/configuracion`.

Total aproximado: 50 abilities + 12 accesos. El plan detalla la matriz rol × permiso.

## 4. Datos

- Migración: `permission_user(user_id FK cascadeOnDelete, permission string, tipo enum grant/deny, timestamps, unique[user_id, permission])`. Sin filas = legacy total; sin flag adicional.
- `config/plantillas_permisos.php`: por slug de rol (`admin, gerente, cajero, mesero, cocina, barra, delivery, repartidor`), cada una = lista de keys = matriz efectiva actual de las policies (el plan la tabula para verificación). La plantilla es punto de partida: al aplicarla genera filas `grant` explícitas solo para lo marcado como otorgar/quitar; lo demás queda en heredar (sin fila).
- Sin backfill: la tabla nace vacía → **nada cambia el día 1**.

## 5. Enforcement

- `User::permisoExplicito(string $key): ?bool` (true=grant, false=deny, null=sin fila; memoizado por request).
- `AuthServiceProvider::boot` → `Gate::before`: slug `admin` → `true` siempre (bypass, antes de todo). Otro usuario: fila deny → `false`; fila grant → `true`; sin fila o ability no mapeada → `null` (legacy: policies e `isX()` intactos).
- Mapa ability+modelo → key de catálogo (el plan lo tabula; test de completitud por reflexión sobre las 8 policies: ability sin mapear = solo no configurable, nunca rompe runtime).
- `isX()` inline y menús de los flujos POS/caja migran a `tienePermiso()` (OR sobre el check explícito + legacy) en este plan; resto de la app, progresivo (fuera de alcance, registrado como deuda explícita).

## 6. UI admin (módulo Trabajadores, crear/editar)

- Select "Plantilla" (opciones = roles; precarga los checks del rol) + grupos colapsables de checks de 3 estados (Otorgar / Heredar / Quitar) con contador.
- Preview del diff efectivo antes de guardar + confirmación explícita al quitar abilities críticas (`pedidos.cobrar`, `turnos.abrir` como mínimo).
- Guardar: validación (`permission` existe en catálogo, `tipo` válido), `sync()` del pivot, auditoría `usuarios.permisos_actualizados` con diff antes/después.
- Crear usuario: aplica automáticamente la plantilla de su rol.
- Solo `admin` ve y opera esta sección (check en el método Livewire, no solo UI); editar el propio usuario admin está prohibido (403 + mensaje).

## 7. Tests (TDD, RED primero)

- Snapshot: cada plantilla == conducta legacy del rol (matriz del plan).
- `Gate::before`: deny explícito niega aunque el rol lo permita; grant otorga aunque el rol no; sin filas → legacy intacto; admin con set vacío entra igual.
- Completitud por reflexión: toda ability pública de las 8 policies está en el mapa o en lista explícita de excluidas.
- UI: aplicar plantilla + tweak + guardar persiste pivot; mesero/cajero guardando → 403; admin editándose a sí mismo → 403.
- Suite completa (385 tests) verde sin modificar tests existentes.

## 8. Fuera de alcance

- Edición de plantillas por UI (son config versionada; cambio futuro).
- Permisos negativos (quitar) — la semántica reemplazo ya lo cubre no marcando.
- Rewiring total de `isX()` inline fuera de POS/caja.
- Spatie u otras dependencias.

## 9. Riesgos (residuales, vigilados)

- Mapeo incompleto: solo limita lo configurable; runtime cae a legacy + `Log::warning`. Sin denegaciones silenciosas.
- Admin bloqueado: eliminado por construcción (bypass + auto-edición prohibida + guard de auto-degradación al guardar).
- Bloquear a otros por error: preview + confirmación en críticas + auditoría con diff + `permisos:verificar`. Decisión humana residual.
- Escalación: check admin en método + validación contra catálogo + tests 403.
- Deriva: test de completitud + checklist de nueva ability (catálogo + plantilla + mapa).
- Sin caché persistente del pivot (memoización por request); `Cache::forget` no aplica.
