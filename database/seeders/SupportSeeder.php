<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Services\TicketNumberGenerator;
use Database\Factories\CommentFactory;
use Database\Factories\SupportDetailFactory;
use Database\Factories\SupportFactory;
use Database\Seeders\CatalogSeeder as C;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Solicitudes (supports) + detalles (support_details = tickets) + comentarios
 * (historial de estados internos), distribuidos en los últimos 90 días.
 *
 * Reglas respetadas (deducidas de SupportController / SupportDetailController):
 *  - area_id = 1 (ATC) ⇒ external_state "Por Asignar"; otra área ⇒ "Asignado" y derived = área.
 *  - status (Pendiente|Atendido|Cerrado) coherente con internal_state.
 *  - Atendido ⇒ attended_start/attended_end; Cerrado ⇒ además ticket_end (fecha de cierre).
 *  - Ticket "TK-00001" correlativo por fecha con ticket_start; sin ticket ⇒ "TK-" (default del backend).
 *  - status_global ("¿Con cita?") = 'Sí' cuando algún detalle tiene tipo de cita y reserva.
 *  - Manzana/proyecto salen de los lotes (sales) del cliente; no se repite
 *    cliente+proyecto+asunto+manzana (validación de duplicados del store).
 *  - channel: rol ATC ⇒ 'whatsapp' (permiso Canal.whatsapp); ATC_Oficina ⇒ null.
 */
class SupportSeeder extends Seeder
{
    private const TOTAL = 80;

    /** asunto => [id_area, id_motivos_cita, type_id] */
    private const SUBJECT_MAP = [
        'Avance de Proyecto' => [6, 17, 1],
        'Boletas' => [5, 13, 2],
        'Cesion' => [3, 7, 2],
        'Cita con legal' => [3, 6, 2],
        'Certificado de lote' => [4, 10, 2],
        'Constancia de no adeudo' => [5, 14, 2],
        'Desestimiento' => [3, 8, 3],
        'EE.CC' => [5, 12, 1],
        'Formalización' => [4, 11, 2],
        'Información de su lote' => [2, 5, 1],
        'Pagos' => [5, 15, 3],
        'Recojo de contrato' => [2, 3, 2],
        'Recojo de Letras' => [2, 4, 2],
        'Traspaso de aportes' => [3, 9, 2],
        'Visita a proyecto' => [6, 16, 2],
    ];

    private const DESCRIPTIONS = [
        'Avance de Proyecto' => [
            'Cliente consulta por el avance de las obras de habilitación urbana en {proyecto}.',
            'Solicita información sobre la fecha estimada de instalación de agua y luz en {proyecto}.',
        ],
        'Boletas' => [
            'Solicita la emisión de las boletas de los pagos realizados en los últimos tres meses.',
            'Cliente indica que no recibió la boleta de su última cuota por correo.',
        ],
        'Cesion' => [
            'Desea ceder la posición contractual del lote {lote} a un familiar directo.',
            'Consulta requisitos y costos para la cesión de derechos del lote {lote}.',
        ],
        'Cita con legal' => [
            'Requiere una cita con el área legal para revisar cláusulas de su contrato.',
            'Solicita asesoría legal por diferencias en el metraje del lote {lote}.',
        ],
        'Certificado de lote' => [
            'Solicita certificado de posesión del lote {lote} para trámite municipal.',
            'Requiere certificado de lote para presentar en entidad financiera.',
        ],
        'Constancia de no adeudo' => [
            'Cliente canceló el total del lote {lote} y solicita constancia de no adeudo.',
            'Solicita constancia de no adeudo para iniciar el proceso de formalización.',
        ],
        'Desestimiento' => [
            'Cliente manifiesta su intención de desistir de la compra del lote {lote} por motivos económicos.',
            'Solicita información sobre penalidades y devolución por desistimiento.',
        ],
        'EE.CC' => [
            'Solicita su estado de cuenta actualizado del lote {lote}.',
            'Cliente no reconoce un cargo en su estado de cuenta y pide el detalle.',
        ],
        'Formalización' => [
            'Consulta el estado del proceso de independización e inscripción en SUNARP.',
            'Desea iniciar la formalización del lote {lote} en {proyecto}.',
        ],
        'Información de su lote' => [
            'Consulta la ubicación exacta y linderos del lote {lote} en {proyecto}.',
            'Solicita el plano de ubicación del lote {lote}.',
        ],
        'Pagos' => [
            'Realizó un depósito que no figura aplicado en su cronograma de pagos.',
            'Solicita reprogramar la fecha de pago de sus cuotas.',
        ],
        'Recojo de contrato' => [
            'Cliente desea coordinar el recojo de su contrato firmado.',
            'Consulta si su contrato ya está listo para recoger en oficina.',
        ],
        'Recojo de Letras' => [
            'Solicita recoger las letras canceladas del lote {lote}.',
            'Coordina el recojo de letras de cambio en la oficina principal.',
        ],
        'Traspaso de aportes' => [
            'Solicita traspasar sus aportes del lote {lote} a otro lote del mismo proyecto.',
            'Consulta la posibilidad de trasladar sus pagos a un lote en otro proyecto.',
        ],
        'Visita a proyecto' => [
            'Desea programar una visita guiada a {proyecto} para conocer su lote.',
            'Solicita visita a {proyecto} para verificar el estado del lote {lote}.',
        ],
    ];

    private const COMMENTS = [
        C::INTERNAL_PENDIENTE => [
            'Solicitud registrada, se derivará al área correspondiente.',
            'Se informó al cliente el plazo de atención.',
            'En cola de atención del área.',
        ],
        C::INTERNAL_OBSERVADO => [
            'Falta copia de DNI del titular, se solicitó por WhatsApp.',
            'Cliente debe presentar el voucher de pago original.',
            'Documentación incompleta, se espera respuesta del cliente.',
        ],
        C::INTERNAL_ATENDIDO => [
            'Se atendió al cliente y se entregó la documentación solicitada.',
            'Se envió la información al correo del cliente. Conforme.',
            'Cliente atendido en oficina, queda conforme con la respuesta.',
        ],
    ];

    private int $ticketSeq = 0;

    public function run(): void
    {
        $faker = fake('es_ES');
        $now = Carbon::now();

        $creators = User::role(['ATC', 'ATC_Oficina', 'Administrador'])->with('roles')->get();
        $staff = User::role(['ATC', 'ATC_Oficina', 'Administrador', 'Asistente'])->get();
        $clients = Client::with('sales')->get()->filter(fn ($c) => $c->sales->isNotEmpty())->values();
        $areaNames = C::AREAS;
        $motives = DB::table('motivos_cita')->get()->keyBy('id_motivos_cita');
        $waitDays = DB::table('dias_espera')->pluck('dias', 'id_dias_espera')
            ->map(fn ($d) => (int) $d);
        $projects = DB::table('proyecto')->pluck('descripcion', 'id_proyecto');

        // Fechas ordenadas para que los tickets sean correlativos en el tiempo.
        $dates = collect(range(1, self::TOTAL))
            ->map(fn () => Carbon::instance($faker->dateTimeBetween('-90 days', '-30 minutes')))
            ->sort()->values();

        $used = [];

        foreach ($dates as $createdAt) {
            $client = $clients->random();
            $creator = $creators->random();
            $role = $creator->roles->first()->name;
            $withCita = $faker->boolean(35);
            $nDetails = $faker->randomElement([1, 1, 1, 1, 1, 1, 1, 2, 2, 3]);

            $support = SupportFactory::new()->create([
                'client_id' => $client->id_cliente,
                'created_by' => $creator->id,
                'cellphone' => $client->Telefono,
                'status_global' => 'No',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $hasCita = false;
            $lastTouch = $createdAt->copy();
            $subjects = collect(array_keys(self::SUBJECT_MAP))->shuffle();

            for ($i = 0; $i < $nDetails; $i++) {
                $sale = $client->sales->random();
                $subject = $subjects->first(function ($s) use ($client, $sale, $used) {
                    return ! isset($used["{$client->id_cliente}|{$sale->project_id}|{$s}|{$sale->mz_lote}"]);
                });
                if (! $subject) {
                    continue;
                }
                $subjects = $subjects->reject(fn ($s) => $s === $subject)->values();
                $used["{$client->id_cliente}|{$sale->project_id}|{$subject}|{$sale->mz_lote}"] = true;

                [$targetArea, $targetMotive, $typeId] = self::SUBJECT_MAP[$subject];
                $detailCreated = $createdAt->copy()->addMinutes($i * 2);
                $status = $this->pickStatus($detailCreated, $now);

                $derivedToArea = $status === 'Pendiente' ? $faker->boolean(55) : $faker->boolean(85);
                $areaId = $derivedToArea ? $targetArea : C::AREA_ATC;
                $motiveId = $derivedToArea ? $targetMotive : ($status === 'Pendiente' ? null : $faker->randomElement([1, 2]));
                $internal = match ($status) {
                    'Atendido' => C::INTERNAL_ATENDIDO,
                    'Cerrado' => C::INTERNAL_CERRADO,
                    default => $faker->boolean(25) ? C::INTERNAL_OBSERVADO : C::INTERNAL_PENDIENTE,
                };

                // Fechas de atención/cierre siempre entre la creación y ahora.
                $span = max(60, $now->getTimestamp() - $detailCreated->getTimestamp());
                $attendedStart = $attendedEnd = $ticketEnd = null;
                if ($status !== 'Pendiente') {
                    $attendedStart = $detailCreated->copy()->addSeconds((int) ($span * $faker->randomFloat(2, 0.05, 0.4)));
                    $attendedEnd = $attendedStart->copy()->addMinutes($faker->numberBetween(10, 60));
                    if ($attendedEnd->gt($now)) {
                        $attendedEnd = $now->copy()->subMinute();
                    }
                }
                if ($status === 'Cerrado') {
                    $rest = max(60, $now->getTimestamp() - $attendedEnd->getTimestamp());
                    $ticketEnd = $attendedEnd->copy()->addSeconds((int) ($rest * $faker->randomFloat(2, 0.05, 0.5)));
                }

                // Cita: solo si hay motivo (aporta tipo de cita y días de espera).
                $tipoCita = $diaEspera = null;
                $reservation = $detailCreated->copy();
                $attendedAt = $detailCreated->copy()->addHour();
                if ($withCita && $motiveId) {
                    $m = $motives[$motiveId];
                    $tipoCita = $m->id_tipo_cita;
                    $diaEspera = $m->id_dia_espera;
                    $reservation = $attendedStart?->copy()
                        ?? $detailCreated->copy()->addDays($waitDays[$diaEspera] ?? 1)->setTime($faker->numberBetween(9, 17), $faker->randomElement([0, 30]));
                    $attendedAt = $reservation->copy();
                    $hasCita = true;
                }

                $hasTicket = $faker->boolean(85);
                $channel = match ($role) {
                    'ATC' => 'whatsapp',
                    'ATC_Oficina' => null,
                    default => $faker->randomElement(['whatsapp', 'presencial']),
                };

                $description = str_replace(
                    ['{lote}', '{proyecto}'],
                    [$sale->mz_lote, $projects[$sale->project_id] ?? ''],
                    $faker->randomElement(self::DESCRIPTIONS[$subject])
                );

                $detail = SupportDetailFactory::new()->create([
                    'support_id' => $support->id,
                    'project_id' => $sale->project_id,
                    'area_id' => $areaId,
                    'id_motivos_cita' => $motiveId,
                    'id_tipo_cita' => $tipoCita,
                    'id_dia_espera' => $diaEspera,
                    'internal_state_id' => $internal,
                    'external_state_id' => $areaId === C::AREA_ATC ? C::EXTERNAL_POR_ASIGNAR : C::EXTERNAL_ASIGNADO,
                    'type_id' => $typeId,
                    'subject' => $subject,
                    'description' => $description,
                    'priority' => $faker->randomElement(['Baja', 'Baja', 'Baja', 'Media', 'Media', 'Alta']),
                    'type' => C::TYPES[$typeId],
                    'status' => $status,
                    'reservation_time' => $reservation,
                    'attended_at' => $attendedAt,
                    'derived' => $areaId === C::AREA_ATC ? null : $areaNames[$areaId],
                    'Manzana' => $sale->mz_lote,
                    'comment' => $faker->boolean(30) ? $faker->randomElement([
                        'Cliente con preferencia de atención por la tarde.',
                        'Adjuntó voucher por WhatsApp.',
                        'Segunda vez que consulta por el mismo tema.',
                        'Titular autoriza a su cónyuge para el trámite.',
                    ]) : null,
                    'ticket' => $hasTicket ? $this->nextTicket() : 'TK-',
                    'ticket_start' => $hasTicket ? $detailCreated : null,
                    'attended_start' => $attendedStart,
                    'attended_end' => $attendedEnd,
                    'ticket_end' => $ticketEnd,
                    'channel' => $channel,
                    'created_at' => $detailCreated,
                    'updated_at' => $ticketEnd ?? $attendedEnd ?? $detailCreated,
                ]);

                $lastComment = $this->seedComments($detail, $status, $internal, $derivedToArea, $detailCreated, $attendedEnd, $now, $creator, $staff);
                $lastTouch = collect([$lastTouch, $ticketEnd, $attendedEnd, $lastComment])->filter()->max();
            }

            $support->forceFill([
                'status_global' => $hasCita ? 'Sí' : 'No',
                'updated_at' => $lastTouch,
            ])->saveQuietly();
        }

        // Los TK se escribieron directamente: la secuencia continúa desde el último.
        app(TicketNumberGenerator::class)->syncWithExisting();
    }

    /** Los tickets más antiguos tienden a estar cerrados; los recientes, pendientes. */
    private function pickStatus(Carbon $created, Carbon $now): string
    {
        $age = $created->diffInDays($now);
        $weights = match (true) {
            $age > 30 => ['Cerrado' => 50, 'Atendido' => 35, 'Pendiente' => 15],
            $age > 7 => ['Cerrado' => 30, 'Atendido' => 40, 'Pendiente' => 30],
            default => ['Cerrado' => 5, 'Atendido' => 25, 'Pendiente' => 70],
        };
        $r = random_int(1, 100);
        foreach ($weights as $status => $w) {
            if (($r -= $w) <= 0) {
                return $status;
            }
        }

        return 'Pendiente';
    }

    private function nextTicket(): string
    {
        return 'TK-' . str_pad((string) ++$this->ticketSeq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Comentarios en orden cronológico; su internal_state_id es el historial
     * de estados del detalle. Devuelve la fecha del último comentario.
     */
    private function seedComments($detail, string $status, int $internal, bool $derived, Carbon $from, ?Carbon $attendedEnd, Carbon $now, User $creator, Collection $staff): ?Carbon
    {
        $faker = fake('es_ES');

        $states = match ($status) {
            'Pendiente' => $derived
                ? [...array_fill(0, $faker->numberBetween(0, 1), C::INTERNAL_PENDIENTE), $internal]
                : ($faker->boolean(40) ? [C::INTERNAL_PENDIENTE] : []),
            'Atendido', 'Cerrado' => [
                C::INTERNAL_PENDIENTE,
                ...($faker->boolean(35) ? [C::INTERNAL_OBSERVADO] : []),
                C::INTERNAL_ATENDIDO,
            ],
        };
        if (! $states) {
            return null;
        }

        $until = $attendedEnd ?? $now;
        $stepSeconds = max(60, intdiv($until->getTimestamp() - $from->getTimestamp(), count($states) + 1));
        $at = $from->copy();
        $last = null;

        foreach ($states as $idx => $stateId) {
            $isLast = $idx === count($states) - 1;
            $at = ($isLast && $attendedEnd) ? $attendedEnd->copy() : $at->copy()->addSeconds($faker->numberBetween(intdiv($stepSeconds, 2), $stepSeconds));
            if ($at->gt($now)) {
                $at = $now->copy();
            }
            CommentFactory::new()->create([
                'support_detail_id' => $detail->id,
                'user_id' => $idx === 0 ? $creator->id : $staff->random()->id,
                'comment' => $faker->randomElement(self::COMMENTS[$stateId]),
                'internal_state_id' => $stateId,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $last = $at;
        }

        return $last;
    }
}
