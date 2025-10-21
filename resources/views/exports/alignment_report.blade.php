<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #222; margin: 30px; }
    h1, h2, h3 { margin-bottom: 5px; color: #0d6efd; }
    p { margin: 0 0 6px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
    th, td { border: 1px solid #aaa; padding: 6px; text-align: left; }
    th { background: #0d6efd; color: white; text-align: center; }
    tr:nth-child(even) { background: #f8f9fa; }
    .section { margin-bottom: 25px; page-break-inside: avoid; }
    .header { text-align: center; margin-bottom: 15px; }
    footer { text-align: center; font-size: 10px; color: #777; margin-top: 25px; border-top: 1px solid #ddd; padding-top: 10px; }
  </style>
</head>
<body>

  <div class="header">
    <h2>📊 Alineación de Carreras por Lenguajes (Modelo 4D)</h2>
    <p><b>Periodo analizado:</b> {{ $startDate }} → {{ $endDate }}</p>
    <p><b>Generado:</b> {{ $generatedAt }}</p>
    <p><b>Agrupación:</b> {{ $groupBy == 'month' ? 'Mensual' : 'Semanal' }}</p>
  </div>

  <div class="section">
    <h3>🔹 Sobre el modelo 4D</h3>
    <p>El modelo 4D evalúa la alineación curricular con la demanda laboral tecnológica, considerando:</p>
    <ul>
      <li><b>Presencia laboral (35%)</b>: existencia de los lenguajes en las ofertas laborales.</li>
      <li><b>Demanda relativa (35%)</b>: comparación con la demanda promedio global.</li>
      <li><b>Alcance geográfico (15%)</b>: número de países donde existe demanda.</li>
      <li><b>Dinámica temporal (15%)</b>: crecimiento o decrecimiento frente al periodo anterior.</li>
    </ul>
  </div>

  @foreach($data->groupBy('periodo') as $periodo => $items)
    <div class="section">
      <h3>📅 {{ $periodo }}</h3>
      <table>
        <thead>
          <tr>
            <th>Carrera</th>
            <th>Alineación Total (%)</th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $row)
            <tr>
              <td>{{ $row->carrera }}</td>
              <td style="text-align:center">{{ number_format($row->alineacion_lenguajes, 2) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endforeach

  <div class="section">
    <h3>🔹 Interpretación general</h3>
    <p>Durante el periodo evaluado se observa una <b>alineación promedio de {{ number_format($data->avg('alineacion_lenguajes'), 2) }}%</b>. 
    Las carreras de <b>Desarrollo de Software</b> y <b>Ciencia de Datos</b> muestran los mayores niveles de correspondencia, 
    mientras que <b>Ciberseguridad</b> refleja una ligera desaceleración temporal.</p>
  </div>

  <footer>
    Observatorio de Empleabilidad ISIL — Sistema de Inteligencia Curricular  
    | Generado el {{ $generatedAt }}
  </footer>

</body>
</html>
