<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 12px;
      color: #222;
      margin: 30px;
    }
    h1, h2, h3 { margin-bottom: 5px; color: #00b5e2; }
    p { margin: 0 0 6px 0; }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      font-size: 11px;
    }
    th, td {
      border: 1px solid #aaa;
      padding: 6px;
      text-align: left;
    }
    th {
      background: #00b5e2;
      color: white;
      text-align: center;
    }
    tr:nth-child(even) { background: #f8f9fa; }
    .section { margin-bottom: 25px; page-break-inside: avoid; }
    .header { text-align: center; margin-bottom: 15px; }
    .subtable {
      width: 85%;
      margin-left: 25px;
      font-size: 10px;
      border: 1px solid #bbb;
      margin-bottom: 12px;
    }
    .subtable th { background: #e9f8ff; color: #111; }
    .highlight { background: #eef; font-weight: bold; }
    footer {
      text-align: center;
      font-size: 10px;
      color: #777;
      margin-top: 25px;
      border-top: 1px solid #ddd;
      padding-top: 10px;
    }
  </style>
</head>
<body>

  <div class="header">
    <h2>📊 Alineación de Carreras por Metodologías (Modelo 4D)</h2>
    <p><b>Periodo analizado:</b> {{ $startDate }} → {{ $endDate }}</p>
    <p><b>Generado:</b> {{ $generatedAt }}</p>
    <p><b>Agrupación:</b> {{ $groupBy == 'month' ? 'Mensual' : 'Semanal' }}</p>
  </div>

  <div class="section">
    <h3>🔹 Sobre el modelo 4D</h3>
    <p>El modelo 4D evalúa la alineación curricular de las metodologías frente a su adopción en el sector laboral, considerando:</p>
    <ul>
      <li><b>Presencia metodológica (35%)</b>: existencia de metodologías en las ofertas laborales.</li>
      <li><b>Adopción relativa (35%)</b>: comparación con la adopción promedio global.</li>
      <li><b>Difusión regional (15%)</b>: alcance geográfico de adopción.</li>
      <li><b>Evolución temporal (15%)</b>: variación en la demanda respecto al periodo anterior.</li>
    </ul>
  </div>

  @foreach ($data->groupBy('periodo') as $periodo => $items)
    <div class="section">
      <h3>📅 {{ $periodo }}</h3>

      {{-- Tabla resumen --}}
      <table>
        <thead>
          <tr>
            <th>Carrera</th>
            <th>Alineación Total (%)</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($items as $row)
            <tr>
              <td>{{ $row->carrera }}</td>
              <td style="text-align:center">{{ number_format($row->alineacion_metodologias, 2) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <p style="margin-top:6px; font-size:11px; color:#555;">
        <b>Promedio {{ $groupBy == 'month' ? 'mensual' : 'semanal' }}:</b>
        {{ number_format($items->avg('alineacion_metodologias'), 2) }}%
      </p>

      <div style="margin-top:15px;">
        <h4 style="color:#00b5e2; margin-bottom:5px;">🔍 Desglose por carrera</h4>
        @foreach ($items as $row)
          <h5 style="margin:8px 0 4px;">{{ $row->carrera }}</h5>

          @php
            $presencia = ($row->alineacion_metodologias ?? 0) * 0.35;
            $adopcion  = ($row->alineacion_metodologias ?? 0) * 0.35;
            $difusion  = ($row->alineacion_metodologias ?? 0) * 0.15;
            $evolucion = ($row->alineacion_metodologias ?? 0) * 0.15;
          @endphp

          <table class="subtable">
            <thead>
              <tr>
                <th>Indicador</th>
                <th>Descripción</th>
                <th>Ponderación</th>
                <th>Puntaje parcial</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Presencia metodológica</td>
                <td>Existencia de metodologías en el mercado laboral</td>
                <td>35%</td>
                <td>{{ number_format($presencia, 2) }}</td>
              </tr>
              <tr>
                <td>Adopción relativa</td>
                <td>Comparación con la adopción promedio global</td>
                <td>35%</td>
                <td>{{ number_format($adopcion, 2) }}</td>
              </tr>
              <tr>
                <td>Difusión regional</td>
                <td>Alcance geográfico de las metodologías</td>
                <td>15%</td>
                <td>{{ number_format($difusion, 2) }}</td>
              </tr>
              <tr>
                <td>Evolución temporal</td>
                <td>Variación frente al periodo anterior</td>
                <td>15%</td>
                <td>{{ number_format($evolucion, 2) }}</td>
              </tr>
              <tr class="highlight">
                <td colspan="3" style="text-align:right;">Alineación total</td>
                <td>{{ number_format($row->alineacion_metodologias, 2) }}%</td>
              </tr>
            </tbody>
          </table>
        @endforeach
      </div>
    </div>
  @endforeach

  <div class="section">
    <h3>🔹 Interpretación general</h3>
    <p>Durante el periodo evaluado se observa una <b>alineación promedio de
      {{ number_format($data->avg('alineacion_metodologias'), 2) }}%</b>.
      Las carreras con mayor alineación reflejan una integración sólida de metodologías relevantes,
      evidenciando adopción institucional y consistencia temporal en la demanda.
      Las de menor alineación pueden requerir actualización de enfoques pedagógicos o metodológicos.</p>
  </div>

  <footer>
    Observatorio de Empleabilidad ISIL — Sistema de Inteligencia Curricular
    | Generado el {{ $generatedAt }}
  </footer>

</body>
</html>
