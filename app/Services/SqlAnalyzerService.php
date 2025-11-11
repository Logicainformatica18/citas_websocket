<?php

namespace App\Services;

use PhpMyAdmin\SqlParser\Parser;

class SqlAnalyzerService
{
    public static function extractTables(string $sql): array
    {
        try {
            $parser = new Parser($sql);
            $statement = $parser->statements[0];
            $tables = [];

            foreach ($statement->from as $tbl) {
                $tables[] = $tbl->table;
            }

            return array_unique($tables);
        } catch (\Exception $e) {
            return [];
        }
    }
}
