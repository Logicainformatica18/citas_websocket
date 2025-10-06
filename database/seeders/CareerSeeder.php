<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CareerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $careers = [
            [
                'name' => 'Arquitectura de Datos',
                'faculty' => 'Ingeniería y Tecnología',
                'degree_title' => 'Bachiller en Arquitectura de Datos',
                'duration_years' => 3,
                'description' => 'Forma profesionales capaces de diseñar, gestionar y optimizar arquitecturas de datos que soportan la toma de decisiones en organizaciones modernas.',
            ],
            [
                'name' => 'Ciberseguridad',
                'faculty' => 'Ingeniería y Tecnología',
                'degree_title' => 'Bachiller en Ciberseguridad',
                'duration_years' => 3,
                'description' => 'Desarrolla especialistas en protección de sistemas informáticos, redes y datos frente a amenazas digitales.',
            ],
            [
                'name' => 'Ciencia de Datos e Inteligencia Artificial',
                'faculty' => 'Ingeniería y Tecnología',
                'degree_title' => 'Bachiller en Ciencia de Datos e Inteligencia Artificial',
                'duration_years' => 3,
                'description' => 'Capacita en análisis predictivo, machine learning y automatización inteligente para resolver problemas complejos en distintos sectores.',
            ],
            [
                'name' => 'Computación en la Nube',
                'faculty' => 'Ingeniería y Tecnología',
                'degree_title' => 'Bachiller en Computación en la Nube',
                'duration_years' => 3,
                'description' => 'Prepara profesionales en infraestructura cloud, servicios distribuidos y gestión de recursos escalables en plataformas como AWS, Azure o Google Cloud.',
            ],
            [
                'name' => 'Desarrollo de Software',
                'faculty' => 'Ingeniería y Tecnología',
                'degree_title' => 'Bachiller en Desarrollo de Software',
                'duration_years' => 3,
                'description' => 'Enfocado en el diseño, desarrollo y mantenimiento de aplicaciones modernas con metodologías ágiles y herramientas DevOps.',
            ],
            [
                'name' => 'Diseño de Medios Interactivos (UX)',
                'faculty' => 'Diseño e Innovación',
                'degree_title' => 'Bachiller en Diseño de Experiencia de Usuario (UX)',
                'duration_years' => 3,
                'description' => 'Forma diseñadores centrados en el usuario que crean experiencias digitales efectivas y atractivas mediante investigación y prototipado.',
            ],
            [
                'name' => 'Diseño y Desarrollo de Videojuegos',
                'faculty' => 'Diseño e Innovación',
                'degree_title' => 'Bachiller en Diseño y Desarrollo de Videojuegos',
                'duration_years' => 3,
                'description' => 'Combina creatividad y tecnología para formar profesionales capaces de crear videojuegos inmersivos y narrativas interactivas.',
            ],
            [
                'name' => 'Redes y Comunicaciones',
                'faculty' => 'Ingeniería y Tecnología',
                'degree_title' => 'Bachiller en Redes y Comunicaciones',
                'duration_years' => 3,
                'description' => 'Desarrolla expertos en infraestructura de redes, conectividad y telecomunicaciones con dominio de tecnologías modernas.',
            ],
            [
                'name' => 'Sistemas de Información',
                'faculty' => 'Ingeniería y Tecnología',
                'degree_title' => 'Bachiller en Sistemas de Información',
                'duration_years' => 3,
                'description' => 'Integra conocimientos de gestión y tecnología para optimizar procesos organizacionales mediante soluciones de información eficientes.',
            ],
        ];

        foreach ($careers as $career) {
            DB::table('careers')->insert([
                'name' => $career['name'],
                'slug' => Str::slug($career['name']),
                'faculty' => $career['faculty'],
                'degree_title' => $career['degree_title'],
                'duration_years' => $career['duration_years'],
                'description' => $career['description'],
                'detail' => null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
