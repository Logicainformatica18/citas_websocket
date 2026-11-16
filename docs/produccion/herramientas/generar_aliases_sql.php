<?php
// Genera los INSERT de market_entity_aliases desde database/data/entity_aliases/{archivo}.php.
// Uso, desde la raíz del proyecto:
//   php83 docs/produccion/herramientas/generar_aliases_sql.php languages language lenguajes > salida.sql
//     argumentos: archivo de reglas, market_entities.entity_type, etiqueta para notes
// El bloque generado borra las reglas sembradas antes con la misma etiqueta (notes = '[seed <etiqueta>]')
// para las entidades de ese tipo y las vuelve a insertar: es idempotente y respeta las reglas creadas a mano
// (INSERT IGNORE: si ya existe una regla manual igual, se conserva la manual).
chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Matching\AliasSpec;

[$_, $file, $type, $label] = $argv + [null, null, null, null];
if (! $file || ! $type || ! $label) {
    fwrite(STDERR, "Uso: generar_aliases_sql.php <archivo> <entity_type> <etiqueta>\n");
    exit(1);
}

$q = fn ($v) => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], (string) $v) . "'";
$marker = "[seed {$label}]";
$spec = AliasSpec::load($file);
$total = 0;

echo "-- Generado por docs/produccion/herramientas/generar_aliases_sql.php {$file} {$type} {$label}\n";
echo "-- " . count($spec) . " entidades. Las entidades que no existan en este entorno se omiten solas (JOIN por slug).\n";
echo "DELETE a FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id\n";
echo " WHERE me.entity_type = {$q($type)} AND a.notes = {$q($marker)};\n";
echo "SELECT CONCAT('Reglas sembradas anteriores borradas: ', ROW_COUNT()) AS resultado;\n\n";

foreach ($spec as $slug => $rules) {
    $rows = AliasSpec::toRows($rules);
    $values = [];
    foreach ($rows as $r) {
        if (mb_strlen($r['pattern']) > 191) {   // market_entity_aliases.pattern es VARCHAR(191)
            fwrite(STDERR, "ERROR: regla de {$slug} con más de 191 caracteres: dividirla\n");
            exit(1);
        }
        $values[] = sprintf('(%s, %s, %d, %d, %d, %s)', $q($r['kind']), $q($r['pattern']), $r['case_sensitive'],
            $r['is_regex'], $r['is_weak'], $r['window_chars'] === null ? 'NULL' : $r['window_chars']);
    }
    $total += count($rows);
    echo "-- {$slug}\n";
    echo "INSERT IGNORE INTO market_entity_aliases (market_entity_id, kind, pattern, case_sensitive, is_regex, is_weak, window_chars, notes, created_at, updated_at)\n";
    echo "SELECT me.id, r.kind, r.pattern, r.cs, r.rx, r.weak, r.win, {$q($marker)}, NOW(), NOW()\n";
    echo "  FROM market_entities me\n";
    echo "  JOIN (\n    SELECT " . "'alias' AS kind, CAST('' AS CHAR(191)) AS pattern, 0 AS cs, 0 AS rx, 0 AS weak, CAST(NULL AS UNSIGNED) AS win FROM DUAL WHERE FALSE\n";
    foreach ($values as $v) {
        $v = substr($v, 1, -1);
        echo "    UNION ALL SELECT {$v}\n";
    }
    echo "  ) r\n";
    echo " WHERE me.entity_type = {$q($type)} AND me.slug = {$q($slug)}\n";
    // Sin advertencias 1062 al re-ejecutar: solo las reglas que aún no existen
    echo "   AND NOT EXISTS (SELECT 1 FROM market_entity_aliases x WHERE x.market_entity_id = me.id AND x.kind = r.kind\n";
    echo "                   AND x.pattern = r.pattern COLLATE utf8mb4_bin AND x.case_sensitive = r.cs);\n\n";
}

echo "SELECT CONCAT('Reglas sembradas: ', COUNT(*), ' (esperadas {$total})') AS resultado\n";
echo "  FROM market_entity_aliases a JOIN market_entities me ON me.id = a.market_entity_id\n";
echo " WHERE me.entity_type = {$q($type)} AND a.notes = {$q($marker)};\n";
