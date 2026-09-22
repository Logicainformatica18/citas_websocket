<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AnalyzeCourseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El análisis encadena hasta tres llamadas a la IA (extracción de marcos,
     * verificación de versiones en línea y análisis curricular), y la de búsqueda
     * es la más lenta. Con el timeout por defecto de 60 segundos el job muere a
     * mitad de camino y deja el análisis sin guardar.
     *
     * IMPORTANTE: `retry_after` en config/queue.php debe ser MAYOR que este valor.
     * Si no, la cola vuelve a tomar el job mientras el primero sigue corriendo y el
     * curso se analiza dos veces, con dos filas insertadas y doble costo de tokens.
     */
    public int $timeout = 600;

    /** Un reintento basta: si falla dos veces, el problema no es transitorio. */
    public int $tries = 2;

    protected int $courseId;

    /** Permite desactivar la búsqueda en línea en corridas masivas o de prueba. */
    protected bool $descubrirVersiones;

    public function __construct(int $courseId, bool $descubrirVersiones = true)
    {
        $this->courseId           = $courseId;
        $this->descubrirVersiones = $descubrirVersiones;
    }

    public function handle(): void
    {
        $parametros = ['course_id' => $this->courseId];

        // Las banderas se pasan como clave con valor true, no como texto suelto.
        if ($this->descubrirVersiones) {
            $parametros['--descubrir-versiones'] = true;
        }

        $inicio = microtime(true);

        // Artisan::call devuelve el código de salida del comando. Sin revisarlo, un
        // curso sin sílabo procesado o un error de la API terminarían como job
        // exitoso y nadie se enteraría.
        $codigo = Artisan::call('curriculum:analyze-course', $parametros);

        $segundos = round(microtime(true) - $inicio, 1);
        $salida   = trim(Artisan::output());

        if ($codigo !== 0) {
            Log::error('ANALISIS CURSO FALLIDO', [
                'course_id' => $this->courseId,
                'exit_code' => $codigo,
                'segundos'  => $segundos,
                'salida'    => $salida,
            ]);

            // Se lanza la excepción para que la cola lo marque como fallido y quede
            // en `failed_jobs`. Sin esto, el fallo se pierde.
            throw new \RuntimeException(
                "El análisis del curso {$this->courseId} terminó con código {$codigo}"
            );
        }

        Log::info('ANALISIS CURSO COMPLETADO', [
            'course_id' => $this->courseId,
            'segundos'  => $segundos,
            'salida'    => $salida,
        ]);
    }

    /**
     * Se ejecuta cuando se agotan los intentos. Útil para que la interfaz pueda
     * mostrarle al usuario que su análisis no salió, en lugar de dejarlo esperando.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('ANALISIS CURSO AGOTADO', [
            'course_id' => $this->courseId,
            'error'     => $e->getMessage(),
        ]);
    }
}