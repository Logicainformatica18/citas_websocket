<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 *
 * El modelo no usa HasFactory: instanciar con ClientFactory::new().
 * La columna "T.Doc." no se rellena aquí porque el query builder no puede
 * escribir columnas con puntos (ver ClientSeeder).
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /** Distritos de Lima con su ubigeo INEI. */
    private const DISTRITOS = [
        ['150101', 'Lima', 'Lima', 'Lima'],
        ['150110', 'Lima', 'Lima', 'Comas'],
        ['150106', 'Lima', 'Lima', 'Carabayllo'],
        ['150132', 'Lima', 'Lima', 'San Juan de Lurigancho'],
        ['150135', 'Lima', 'Lima', 'San Martín de Porres'],
        ['150142', 'Lima', 'Lima', 'Villa El Salvador'],
        ['150133', 'Lima', 'Lima', 'San Juan de Miraflores'],
        ['150103', 'Lima', 'Lima', 'Ate'],
        ['150119', 'Lima', 'Lima', 'Lurín'],
        ['150125', 'Lima', 'Lima', 'Puente Piedra'],
        ['150501', 'Lima', 'Cañete', 'San Vicente de Cañete'],
        ['150801', 'Lima', 'Huaura', 'Huacho'],
        ['070101', 'Callao', 'Callao', 'Callao'],
    ];

    public function definition(): array
    {
        $faker = fake('es_ES');
        [$ubigeo, $dep, $prov, $dist] = $faker->randomElement(self::DISTRITOS);
        $nombre = $faker->firstName();
        $apellidos = $faker->lastName() . ' ' . $faker->lastName();
        $dni = $faker->unique()->numerify('########');

        return [
            'id_slin' => (string) $faker->unique()->numberBetween(10000, 99999),
            'Codigo' => 'CLI-' . $faker->unique()->numerify('#####'),
            'Razon_Social' => mb_strtoupper("{$apellidos} {$nombre}"),
            'DNI' => $dni,
            'NIT' => $dni,
            'Direccion' => $faker->randomElement(['Av.', 'Jr.', 'Calle', 'Psje.']) . ' ' . $faker->lastName() . ' ' . $faker->buildingNumber(),
            'Ubigeo' => $ubigeo,
            'Departamento' => $dep,
            'Provincia' => $prov,
            'Distrito' => $dist,
            'Telefono' => '9' . $faker->numerify('########'),
            'Email' => $faker->unique()->safeEmail(),
            'clave' => null,
            'c_clave' => false,
            'ref_telefono1' => $faker->boolean(60) ? '9' . $faker->numerify('########') : null,
            'ref_telefono2' => $faker->boolean(25) ? '9' . $faker->numerify('########') : null,
            'comentario' => $faker->boolean(20) ? $faker->randomElement([
                'Prefiere contacto por WhatsApp.',
                'Llamar después de las 6 p. m.',
                'Cliente referido por otro propietario.',
                'Solicitó estado de cuenta en la última visita.',
            ]) : null,
            'canal' => $faker->randomElement(['whatsapp', 'presencial', 'telefono', 'web']),
            'habilitado' => $faker->boolean(95),
            'id_rol' => 2,
        ];
    }
}
