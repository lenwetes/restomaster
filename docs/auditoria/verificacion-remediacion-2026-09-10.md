# Auditoría de Verificación de la Remediación — Sushixpress

- **Fecha:** 2026-09-10
- **Autor:** OpenCode (solo lectura, 4 auditores: seguridad, integridad BD, rendimiento, calidad)
- **Objeto auditado:** commit `a01360c` "fix(auditoria): remediacion integral P0, P1 y P2" (Antigravity)
- **Suite verificada localmente:** **237/237 tests, 761 assertions** ✅ · **Pint 0 violaciones** ✅
- **Estado del entorno:** lock `public_and_auth_redesign.lock` activo de Antigravity (no se tocó); lock de remediación liberado.

---

## Veredicto global

**La remediación está ~70% completa y NO es "integral" como afirma Antigravity.** Los hallazgos puntuales de la lista P0/P1/P2 se cerraron en gran parte, pero:

- **2 mutaciones de dinero/estado quedaron SIN `authorize` (delivery y KDS)** → sigue siendo bloqueante de merge (broken access control, clase #1 OWASP).
- **3 FKs del histórico declarado siguen en `CASCADE`** (recetas ×2, direcciones_cliente) → la garantía "cero cascades en auditables" no se cumple.
- Varias afirmaciones verificadas como **PARCIAL** (F1 reportes, P1-06 polling, P1-09 índices, enums) y quedan **5 hallazgos STILL** (F11/F13/F15 sin paginar, L4, L8) + un **N+1 nuevo en POS**.

**Gate: ✗ NO mergeable** hasta cerrar: authorize en delivery/KDS, `direcciones_cliente.cliente_id` restrict, y (recomendado) `(estado, estado_delivery)`.

---

## 1. Calificativos

| Dominio | 2ª pasada | **Verificación remediación** | Cambio |
|---|---|---|---|
| Seguridad | 5.5/10 | **6.5/10** | 7 de 8 hallazgos puntuales FIXED; pero 2 mutaciones dinero sin authorize siguen |
| Integridad BD | 7.5/10 | **8.5/10** | 14/17 cascades del alcance endurecidos; 3 vivos |
| Performance | 6/10 | **7/10** | F3/F4/F5 reales; F1 a medias; N+1 nuevo en POS |
| Calidad código | 8.3/10 | **8.5/10** | deps/@/casts/backups ✓; enums a medias |
| Dependencias | 9/10 | **9/10** | bacon ^3.0 ✓, Tailwind v4 a medias (lock desincronizado) |
| Tests | 8.5/10 | **9/10** | 237/237 + `AuthorizePoliciesTest` + `IntegridadHistoricoFkTest` (cobertura genuina) |
| **Global** | **≈7.2/10** | **≈7.8/10** | **Sigue bloqueado para producción** (2 mutaciones sin autorizar) |

---

## 2. FIXED — verificado con evidencia

| Hallazgo | Evidencia |
|---|---|
| **H4** Allowlist caja (`ingreso,egreso,retiro`), `autorizadoPor` requerido y no auto-aprobación | `CajaService.php:100-110` + test `AuthorizePoliciesTest:168-195` |
| **H7** `puntos_fidelidad` no-negativo + `tipoAjuste in:suma,resta` | `ClienteService.php:34`, `FidelizacionService.php:155`, `clientes/index:173-179` |
| **H1** Adiós `123456` en seeder → `env('DEMO_USERS_PASSWORD') ?: Str::password(12)` | `AdminUserSeeder.php:29` + test `AuthorizePoliciesTest:163-165` |
| **H2** Adiós `'secret'` fallback → `Str::password(12)` | `TrabajadorService.php:36` |
| **H6** Throttle rutas públicas + honeypot `$empresa` + RateLimiter IP | `routes/web.php:27,28,34,37,38`, `delivery/pedido-publico:30,114-124` |
| **P0-06** Descuego puntos acotado a remanente y saldo, en 4 capas | `PedidoService.php:47-51,77-80`, `Pedido.php:134-149`, `FidelizacionService:97-143`, POS `:186-197` |
| **C3 (listado original)** 8 Policies creadas + autorizadas en 31 métodos listados | `app/Policies/*Policy.php` (8) + `AppServiceProvider:46-50` (bypass admin) |
| **H3** CRUD mesas exigido por policy (create/update/delete → gerente/admin) | `MesaPolicy:20-36` |
| **C4 (14 FKs)** `restrict` corroborado en PostgreSQL runtime | `information_schema.referential_constraints` dev |
| **F3** Flota motorizados 0 N+1 (`withCount`/`withSum`) | `DeliveryService.php:223-239`, vista `:585-589` |
| **F4** KDS `whereHas` + conteos SQL por área | `kds.blade.php:68-90` |
| **F5** Delivery paginado `paginate(30)` + `links()` | `delivery/index:197,564-568` |
| **P1-07** Categorías/productos `activo=true` en catálogo público | `menu-publico:172,176`, `agregarProducto:50` |
| **P1-08** POS top 50 clientes (`limit(50)`) | `pos/terminal:420` |
| **P2-05b** `bacon/bacon-qr-code ^3.0` (instalado 3.1.1) | `composer.json:10`, `composer.lock:11` |
| **P2-07/L6** `@` suppression eliminadas (set_error_handler/try-catch) | `Impresora.php ~160`, `ImpresionService ~186,329` |
| **P2-08** `Insumo::casts()` como método | `Insumo.php:35` |
| **P2-09** Validación estricta extensión `.sql/.txt` + authorize + mimes | `configuracion/index:220-237` |
| **L2** `tier` normalizado (`black/imperial` → "Imperial VIP" label) | `Cliente.php:60-66` |
| **H7-nuevo** Credencial DB cifrada con `Crypt` | `ConfiguracionService.php:20-39` |

---

## 3. STILL PRESENT / PARCIAL — pendientes reales

### 🔴 CRÍTICO (bloquean merge)

| # | Hallazgo | Ubicación |
|---|---|---|
| **N1-DELIVERY** | **0 `authorize` en delivery**: `confirmarEntregaYCobro` (cobro+`estado=pagado`, monto/metododedel cliente), `liquidarRepartidor` (crea `MovimientoCaja` de ingreso para **cualquier** repartidor), `asignarRepartidor`, `marcarSalida`, `guardarNuevoPedido`. Ruta = `cajero,delivery,repartidor,gerente` | `delivery/index.blade.php:104-131,63,77,149` + `routes/web.php:47` |
| **N2-KDS** | **Descuenta inventario sin authorize**: `marcarListo` → `descontarPorItemPedido` (`PedidoService.php:131`); `tomarItem`, `marcarTodaComandaLista`, `marcarComandaEntregada` sin policy. Un `barra` puede tocar items de `sushi` | `cocina/kds.blade.php:24,37,44,56` |
| **C4-residual** | FKs **siguen `CASCADE`** en runtime (verificadas en PG): `recetas.producto_id`, `recetas.insumo_id`, `direcciones_cliente.cliente_id` — histórico de escandallo y domicilios destruible con borrado físico | `191010:16-17`, `194010:16` (no tocadas por 220000) |

### 🟠 HIGH / MEDIUM

| # | Hallazgo | Ubicación |
|---|---|---|
| **F1** | "Agregación SQL" solo en `estadoResultados`; 8 métodos materializan columnas y suman en PHP (mes de alto volumen = ram por render) | `ReporteService.php:86-96,138-168,173-205,229-243,248-264,270-298` |
| **P1-06** | `wire:poll.6s="refrescarEstado"` pero `refrescarEstado` es **no-op** → `with()` se re-ejecuta completo (mitigated: en seguimiento la rama es ligera) | `menu-publico.blade.php:158-161,221` |
| **P1-09** | Falta índice `(estado, estado_delivery)` prometido (solo `(tipo, estado_delivery)` de 120000 existe) | `230000` vs mensaje delivery/KDS |
| **F11** | Clientes sin paginar (`->get()`) | `clientes/index:237` |
| **F13** | Cxp pendientes sin paginar/limitar | `cxp/index:39-42` |
| **F15** | Turno de caja materializa TODOS los pedidos para contarlos | `caja/control:246,408` → `withCount('pedidos')` |
| **N3-POS** | **N+1 nuevo por categoría**: `$cat->productos->count()` y `productos()->count()` sin `withCount('productos')` | `pos/terminal:949,1272` (carga `:423`) |
| **P2-01** | Enums: `PedidoEstado` usable SOLO en `Pedido.php`; ~38 literales en services y ~45 en Volt siguen en string | `PedidoService`, `ReporteService`, `DeliveryService`, etc. |
| **P2-01b** | `TurnoCajaEstado` 100% dead code (1 match: su propio archivo) | `app/Enums/TurnoCajaEstado.php` |
| **P2-05a** | package-lock/node_modules siguen con `@tailwindcss/vite ^4` y `multiplex`, y `tailwindcss 4.3.3` instalado → `npm install` pendiente | `package-lock.json`, `node_modules/` |
| **L8** | `Reserva.hora_llegada` sin cast | `Reserva.php:23-31` |
| **M16** | 2 queries reales en `@php`/tiempo de render (pos `:1133`, reservas `:237`) | `pos/terminal`, `reservas/index` |
| **L4** | CSRF exento en `api/*` (no remediado; mitigado por token `hash_equals`) | `bootstrap/app.php:19-21` |

### 🟡 NUEVOS detectados

| # | Hallazgo | Severidad |
|---|---|---|
| **login.blade `123456`** | Hint demo obsoleto y default de `$password='123456'` en `:12`/`:182` tras H1 (la clave real es aleatoria ahora) | Baja |
| **down() asimétrico** | 220000 rollback no restaura `categoria_id` a NOT NULL y reintroduce 14 CASCADE sin aviso | Baja |
| **Test vacuo** | `test_admin_super_usuario...` en `AuthorizePoliciesTest:145-152` pasa por inacción (`dbForm=[]`) | Baja |
| **Lock sin registro** | `.locks/public_and_auth_redesign.lock` presente sin asociarse a WIP en coordination.md | Info |
| **`accept=".sql,.dump,.txt"`** vs regla real `.sql/.txt` | Promesa de UI ≠ validación server-side | Cosmético |

---

## 4. Cobertura de tests de la remediación — análisis

| Test nuevo | Estado | Calidad |
|---|---|---|
| `AuthorizePoliciesTest.php` (219 ln) | ✅ 21/21 (59 asserts) según security agent | **Genua** (mapeo método→authorize verificado). Debilidades: no cubre pos/caja-turnos/cxp-pago/reservas/ajustarPuntos; nada de delivery ni KDS; `test_admin...` vacuo; no ejercita middleware de ruta (Volt::test no lo corre) |
| `IntegridadHistoricoFkTest.php` (304 ln) | ✅ 9/9 (10 asserts) SQLite | **Genua** (DELETE + expectException(QueryException) sobre constraints reales). Gaps: no cubre `mesas.sucursal_id`, `reserva_mesa.*`, `items_pedido.pedido_id` — y **no cubre las 3 FKs que quedaron CASCADE** (coherente: hoy 3 tests fallarían) |
| No hay tests de performance | — | Ninguno usa `DB::enableQueryLog`/`assertQueryCount` → F1-F5/P1-06 sin protección de regresión |

---

## 5. Migraciones de la remediación (220000 / 230000)

- ✅ Migraciones originales **intactas** (`git show a01360c --name-status` → solo `A`, ninguna `M` sobre 09_09).
- ✅ 220000 usa `dropForeign`/`foreign`/`change()` genéricos (no trucos de driver) → funciona en SQLite (rebuild) y PostgreSQL.
- ✅ 220000/230000 **ya corrió** en dev PG (batch 4/5).
- ✅ Índices 230000 presentes en PG: `(estado,pagado_en)`, `(caja_id,estado)`, `(turno_caja_id,tipo)`, `auditorias(created_at)`. **Falta `(estado,estado_delivery)`.**
- ⚠️ `down()` de 220000 asimétrico (no restaura NOT NULL de `categoria_id`).
- ⚠️ Se endurecen **14** FKs y el comentario/doc dice "13" (desfase menor).

---

## 6. Plan de cierre recomendado (para que quede Integral)

**Bloqueante:**
1. `authorize()` en `delivery/index.blade.php` (Política `PedidoPolicy` reutilizable: `confirmarEntrega`, `liquidarRepartidor`, `asignarRepartidor`; restringir `liquidarRepartidor` al repartidor dueño o gerente/cajero).
2. `authorize()`/policy en KDS (`marcarListo` describiendo inventario → `InsumoPolicy`/`PedidoPolicy`; validar que el `area_cocina` del item = área del rol).
3. Endurecer `direcciones_cliente.cliente_id` y `recetas.*` en una migración adicional (`restrict`).
4. Índice `(estado, estado_delivery)`.

**Alto:**
5. `ReporteService`: convertir kpisRealtime/ventasPor*/topClientes a `sum()`/`groupBy` SQL en agregados.
6. `pos/terminal:423` → `Categoria::withCount('productos')`.
7. Paginar `clientes`, `cxp.pendientes`; `caja` → `withCount('pedidos')`.
8. `refrescarEstado` real o `wire:poll`/`[Computed]` en menú QR.

**Medio:**
9. Migrar literales de estado → enums en services y Volt; usar `TurnoCajaEstado` o eliminarlo.
10. `npm install` para sincronizar lock; decidir Tailwind único.
11. `Reserva::casts` incluir `hora_llegada`; remover queries `@php`.
12. Corregir `login.blade` demo hint; `down()` asimétrico; test vacuo.

---

## 7. Notas de coordinación

- **Lock activo respetado**: `.locks/public_and_auth_redesign.lock` (Antigravity), archivos `layouts/publico.blade.php`, `guest.blade.php`, `auth/login.blade.php`, `delivery/pedido-publico`, `carta-publica`, `reservas/crear`. **No se tocaron.**
- Esta auditoría fue 100% solo lectura; no se modificó código ni migraciones.
- Recomendado pasar el plan de cierre (§6) a Antigravity con su propio lock para la siguiente iteración.

---

*Generado por OpenCode · 2026-09-10 · Verificación de remediación commit a01360c · solo lectura.*