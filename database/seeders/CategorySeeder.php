<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Catalogo provisional de categorias. El catalogo definitivo lo define
     * negocio (PD-19b).
     */
    public function run(): void
    {
        $categories = [
            'Hardware' => 'Equipos, periféricos e impresoras',
            'Software' => 'Aplicaciones e instalaciones',
            'Redes' => 'Conectividad e internet',
            'Accesos' => 'Cuentas, contraseñas y permisos',
            'Otro' => 'Solicitudes que no encajan en otra categoría',
        ];

        foreach ($categories as $name => $description) {
            Category::firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
