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
    .subtable { width: 85%; margin-left: 25px; font-size: 10px; border: 1px solid #bbb; margin-bottom: 12px; }
    .subtable th { background: #e9f1ff; color: #111; }
    .highlight { background: #eef; font-weight: bold; }
    footer { text-align: center; font-size: 10px; color: #777; margin-top: 25px; border-top: 1px solid #ddd; padding-top: 10px; }
  </style>
</head>
<body>

  <div class="header">
    <h2>⚙️ Alineación de Carreras por Tecnologías (Modelo 4D)</h2>
    <p><b>Periodo analizado:</b> {{ $startDate }} → {{ $endDate }}</p>
    <p><b>Generado:</b> {{ $generatedAt }}</p>
    <p><b>Agrupación:</b> {{ $groupBy == 'month' ? 'Mensual' : 'Semanal' }}</p>
  </div>

  <div class="section">
    <h3>🔹 Sobre el modelo 4D Tecnológico</h3>
    <p>El modelo 4D Tecnológico mide la correspondencia entre la formación académica y la demanda de herramientas y tecnologías en el mercado laboral.</p>
    <p>Evalúa las siguientes dimensiones:</p>
    <ul>
      <li><b>Presencia tecnológica (35%)</b>: existencia de la tecnología en las ofertas laborales.</li>
      <li><b>Demanda relativa (35%)</b>: comparación con la demanda promedio global del periodo.</li>
      <li><b>Cobertura geográfica (15%)</b>: número de países donde la tecnología presenta demanda activa.</li>
      <li><b>Dinámica temporal (15%)</b>: crecimiento o decrecimiento de la demanda frente al periodo anterior.</li>
    </ul>
  </div>

  @foreach($data->groupBy('periodo') as $periodo => $items)
    <div class="section">
      <h3>📅 {{ $periodo }}</h3>

      {{-- Tabla resumen --}}
      <table>
        <thead>
          <tr>
            <th>Carrera</th>
            <th>Alineación Tecnológica (%)</th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $row)
            <tr>
              <td>{{ $row->carrera }}</td>
              <td style="text-align:center">{{ number_format($row->alineacion_tecnologias ?? 0, 2) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <p style="margin-top:6px; font-size:11px; color:#555;">
        <b>Promedio {{ $groupBy == 'month' ? 'mensual' : 'semanal' }}:</b>
        {{ number_format($items->avg('alineacion_tecnologias'), 2) }}%
      </p>

      {{-- Desglose por carrera --}}
      <div style="margin-top:15px;">
        <h4 style="color:#0d6efd; margin-bottom:5px;">🔍 Desglose por carrera</h4>

        @foreach($items as $row)
          <h5 style="margin:8px 0 4px;">{{ $row->carrera }}</h5>
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
                <td>Presencia tecnológica</td>
                <td>Existencia de la tecnología en las ofertas laborales</td>
                <td>35%</td>
                <td>{{ $row->empleos_actuales > 0 ? '35.00' : '0.00' }}</td>
              </tr>
              <tr>
                <td>Demanda relativa</td>
                <td>Comparación con la demanda promedio global</td>
                <td>35%</td>
                <td>
                  {{ $row->promedio_empleos > 0
                      ? number_format(35 * min($row->empleos_actuales / $row->promedio_empleos, 1), 2)
                      : '0.00' }}
                </td>
              </tr>
              <tr>
                <td>Cobertura geográfica</td>
                <td>Cantidad de países donde existe demanda</td>
                <td>15%</td>
                <td>{{ number_format(15 * min(($row->paises_actuales ?? 0) / 5, 1), 2) }}</td>
              </tr>
              <tr>
                <td>Dinámica temporal</td>
                <td>Evolución frente al periodo anterior</td>
                <td>15%</td>
                <td>
                  {{ $row->empleos_previos > 0
                      ? number_format(15 * min(($row->empleos_actuales - $row->empleos_previos) / $row->empleos_previos, 1), 2)
                      : '0.00' }}
                </td>
              </tr>
              <tr class="highlight">
                <td colspan="3" style="text-align:right;">Alineación total</td>
                <td>{{ number_format($row->alineacion_tecnologias ?? 0, 2) }}%</td>
              </tr>
            </tbody>
          </table>
        @endforeach
      </div>
    </div>
  @endforeach

  <div class="section">
    <h3>🔹 Interpretación general</h3>
    <p>
      Durante el periodo evaluado, el promedio de <b>alineación tecnológica</b> fue de
      <b>{{ number_format($data->avg('alineacion_tecnologias'), 2) }}%</b>.
    </p>
    <p>
      Las carreras con mayor puntuación presentan una mejor correspondencia entre las tecnologías que enseñan y las que
      actualmente demandan los empleadores, especialmente en ámbitos relacionados con desarrollo de software, nube e inteligencia artificial.
    </p>
    <p>
      Los valores más bajos indican menor presencia de ciertas herramientas o frameworks en las ofertas analizadas,
      lo cual puede deberse a cambios de tendencia, baja cobertura en fuentes o brechas curriculares específicas.
    </p>
  </div>

  <footer>
    Observatorio de Empleabilidad ISIL — Sistema de Inteligencia Curricular<br>
    Generado el {{ $generatedAt }}
  </footer>

</body>
</html>
