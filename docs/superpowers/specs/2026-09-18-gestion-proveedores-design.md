# Spec: Gestión de Proveedores (F1 CRUD+facturas, F2 comparador)

Fecha: 2026-09-18 · Estado: diseño aprobado en chat, pendiente revisión de spec y plan (writing-plans).

## 1. Objetivo

Módulo Proveedores: CRUD de fichas, vinculación con inventario, registro de facturas multi-insumo, historial de cuánto se le compra a cada proveedor, CxP automática a crédito y comparador de precios vs otros proveedores y vs referencia de mercado digitada por admin.

## 2. Decisiones aprobadas

- Factura = documento con cabecera + líneas (no movimientos sueltos).
- Precio de mercado = `insumos.precio_referencia_mercado` digitado por admin/gerente.
- Datos viejos (textos sueltos) intactos, sin vincular; el admin crea fichas y vincula.
- Factura a crédito crea CxP automáticamente vinculada (`compra_id`).
- Rollout en 2 fases, cada una usable sola.

## 3. Datos

- `proveedores`: nombre unique, nit nullable unique, telefono, email, direccion, contacto, dias_credito default 0, activo default true, timestamps.
- `compras`: proveedor_id FK restrict, numero_factura, fecha, subtotal, forma_pago enum string contado/credito, estado string registrada/anulada, user_id (registró), timestamps, unique(proveedor_id, numero_factura).
- `compra_lineas`: compra_id FK cascade, insumo_id FK restrict, cantidad decimal, costo_unitario decimal, subtotal decimal.
- `insumos`: +`proveedor_id` nullable nullOnDelete, +`precio_referencia_mercado` decimal nullable.
- `cuentas_por_pagar`: +`compra_id` nullable nullOnDelete.

## 4. Flujos (F1)

- Registrar factura (gerente/admin, transacción): valida proveedor activo + líneas (insumo existe, cantidad>0, costo>=0) → por línea `InventarioService::registrarCompra()` (reutiliza costo promedio + Kardex) → si crédito, `CuentasPorPagarService::crear()` con `compra_id` y vencimiento = fecha + dias_credito.
- Anular factura: solo si estado registrada y sin pagos aplicados a su CxP; reversa con contramovimientos Kardex + marca anulada (no borrado físico); libera/ajusta CxP vinculada.
- Proveedor: soft delete (`activo=false`); `destroy` bloqueado con 422 si tiene compras (restrict a nivel servicio + FK).
- Vincular insumo↔proveedor: `proveedor_id` editable desde ficha insumo y ficha proveedor.

## 5. Comparador y ficha (F2)

- Por insumo: último costo por proveedor (de líneas), mejor precio, delta % vs `precio_referencia_mercado`; alerta si último > referencia.
- Ficha proveedor (pestañas): datos, insumos que surte (último/mejor precio), facturas (paginado), CxP vinculadas (saldo), KPIs (total comprado en rango, # facturas, ticket promedio, participación % del gasto, días promedio de pago).
- Reporte gasto por proveedor en rango (agregado SQL, no materializar filas).

## 6. Permisos/UI

- `ProveedorPolicy` (ver/crear/actualizar/eliminar: gerente/admin) + abilities `compras.ver/crear/anular` (gerente/admin). Entran a `config/permisos.php` + plantillas gerente/admin (regla anti-deriva del plan de privilegios: el test de completitud las exige).
- UI: ruta `/proveedores` (middleware rol gerente/admin, espejo inventario) con lista + ficha; desde Inventario: ver proveedor del insumo y acceso "Nueva factura".
- Auditoría: `proveedor.creado/actualizado/desactivado`, `compra.registrada/anulada` con diff.

## 7. Tests (TDD, RED primero)

- CRUD + restrict con compras + soft delete.
- Factura multi-línea: Kardex + costo promedio por línea; crédito crea CxP con `compra_id` y vencimiento; contado no crea CxP.
- Anulación reversa Kardex y bloquea con pagos aplicados.
- Comparador: 2 proveedores, mejor precio y delta vs referencia correctos.
- Catálogo incluye nuevas abilities; plantillas gerente/admin las contienen.
- Suite completa verde sin modificar tests existentes.

## 8. Fuera de alcance

- Migración de textos viejos (quedan como historia).
- Órdenes de compra / cotizaciones formales (solo facturas registradas).
- Pagos parciales multi-factura (flujo CxP existente).
- Importación masiva (CSV) de proveedores o precios.

## 9. Riesgos

- Doble CxP si se reintenta registrar: mitigado con unique `compras(proveedor_id, numero_factura)` + idempotencia por factura.
- Costo promedio corrompido por factura errada: mitigado con anulación con contramovimiento (no borrado).
- Divergencia Kardex vs líneas: la línea es la que escribe Kardex vía `registrarCompra()` (única vía), sin doble escritura.
