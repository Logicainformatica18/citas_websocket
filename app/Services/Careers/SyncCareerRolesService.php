<?php

namespace App\Services\Careers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SyncCareerRolesService
{
    public function run(): array
    {
        DB::beginTransaction();

        try {
            // 1️⃣ Obtener última fecha procesada
            $state = DB::table('career_role_sync_state')->lockForUpdate()->first();

            $lastProcessed = $state?->last_processed_at
                ? Carbon::parse($state->last_processed_at)
                : null;

            // 2️⃣ Traer nuevas ofertas
            $jobsQuery = DB::table('job_offers')
                ->select('id', 'title', 'published_at');

            if ($lastProcessed) {
                $jobsQuery->where('published_at', '>', $lastProcessed);
            }

            $jobs = $jobsQuery->orderBy('published_at')->get();

            if ($jobs->isEmpty()) {
                DB::rollBack();

                return [
                    'inserted' => 0,
                    'message' => 'No hay nuevas ofertas para procesar',
                ];
            }

            // 3️⃣ Roles existentes
            $roles = DB::table('tech_positions')
                ->select('id', 'position_name')
                ->where('active', 1)
                ->get();

            $inserted = 0;
            $maxDate = $lastProcessed;

            foreach ($jobs as $job) {
                $title = mb_strtolower($job->title);

                foreach ($roles as $role) {
                    if (str_contains($title, mb_strtolower($role->position_name))) {
                        DB::table('tech_position_job_offer')->insertOrIgnore([
                            'tech_position_id' => $role->id,
                            'job_offer_id'     => $job->id,
                            'created_at'       => now(),
                        ]);

                        $inserted++;
                    }
                }

                $maxDate = $job->published_at;
            }

            // 4️⃣ Actualizar checkpoint
            DB::table('career_role_sync_state')->update([
                'last_processed_at' => $maxDate,
                'updated_at'        => now(),
            ]);

            DB::commit();

            return [
                'inserted' => $inserted,
                'last_processed_at' => $maxDate,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
