<?php

namespace App\Exports;

use App\Models\Support;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SupportExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $supports = Support::with([
            'client',
            'creator',
            'details.area',
            'details.project',
            'details.motivoCita',
            'details.tipoCita',
            'details.diaEspera',
            'details.internalState',
            'details.externalState',
            'details.supportType',
        ])->get();

        $exportData = [];

        foreach ($supports as $support) {
            $client = $support->client ? $support->client->toFrontend() : null;

            foreach ($support->details as $detail) {
                $exportData[] = [
                    'ID de Soporte'        => $support->id,
                    'Cliente'              => $client['names'] ?? '',
                    'DNI'                  => $client['dni'] ?? '',
                    'Celular'              => $client['cellphone'] ?? '',
                    'Email'                => $client['email'] ?? '',
                    'Dirección'            => $client['address'] ?? '',

                    'Asunto'               => $detail->subject,
                    'Descripción'          => $detail->description,
                    'Prioridad'            => $detail->priority,
                    'Proyecto'             => $detail->project->descripcion ?? '',
                    'Manzana'              => $detail->Manzana,
                    'Lote'                 => $detail->Lote,
                    'Área'                 => $detail->area->descripcion ?? '',
                    'Motivo de Cita'       => $detail->motivoCita->nombre_motivo ?? '',
                    'Tipo de Cita'         => $detail->tipoCita->tipo ?? '',
                    'Día de Espera'        => $detail->diaEspera->dias ?? '',
                    'Estado Interno'       => $detail->internalState->description ?? '',
                    'Estado de Solicitud'   => $detail->externalState->description ?? '',
                    'Tipo (Catálogo)'      => $detail->supportType->description ?? '',
                    'Fecha Reserva'        => $detail->reservation_time,
                    'Fecha Solicitud'       => $detail->attended_at,
                    'Derivado'             => $detail->derived,
                    'Registrado Por'       => $support->creator->email ?? '',
                    'Fecha Creación'       => $support->created_at,
                ];
            }
        }

        return collect($exportData);
    }

    public function headings(): array
    {
        return [
            'ID de Soporte',
            'Cliente',
            'DNI',
            'Celular',
            'Email',
            'Dirección',
            'Asunto',
            'Descripción',
            'Prioridad',
            'Proyecto',
            'Manzana',
            'Lote',
            'Área',
            'Motivo de Cita',
            'Tipo de Cita',
            'Día de Espera',
            'Estado Interno',
            'Estado de Solicitud',
            'Tipo (Catálogo)',
            'Fecha Reserva',
            'Fecha Solicitud',
            'Derivado',
            'Registrado Por',
            'Fecha Creación',
        ];
    }
}
