<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Genera números de ticket correlativos "TK-00001" (uno por detalle de solicitud).
 *
 * Usa la fila 'support_ticket' de ticket_sequences bloqueada con SELECT ... FOR UPDATE,
 * así dos peticiones simultáneas nunca obtienen el mismo número. Si se llama dentro
 * de una transacción externa, el bloqueo dura hasta que esa transacción termine y,
 * si se revierte, el número también se revierte (no se "queman" números).
 */
class TicketNumberGenerator
{
    public const SEQUENCE = 'support_ticket';

    public const PLACEHOLDER = 'TK-'; // detalle sin ticket (el frontend depende de este valor)

    public function next(): string
    {
        return DB::transaction(function () {
            $row = $this->lockedRow();

            if (! $row) {
                DB::table('ticket_sequences')->insertOrIgnore([
                    'name' => self::SEQUENCE,
                    'last_number' => self::maxExisting(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = $this->lockedRow();
            }

            $next = $row->last_number + 1;

            DB::table('ticket_sequences')
                ->where('name', self::SEQUENCE)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return self::format($next);
        });
    }

    /** Ajusta la secuencia si se insertaron tickets por fuera (seeders, importaciones). */
    public function syncWithExisting(): void
    {
        DB::transaction(function () {
            $max = self::maxExisting();
            $row = $this->lockedRow();

            if (! $row) {
                DB::table('ticket_sequences')->insert([
                    'name' => self::SEQUENCE, 'last_number' => $max,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } elseif ($max > $row->last_number) {
                DB::table('ticket_sequences')->where('name', self::SEQUENCE)
                    ->update(['last_number' => $max, 'updated_at' => now()]);
            }
        });
    }

    public static function isTicket(?string $value): bool
    {
        return (bool) preg_match('/^TK-\d+$/', (string) $value);
    }

    public static function format(int $number): string
    {
        return 'TK-' . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    /** Mayor número TK-n existente en support_details (independiente del motor de BD). */
    public static function maxExisting(): int
    {
        return (int) DB::table('support_details')
            ->where('ticket', 'like', 'TK-%')
            ->pluck('ticket')
            ->filter(fn ($t) => self::isTicket($t))
            ->map(fn ($t) => (int) substr($t, 3))
            ->max();
    }

    private function lockedRow(): ?object
    {
        return DB::table('ticket_sequences')->where('name', self::SEQUENCE)->lockForUpdate()->first();
    }
}
