---
name: config-env-guard
description: Use when setting up environments, reviewing .env files, config files, Docker deployments, or when a secret or credential appears in any config — to keep secrets out of repositories, protect environment variables, and prevent credential leakage through config files, .env.example, or MCP configurations.
---

# Config & Env Guard

## Overview

El `.env` contiene credenciales reales y no puede llegar al repositorio. Los archivos de ejemplo no deben contener secretos y toda referencia de config debe pasar por `env()`, jamás valores literales.

## Reglas de oro

1. **`.env` SIEMPRE en `.gitignore`** — verificar con `git check-ignore .env`
2. **`.env.example` con variables vacías** (`DB_PASSWORD=`) — sin valores falsos ni deterministas
3. **Todo valor sensible en `config/*.php` se lee con `env('X', null)`** — nunca `password => 'admin'`
4. **APP_KEY** generada, única por entorno (`php artisan key:generate`)
5. La config no se hardcodea según entorno; se copia el archivo y se cambia la variable

## Checklist

- [ ] `.env` no versionado ni copiado a `resources/views`, `docs/`, `tests/`
- [ ] `.env.example` sin credenciales de forma (ni vacías pero con formato que el agente llene con datos reales)
- [ ] `.gitignore` contiene: `.env`, `*.env.local`, `.env.backup`, `*.sql`, `*.bak`, dumps
- [ ] `config/database.php` usa `env('DB_*')`, no literales
- [ ] En MCP configs, headers de auth no contienen el token real (usar `{env:VAR}`)
- [ ] Docker: `.env` no se copia en el `Dockerfile` (los build args con secretos quedan en el historial de capas)
- [ ] `main.php`/`services.php` sin claves de terceros harcodeadas (stripe, mailgun, aws)

## Verificación

```bash
# .env ignorado
git check-ignore .env

# Referencias env() en config
rg -n "env\(" config

# Valores literales sospechosos en config
rg -n "password.*=>.*['\"]|secret.*=>.*['\"]|api_key.*=>.*['\"]" config

# .env no commiteado en el index
git ls-files | Select-String "\.env"
```

## Anti-patrones

| Anti-pattern | Fix |
|--------------|-----|
| `config('app.key')` con valor literal | `env('APP_KEY')` |
| `.env.example` con `DB_PASSWORD=secret` | Dejar `DB_PASSWORD=` |
| Subir `.env` "para que el otro agente lo tenga" | Compartir vía variables de entorno o secret manager |
| Documentar contraseñas en README | Referenciar `docs/runbook-setup.md` como procedimiento — sin valores |
| Incorporar `APP_KEY` en el commit inicial | `php artisan key:generate` local, no commiteado |

## Red Flags — STOP

- "Lo dejo en el ejemplo para que funcione de una" → se replica a producción
- "El otro agente necesita el .env" → se comparte por canal seguro, no por el repo
- "Es docker-compose local" → docker-compose.yml con credenciales se commitea (mal)
- "Config tiene valores por defecto solo" → un defecto puede ser un secreto

## Salida requerida

Lista: `archivo → variable/secreto → exposición actual → riesgo`. Secreto real fuera de `.env` (ignorado) → Critical. `.env.example` poblado o credencial en config literal → High, bloqueante.