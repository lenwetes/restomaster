# Guía Rápida de Despliegue en VPS con Coolify

Esta guía explica cómo desplegar **RestoMaster** en tu VPS usando **Coolify (v4+)** con el archivo `docker-compose.coolify.yml`.

---

## 1. Opciones de Despliegue en Coolify

### Opción A: Desde Repositorio Git (GitHub / GitLab / Gitea) — **Recomendada**
1. En tu panel de Coolify, entra a tu **Project / Environment** y haz clic en **+ New Resource**.
2. Selecciona **Public Repository** o **Private Repository (GitHub App)**.
3. Ingresa la URL de tu repositorio: `https://github.com/tu-usuario/restomaster` y rama `main` o `master`.
4. En **Build Pack**, selecciona **Docker Compose**.
5. En **Docker Compose Location**, ingresa:
   ```text
   /docker-compose.coolify.yml
   ```
   *(o déjalo en `/docker-compose.yml`, ambos están configurados).*
6. En el campo **Domains**, define tu dominio o subdominio:
   - Ejemplo: `https://resto.tudominio.com`
   - Coolify configurará automáticamente el proxy inverso Traefik y el certificado SSL gratuito (Let's Encrypt).
7. Haz clic en **Deploy**.

---

### Opción B: Despliegue Directo de Docker Compose (Pegar YAML)
1. En Coolify, haz clic en **+ New Resource** -> **Docker Compose**.
2. Pega el contenido completo del archivo [`docker-compose.coolify.yml`](../docker-compose.coolify.yml).
3. Si vas a construir la imagen desde tu repo, asegúrate de configurar el Git Source, o construirla con el botón **Deploy**.

---

## 2. Variables de Entorno en Coolify

En la pestaña **Environment Variables** de tu aplicación en Coolify, puedes configurar:

| Variable | Valor por Defecto | Descripción |
|---|---|---|
| `APP_NAME` | `RestoMaster` | Nombre del restaurante / sistema |
| `APP_ENV` | `production` | Entorno de ejecución |
| `APP_KEY` | *(Auto-generado si se deja vacío)* | Clave de cifrado de Laravel |
| `APP_DEBUG` | `false` | Depuración (dejar en `false` en producción) |
| `APP_URL` | `https://tu-dominio.com` | URL pública de la aplicación |
| `DB_DATABASE` | `restomaster` | Nombre de base de datos PostgreSQL |
| `DB_USERNAME` | `adminresto` | Usuario de base de datos |
| `DB_PASSWORD` | *(generar con `openssl rand -base64 42`)* | Contraseña de base de datos — obligatoria, sin valor por defecto |
| `AUTO_MIGRATE` | `true` | Ejecuta migraciones automáticamente al iniciar |
| `AUTO_SEED` | `true` | Crea usuarios principales y configuración inicial |
| `DEMO_USERS_PASSWORD`| `password` | Contraseña unificada para los 4 usuarios base |
| `APP_PORT` | `8004` | Puerto directo en el VPS (http://IP_VPS:8004) |

---

## 3. Usuarios y Credenciales Creados Automáticamente

Al arrancar con `AUTO_SEED=true`, el sistema genera los 4 usuarios iniciales del restaurante:

| Rol | Correo Electrónico | Contraseña Inicial |
|---|---|---|
| **Administrador** | `admin@restomaster.com` | `password` *(o la definida en `DEMO_USERS_PASSWORD`)* |
| **Cajero** | `cajero@restomaster.com` | `password` *(o la definida en `DEMO_USERS_PASSWORD`)* |
| **Mesero** | `mesero@restomaster.com` | `password` *(o la definida en `DEMO_USERS_PASSWORD`)* |
| **Cocina (KDS)** | `cocina@restomaster.com` | `password` *(o la definida en `DEMO_USERS_PASSWORD`)* |

> 🔒 **Recomendación de Seguridad:** Una vez que inicies sesión en tu VPS por primera vez, cambia la contraseña del administrador desde la pantalla de perfil (`/profile`) o define `DEMO_USERS_PASSWORD` con una contraseña personalizada en las variables de Coolify antes del primer despliegue.

---

## 4. Pruebas Rápidas sin Dominio (Vía IP del VPS)

Si aún no tienes un dominio apuntando a tu VPS, puedes acceder directamente usando la IP pública de tu servidor:
```text
http://<TU_IP_DEL_VPS>:8004
```
El contenedor expone internamente el puerto `80` a Coolify y el puerto `8004` al host para acceso de prueba directo.

---

## 5. Volúmenes Persistentes

Los datos críticos están protegidos mediante volúmenes con nombre:
- `restomaster_postgres_data`: Base de datos PostgreSQL (mesas, comandas, usuarios, auditorías).
- `restomaster_storage_app`: Backups y documentos generados.
- `restomaster_storage_public`: Fotos de platos, avatares y archivos subidos.
- `restomaster_storage_logs`: Registro histórico de logs de Laravel.

Los datos **no** se perderán al reiniciar contenedores o desplegar nuevas versiones de código.
