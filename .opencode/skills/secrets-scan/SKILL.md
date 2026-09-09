---
name: secrets-scan
description: Use when reviewing files, before committing to git, or before deploying — to find hardcoded secrets, API keys, passwords, tokens, or credentials that an AI agent may have accidentally written into source code, config files, or documentation.
---

# Secrets Scan

## Overview

La mayor clase de vulnerabilidad en código generado por agentes IA: credenciales y secretos incrustados en código, configs o commits. Detectarlos antes de que lleguen a un repositorio compartido.

## When to Use

- Antes de cualquier `git add` / commit
- Al revisar un PR o diff generado por un agente
- Al revisar archivos nuevos: `.env`, `config/*.php`, MCP configs, scripts, tests, fixtures
- Al preparar deploys (el `.env` jamás se copia a producción por commit)

## Checklist de escaneo

Buscar en **todo el árbol de trabajo** (excepto `node_modules/`, `vendor/`, `storage/`):

1. **Patrones de credenciales**:
   - `password`, `passwd`, `pwd`, `secret`, `token`, `api_key`, `apikey`, `client_secret`
   - `DB_PASSWORD`, `APP_KEY`, `MAIL_PASSWORD`, `AWS_SECRET`, `PRIVATE KEY`
   - `sk-` (OpenAI), `ghp_`/`github_pat_` (GitHub), `AKIA` (AWS), `Bearer <valor>`

2. **Fuentes típicas de fuga**:
   - `.env` referenciado dentro de código (p. ej. `'password' => 'admin'` hardcodeado)
   - `config/*.php` con valores literales en lugar de `env(...)`
   - Fixtures, seeders o tests con credenciales reales
   - Comentarios con URLs `https://user:pass@host`
   - Documentación `.md` con claves de ejemplo válidas
   - `opencode.json` / MCP configs con headers `Authorization`

3. **Casos de Laravel**:
   - `php artisan key:generate` pendiente (APP_KEY vacío)
   - `DB_USERNAME`/`DB_PASSWORD` literales en vez de `env()`
   - Dumps/backups de BD en el repositorio (`*.sql`, `*.bak`, `pg_dump`)

## Comandos de verificación

```bash
# Buscar patrones de credenciales en archivos de código
rg -n --hidden -g "!vendor" -g "!node_modules" -g "!.git" \
  "(password|secret|token|api[_-]?key|client[_-]?secret)\s*[=:]\s*['\"][^'\"]{6,}" .

# Buscar archivos de credenciales olvidados
rg --hidden --files -g "*.env*" -g "*.pem" -g "*.key" -g "*.sql" -g "*.bak" .

# Verificar que .env NO está en git
git check-ignore .env
if (-not $?) { "⚠ .env NO está ignorado" }
```

## Reglas de negocio (obligatorias)

1. **NUNCA** escribir credenciales reales en código, fixtures, seeders ni docs.
2. Usar `env('VAR', null)` o ConfigService con variables de entorno.
3. Si se encuentra un secreto **ya commiteado**: rotarlo/revocarlo de inmediato (no basta borrarlo del código — puede estar en el historial git).
4. Seeder de admin: credenciales generadas por env, nunca hardcodeadas.
5. Los backups de BD NO se suben al repo (agregar a `.gitignore`).

## Red Flags — STOP

- "Es solo para pruebas" → los secrets de pruebas terminan en producción
- "El repo es privado" → los repos privados se vuelven públicos o se comparten
- "Lo borro después" → el historial de git lo conserva para siempre
- "Es un ejemplo en docs" → los ejemplos se copian y pegan como reales

## Common Mistakes

| Error | Fix |
|-------|-----|
| Borrar el secreto del código pero no del historial | Rotar la credencial |
| Poner credenciales en `.env.example` | Poner solo `VARIABLE=` vacío |
| Cifrar algo en el código con clave hardcodeada | Usar secret manager o env |
| Ignorar archivos `.sql`/`.bak` | Agregarlos a `.gitignore` y escanear |

## Salida requerida

Reportar con rutas: `archivo:línea → tipo de secreto → severidad (critical/high/medium)`. Todo hallazgo critical debe bloquear el commit/deploy hasta resolverse.