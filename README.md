# GENX-03

Aplicación web desarrollada con **Laravel 13**, **Filament 5** y **Filament Shield**.

El proyecto cuenta con un panel administrativo para la gestión de usuarios, roles y permisos.

## Requisitos

Antes de comenzar, asegúrate de tener instaladas las siguientes herramientas:

* Windows 10 u 11.
* Git.
* PHP 8.4.1 o superior (las dependencias de Symfony 8 lo exigen).
* Composer 2.
* Node.js 20.19 o superior, o Node.js 22.12 o superior.
* npm.
* Laravel Herd para Windows.

Puedes verificar las versiones desde PowerShell:

```powershell
git --version
php -v
composer --version
node -v
npm -v
```

### Laravel Herd

Se recomienda utilizar **Laravel Herd** para ejecutar el proyecto localmente, ya que proporciona el entorno necesario para trabajar con aplicaciones Laravel en Windows.

Puedes descargarlo desde:

https://herd.laravel.com/windows

Instálalo utilizando las opciones predeterminadas.

---

# Instalación del proyecto

## 1. Clonar el repositorio

Clona el repositorio y accede a la carpeta del proyecto:

```powershell
git clone URL_DEL_REPOSITORIO
cd GEX-ANT-03-MAR-Z
```

Reemplaza `URL_DEL_REPOSITORIO` por la URL correspondiente al repositorio.

Si ya tienes el proyecto descargado, simplemente abre PowerShell dentro de la carpeta del proyecto.

---

## 2. Configurar el archivo `.env`

El proyecto incluye un archivo `.env.example` con la configuración base necesaria.

Crea tu archivo `.env` a partir de este:

```powershell
Copy-Item .env.example .env
```

El archivo `.env` contiene la configuración específica del entorno local, por lo que **no debe subirse al repositorio**.

---

## 3. Instalar las dependencias de PHP

Instala las dependencias del proyecto mediante Composer:

```powershell
composer install
```

---

## 4. Generar la clave de la aplicación

Genera la clave de Laravel:

```powershell
php artisan key:generate
```

---

## 5. Configurar la base de datos

El proyecto está configurado para utilizar **SQLite por defecto**, por lo que no es necesario instalar o configurar un servidor de base de datos para comenzar a trabajar.

### Opción A — SQLite

Si quieres utilizar la configuración predeterminada, crea el archivo de base de datos:

```powershell
New-Item database\database.sqlite -ItemType File -Force
```

Verifica que el archivo `.env` tenga configurado:

```env
DB_CONNECTION=sqlite
```

Con SQLite no es necesario realizar ninguna configuración adicional.

### Opción B — MySQL

Si prefieres utilizar MySQL, puedes modificar la configuración de la base de datos en el archivo `.env`.

Por ejemplo:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=genx_03
DB_USERNAME=root
DB_PASSWORD=
```

Antes de ejecutar las migraciones, asegúrate de que:

* MySQL esté instalado y ejecutándose.
* La base de datos indicada en `DB_DATABASE` exista.
* El usuario y contraseña configurados tengan permisos sobre la base de datos.

> **Nota:** La base de datos es independiente de las dependencias del proyecto. Puedes trabajar con SQLite o configurar MySQL según tu entorno local.

---

## 6. Instalar las dependencias de JavaScript

Instala las dependencias frontend:

```powershell
npm install
```

---

## 7. Ejecutar las migraciones y datos iniciales

Una vez configurada la base de datos, ejecuta las migraciones:

```powershell
php artisan migrate
```

Después, ejecuta los seeders para crear los datos iniciales:

```powershell
php artisan db:seed
```

Los seeders crean los roles (`super_admin`, `solicitante`, `agente`, `coordinador`, `auditor`) con sus permisos, el catálogo inicial de categorías y un usuario de prueba por rol (ver [Credenciales de prueba](#credenciales-de-prueba)).

> Si ya tenías la base de datos creada antes de los ajustes de HU01/HU02, el esquema cambió: ejecuta `php artisan migrate:fresh --seed`.

---

## 8. Configurar Filament Shield

El proyecto utiliza **Filament Shield** para la gestión de roles y permisos. Los permisos de cada rol ya se crean con los seeders (`RolePermissionSeeder` y `ShieldSeeder`), así que no hace falta ningún paso adicional.

Si agregas un Resource nuevo y necesitas generar su policy y sus permisos, usa **siempre** `--ignore-existing-policies`. Sin esa opción, Shield sobrescribe las policies existentes y se pierden las reglas escritas a mano (por ejemplo, que las solicitudes no se pueden editar):

```powershell
php artisan shield:generate --all --ignore-existing-policies
```

Finalmente, limpia la caché de Laravel:

```powershell
php artisan optimize:clear
```

---

# Configuración con Laravel Herd

Una vez instalado y configurado el proyecto, puedes registrarlo en Laravel Herd.

## 1. Abrir Herd

Inicia **Laravel Herd** y verifica que esté utilizando una versión de PHP compatible con el proyecto:

**PHP 8.4.1 o superior.**

## 2. Registrar el proyecto

Desde PowerShell, ubicado dentro de la carpeta del proyecto, ejecuta:

```powershell
herd link
```

Herd asignará un dominio local al proyecto, normalmente utilizando el nombre de la carpeta.

Por ejemplo:

```text
http://gex-ant-03-mar-z.test
```

El dominio exacto puede variar dependiendo del nombre de la carpeta.

## 3. Iniciar Vite

En otra terminal, dentro del proyecto, ejecuta:

```powershell
npm run dev
```

Mantén este proceso ejecutándose mientras trabajas en el proyecto para que Vite compile y actualice los recursos frontend.

---

# Acceder al panel administrativo

Una vez completados todos los pasos anteriores, abre en el navegador:

```text
http://NOMBRE-DEL-PROYECTO.test/admin/login
```

También puedes acceder a la raíz:

```text
http://NOMBRE-DEL-PROYECTO.test
```

La aplicación redirigirá automáticamente al inicio de sesión del panel administrativo.

## Credenciales de prueba

El seeder crea un usuario por rol. La contraseña de todos es `password`:

| Rol           | Correo                    |
| ------------- | ------------------------- |
| `super_admin` | `test@example.com`        |
| `solicitante` | `solicitante@example.com` |
| `agente`      | `agente@example.com`      |
| `coordinador` | `coordinador@example.com` |
| `auditor`     | `auditor@example.com`     |

Un usuario inactivo o sin rol no puede entrar al panel. Los usuarios no se eliminan: se desactivan desde **Administración → Usuarios**.

> **Importante:** Estas credenciales son únicamente para desarrollo local. No deben utilizarse en un entorno de producción.

---

# Flujo rápido de instalación

Si ya tienes todos los requisitos instalados, el proceso básico es:

```powershell
git clone URL_DEL_REPOSITORIO
cd GEX-ANT-03-MAR-Z

Copy-Item .env.example .env

composer install
php artisan key:generate

New-Item database\database.sqlite -ItemType File -Force

npm install

php artisan migrate --seed

php artisan optimize:clear

herd link
npm run dev
```

Después abre:

```text
http://NOMBRE-DEL-PROYECTO.test/admin/login
```

E inicia sesión con:

```text
Correo: test@example.com
Contraseña: password
```

---

# Estructura principal

```text
app/
├── Actions/             Servicios de dominio (una clase por operación de negocio)
├── Enums/               Estados de solicitud y eventos de auditoría
├── Filament/
│   └── Resources/       Recursos del panel administrativo
├── Models/              Modelos Eloquent
├── Policies/            Policies de autorización
└── Support/Audit/       AuditLogger: único punto de escritura de la auditoría

config/
└── filament-shield.php  Configuración de Filament Shield

database/
├── migrations/          Migraciones de la base de datos
└── seeders/             Datos iniciales

docs/
└── plan-tecnico.md      Plan técnico de la plataforma (modelo, roles, flujo y plan por sprint)

lang/es/
└── shield-permissions.php  Etiquetas en español de los permisos propios en la pantalla de Roles

resources/
└── ...                  Recursos frontend y vistas

routes/
└── ...                  Rutas de la aplicación
```

---

# Comandos útiles

### Limpiar caché

```powershell
php artisan optimize:clear
```

### Ver estado de las migraciones

```powershell
php artisan migrate:status
```

### Ejecutar las pruebas

```powershell
php artisan test
```

### Ver las rutas del panel administrativo

```powershell
php artisan route:list --path=admin
```

### Generar los recursos frontend para producción

```powershell
npm run build
```

---

## Reiniciar la base de datos

Si necesitas comenzar nuevamente con una base de datos limpia durante el desarrollo:

```powershell
php artisan migrate:fresh --seed
```

> **Advertencia:** `migrate:fresh` elimina todas las tablas y todos los datos de la base de datos configurada. Utilízalo únicamente en entornos de desarrollo.