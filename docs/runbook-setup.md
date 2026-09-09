# Runbook de Instalación y Configuración — Proyecto Sushixpress

Documento de ejecución autónoma para que un agente de IA (**Antigravity**) prepare el entorno y levante el proyecto **Laravel + PostgreSQL** en Windows (PowerShell).

Proyecto destino: `D:\Proyectos\sushixpress`
Boot de herramientas: `D:\Proyectos\tools`

> **Regla para el agente:** Ejecutar los pasos EN ORDEN. Verificar cada uno antes de continuar. Si un paso falla, usar la alternativa indicada e informar en el log. No saltar verificaciones.

---

## 0. Diagnóstico inicial

Verificar qué hay instalado antes de empezar:

```powershell
php -v;                      # ¿PHP instalado?
composer -V;                 # ¿Composer instalado?
git --version;               # Debe responder 2.x
Get-Service postgresql*      # Servicio PostgreSQL (debe estar Running)
Test-Path "C:\Program Files\PostgreSQL"
```

| Resultado | Acción |
|-----------|--------|
| git no aparece | Detener: requerido por Composer/Laravel |
| PostgreSQL no corre | Arrancar o instalar antes de continuar |
| php no aparece | Seguir a Paso 1 |
| composer no aparece | Seguir a Paso 2 |

---

## 1. Instalación de PHP (8.3, x64, Thread Safe)

> **Por qué manual:** El paquete de winget tiene la URL rota (404) y Chocolatey requiere permisos de administrador. La instalación por ZIP no requiere admin.

### 1.1 Descargar e instalar

```powershell
New-Item -ItemType Directory -Path "D:\Proyectos\tools" -Force | Out-Null

# 1) Buscar la versión actual de PHP 8.3 en el listado oficial
$page = (Invoke-WebRequest -Uri "https://windows.php.net/downloads/releases/" -UseBasicParsing).Content
$match = [regex]::Match($page, 'php-8\.3\.[0-9]+-Win32-vs16-x64\.zip')
$file  = $match.Value
Write-Output "Archivo a descargar: $file"

# 2) Descargar el ZIP
$url = "https://windows.php.net/downloads/releases/$file"
Invoke-WebRequest -Uri $url -OutFile "D:\Proyectos\tools\$file" -UseBasicParsing

# 3) Extraer a la carpeta de instalación
Expand-Archive -Path "D:\Proyectos\tools\$file" -DestinationPath "D:\Proyectos\tools\php83" -Force
Remove-Item "D:\Proyectos\tools\$file"
```

**Alternativa si falla el regex:** visitar `https://windows.php.net/downloads/` con el navegador, descargar **PHP 8.3 (x64) · Thread Safe** y extraerlo manualmente a `D:\Proyectos\tools\php83`.

### 1.2 Configurar `php.ini`

```powershell
$phpDir = "D:\Proyectos\tools\php83"
Copy-Item "$phpDir\php.ini-development" "$phpDir\php.ini" -Force
```

Habilitar extensiones requeridas (quitar el `;` inicial de cada línea en `php.ini`):

```
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=pdo_pgsql
extension=pgsql
extension=sqlite3
extension=zip
```

**Buscar y editar las líneas** (verificación con grep):

```powershell
Select-String -Path "$phpDir\php.ini" -Pattern "^;extension=(curl|fileinfo|gd|mbstring|openssl|pdo_pgsql|pgsql|sqlite3|zip)"
```

### 1.3 Agregar PHP al PATH del usuario (sin admin)

```powershell
$phpDir = "D:\Proyectos\tools\php83"
$current = [Environment]::GetEnvironmentVariable("Path", "User")
if ($current -notlike "*$phpDir*") {
    [Environment]::SetEnvironmentVariable("Path", "$current;$phpDir", "User")
}
# Recargar PATH en la sesión actual
$env:Path = [Environment]::GetEnvironmentVariable("Path", "User") + ";" + [Environment]::GetEnvironmentVariable("Path", "Machine")
```

**Verificar:**

```powershell
php -v
php -m | Select-String -Pattern "pdo_pgsql|pgsql|mbstring|openssl|curl|fileinfo|zip"
```

**Salida esperada:** versión PHP 8.3.x y que aparezcan las extensiones listadas (en especial `pdo_pgsql`).

> **Importante:** Abrir una **nueva ventana de terminal** para que el PATH surta efecto. Si `php` aún no responde, reiniciar el IDE/terminal.

---

## 2. Instalación de Composer (portátil, sin admin)

### 2.1 Instalar el instalador

```powershell
# Opción A (recomendada) — Instalador portable manual
New-Item -ItemType Directory -Path "D:\Proyectos\tools\composer" -Force | Out-Null
Copy-Item "D:\Proyectos\tools\php83\php.exe" "$env:TEMP\php-for-composer.exe"
```

**Opción B — Instalador clásico (recomendada para simplicidad):**

```powershell
Invoke-WebRequest -Uri "https://getcomposer.org/Composer-Setup.exe" `
    -OutFile "D:\Proyectos\tools\composer-setup.exe" -UseBasicParsing
Start-Process -FilePath "D:\Proyectos\tools\composer-setup.exe" -Wait
```

> El instalador pide la ruta de `php.exe` → indicar `D:\Proyectos\tools\php83\php.exe`. Marcar "agregar composer al PATH del usuario".

**Opción C — Instalación portable 100% manual:**

```powershell
$phpDir = "D:\Proyectos\tools\php83"
Invoke-WebRequest -Uri "https://getcomposer.org/download/latest-stable/composer.phar" `
    -OutFile "$phpDir\composer.phar" -UseBasicParsing
# Crear wrapper .bat en la carpeta PHP
@"
@echo off
"%phpDir%\php.exe" "%phpDir%\composer.phar" %*
"@ | Set-Content "$phpDir\composer.bat" -Encoding ASCII
```

> Si se usó la Opción C y se instaló composer.bat junto a php.exe ya en el PATH, verificar: `composer --version`.

### 2.2 Verificar

```powershell
composer --version
```

**Salida esperada:** `Composer version 2.x.x`.

---

## 3. Configuración de PostgreSQL

### 3.1 Ubicar `psql`

```powershell
Get-ChildItem "C:\Program Files\PostgreSQL" -Recurse -Filter "psql.exe" -ErrorAction SilentlyContinue | Select-Object FullName
Get-Service postgresql* | Select-Object Name, Status
```

Ruta típica: `C:\Program Files\PostgreSQL\18\bin\psql.exe`

### 3.2 Conectarse (pedirá contraseña de superusuario `postgres`)

```powershell
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5432
```

**Si solicita contraseña y no se conoce:** detenerse y pedir la contraseña del usuario `postgres` al administrador del equipo (no intentar adivinar ni forzar).

### 3.3 Crear usuario y base de datos

SQL a ejecutar dentro de `psql` (reemplazar `TU_CLAVE_SEGURA`):

```sql
CREATE ROLE sushixpress LOGIN PASSWORD 'TU_CLAVE_SEGURA';
CREATE DATABASE sushixpress OWNER sushixpress ENCODING 'UTF8';
GRANT ALL PRIVILEGES ON DATABASE sushixpress TO sushixpress;
\q
```

### 3.4 Verificar acceso

```powershell
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U sushixpress -h 127.0.0.1 -d sushixpress -c "SELECT version();"
```

---

## 4. Creación del proyecto Laravel

### 4.1 Inicializar repositorio Git

```powershell
Set-Location "D:\Proyectos\sushixpress"
git init
# Crear .gitignore estándar de Laravel (se completará con el proyecto)
```

Ésta es la carpeta definitiva del proyecto (ya contiene la carpeta `docs/`). Crear Laravel **dentro** usando el flag para no anidar dos carpetas:

```powershell
composer create-project laravel/laravel . --prefer-dist
```

> Este comando instala la última versión estable de Laravel (actualmente **13.x**) en el directorio actual. Si el CLI marca la carpeta como no vacía (por `docs/`), mover temporalmente `docs/` fuera, crear el proyecto y volver a moverla adentro:

```powershell
Move-Item "D:\Proyectos\sushixpress\docs" "D:\Proyectos\tools\docs-aux"
composer create-project laravel/laravel . --prefer-dist
Move-Item "D:\Proyectos\tools\docs-aux" "D:\Proyectos\sushixpress\docs"
```

### 4.2 Verificar el proyecto

```powershell
php artisan --version
php artisan key:generate
```

**Salida esperada:** `Laravel Framework 13.x.x` y mensaje de clave generada.

---

## 5. Configuración `.env` (conexión a PostgreSQL)

Editar `D:\Proyectos\sushixpress\.env`:

```
APP_NAME="Sushixpress"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sushixpress
DB_USERNAME=sushixpress
DB_PASSWORD=TU_CLAVE_SEGURA
```

(Los otros valores del `.env` generado por Laravel pueden quedar como están.)

**Verificar la conexión:**

```powershell
php artisan migrate:fresh
```

**Salida esperada:** migraciones de Laravel base ejecutadas sin errores en PostgreSQL.

> **Si falla:** revisar `php -m` (debe contener `pdo_pgsql`), la clave en `.env` y que el servicio PostgreSQL esté Running.

---

## 6. Autenticación y estructura base (Fase 0)

### 6.1 Instalar scaffolding de autenticación (Laravel Breeze)

```powershell
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install
npm run build
```

### 6.2 Configuración de idioma base del sistema

> Opcional en esta fase: cambiar `locale` a `es` en `config/app.php` (`'locale' => 'es'`). Si la app usa fechas en español puede instalar Carbon localizada (viene incluida).

### 6.3 Migraciones base según modelo de datos

Si Antigravity continúa más allá de la verificación (opcional, se puede posponer):

```powershell
php artisan make:model Role -m
php artisan make:model Sucursal -m
php artisan make:model Mesa -m
php artisan make:model Categoria -m
php artisan make:model Producto -m
php artisan make:model Insumo -m
php artisan make:model Cliente -m
php artisan make:model Pedido -m
```

> Las columnas exactas están definidas en `docs/modelo-datos/modelo-datos.md`.

### 6.4 Productores de datos iniciales (seeders)

```powershell
php artisan make:seeder RoleSeeder
php artisan make:seeder AdminUserSeeder
php artisan make:seeder SucursalSeeder
php artisan make:seeder MesaSeeder
```

**Contenido esperado:**
- `RoleSeeder`: roles admin, gerente, cajero, mesero, cocina, barra, delivery.
- `AdminUserSeeder`: usuario administrador inicial (email/password definidos; cambiar en producción).
- `SucursalSeeder`: sucursal principal con datos del restaurante.
- `MesaSeeder`: mesas iniciales (nº 1–10, zona salón).

```powershell
php artisan db:seed
```

---

## 7. Verificación final del arranque

```powershell
php artisan serve
```

Abrir en el navegador: `http://127.0.0.1:8000`

**Criterios de éxito (todos obligatorios):**

- [ ] `php -v` responde **8.3.x**
- [ ] `php -m` incluye **pdo_pgsql, pgsql, mbstring, openssl, curl, fileinfo, zip**
- [ ] `composer --version` responde **2.x**
- [ ] Base de datos **sushixpress** creada + usuario **sushixpress** con acceso
- [ ] `composer create-project` completado en `D:\Proyectos\sushixpress`
- [ ] `.env` conectado a PostgreSQL
- [ ] `php artisan migrate:fresh` ejecuta migraciones sin error
- [ ] Login de Laravel Breeze funciona (registro/ingreso)
- [ ] Seeder de roles/admin/sucursal/mesas ejecutado
- [ ] `php artisan serve` levanta el sitio en `127.0.0.1:8000`
- [ ] Git inicializado en `D:\Proyectos\sushixpress`

---

## 8. Decisiones a reportar al usuario

Al finalizar, este runbook dejó definidos:

| Dato | Valor |
|------|-------|
| Ruta de PHP | `D:\Proyectos\tools\php83` |
| Ruta de Composer | (según opción usada) |
| Usuario BD | `sushixpress` |
| Base de datos | `sushixpress` |
| Credenciales de BD | definidas en `.env` (no commitear) |
| Usuario admin inicial | según `AdminUserSeeder` |

> **Advertencia de seguridad:** `.env` contiene credenciales y **no debe subirse al repositorio**. Verificar que `.gitignore` lo excluya (Laravel lo incluye por defecto).

---

## Apéndice: solución de problemas comunes

| Problema | Causa probable | Solución |
|----------|----------------|----------|
| `php` no reconocido | PATH no recargado | Nueva terminal; verificar PATH de usuario |
| `Composer not found` | Instalación sin PATH | Reinstalar marcando "agregar al PATH"; nueva terminal |
| Migrate falla "could not find driver" | Falta `pdo_pgsql` | Habilitar `extension=pdo_pgsql` en php.ini; reiniciar terminal |
| `connection refused 5432` | PostgreSQL detenido | `Start-Service postgresql-x64-18` |
| Password auth failed | Contraseña incorrecta en `.env` | Corregir `DB_PASSWORD`; usar la de Paso 3 |
| ZIP de PHP 404 | URL de releases desactualizada | Usar el regex del Paso 1.1 o el navegador en `windows.php.net/downloads/` |
| `composer create-project` lento | Red/proxy | Reintentar; configurar proxy si aplica |