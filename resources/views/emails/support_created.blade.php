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
    </style>
</head>
<body>

<div class="title">
    {{ $action === 'updated' ? 'Soporte Actualizado' : 'Nuevo Soporte Registrado' }}
</div>

<p>
    Estimado equipo {{ $support->area->descripcion ?? '' }},<br>
    Se ha {{ $action === 'updated' ? 'actualizado' : 'registrado' }} un soporte en el sistema con la siguiente información:
</p>

<a href="{{ url('/reports/' . $support->id) }}" class="button" target="_blank">🔍 Ver reporte</a>

<p>
    Estimado equipo ATC,<br>
    Se ha registrado un nuevo soporte en el sistema con la siguiente información:
</p>

<h3 class="section-title">Datos del Atención</h3>
<table>
    <tr><th>Asunto:</th><td>{{ $support->subject }}</td></tr>
    <tr><th>Descripción:</th><td>{{ $support->description }}</td></tr>
    <tr><th>Prioridad:</th><td>{{ $support->priority }}</td></tr>
    <tr><th>Tipo:</th><td>{{ $support->type }}</td></tr>
    <tr><th>Estado:</th><td>{{ $support->status }}</td></tr>
    <tr><th>Área:</th><td>{{$support->area->descripcion }}</td></tr>
    <tr><th>Cliente:</th><td>{{ $support->client->Razon_Social ?? '-' }}</td></tr>
    <tr><th>Celular:</th><td>{{ $support->client->Telefono }}</td></tr>
    <tr><th>Proyecto:</th><td>{{ $support->project->descripcion ?? '-' }}</td></tr>
</table>




<h3 class="section-title">Registrado por</h3>
<table>
    <tr><th>Nombre:</th><td>{{ $support->creator->names  }} {{ $support->creator->firstname  }} {{ $support->creator->lastname  }}</td></tr>
    <tr><th>Email:</th><td>{{ $support->creator->email }}</td></tr>
</table>

<a href="{{ url('/reports/' . $support->id) }}" class="button" target="_blank">🔍 Ver reporte</a>

</body>
</html>
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
    {{ $action === 'updated' ? 'Soporte Actualizado' : 'Nuevo Soporte Registrado' }}
</div>

<p>
    Estimado equipo {{ $support->details[0]->area->descripcion ?? 'ATC' }},<br>
    Se ha {{ $action === 'updated' ? 'actualizado' : 'registrado' }} un soporte en el sistema con la siguiente información:
</p>

<a href="{{ url('/reports/' . $support->id) }}" class="button" target="_blank">🔍 Ver reporte</a>

<h3 class="section-title">Datos del Cliente</h3>
<table>
    <tr><th>Cliente:</th><td>{{ $support->client->Razon_Social ?? '-' }}</td></tr>
    <tr><th>Celular:</th><td>{{ $support->client->Telefono ?? '-' }}</td></tr>
</table>

<h3 class="section-title">Registrado por</h3>
<table>
    <tr><th>Nombre:</th><td>{{ $support->creator->names ?? '' }} {{ $support->creator->firstname ?? '' }} {{ $support->creator->lastname ?? '' }}</td></tr>
    <tr><th>Email:</th><td>{{ $support->creator->email ?? '-' }}</td></tr>
</table>

<h3 class="section-title">Detalles de Atención</h3>

@forelse($support->details as $detail)
    <div class="detail-block">
        <div class="sub-title">Detalle #{{ $loop->iteration }}</div>
        <table>
            <tr><th>Asunto:</th><td>{{ $detail->subject }}</td></tr>
            <tr><th>Descripción:</th><td>{{ $detail->description }}</td></tr>
            <tr><th>Prioridad:</th><td>{{ $detail->priority }}</td></tr>
            <tr><th>Tipo:</th><td>{{ $detail->type }}</td></tr>
            <tr><th>Estado:</th><td>{{ $detail->status }}</td></tr>
            <tr><th>Área:</th><td>{{ $detail->area->descripcion ?? '-' }}</td></tr>
            <tr><th>Proyecto:</th><td>{{ $detail->project->descripcion ?? '-' }}</td></tr>
            <tr><th>Motivo:</th><td>{{ $detail->motivoCita->motivo_cita ?? '-' }}</td></tr>
            <tr><th>Tipo de Cita:</th><td>{{ $detail->tipoCita->tipo_cita ?? '-' }}</td></tr>
            <tr><th>Día de Espera:</th><td>{{ $detail->diaEspera->dia_espera ?? '-' }}</td></tr>
            <tr><th>Estado Interno:</th><td>{{ $detail->internalState->internal_state ?? '-' }}</td></tr>
            <tr><th>Estado Externo:</th><td>{{ $detail->externalState->external_state ?? '-' }}</td></tr>
            <tr><th>Reservado:</th><td>{{ $detail->reservation_time }}</td></tr>
            <tr><th>Atendido:</th><td>{{ $detail->attended_at }}</td></tr>
            <tr><th>Manzana:</th><td>{{ $detail->Manzana }}</td></tr>
            <tr><th>Lote:</th><td>{{ $detail->Lote }}</td></tr>
        </table>
    </div>
@empty
    <p>No hay detalles registrados.</p>
@endforelse

<a href="{{ url('/reports/' . $support->id) }}" class="button" target="_blank">🔍 Ver reporte completo</a>

</body>
</html>
