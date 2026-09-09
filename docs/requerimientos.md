# Requerimientos para la Preparación del Proyecto

Lista de requisitos de entorno, software, hardware y datos necesarios antes de iniciar la **Fase 0 (Cimientos)** del proyecto Laravel + PostgreSQL.

---

## 1. Software del equipo de desarrollo (máquina)

| # | Requisito | Versión mínima | Estado |
|---|-----------|----------------|--------|
| 1 | **PHP** | 8.2+ (recomendado 8.3) | ⬜ Pendiente |
| 2 | **Composer** | 2.x | ⬜ Pendiente |
| 3 | **PostgreSQL** | 15+ (instalado: **18 — Running** ✅) | ✅ |
| 4 | **Git** | 2.x (instalado **2.55.0** ✅) | ✅ |
| 5 | **Node.js + npm** | Node 18+ (instalado **24.16.0** ✅) | ✅ |
| 6 | Editor/IDE | VS Code, PHPStorm u otro | ⬜ Confirmar |

### Extensiones PHP requeridas (se habilitan en `php.ini`)

Indispensables para Laravel y PostgreSQL:

- `pgsql` y `pdo_pgsql` — conexión a PostgreSQL
- `mbstring` — cadenas y UTF-8
- `openssl` — seguridad y encriptación
- `curl` — peticiones HTTP
- `fileinfo` — detección de archivos (necesaria en Laravel)
- `zip` — instalación de paquetes vía Composer
- `gd` o `imagick` — imágenes de productos (menú)
- `sqlite3` / `pdo_sqlite` — pruebas con BD ligera
- `intl` — internacionalización / fechas (recomendada)

> **Nota instalación manual:** PHP se puede descargar como ZIP desde `https://windows.php.net/downloads/` (build **x64, Thread Safe** para servidor local con `php artisan serve`), extraer a una carpeta (ej. `D:\Proyectos\tools\php83`), renombrar `php.ini-development` a `php.ini`, habilitar las extensiones y agregar la carpeta al **PATH** del sistema (config requerida).

---

## 2. Base de datos (PostgreSQL)

| # | Requisito | Estado |
|---|-----------|--------|
| 1 | Servicio PostgreSQL en ejecución | ✅ Running (postgresql-x64-18) |
| 2 | Usuario de BD con privilegios (ej. `sushixpress`) + contraseña | ⬜ Crear |
| 3 | Base de datos `sushixpress` creada | ⬜ Crear |
| 4 | Acceso del servidor a la puerta 5432 | ⬜ Verificar |
| 5 | Herramienta de administración (pgAdmin incluido con PostgreSQL) | ✅ |

**Credenciales a definir y guardar en `.env` (nunca en el código):**

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sushixpress
DB_USERNAME=sushixpress
DB_PASSWORD=********
```

---

## 3. Hardware y dispositivos en el restaurante (para operación)

| Dispositivo | Cantidad sugerida | Uso |
|-------------|-------------------|-----|
| Tablets táctiles (10"+ Android/iPad) | 2–4 | POS cajero, toma de pedidos mesero |
| Tablet montada o PC táctil (cocina) | 1–2 | KDS (pantalla de cocina) |
| Celulares (repartidores) | según repartidores | Delivery / aprobación de estado |
| Impresora térmica 80mm (caja) | 1 | Tickets de venta / facturas |
| Impresoras térmicas de comanda (cocina/sushi/barra) | 1 por área | Comandas |
| Router / red local estable (Wi-Fi) | — | Tablets, impresoras de red |
| Servidor / PC de caja (opcional dedicado) | 1 | Base de datos en producción |

**Tipos de conexión de impresoras a soportar (confirmar las del cliente):**
- ⬜ USB local (PC de caja)
- ⬜ Red / IP (impresoras Epson/Star térmicas)
- ⬜ Bluetooth (tablets)

---

## 4. Datos de configuración que debe entregar el cliente

Para cargar el sistema en Fase 0/1:

### Restaurante
- [ ] Nombre legal y nombre comercial
- [ ] Dirección, teléfono, horarios
- [ ] NIT/RUC y datos fiscales (para ticket/factura)
- [ ] Número de sucursales (1 inicial)

### Salón
- [ ] Lista de mesas (número/zona: salón, barra, terraza) y capacidad
- [ ] Zonas del local

### Menú (para cargar catálogo)
- [ ] Categorías (Rolls, Nigiri, Sushi, Entradas, Bebidas, Postres, etc.)
- [ ] Productos: nombre, precio, descripción, foto, alérgenos
- [ ] Modificadores/opciones (ej. "sin sésamo", "huevo extra")

### Operación y contabilidad
- [ ] Tipos de pago aceptados (efectivo/tarjeta/mixto)
- [ ] Porcentaje de propina por defecto (si aplica)
- [ ] Reglas de descuento máximo permitido
- [ ] Cuentas contables / categorías de gasto a usar
- [ ] Proveedores de insumos (para Fase 3)

### Acceso
- [ ] Lista inicial de usuarios y roles (admin, gerente, cajeros, meseros, cocina)
- [ ] Personal que operará tablets/POS (para capacitación)

---

## 5. Configuración del proyecto (se hará en Fase 0)

- [ ] Inicializar repositorio Git en `D:\Proyectos\sushixpress`
- [ ] `composer create-project` de Laravel (versión 13)
- [ ] Configurar `.env` con PostgreSQL
- [ ] Ejecutar migraciones base + seeder de roles y admin
- [ ] Verificar `php artisan serve` + vista de bienvenida
- [ ] Definir estructura de carpetas por módulo
- [ ] Autenticación y permisos mínimos (login de Admin)

---

## 6. Entorno de producción (plan a futuro, no bloquea desarrollo)

| Aspecto | Opciones sugeridas |
|---------|--------------------|
| Servidor | VPS Linux (Ubuntu), o servidor local Windows/Linux |
| Web server | Nginx + PHP-FPM (recomendado) o Apache |
| HTTPS | Certificado SSL (Let's Encrypt) para acceso web seguro |
| Cola de trabajos | Redis o cola de base de datos (impresión/notificaciones) |
| Programador de tareas | Cron (reportes programados, recordatorios) |
| Respaldo | Copia automática de la base de datos (pg_dump) |

---

## 7. Checklist general de preparación

- [ ] PHP y Composer instalados y verificados (`php -v`, `composer -V`)
- [ ] Extensiones PHP habilitadas (pgsql, pdo_pgsql, mbstring, openssl, curl, fileinfo, zip)
- [ ] Usuario y base de datos PostgreSQL creados
- [ ] Git configurado en el proyecto
- [ ] Datos del cliente recopilados (sección 4)
- [ ] Dispositivos de red disponibles para probar impresión
- [ ] Plan de usuarios/roles definido

---

> **Próximo paso:** cuando apruebe la instalación, proceder en orden: **(1)** instalar PHP + Composer, **(2)** crear usuario/BD en PostgreSQL, **(3)** generar el proyecto Laravel con `composer create-project`, **(4)** verificar arranque del sistema. La lista de esta sección 4 (datos del cliente) se recoge en paralelo para no retrasar la Fase 1.