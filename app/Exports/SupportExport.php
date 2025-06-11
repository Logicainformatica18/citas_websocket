<?php
namespace App\Exports;

use App\Models\Support;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SupportExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Support::with([
            'client',
            'area',
            'creator',
            'motivoCita',
            'tipoCita',
            'diaEspera',
            'internalState',
            'externalState',
            'supportType',
            'project'
        ])->get()->map(function ($item) {
            return [
                'ID' => $item->id,
                'Cliente' => $item->client->Razon_Social ?? '',
                'DNI' => $item->client->DNI ?? '',
                'Celular' => $item->cellphone,
                'Email' => $item->client->Email ?? '',
                'Dirección' => $item->client->Direccion?? '',
                'Asunto' => $item->subject,
                'Descripción' => $item->description,
                'Prioridad' => $item->priority,
                'Proyecto' => $item->project->descripcion ?? '',
                'Manzana' => $item->Manzana,
                'Lote' => $item->Lote,
                'Área' => $item->area->descripcion ?? '',
                'Motivo de Cita' => $item->motivoCita->nombre_motivo ?? '',
                'Tipo de Cita' => $item->tipoCita->tipo ?? '',
                'Día de Espera' => $item->diaEspera->dias ?? '',
                'Estado Interno' => $item->internalState->description ?? '',
                'Estado de Atención' => $item->externalState->description ?? '',
                'Tipo (Catálogo)' => $item->supportType->description ?? '',
                'Fecha Reserva' => $item->reservation_time,
                'Fecha Atención' => $item->attended_at,
                'Derivado' => $item->derived,
                'Registrado Por' => $item->creator->email ?? '',
                'Fecha Creación' => $item->created_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
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
            'Estado de Atención',
            'Tipo (Catálogo)',
            'Fecha Reserva',
            'Fecha Atención',
            'Derivado',
            'Registrado Por',
            'Fecha Creación',
        ];
    }
}
