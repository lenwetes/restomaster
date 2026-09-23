# Spec: Gestor de Zonas + Drag & Drop en Mapa de Mesas

- **Fecha:** 2026-09-23
- **Estado:** Aprobada por el usuario
- **Decisiones aprobadas:** zonas por sucursal · paleta fija de colores · `zona` como slug validado contra catálogo (sin migración de datos)

## 1. Problema

Las zonas de mesas están quemadas en código (`in:salon,terraza,barra,vip` en la validación + mapas fijos de etiqueta/icono/color). No existe forma de crear zonas, cambiarles el color ni mover mesas entre zonas sin editar código.

## 2. Alcance

Dentro del alcance:
- Tabla `zonas` + modelo `Zona` + policy (admin/gerente gestionan; cajero puede mover).
- Modal "Gestionar zonas" en `mesas/index` (crear/editar/desactivar).
- Drag & drop táctil (Pointer Events) entre salas del mapa + método `moverMesaAZona`.
- Render del mapa/tabs/filtros leído del catálogo (se eliminan los mapas fijos).
- Seed de las 4 zonas clásicas por sucursal existente.
- Tests de CRUD, movimiento y orden.

Fuera del alcance:
- Reordenar mesas DENTRO de una zona (el orden interno sigue automático por número).
- Borrado físico de zonas con mesas (solo desactivación + reasignación previa).
- Colores hex libres (solo paleta Aura de 8).
- Cambios en cocina, POS o reportes (leen `mesas.zona` como string: sin impacto).

## 3. Datos

### 3.1 Migración `create_zonas_table`
- `id`, `sucursal_id` FK `sucursales` cascadeOnDelete, `nombre` string(60), `slug` string(40), `color` string(30) (clave de paleta, ej. `terracota`), `icono` string(40) (clave de set Material Symbols), `orden` integer default 0, `activa` boolean default true, timestamps.
- Unique compuesta `zonas_sucursal_slug_unique (sucursal_id, slug)`.
- Índice en `(sucursal_id, activa, orden)` para el render del mapa.

### 3.2 `mesas.zona`: sin cambios
- Sigue `string` con el slug. El índice existente `(sucursal_id, zona)` permanece válido.
- Validación en `formMesa.zona`: `required|string|max:40` + `Rule::exists('zonas', 'slug')->where(sucursal_id, ...)->where(activa, true)`.
- Seed: por cada sucursal, crear `salon` (Salón Principal), `terraza` (Terraza), `barra` (Barra / Bar), `vip` (Área VIP) con colores/iconos equivalentes a los actuales. Slugs históricos (`patio`, `primer_piso`) se crean solo si existen mesas con ese slug (detectado por `distinct`).

### 3.3 Paleta fija (claves → tokens Tailwind existentes)
`terracota` (primary), `salvia` (secondary), `lavanda` (tertiary), `ambar` (amber-600), `esmeralda` (emerald-600), `indigo` (indigo-600), `rosa` (rose-500), `pizarra` (outline-variant). Sin hex libres: el mapa nunca se rompe visualmente.

## 4. Gestor de zonas (UI)

Modal "Gestionar zonas" en `resources/views/livewire/mesas/index.blade.php`, visible para admin/gerente (botón junto a "Gestionar Terminales"):
- Lista de zonas de la sucursal (orden, nombre, muestra de color, conteo de mesas, estado).
- Crear: nombre (se slugifica solo; validación de unicidad por sucursal), color (selector visual de 8), icono (set de 8), orden.
- Editar: nombre, color, icono, orden. Cambiar el slug NO está permitido (es la llave operativa).
- Desactivar: bloqueado con mensaje si tiene mesas (`count > 0`); primero reasignar desde el mapa. Reactivar permitido.
- Todo con `$this->authorize(...)` por acción + `wire:confirm` en desactivar.

## 5. Drag & drop táctil

- Técnica: Pointer Events (`pointerdown/move/up`), NO HTML5 DnD (no existe en táctil).
- Mantener 250ms sobre una mesa la "recoge": escala + sombra + clon fantasma que sigue el dedo. `touch-action: none` SOLO durante el arrastre (el scroll del mapa sigue intacto en uso normal).
- Las salas (zonas) válidas se resaltan al pasar el fantasma; soltar fuera revierte sin cambios.
- Al soltar: `moverMesaAZona(mesaId, zonaSlug)` valida (mesa existe, zona activa de la sucursal) y guarda. Re-render Livewire: la mesa aparece en la nueva sala; conteos, tabs, filtros y leyenda se actualizan solos (ya son reactivos).
- Accesibilidad/seguridad: la acción equivale al método (misma autorización y validación); el tap simple sigue abriendo el sheet (sin regresión).
- Mover mesa ocupada: permitido (la zona es agrupación operativa, no estado de la mesa).

## 6. Render dinámico del mapa

- Se reemplazan los mapas fijos (`$zonasEtiqueta`, `$zonasIcono`, `$tintesZona`, `$puntosZona`, `$rankingZonas`, `$zonasConfig`) por lectura del catálogo `zonas` (orden por `orden`, luego nombre).
- Zonas con slug huérfano (mesas cuya zona no está en el catálogo): se agrupan al final con estilo neutro + aviso en el gestor ("X mesas sin zona válida — reasignar").
- Filtros existentes (`Zonas:` con conteos desde `distinct`) siguen funcionando sin cambios.

## 7. Seguridad y reglas

- Rutas/componente: la pantalla ya exige auth; cada mutación (`crearZona`, `editarZona`, `desactivarZona`, `moverMesaAZona`) con policy/`authorize` + alcance por sucursal (patrón `obtenerTurnoValido`).
- `moverMesaAZona`: cajero+ puede mover dentro de su sucursal; admin/gerente en cualquiera.
- Rate limiting natural de Livewire; `wire:confirm` solo en desactivar (el mover es reversible y de bajo riesgo).

## 8. Testing

- `ZonaGestionTest` (nuevo): crear (ok + duplicada + otra sucursal mismo slug ok), editar color/orden, desactivar bloqueada con mesas, reactivar, guest/no-autorizado → forbidden.
- `MesaMoverZonaTest` (nuevo o en MultipleShifts): mover ok actualiza `zona` y re-renderiza; zona inexistente/inactiva/otra sucursal → 422/404; mover ocupada permitido.
- Regresión: suites de mesas + pint en verde.
- Verificación manual en móvil real: drag con dedo entre zonas, scroll intacto, tap abre sheet.

## 9. Archivos tocados

- `database/migrations/20*_create_zonas_table.php` (nueva)
- `app/Models/Zona.php` (nuevo) + `app/Policies/ZonaPolicy.php` (nueva)
- `database/seeders/ZonaSeeder.php` (nueva; llamada desde flujo de sucursales nuevas)
- `resources/views/livewire/mesas/index.blade.php` (gestor + DnD + render dinámico)
- `app/Services/MesaService.php` (método `moverMesa` si existe; si no, lógica en el componente siguiendo el patrón actual)
- `tests/Feature/ZonaGestionTest.php` (nuevo)
- `routes/*` si el gestor requiere ruta (se prefiere modal dentro de `mesas.index`, sin rutas nuevas)
