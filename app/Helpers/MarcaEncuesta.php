<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Marcas de dispositivo para una encuesta pública anónima.
 *
 * Hay DOS cookies por encuesta y las dos se llaman a partir del id:
 *
 *   encuesta_{id}_ok      -> el dispositivo YA completó la encuesta.
 *                            Vigencia 1 año. Es la que bloquea.
 *
 *   encuesta_{id}_sesion  -> el client_id anónimo en curso.
 *                            Vigencia 30 días. Es la que permite retomar
 *                            una encuesta abandonada a la mitad.
 *
 * POR QUÉ DOS COOKIES Y NO UNA
 *
 * Son dos estados distintos y hace falta poder distinguirlos sin consultar
 * la base: "empezó y no terminó" tiene que retomar, "terminó" tiene que
 * bloquear. Con una sola cookie habría que ir a la base en cada GET para
 * saber cuál de los dos es.
 *
 * POR QUÉ NO SON httpOnly
 *
 * El front necesita ver que la marca existe para no dibujar el asistente
 * antes de que el servidor conteste. OJO: el valor que ve el JS es el
 * texto CIFRADO por EncryptCookies, no el client_id en claro. El front
 * solo puede preguntar si la cookie está, no leer su contenido. Eso es
 * suficiente y a la vez impide que alguien fabrique una cookie válida a
 * mano: si el descifrado falla, Laravel la descarta y llega como null.
 *
 * LIMITACIÓN ACEPTADA
 *
 * Esto NO impide el duplicado en la base. Quien borre sus cookies o entre
 * en incógnito arranca con un client_id nuevo y sus dos respuestas entran
 * al reporte. Es una barrera contra el reingreso accidental, no un
 * control de unicidad. Ver el comentario de duplicados en ReportController
 * si algún día se decide detectarlos a posteriori.
 */
final class MarcaEncuesta
{
    /** 1 año, en minutos. */
    public const VIGENCIA_BLOQUEO = 525600;

    /** 30 días, en minutos. Alcanza de sobra para retomar. */
    public const VIGENCIA_SESION = 43200;

    public static function nombreBloqueo(int $surveyId): string
    {
        return 'encuesta_' . $surveyId . '_ok';
    }

    public static function nombreSesion(int $surveyId): string
    {
        return 'encuesta_' . $surveyId . '_sesion';
    }

    /**
     * ¿Este dispositivo ya completó esta encuesta?
     */
    public static function yaRespondio(Request $request, int $surveyId): bool
    {
        return $request->cookie(self::nombreBloqueo($surveyId)) !== null;
    }

    /**
     * client_id de la sesión en curso, o null si no hay ninguna.
     *
     * Se valida que sea un entero: una cookie con basura adentro no debe
     * terminar en un findOrFail ni en una query.
     */
    public static function clienteEnCurso(Request $request, int $surveyId): ?int
    {
        $valor = $request->cookie(self::nombreSesion($surveyId));

        if ($valor === null || ! ctype_digit((string) $valor)) {
            return null;
        }

        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    public static function cookieBloqueo(int $surveyId): Cookie
    {
        return cookie(
            name: self::nombreBloqueo($surveyId),
            value: '1',
            minutes: self::VIGENCIA_BLOQUEO,
            httpOnly: false,
        );
    }

    public static function cookieSesion(int $surveyId, int $clientId): Cookie
    {
        return cookie(
            name: self::nombreSesion($surveyId),
            value: (string) $clientId,
            minutes: self::VIGENCIA_SESION,
            httpOnly: false,
        );
    }
}
