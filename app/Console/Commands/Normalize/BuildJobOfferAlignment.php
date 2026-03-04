<?php

namespace App\Console\Commands\Normalize;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildJobOfferAlignment extends Command
{
    protected $signature = 'jobs:build-alignment {--truncate}';

    protected $description = 'Construye la tabla job_offer_alignment basada en tecnologías, lenguajes y metodologías';

    public function handle()
    {
        if ($this->option('truncate')) {
            DB::table('job_offer_alignment')->truncate();
            $this->info('Tabla limpiada');
        }

        $this->info('Procesando tecnologías...');

        DB::statement("
            INSERT IGNORE INTO job_offer_alignment
            (job_offer_id, career_id, source, created_at, updated_at)

            SELECT
                tj.job_offer_id,
                cc.career_id,
                'technology',
                NOW(),
                NOW()

            FROM technology_job tj
            JOIN course_technology ct
                ON ct.technology_id = tj.technology_id
            JOIN career_course cc
                ON cc.course_id = ct.course_id
        ");

        $this->info('Procesando lenguajes...');

        DB::statement("
            INSERT IGNORE INTO job_offer_alignment
            (job_offer_id, career_id, source, created_at, updated_at)

            SELECT
                lj.job_offer_id,
                cc.career_id,
                'language',
                NOW(),
                NOW()

            FROM language_job lj
            JOIN course_language cl
                ON cl.language_id = lj.language_id
            JOIN career_course cc
                ON cc.course_id = cl.course_id
        ");

        $this->info('Procesando metodologías...');

        DB::statement("
            INSERT IGNORE INTO job_offer_alignment
            (job_offer_id, career_id, source, created_at, updated_at)

            SELECT
                mj.job_offer_id,
                cc.career_id,
                'methodology',
                NOW(),
                NOW()

            FROM methodology_job mj
            JOIN course_methodology cm
                ON cm.methodology_id = mj.methodology_id
            JOIN career_course cc
                ON cc.course_id = cm.course_id
        ");

        $total = DB::table('job_offer_alignment')->count();

        $this->info("Alineaciones totales: {$total}");
    }
}
