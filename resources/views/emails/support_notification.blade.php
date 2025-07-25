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

@php
    $isClient = str_starts_with($action, 'client.');
    $isUpdate = str_ends_with($action, 'updated');
@endphp

<div class="title">
    @if ($isClient)
        {{ $isUpdate ? 'Actualización de su solicitud' : 'Registro de solicitud exitoso' }}
    @else
        {{ $isUpdate ? 'Solicitud Actualizada' : 'Nueva Solicitud Registrada' }}
    @endif
</div>

@if ($isClient)
    <p>
        Estimado/a {{ $support->client->Razon_Social ?? 'cliente' }},<br>
        Su solicitud ha sido {{ $isUpdate ? 'actualizada' : 'registrada exitosamente' }}. Nuestro equipo de atención al cliente dará seguimiento lo antes posible.
    </p>
@else
  <p>
    Estimado equipo de {{ $support->details[0]->area->descripcion ?? 'ATC' }},<br>
    Se ha {{ $isUpdate ? 'actualizado' : 'registrado' }} una atención en el sistema de atención al cliente.<br>
   <b> Se recomienda dar seguimiento desde la plataforma de atención al cliente y mantener actualizado el estado de la atención del ticket.</b>
</p>


    {{-- Botón principal al inicio para internos --}}
    <a href="{{ url('/reports/' . $support->id) }}" class="button" target="_blank">🔍 Ver Ticket</a>
@endif

<h3 class="section-title">Información General</h3>
<div class="info-grid">
    <table>
        <tr><th>Cliente:</th><td>{{ $support->client->Razon_Social ?? '-' }}</td></tr>
        <tr><th>Celular:</th><td>{{ $support->client->Telefono ?? '-' }}</td></tr>
        <tr><th>DNI:</th><td>{{ $support->client->DNI ?? '-' }}</td></tr>
        <tr><th>Correo:</th><td>{{ $support->client->Email ?? '-' }}</td></tr>
        <tr><th>Dirección:</th><td>{{ $support->client->Direccion ?? '-' }}</td></tr>
    </table>

   
</div>

<h3 class="section-title">Detalle de Solicitud</h3>

@forelse($support->details as $detail)
    <div class="detail-block">
      
        <div class="detail-columns">
            <table>
                <tr><th>Asunto:</th><td>{{ $detail->subject ?? '-' }}</td></tr>
                <tr><th>Descripción:</th><td>{{ $detail->description ?? '-' }}</td></tr>
                <tr><th>Prioridad:</th><td>{{ $detail->priority ?? '-' }}</td></tr>
                <tr><th>Tipo:</th><td>{{ $detail->type ?? '-' }}</td></tr>
                <tr><th>Área:</th><td>{{ $detail->area->descripcion ?? '-' }}</td></tr>
                <tr><th>Proyecto:</th><td>{{ $detail->project->descripcion ?? '-' }}</td></tr>
                
            </table>

            <table>
                <tr><th>Estado de Solicitud:</th><td>{{ $detail->internalState->description ?? '-' }}</td></tr>
                <tr><th>Estado ATC:</th><td>{{ $detail->externalState->description ?? '-' }}</td></tr>
                <tr><th>Manzana:</th><td>{{ $detail->Manzana ?? '-' }}</td></tr>
             
            </table>
        </div>
    </div>
@empty
    <p>No hay detalles registrados.</p>
@endforelse

@if (!$isClient)
    {{-- ✅ Solo visible para equipo interno --}}
    <p style="margin-top: 30px; font-weight: bold; font-size: 14px; color: #004D5A;">
        Registrado por: {{ $support->creator->firstname ?? '' }} {{ $support->creator->lastname ?? '' }} ({{ $support->creator->email ?? '' }})
    </p>
@endif


</body>
</html>
