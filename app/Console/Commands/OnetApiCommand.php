<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Occupation;
use App\Models\OccupationSkill;

class OnetApiCommand extends Command
{
    protected $signature = 'onet:import {--code=}';
    protected $description = 'Importa ocupaciones y habilidades desde la API oficial de O*NET';

    public function handle()
    {
        $baseUrl = 'https://services.onetcenter.org/ws/online/occupations/';
        $username = env('ONET_USER');
        $password = env('ONET_PASS');

        // Si no se pasa código, traer lista general
        if (!$this->option('code')) {
            $this->info("📘 Descargando lista general de ocupaciones...");

            $response = Http::withBasicAuth($username, $password)
                ->accept('application/json')
                ->get($baseUrl);

            if ($response->failed()) {
                $this->error("❌ Error al consultar la API.");
                return;
            }

            $occupations = $response->json()['occupation'] ?? [];

            foreach ($occupations as $occ) {
                $code = $occ['code'] ?? null;
                $title = $occ['title'] ?? null;

                $existing = Occupation::where('code', $code)->exists();

                $occupation = Occupation::updateOrCreate(
                    ['code' => $code],
                    ['title' => $title]
                );

                if (!$existing) $this->info("✅ {$title} agregado ({$code})");
            }

            $this->info("🎉 Importación base completada.");
            return;
        }

        // Si se pasa un código, traer detalles y skills
        $code = $this->option('code');
        $this->info("🔍 Importando detalles para ocupación {$code}...");

        $response = Http::withBasicAuth($username, $password)
            ->accept('application/json')
            ->get("{$baseUrl}{$code}/details");

        if ($response->failed()) {
            $this->error("❌ Error al obtener detalles de {$code}");
            return;
        }

        $details = $response->json();

        $occupation = Occupation::updateOrCreate(
            ['code' => $code],
            [
                'title' => $details['title'] ?? $code,
                'description' => $details['description'] ?? null,
                'category' => $details['category'] ?? null
            ]
        );

        // Skills
        $skillsUrl = "{$baseUrl}{$code}/skills";
        $skillsRes = Http::withBasicAuth($username, $password)
            ->accept('application/json')
            ->get($skillsUrl);

        if ($skillsRes->ok()) {
            $skills = $skillsRes->json()['element'] ?? [];
            foreach ($skills as $skill) {
                OccupationSkill::updateOrCreate(
                    [
                        'occupation_id' => $occupation->id,
                        'skill_name' => $skill['name'] ?? null,
                    ],
                    [
                        'category' => $skill['category'] ?? null,
                        'importance' => $skill['data']['importance'] ?? null,
                        'level' => $skill['data']['level'] ?? null,
                    ]
                );
            }

            $this->info("✅ Skills actualizadas para {$occupation->title}");
        }

        $this->info("🎯 Importación completa para {$occupation->title}");
    }
}
