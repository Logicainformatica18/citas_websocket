<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class JobOffersExport implements FromCollection, WithHeadings
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    /**
     * Convierte las filas en una colección exportable a Excel
     */
    public function collection()
    {
        return $this->rows->map(function ($o) {

            return [
                'ID'                   => $o['ID'] ?? null,
                'Título'               => $o['Titulo'] ?? null,
                'Empresa'              => $o['Empresa'] ?? null,
                'País'                 => $o['Pais'] ?? null,
                'Región'               => $o['Region'] ?? null,
                'State Code'           => $o['State Code'] ?? null,
                'Ciudad'               => $o['Ciudad'] ?? null,
                'Ciudad ASCII'         => $o['Ciudad ASCII'] ?? null,
                'Ubicación'            => $o['Location'] ?? null,
                'Código Postal'        => $o['ZIP'] ?? null,
                'Modalidad'            => $o['Modalidad'] ?? null,
                'Tipo de Trabajo'      => $o['Job Type'] ?? null,
                'Tipo de Remoto'       => $o['Remote Type'] ?? null,
                'Carga Laboral'        => $o['Workload'] ?? null,
                'Nivel de Experiencia' => $o['Experiencia'] ?? null,
                'Nivel Educativo'      => $o['Educación Nivel'] ?? null,
                'Campo Educativo'      => $o['Educación Campo'] ?? null,
                'Certificaciones'      => $o['Certificaciones'] ?? null,
                'Requisitos'           => $o['Requisitos'] ?? null,
                'Skills'               => $o['Skills'] ?? null,
                'Descripción'          => $o['Descripción'] ?? null,
                'Beneficios'           => $o['Beneficios'] ?? null,
                'Latitud'              => $o['Latitud'] ?? null,
                'Longitud'             => $o['Longitud'] ?? null,
                'Salario Min'          => $o['Salario Min'] ?? null,
                'Salario Max'          => $o['Salario Max'] ?? null,
                'Moneda'               => $o['Moneda'] ?? null,
                'Tipo de Pago'         => $o['Tipo Pago'] ?? null,
                'Fuente'               => $o['Fuente'] ?? null,
                'Consulta'             => $o['Search Query'] ?? null,
                'ID Externo'           => $o['External ID'] ?? null,
                'URL'                  => $o['URL'] ?? null,
                'URL Aplicación'       => $o['Application URL'] ?? null,
                'Tipo Aplicación'      => $o['Application Type'] ?? null,
                'Publicado'            => $o['Publicado'] ?? null,
                'Expira'               => $o['Expira'] ?? null,
                'Registrado'           => $o['Creado'] ?? null,
                'Actualizado'          => $o['Actualizado'] ?? null,
            ];
        });
    }

    /**
     * Encabezados del Excel
     */
    public function headings(): array
    {
        return [
            'ID',
            'Título',
            'Empresa',
            'País',
            'Región',
            'State Code',
            'Ciudad',
            'Ciudad ASCII',
            'Ubicación',
            'Código Postal',
            'Modalidad',
            'Tipo de Trabajo',
            'Tipo de Remoto',
            'Carga Laboral',
            'Nivel de Experiencia',
            'Nivel Educativo',
            'Campo Educativo',
            'Certificaciones',
            'Requisitos',
            'Skills',
            'Descripción',
            'Beneficios',
            'Latitud',
            'Longitud',
            'Salario Min',
            'Salario Max',
            'Moneda',
            'Tipo de Pago',
            'Fuente',
            'Consulta',
            'ID Externo',
            'URL',
            'URL Aplicación',
            'Tipo Aplicación',
            'Publicado',
            'Expira',
            'Registrado',
            'Actualizado',
        ];
    }
}
