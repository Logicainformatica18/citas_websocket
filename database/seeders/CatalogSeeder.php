<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos base con IDs fijos (corre en todos los entornos; insertOrIgnore no
 * modifica filas existentes). Los proyectos de demo están en ProjectSeeder.
 * El backend depende de varios IDs concretos:
 *  - areas.id_area = 1 → ATC (SupportDetailController@updateAreaMotivo: area != 1 ⇒ "Asignado")
 *  - internal_states.id = 3 → Pendiente (default en SupportController@store)
 *  - external_states.id = 1 → Por Asignar, id = 2 → Asignado
 */
class CatalogSeeder extends Seeder
{
    public const AREA_ATC = 1;

    public const AREAS = [
        1 => 'ATC',
        2 => 'Postventa',
        3 => 'Legal',
        4 => 'Titulación',
        5 => 'Contabilidad',
        6 => 'Obras',
        7 => 'Cobranza',
        8 => 'BackOffice',
        9 => 'SuperAdmin',
    ];

    public const INTERNAL_ATENDIDO = 1;
    public const INTERNAL_OBSERVADO = 2;
    public const INTERNAL_PENDIENTE = 3;
    public const INTERNAL_CERRADO = 4;

    public const EXTERNAL_POR_ASIGNAR = 1;
    public const EXTERNAL_ASIGNADO = 2;

    public const TYPES = [1 => 'Consulta', 2 => 'Solicitud', 3 => 'Reclamo', 4 => 'Queja'];

    public function run(): void
    {
        $now = now();

        DB::table('areas')->insertOrIgnore(collect(self::AREAS)->map(fn ($d, $id) => [
            'id_area' => $id, 'descripcion' => $d, 'habilitado' => true,
        ])->values()->all());

        DB::table('tipos_cita')->insertOrIgnore([
            ['id_tipo_cita' => 1, 'tipo' => 'Presencial', 'habilitado' => true],
            ['id_tipo_cita' => 2, 'tipo' => 'Virtual', 'habilitado' => true],
            ['id_tipo_cita' => 3, 'tipo' => 'Telefónica', 'habilitado' => true],
        ]);

        DB::table('dias_espera')->insertOrIgnore([
            ['id_dias_espera' => 1, 'dias' => '1 día', 'habilitado' => true],
            ['id_dias_espera' => 2, 'dias' => '2 días', 'habilitado' => true],
            ['id_dias_espera' => 3, 'dias' => '3 días', 'habilitado' => true],
            ['id_dias_espera' => 4, 'dias' => '5 días', 'habilitado' => true],
            ['id_dias_espera' => 5, 'dias' => '7 días', 'habilitado' => true],
        ]);

        // [nombre, id_tipo_cita, id_dia_espera, id_area]
        $motivos = [
            ['Consulta general', 3, 1, 1],
            ['Actualización de datos', 3, 1, 1],
            ['Entrega de contrato', 1, 2, 2],
            ['Entrega de letras', 1, 2, 2],
            ['Información de lote', 3, 1, 2],
            ['Revisión de contrato', 1, 3, 3],
            ['Cesión de derechos', 1, 4, 3],
            ['Resolución de contrato', 1, 4, 3],
            ['Traspaso de aportes', 1, 3, 3],
            ['Certificado de lote', 1, 4, 4],
            ['Inicio de formalización', 1, 5, 4],
            ['Estado de cuenta', 2, 1, 5],
            ['Emisión de boletas', 2, 1, 5],
            ['Constancia de no adeudo', 2, 3, 5],
            ['Validación de pagos', 2, 2, 5],
            ['Visita guiada a proyecto', 1, 3, 6],
            ['Consulta de avance de obra', 2, 2, 6],
            ['Refinanciamiento de deuda', 1, 2, 7],
        ];
        DB::table('motivos_cita')->insertOrIgnore(collect($motivos)->map(fn ($m, $i) => [
            'id_motivos_cita' => $i + 1,
            'nombre_motivo' => $m[0],
            'id_tipo_cita' => $m[1],
            'id_dia_espera' => $m[2],
            'id_area' => $m[3],
            'id_areap' => 1,
            'habilitado' => true,
        ])->all());

        DB::table('internal_states')->insertOrIgnore([
            ['id' => self::INTERNAL_ATENDIDO, 'description' => 'Atendido', 'detail' => 'La solicitud fue atendida por el área.', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::INTERNAL_OBSERVADO, 'description' => 'Observado', 'detail' => 'Falta información o documentación del cliente.', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::INTERNAL_PENDIENTE, 'description' => 'Pendiente', 'detail' => 'Solicitud registrada, aún sin atender.', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::INTERNAL_CERRADO, 'description' => 'Cerrado', 'detail' => 'Solicitud finalizada y cerrada.', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('external_states')->insertOrIgnore([
            ['id' => self::EXTERNAL_POR_ASIGNAR, 'description' => 'Por Asignar', 'detail' => 'En ATC, aún no derivada a un área.', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::EXTERNAL_ASIGNADO, 'description' => 'Asignado', 'detail' => 'Derivada a un área responsable.', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('types')->insertOrIgnore(collect(self::TYPES)->map(fn ($d, $id) => [
            'id' => $id, 'description' => $d, 'detail' => null,
        ])->values()->all());
    }
}
