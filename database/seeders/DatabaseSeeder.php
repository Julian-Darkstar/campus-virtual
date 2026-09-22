<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            IdentityDemoSeeder::class,
            RoleSeeder::class,
            StudentCatalogSeeder::class,
            DemoAccountsSeeder::class,
        ]);

        // Con /roles/assign ahora restringido a administradores, se
        // necesita al menos un admin sembrado para poder operar y
        // asignar el resto de los roles desde la UI. En un entorno
        // real esto se haría con un comando/artisan protegido, no con
        // el seeder de desarrollo.
        $user->assignRole(\App\Models\Role::ADMIN);
    }
}
