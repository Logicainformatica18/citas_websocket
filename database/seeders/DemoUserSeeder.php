<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios demo con credenciales fijas (uno por rol) + usuarios adicionales.
 * Contraseña de los usuarios demo: "password".
 */
class DemoUserSeeder extends Seeder
{
    use \Database\Seeders\Concerns\OnlyInDemoEnvironments;

    public const DEMO_USERS = [
        ['admin@demo.com', 'Administrador', 'Ramírez', 'Torres', 'Carlos Alberto', 'M'],
        ['atc@demo.com', 'ATC', 'Quispe', 'Huamán', 'Lucía Fernanda', 'F'],
        ['oficina@demo.com', 'ATC_Oficina', 'Mendoza', 'Rojas', 'Jorge Luis', 'M'],
        ['asistente@demo.com', 'Asistente', 'Flores', 'Castillo', 'María José', 'F'],
    ];

    public function run(): void
    {
        if ($this->skipOutsideDemo()) {
            return;
        }

        foreach (self::DEMO_USERS as $i => [$email, $role, $ap1, $ap2, $nombres, $sex]) {
            UserFactory::new()->create([
                'email' => $email,
                'firstname' => $ap1,
                'lastname' => $ap2,
                'names' => $nombres,
                'sex' => $sex,
                'dni' => str_pad((string) (70000001 + $i), 8, '0', STR_PAD_LEFT),
            ])->assignRole($role);
        }

        // Usuarios que ya creaba el seeder original (se conservan).
        User::create([
            'dni' => '44444444', 'firstname' => 'Cardenas', 'lastname' => 'Aquino',
            'names' => 'Anthony Robert', 'password' => Hash::make('sdc123456'),
            'datebirth' => '2000-10-10', 'cellphone' => '999999999', 'sex' => 'M',
            'email' => 'admin@gmail.com', 'email_verified_at' => now(),
        ])->assignRole('Administrador');

        User::create([
            'dni' => '44444445', 'firstname' => 'Cardenas1', 'lastname' => 'Aquino1',
            'names' => 'Anthony Robert1', 'password' => Hash::make('sdc123456'),
            'datebirth' => '2000-10-10', 'cellphone' => '999999999', 'sex' => 'M',
            'email' => 'admin1@gmail.com', 'email_verified_at' => now(),
        ]);

        // 16 usuarios adicionales repartidos entre roles.
        $reparto = ['ATC' => 7, 'ATC_Oficina' => 5, 'Asistente' => 3, 'Administrador' => 1];
        foreach ($reparto as $role => $n) {
            UserFactory::new()->count($n)->create()->each->assignRole($role);
        }
    }
}
