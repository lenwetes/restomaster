---
name: dependency-audit
description: Use before adding a new Composer or npm package, after installing many packages, or before deploy — to detect vulnerable, deprecated, or hallucinated dependencies and validate that vendor-supplied packages actually exist and are maintained.
---

# Dependency Audit

## Overview

Riesgo doble en desarrollo asistido por IA: (1) agentes inventan paquetes que no existen ("package hallucination") o sugieren versiones inexistentes; (2) dependencias desactualizadas arrastran vulnerabilidades conocidas.

## When to Use

- ANTES de `composer require` / `npm install` de un paquete que sugiere un agente
- Después de una sesión de instalación masiva
- Como parte de CI o antes de deploy
- Al revisar `composer.lock` / `package-lock.json` de otro agente

## Verificación de paquete sugerido (anti-hallucination)

1. **¿Existe?** Buscar en Packagist (`composer show -a <pkg>` o web) / npm (`npm view <pkg>`)
   - No confiar en el nombre: los typosquats existen (`laravel/wallet` vs `laravel-wallet`)
2. **¿Es el paquete oficial?** Verificar el vendor (`laravel/*`, `spatie/*`, `filament/*`)
3. **¿Versión real?** `npm view <pkg> version` antes de `@^x.y.z`
4. **¿Mantenido?** Último release < 1 año y PHP/compat de la versión actual
5. **¿Auténtico?** En npm revisar `dist-tags`, `maintainers`, `created` — paquetes nuevos o sin mantenimiento señal de posible compromiso o typosquat

## Auditoría automática

```bash
# Composer: lista vulnerabilidades conocidas
composer audit

# npm: lista vulnerabilidades conocidas
npm audit --omit=dev

# Dependencias desactualizadas (uso informativo)
composer outdated --direct
npm outdated
```

## Reglas

1. **Toda dependencia requiere composer.lock/package-lock.json commiteado** (reproducibilidad)
2. **No instalar un paquete solo porque lo sugirió un agente** — verificar utilidad y el tamaño del árbol
3. `composer audit` debe salir limpio antes de merge
4. Preferir paquetes oficiales del framework (`laravel/*`) o de vendors de confianza (`spatie/*`)
5. No mezclar `composer require` de paquetes desconocidos sin revisión de mantenimiento

## Comandos de inspección de árbol

```bash
# ¿Por qué se instaló esto? (quién lo depende)
composer why <pkg>

# Tamaño/top packages
composer show | Sort-Object

# Paquetes de npm huérfanos
npm ls --depth=0
```

## Red Flags — STOP

- "Lo vi en un tutorial con esa versión" → verificar que la versión existe
- "Es el paquete estándar para X" → verificar vendor y mantenimiento
- "Ya corrió composer install" → verificar lock commiteado y `composer audit`
- "Solo es una dependencia dev" → `npm audit --omit=dev` revisa producción

## Clasificación de resultados

| Halazgo | Acción |
|---------|--------|
| Paquete no existe / typosquat | NO instalar. Bloquear. |
| Vulnerabilidad de alt/Critical | `composer audit` no pasa deploy. Actualizar o parche. |
| Paquete sin mantenimiento 1yr+ | Considerar alternativa mantenida |
| Desactualizado mayor | Agendar update con tests |
| Lock no commiteado | Commiteo del lock como blocker |

## Salida requerida

Tabla: `paquete → versión nueva/usada → riesgo (hallucination/cve/abandonado) → acción`. Vulnerabilidades critical bloquean el merge/deploy.