<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizeGeoRegions extends Command
{
    protected $signature = 'normalize:geo-regions {--dry-run}';

    protected $description = 'Normaliza country y asigna región macro cuando está como Desconocido';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🌍 Iniciando normalización geográfica');
        if ($dryRun) {
            $this->warn('⚠️ DRY RUN activado — no se aplicarán cambios');
        }

        DB::beginTransaction();

        try {
            /* =====================================================
               1️⃣ NORMALIZAR COUNTRY
            ===================================================== */

            $this->executeUpdate("
                UPDATE job_offers
                SET country = 'United States'
                WHERE country IN ('US','USA','Estados Unidos')
            ", $dryRun);

            $this->executeUpdate("
                UPDATE job_offers
                SET country = 'United Kingdom'
                WHERE country IN ('Reino Unido')
            ", $dryRun);

            $this->executeUpdate("
                UPDATE job_offers
                SET country = 'Canada'
                WHERE country IN ('CANADA','Canadá')
            ", $dryRun);

            $this->executeUpdate("
                UPDATE job_offers
                SET country = 'India'
                WHERE UPPER(country) = 'INDIA'
            ", $dryRun);

            /* =====================================================
               2️⃣ ASIGNAR REGIÓN MACRO (SOLO DESCONOCIDO)
            ===================================================== */

            $this->executeUpdate("
                UPDATE job_offers
                SET region = 'Norteamérica'
                WHERE  country IN ('United States','Canada')
            ", $dryRun);

            $this->executeUpdate("
                UPDATE job_offers
                SET region = 'Europa'
                WHERE  country = 'United Kingdom'
            ", $dryRun);

            $this->executeUpdate("
                UPDATE job_offers
                SET region = 'Asia'
                WHERE  country = 'India'
            ", $dryRun);

            /* =====================================================
               3️⃣ LIMPIEZA FINAL (TRIM)
            ===================================================== */

            $this->executeUpdate("
                UPDATE job_offers
                SET country = TRIM(country),
                    region  = TRIM(region)
            ", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET country = 'United States'
    WHERE country IN (
        'Ny','Il','Ga','Tx','Va','Wa','Md','Az','Pa','Oh','Fl','Nj'
    )
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET country = 'United States'
    WHERE LOWER(country) LIKE '%county%'
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Norteamérica'
    WHERE  country = 'United States'
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'United Kingdom'
    WHERE country = 'UK'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Europa'
    WHERE  country IN (
        'United Kingdom',
        'Ireland',
        'Spain',
        'Poland',
        'Ukraine'
      )
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'South Africa'
    WHERE country IN ('SOUTH AFRICA','Sudáfrica')
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Sierra Leone'
    WHERE LOWER(country) = 'sierra leone'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'África'
    WHERE  country IN ('South Africa','Sierra Leone')
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Singapore'
    WHERE UPPER(country) = 'SINGAPORE'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Asia'
    WHERE  country IN ('Singapore')
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Australia'
    WHERE UPPER(country) = 'AUSTRALIA'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Oceanía'
    WHERE  country = 'Australia'
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Brazil'
    WHERE country = 'BRASIL'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Latinoamérica'
    WHERE  country IN ('Brazil','Argentina')
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'United States'
    WHERE country IN (
        'Nc','Al','Tn','Hi','Mn','Nm','Or','Mo','Ct','Wi',
        'Ia','Ut','Mi','Ne','Vt'
    )
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Norteamérica'
    WHERE  country = 'United States'
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Poland'
    WHERE country = 'POLSKA'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Europa'
    WHERE  country = 'Poland'
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Spain'
    WHERE country IN ('ESPAñA')
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Europa'
    WHERE  country = 'Spain'
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Japan'
    WHERE country IN ('Japón')
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Asia'
    WHERE  country IN ('Japan','Pakistan','Malaysia')
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Europa'
    WHERE  country IN (
        'France','Greece','Croatia','Lithuania','Cyprus'
      )
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'United Arab Emirates'
    WHERE LOWER(country) = 'united arab emirates'
", $dryRun);

 $this->executeUpdate("
    UPDATE job_offers
    SET region = 'Asia'
    WHERE  country IN ('United Arab Emirates','Saudi Arabia')
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Caribe'
    WHERE  country IN ('Montserrat','Trinidad and Tobago')
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Europa'
    WHERE region = 'Desconocido'
      AND city LIKE '%CET%'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'África'
    WHERE  country = 'África'
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'United States'
    WHERE country IN (
        'Ok','Ks','Sc','Nh','Id','Ms','Nv','Ky','Mt','Ri','Sd'
    )
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Norteamérica'
    WHERE  country = 'United States'
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET country = 'France'
    WHERE country = 'Francia'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Germany'
    WHERE country IN ('DEUTSCHLAND','Alemania')
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Poland'
    WHERE country = 'Polonia'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Italy'
    WHERE country = 'ITALIA'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET country = 'Belgium'
    WHERE country = 'Bélgica'
", $dryRun);

$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Europa'
    WHERE  country IN (
        'France','Germany','Poland','Italy','Belgium',
        'Romania','Serbia','Hungary','Portugal','Finland','Estonia'
      )
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Asia'
    WHERE  country IN (
        'Philippines','Pakistan','Japan','Taiwan',
        'Thailand','Indonesia','Malaysia'
      )
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Asia'
    WHERE  country IN (
        'Israel',
        'Saudi Arabia',
        'United Arab Emirates',
        'Turkey',
        'Armenia',
        'Pakistan',
        'Japan',
        'Taiwan',
        'Thailand',
        'Indonesia',
        'Malaysia',
        'Philippines'
      )
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Europa'
    WHERE  country IN (
        'France',
        'Germany',
        'Poland',
        'Italy',
        'Belgium',
        'Romania',
        'Serbia',
        'Hungary',
        'Portugal',
        'Finland',
        'Estonia',
        'Spain',
        'Greece',
        'Croatia',
        'Lithuania',
        'Cyprus'
      )
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET region = 'África'
    WHERE  country IN (
        'South Africa',
        'Sierra Leone'
      )
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Latinoamérica'
    WHERE  country IN (
        'Argentina',
        'Brazil',
        'Colombia',
        'Panama',
        'Costa Rica'
      )
", $dryRun);
$this->executeUpdate("
    UPDATE job_offers
    SET region = 'Caribe'
    WHERE  country IN (
        'Montserrat',
        'Trinidad and Tobago',
        'Saint Vincent and the Grenadines'
      )
", $dryRun);

            
            if ($dryRun) {
                DB::rollBack();
                $this->info('🧪 DRY RUN finalizado — sin cambios aplicados');
            } else {
                DB::commit();
                $this->info('✅ Normalización geográfica aplicada con éxito');
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('❌ Error en normalize:geo-regions', [
                'message' => $e->getMessage(),
            ]);

            $this->error('❌ Error durante la normalización: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /* =====================================================
       Helper seguro para ejecutar updates
    ===================================================== */
    private function executeUpdate(string $sql, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line('🧪 DRY RUN → ' . trim($sql));
            return;
        }

        $affected = DB::update($sql);

        $this->line("✔️ {$affected} filas afectadas");
    }
}
