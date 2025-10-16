<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; color: #222; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
    th { background: #f0f0f0; }
    h1 { font-size: 18px; margin-bottom: 4px; }
    h3 { font-size: 14px; color: #444; margin-top: 20px; }
    .header { display: flex; align-items: center; justify-content: space-between; }
    .logo { height: 40px; }
  </style>
</head>
<body>

  <div class="header">
    <h1>Observatorio de Empleabilidad ISIL</h1>
    <img src="{{ public_path('logo/isil_logo.png') }}" class="logo" alt="ISIL Logo">
  </div>

  <p>
    <strong>Reporte:</strong> Demanda laboral por ciudad<br>
    <strong>Año:</strong> {{ $filters['year'] ?? date('Y') }}<br>
    <strong>Fuentes:</strong> {{ implode(', ', $filters['sources'] ?? ['Todas']) }}<br>
    <strong>Modalidades:</strong> {{ implode(', ', $filters['modalities'] ?? ['Todas']) }}<br>
    <strong>Países:</strong> {{ implode(', ', $filters['countries'] ?? ['Todos']) }}
  </p>

  <table>
    <thead>
      <tr>
        <th>País</th>
        <th>Ciudad</th>
        <th>Empresa</th>
        <th>Título</th>
        <th>Modalidad</th>
        <th>Fuente</th>
        <th>Fecha Publicación</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($data as $row)
        <tr>
          <td>{{ $row->country }}</td>
          <td>{{ $row->city }}</td>
          <td>{{ $row->company }}</td>
          <td>{{ $row->title }}</td>
          <td>{{ $row->modality }}</td>
          <td>{{ $row->source }}</td>
          <td>{{ $row->published_at }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

</body>
</html>
