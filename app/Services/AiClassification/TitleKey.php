<?php

namespace App\Services\AiClassification;

use App\Services\JobOffers\TitleExclusionMatcher;

/**
 * Clave del título de una oferta para la clasificación con IA (decisión: sin paréntesis ni "(m/w/d)").
 *
 *   normalize()  minúsculas y sin tildes (como la exclusión por título), sin texto entre paréntesis o
 *                corchetes, sin marcas de género (m/w/d, h/m, f/m/x…) ni puntuación (se conservan + # . /
 *                para C++, C#, .NET, CI/CD), espacios colapsados.
 *   key()        md5 del título normalizado (CHAR(32) en job_offers.title_key); '' si queda vacío.
 *   isGeneric()  el título, sin palabras de nivel (senior, jr, ii…) ni conectores, solo tiene palabras
 *                genéricas (analista, engineer, técnico, manager…): su clase depende de la descripción y
 *                NO se clasifica ni se hereda por título (decisión 13): cada oferta va con su descripción.
 */
final class TitleKey
{
    /** Palabras que por sí solas no dicen qué hace la persona. */
    private const GENERIC = [
        'analista', 'analyst', 'analysts', 'analytiker', 'consultor', 'consultora', 'consultores', 'consultant', 'consultants', 'berater',
        'ingeniero', 'ingeniera', 'ingenieros', 'engineer', 'engineers', 'engineering', 'ingenieur', 'ingenieurin', 'engenheiro', 'ingenieur.in',
        'tecnico', 'tecnica', 'tecnicos', 'technician', 'technical', 'techniker', 'tecnologo', 'tecnologa',
        'especialista', 'especialistas', 'specialist', 'specialists', 'spezialist', 'expert', 'experto', 'experta',
        'coordinador', 'coordinadora', 'coordinator', 'koordinator', 'asistente', 'assistant', 'assistent', 'auxiliar',
        'associate', 'associates', 'asociado', 'asociada', 'manager', 'managers', 'gerente', 'jefe', 'jefa', 'head',
        'director', 'directora', 'lead', 'leader', 'lider', 'ejecutivo', 'ejecutiva', 'executive', 'supervisor', 'supervisora',
        'administrador', 'administradora', 'administrator', 'operador', 'operadora', 'operator', 'operations', 'operaciones',
        'profesional', 'professional', 'practicante', 'pasante', 'intern', 'internship', 'trainee', 'becario', 'becaria',
        'werkstudent', 'werkstudentin', 'praktikant', 'praktikum', 'officer', 'oficial', 'agente', 'agent',
        'representante', 'representative', 'advisor', 'adviser', 'asesor', 'asesora', 'staff', 'member', 'team',
        'programa', 'program', 'programme', 'project', 'proyecto', 'proyectos', 'projects', 'product', 'producto', 'productos',
        'business', 'negocio', 'negocios', 'responsable', 'encargado', 'encargada', 'colaborador', 'colaboradora',
        'generalist', 'generalista', 'scientist', 'cientifico', 'cientifica', 'architect', 'arquitecto', 'arquitecta',
        'developer', 'desarrollador', 'desarrolladora', 'owner', 'partner', 'principal', 'chief', 'officer',
    ];

    /** Nivel, idioma del aviso y conectores: no cuentan para decidir si es genérico. */
    private const FILLER = [
        'senior', 'sr', 'sr.', 'semi', 'semisenior', 'ssr', 'junior', 'jr', 'jr.', 'mid', 'level', 'nivel', 'entry',
        'i', 'ii', 'iii', 'iv', 'v', '1', '2', '3', '4', 'de', 'del', 'la', 'el', 'los', 'las', 'en', 'y', 'e', 'o', 'a',
        'and', 'or', 'of', 'the', 'for', 'to', 'in', 'para', 'con', 'with', 'und', 'fur', 'im', 'der', 'die', 'das',
        'remote', 'remoto', 'remota', 'hybrid', 'hibrido', 'part', 'time', 'full', 'temporary', 'contract',
        '-', '/', '.', '&', 'sr/ssr', 'jr/ssr', 'ssr/sr',
    ];

    /** Clave para guardar en job_offers.title_key ('' si el título queda vacío). */
    public static function key(?string $title): string
    {
        $n = self::normalize((string) $title);

        return $n === '' ? '' : md5($n);
    }

    public static function normalize(string $title): string
    {
        $t = TitleExclusionMatcher::normalize($title);
        $t = preg_replace('/\([^()]*\)|\[[^\]]*\]/u', ' ', $t);                                   // (…) y […]
        $t = preg_replace('/\b[mwfdhxa]\s*\/\s*[mwfdhxa](\s*\/\s*[mwfdhxa]){0,2}\b/u', ' ', $t);   // m/w/d, h/m, f/m/x
        $t = preg_replace('/[^\p{L}\p{N}+#.\/ ]+/u', ' ', $t);
        $t = preg_replace('/\s+/u', ' ', $t);

        return trim($t, " ./");
    }

    /** Recibe el título original o ya normalizado. */
    public static function isGeneric(string $title): bool
    {
        static $generic = null, $filler = null;
        $generic ??= array_flip(self::GENERIC);
        $filler ??= array_flip(self::FILLER);

        $words = preg_split('/[\s\/]+/u', self::normalize($title), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $w) {
            $w = trim($w, '.');
            if ($w === '' || isset($filler[$w]) || isset($generic[$w])) {
                continue;
            }

            return false;   // una palabra con contenido propio (java, sales, nurse, data…)
        }

        return true;
    }
}
