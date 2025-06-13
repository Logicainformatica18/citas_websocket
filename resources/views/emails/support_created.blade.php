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
