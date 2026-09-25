# GENX-03

Aplicacion web construida con Laravel 13, Filament 5 y Filament Shield. El panel administrativo permite gestionar usuarios, roles y permisos.

## Requisitos

Antes de comenzar, instala o verifica las siguientes herramientas:

- Windows 10 u 11.
- Git.
- PHP 8.3 o superior.
- Composer 2.
- Node.js 20.19 o superior, o Node.js 22.12 o superior.
- npm.
- Laravel Herd para Windows (recomendado).

Puedes comprobar las versiones desde PowerShell:

```powershell
git --version
php -v
composer --version
node -v
npm -v
```

Laravel Herd incluye un entorno local con PHP y el servidor web necesario para ejecutar aplicaciones Laravel. Descarga Herd desde [herd.laravel.com/windows](https://herd.laravel.com/windows) e instalalo con las opciones predeterminadas.

## Descargar el proyecto

Clona el repositorio y entra en su carpeta:

```powershell
git clone URL_DEL_REPOSITORIO
Set-Location GEX-ANT-03-MAR-Z
```

Reemplaza `URL_DEL_REPOSITORIO` por la URL real del repositorio. Si ya tienes el proyecto descargado, solo abre PowerShell en la carpeta del proyecto.

## Configurar el proyecto

### 1. Instalar dependencias de PHP

```powershell
composer install
```

### 2. Crear el archivo de entorno

En PowerShell:

```powershell
Copy-Item .env.example .env
```

No subas el archivo `.env` al repositorio. Contiene configuracion local y puede contener credenciales.

### 3. Crear la base de datos SQLite

Este proyecto usa SQLite por defecto. Crea el archivo de base de datos si todavía no existe:

```powershell
New-Item database\database.sqlite -ItemType File -Force
```

Genera la clave de la aplicacion:

```powershell
php artisan key:generate
```

### 4. Instalar dependencias de JavaScript

```powershell
npm install
```

## Migraciones, usuario y permisos

Ejecuta las migraciones:

```powershell
php artisan migrate
```

Carga el usuario inicial definido en el seeder:

```powershell
php artisan db:seed
```

Genera los permisos y las politicas de Filament Shield:

```powershell
php artisan shield:generate --all
```

Asigna el rol de superadministrador al usuario con ID `1`:

```powershell
php artisan shield:super-admin --user=1
```

Si el usuario administrador tiene otro ID, consulta los usuarios con Tinker y cambia el valor del comando:

```powershell
php artisan tinker --execute="dump(App\Models\User::query()->get(['id', 'name', 'email'])->toArray());"
```

Limpia las caches despues de cambiar la configuracion o los permisos:

```powershell
php artisan optimize:clear
```

### Usuario inicial

El seeder crea estas credenciales de desarrollo:

| Campo | Valor |
| --- | --- |
| Correo | `test@example.com` |
| Contraseña | `password` |

Estas credenciales son solo para desarrollo. Cambia la contraseña antes de utilizar el proyecto en un entorno real.

## Ejecutar con Laravel Herd

1. Abre Herd y confirma que PHP 8.3 o superior esté seleccionado.
2. Desde PowerShell, ubícate en la carpeta del proyecto.
3. Registra la carpeta en Herd:

```powershell
herd link
```

4. Abre en Herd la URL local asignada al proyecto. Normalmente tendrá un dominio `.test` basado en el nombre de la carpeta.
5. En otra terminal, inicia Vite para compilar los recursos durante el desarrollo:

```powershell
npm run dev
```

El panel administrativo está disponible en:

```text
/admin/login
```

También puedes abrir la raíz del proyecto; `/` redirige automáticamente al inicio de sesión del panel.

## Ejecutar sin Herd

Como alternativa, puedes utilizar el servidor de desarrollo de Laravel:

```powershell
composer run dev
```

Este comando inicia el servidor de Laravel, Vite y los servicios de desarrollo configurados en `composer.json`. Luego visita [http://localhost:8000/admin/login](http://localhost:8000/admin/login).

Para generar los recursos frontend sin modo desarrollo:

```powershell
npm run build
```

## Traducciones de Shield

Si necesitas generar las etiquetas traducibles de permisos en ingles, asegúrate de que exista la carpeta `lang/en`:

```powershell
New-Item lang\en -ItemType Directory -Force
php artisan shield:translation en --panel=admin
```

Cuando el comando pregunte dónde guardar el archivo, acepta la ruta propuesta.

## Comandos utiles

```powershell
# Ver el estado de las migraciones
php artisan migrate:status

# Ejecutar las pruebas
php artisan test

# Ver las rutas del panel administrativo
php artisan route:list --path=admin

# Limpiar caches de Laravel y Filament
php artisan optimize:clear
php artisan filament:optimize-clear
```

Para reiniciar completamente la base de datos de desarrollo y volver a cargar los datos:

```powershell
php artisan migrate:fresh --seed
php artisan shield:generate --all
php artisan shield:super-admin --user=1
```

`migrate:fresh` elimina todas las tablas. No lo ejecutes en una base de datos con información importante.

## Estructura principal

```text
app/Filament/Resources/    Recursos del panel administrativo
app/Models/                Modelos Eloquent
app/Policies/              Politicas de autorizacion
config/filament-shield.php Configuracion de Shield
database/migrations/       Migraciones de la base de datos
database/seeders/          Datos iniciales
resources/                 Archivos frontend y vistas
routes/                    Rutas de la aplicacion
```

## Solucion de problemas

### `php` o `composer` no se reconoce

Cierra y vuelve a abrir PowerShell despues de instalar Herd. Comprueba que Herd esté activo y que sus ejecutables estén disponibles en el `PATH` del sistema.

### No aparece Usuarios o Shield en el menú

Ejecuta:

```powershell
php artisan shield:generate --all
php artisan shield:super-admin --user=1
php artisan optimize:clear
```

Después cierra sesión y vuelve a entrar al panel. El panel debe tener registrado `FilamentShieldPlugin` en `app/Providers/Filament/AdminPanelProvider.php`.

### Error al generar la traducción de Shield

El error `Failed to open stream: No such file or directory` indica que no existe la carpeta de destino. Créala y repite el comando:

```powershell
New-Item lang\en -ItemType Directory -Force
php artisan shield:translation en --panel=admin
```

## Licencia

Este proyecto utiliza Laravel y sus dependencias bajo las licencias correspondientes de cada paquete.
