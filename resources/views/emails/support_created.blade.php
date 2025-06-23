<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #000; }
        .title { font-size: 20px; font-weight: bold; background: #f4f4f4; padding: 10px; color: #004D5A; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 8px; vertical-align: top; }
        th { background: #0D4D58; color: white; width: 250px; }
        .section-title { font-weight: bold; margin-top: 20px; font-size: 16px; }
        .button {
            margin-top: 20px;
            display: inline-block;
            padding: 10px 20px;
            background: #22f757;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .detail-block {
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 15px;
            border-radius: 6px;
        }
        .sub-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: #0D4D58;
        }
    </style>
</head>
<body>

<div class="title">
    {{ $action === 'updated' ? 'Atención Actualizado' : 'Nuevo Atención Registrado' }}
</div>

<p>
    Estimado equipo {{ $support->details[0]->area->descripcion ?? 'ATC' }},<br>
    Se ha {{ $action === 'updated' ? 'actualizado' : 'registrado' }} una atención en el sistema con la siguiente información:
</p>

<a href="{{ url('/reports/' . $support->id) }}" class="button" target="_blank">🔍 Ver reporte</a>

<h3 class="section-title">Datos del Cliente</h3>
<table>
    <tr><th>Cliente:</th><td>{{ $support->client->Razon_Social ?? '-' }}</td></tr>
    <tr><th>Celular:</th><td>{{ $support->client->Telefono ?? '-' }}</td></tr>
</table>

<h3 class="section-title">Registrado por</h3>
<table>
    <tr>
        <th>Nombre:</th>
        <td>{{ $support->creator->names ?? '' }} {{ $support->creator->firstname ?? '' }} {{ $support->creator->lastname ?? '' }}</td>
    </tr>
    <tr><th>Email:</th><td>{{ $support->creator->email ?? '-' }}</td></tr>
</table>

<h3 class="section-title">Detalles de Atención</h3>

@forelse($support->details as $detail)
    <div class="detail-block">
        <div class="sub-title">Atención: #0{{ $loop->iteration }}</div>
        <table>
            <tr><th>Asunto:</th><td>{{ $detail->subject ?? '-' }}</td></tr>
            <tr><th>Descripción:</th><td>{{ $detail->description ?? '-' }}</td></tr>
            <tr><th>Prioridad:</th><td>{{ $detail->priority ?? '-' }}</td></tr>
            <tr><th>Tipo:</th><td>{{ $detail->type ?? '-' }}</td></tr>
            {{-- <tr><th>Estado:</th><td>{{ $detail->status ?? '-' }}</td></tr> --}}
            <tr><th>Área:</th><td>{{ $detail->area->descripcion ?? '-' }}</td></tr>
            <tr><th>Proyecto:</th><td>{{ $detail->project->descripcion ?? '-' }}</td></tr>
            <tr><th>Motivo:</th><td>{{ $detail->motivoCita->nombre_motivo ?? '-' }}</td></tr>
            {{-- <tr><th>Tipo de Cita:</th><td>{{ $detail->tipoCita->tipo ?? '-' }}</td></tr> --}}
            {{-- <tr><th>Día de Espera:</th><td>{{ $detail->diaEspera->dias ?? '-' }}</td></tr> --}}
            <tr><th>Estado de Atención</th><td>{{ $detail->internalState->description ?? '-' }}</td></tr>
            <tr><th>Estado Global</th><td>{{ $support->status_global ?? '-' }}</td></tr>

            <tr><th>Estado ATC:</th><td>{{ $detail->externalState->description ?? '-' }}</td></tr>
            {{-- <tr><th>Tipo de Atención:</th><td>{{ $detail->supportType->description ?? '-' }}</td></tr> --}}
            <tr><th>Reservado:</th><td>{{ $detail->reservation_time ?? '-' }}</td></tr>
            <tr><th>Atendido:</th><td>{{ $detail->attended_at ?? '-' }}</td></tr>
            <tr><th>Manzana:</th><td>{{ $detail->Manzana ?? '-' }}</td></tr>
            <tr><th>Lote:</th><td>{{ $detail->Lote ?? '-' }}</td></tr>
        </table>
    </div>
@empty
    <p>No hay detalles registrados.</p>
@endforelse

<a href="{{ url('/reports/' . $support->id) }}" class="button" target="_blank">🔍 Ver reporte completo</a>

</body>
</html>
