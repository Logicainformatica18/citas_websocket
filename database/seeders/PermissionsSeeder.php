<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 👇 Solo permisos relevantes a scrapings y usuarios
        Permission::create(['name' => 'scrapings.ver']);
        Permission::create(['name' => 'usuarios.ver']);
        Permission::create(['name' => 'roles.ver']);
        Permission::create(['name' => 'administrar']); // permiso global

        // Rol Asistente (solo puede ver scrapings)
        $roleAsistente = Role::create(['name' => 'Asistente']);
        $roleAsistente->syncPermissions(['scrapings.ver']);

        // Rol Administrador (puede todo)
        $roleAdmin = Role::create(['name' => 'Administrador']);
        $roleAdmin->syncPermissions(Permission::all());

        // Usuario administrador por defecto
        $user1 = User::create([
            'dni'        => '44444444',
            'firstname'  => 'Cardenas',
            'lastname'   => 'Aquino',
            'names'      => 'Anthony Robert',
            'password'   => Hash::make('sdc123456'),
            'datebirth'  => '2000-10-10',
            'cellphone'  => '999999999',
            'sex'        => 'M',
            'email'      => 'admin@gmail.com',
        ]);
        $user1->assignRole('Administrador');

        // Usuario asistente de prueba
        $user2 = User::create([
            'dni'        => '55555555',
            'firstname'  => 'Asistente',
            'lastname'   => 'Demo',
            'names'      => 'Usuario Demo',
            'password'   => Hash::make('sdc123456'),
            'datebirth'  => '2000-01-01',
            'cellphone'  => '999999998',
            'sex'        => 'M',
            'email'      => 'asistente@gmail.com',
        ]);
        $user2->assignRole('Asistente');
    }
}
