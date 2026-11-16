<?php
// Compatibilidad: foto dorada del Ranking de Lenguajes. Uso: php83 foto_dorada_lenguajes.php capture|compare <dir>
// Equivale a: php83 docs/produccion/herramientas/foto_dorada.php languages capture|compare <dir>
$argv = [$argv[0], 'languages', $argv[1] ?? '', $argv[2] ?? ''];
require __DIR__ . '/foto_dorada.php';
