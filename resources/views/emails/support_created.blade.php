<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #000;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            background: #f4f4f4;
            padding: 10px;
            color: #004D5A;
            border: 1px solid #ccc;
        }

        .section-title {
            font-weight: bold;
            margin-top: 20px;
            font-size: 16px;
        }

        .button {
            margin-top: 20px;
            display: inline-block;
            padding: 10px 20px;
            background: #0D4D58;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .info-grid {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .info-grid table {
            width: 100%;
            border: 1px solid #ccc;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        th {
            font-weight: bold;
            width: 150px;
            border-right: 1px solid #ddd;
        }

        .detail-block {
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 15px;
            border-radius: 6px;
        }

        .sub-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #0D4D58;
        }

        .detail-columns {
            display: flex;
            gap: 20px;
        }

        .detail-columns table {
            width: 100%;
            border: 1px solid #ccc;
            border-collapse: collapse;
        }

        .detail-columns th, .detail-columns td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .detail-columns th {
            font-weight: bold;
            width: 150px;
            border-right: 1px solid #ddd;
        }

        .center-button {
            margin: 20px 0;
        }
    </style>
</head>
<body>

<div class="title">
    {{ $action === 'updated' ? 'Solicitud Actualizada' : 'Nueva Solicitud Registrada' }}
</div>

<p>
    Estimado equipo {{ $support->details[0]->area->descripcion ?? 'ATC' }},<br>
    Se ha {{ $action === 'updated' ? 'actualizado' : 'registrado' }} una atención en el sistema con la siguiente información:
</p>

<a href="{{ url('/reports/' . $support->id) }}" class="button" target="_blank">🔍 Ver reporte</a>

<h3 class="section-title">Información General</h3>
<div class="info-grid">
    <table>
        <tr><th>Cliente:</th><td>{{ $support->client->Razon_Social ?? '-' }}</td></tr>
        <tr><th>Celular:</th><td>{{ $support->client->Telefono ?? '-' }}</td></tr>
        <tr><th>DNI:</th><td>{{ $support->client->dni ?? '-' }}</td></tr>
        <tr><th>Correo:</th><td>{{ $support->client->email ?? '-' }}</td></tr>
        <tr><th>Dirección:</th><td>{{ $support->client->direccion ?? '-' }}</td></tr>
    </table>

    <table>
        <tr>
            <th>Nombre:</th>
            <td>{{ $support->creator->names ?? '' }} {{ $support->creator->firstname ?? '' }} {{ $support->creator->lastname ?? '' }}</td>
        </tr>
        <tr><th>Email:</th><td>{{ $support->creator->email ?? '-' }}</td></tr>
    </table>
</div>

<h3 class="section-title">Detalles de Solicitud</h3>

@forelse($support->details as $detail)
    <div class="detail-block">
        <div class="sub-title">📝 Solicitud #0{{ $loop->iteration }}</div>
        <div class="detail-columns">
            <table>
                <tr><th>Asunto:</th><td>{{ $detail->subject ?? '-' }}</td></tr>
                <tr><th>Descripción:</th><td>{{ $detail->description ?? '-' }}</td></tr>
                <tr><th>Prioridad:</th><td>{{ $detail->priority ?? '-' }}</td></tr>
                <tr><th>Tipo:</th><td>{{ $detail->type ?? '-' }}</td></tr>
                <tr><th>Área:</th><td>{{ $detail->area->descripcion ?? '-' }}</td></tr>
                <tr><th>Proyecto:</th><td>{{ $detail->project->descripcion ?? '-' }}</td></tr>
                <tr><th>Motivo:</th><td>{{ $detail->motivoCita->nombre_motivo ?? '-' }}</td></tr>
            </table>

            <table>
                <tr><th>Estado de Solicitud:</th><td>{{ $detail->internalState->description ?? '-' }}</td></tr>
                <tr><th>Estado Global:</th><td>{{ $support->status_global ?? '-' }}</td></tr>
                <tr><th>Estado ATC:</th><td>{{ $detail->externalState->description ?? '-' }}</td></tr>
                <tr><th>Reservado:</th><td>{{ $detail->reservation_time ?? '-' }}</td></tr>
                <tr><th>Atendido:</th><td>{{ $detail->attended_at ?? '-' }}</td></tr>
                <tr><th>Manzana:</th><td>{{ $detail->Manzana ?? '-' }}</td></tr>
                <tr><th>Lote:</th><td>{{ $detail->Lote ?? '-' }}</td></tr>
            </table>
        </div>
    </div>
@empty
    <p>No hay detalles registrados.</p>
@endforelse

<div class="center-button">
    <a href="{{ url('/reports/' . $support->id) }}" class="button" target="_blank">🔍 Ver reporte completo</a>
</div>

</body>
</html>
