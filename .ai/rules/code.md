# Reglas de Código — Sushixpress

> Reglas técnicas que deben seguir todos los agentes al escribir código.

## Stack obligatorio
- **PHP** 8.3, **Laravel** 12, **PostgreSQL** 18
- **Frontend:** Blade + Livewire (mobile-first)
- **Autenticación:** Laravel Breeze (blade)

## Estándares de código
- Seguir PSR-12 (Espaciado, llaves, nombrado)
- Nombrado Eloquent: nombres en snake_case (BD), camelCase (PHP)
- Nombres de migraciones descriptivos (ej. `create_pedidos_table`)
- Usar transacciones DB para flujos críticos (pedido → pago → inventario)
- Estados en ENUM/strings controladas, nunca enteros mágicos

## Base de datos
- Migraciones siempre con `up()` reversible en `down()`
- Índices en columnas de FK y búsqueda frecuente
- Todos los estados como string con check constraint
- Tablas de historial solo-insert (sin UPDATE/DELETE)

## Archivos que NUNCA se suben a Git
- `.env`
- `node_modules/`
- `vendor/`
- Base de datos local

## Verificación antes de terminar
- `php artisan migrate:fresh --force` sin errores
- `php artisan --version` responde Laravel 13
- `npm run build` sin errores (si hay assets)
- Registro en coordination.md

## Testing
- Escribir tests antes de implementar features (TDD)
- Tests en `tests/` siguiendo estructura de Laravel