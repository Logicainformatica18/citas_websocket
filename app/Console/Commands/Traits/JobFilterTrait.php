<?php

namespace App\Console\Commands\Traits;

trait JobFilterTrait
{
    /**
     * 🚫 Palabras clave NO relacionadas con tecnología.
     */
 protected array $jobTitleBlacklist = [
    // 🚗 Transporte / Logística
    'chofer', 'conductor', 'repartidor', 'delivery', 'motorizado', 'camionero',
    'transportista', 'taxista', 'ayudante de reparto', 'operador de grúa',
    'peón', 'estibador', 'carga', 'descarga',

    // 🏭 Producción / Obreros
    'operario', 'obrero', 'ensamblador', 'fabricación', 'planta', 'producción',
    'maquinista', 'tornero', 'soldador', 'pintor', 'mecánico', 'electricista',
    'gasfitero', 'carpintero', 'ebanista', 'cerrajero', 'jardinero',
    'mantenimiento', 'limpieza', 'supervisor de planta', 'ayudante general',
    'montacargas', 'lavador', 'operador de máquina', 'ensamble',

    // 🏪 Ventas / Atención al cliente
    'vendedor', 'cajero', 'promotor', 'teleoperador', 'call center',
    'representante de ventas', 'asesor comercial', 'atención al cliente',
    'mercaderista', 'impulsador', 'anfitrión', 'canillita', 'tendero',
    'comercial', 'distribuidor', 'demostrador', 'supervisor de ventas',

    // 🍽️ Gastronomía / Restauración
    'mozo', 'cocinero', 'chef', 'ayudante de cocina', 'pastelero',
    'panadero', 'bartender', 'barista', 'barman', 'camarero',
    'mesero', 'lavaplatos', 'hostess', 'encargado de restaurante',

    // 🧹 Servicios generales
    'limpieza', 'housekeeping', 'mucama', 'conserje', 'portero',
    'vigilante', 'seguridad', 'sereno', 'custodio', 'guardia',
    'control de accesos', 'valet parking',

    // 🏢 Administración / Contabilidad
    'contador', 'asistente contable', 'auxiliar contable', 'tesorero',
    'pagador', 'facturador', 'administrativo', 'recepcionista',
    'secretaria', 'asistente de oficina', 'asistente administrativo',
    'auxiliar administrativo', 'documentador', 'archivador',
    'digitador', 'mensajero', 'cadete', 'notificador',

    // 🧠 Recursos Humanos / RRHH
    'recursos humanos', 'rrhh', 'generalista rrhh', 'analista de recursos humanos',
    'asistente de recursos humanos', 'reclutador', 'seleccionador',
    'headhunter', 'psicólogo organizacional', 'nómina', 'beneficios',

    // 📦 Logística / Compras / Almacén
    'almacén', 'almacenista', 'inventario', 'logística', 'compras',
    'abastecimiento', 'planeador logístico', 'coordinador logístico',
    'analista de inventario', 'embalador', 'paquetería',

    // 💬 Marketing / Ventas / Publicidad
    'marketing', 'publicidad', 'comunicaciones', 'community manager',
    'social media', 'diseñador gráfico', 'creativo publicitario',
    'redactor', 'copywriter', 'fotógrafo', 'videógrafo',
    'content creator', 'influencer', 'editor de video',

    // 🧑‍⚕️ Salud
    'enfermero', 'médico', 'odontólogo', 'nutricionista', 'terapeuta',
    'psicólogo', 'farmacéutico', 'paramédico', 'camillero', 'auxiliar de enfermería',
    'fisioterapeuta', 'laboratorista', 'veterinario',

    // 🧱 Construcción / Campo
    'albañil', 'obrero de construcción', 'maestro de obra',
    'capataz', 'ayudante de albañil', 'topógrafo', 'ingeniero civil',
    'técnico de obras', 'operario de campo', 'geólogo', 'obrero agrícola',

    // 👕 Retail / Moda / Belleza
    'dependiente', 'asesor de tienda', 'vendedor retail', 'stylist',
    'manicurista', 'peluquero', 'barbero', 'cosmetóloga', 'estilista',
    'maquillador', 'tinturista',

    // 🎓 Educación / Formación
    'profesor', 'docente', 'maestro', 'educador', 'instructor',
    'capacitador', 'tutor', 'orientador', 'psicopedagogo',

    // 🏠 Otros servicios varios
    'niñera', 'canguro', 'ama de casa', 'cuidador', 'cuidadora',
    'mayordomo', 'empleada doméstica', 'domiciliario', 'sirviente',
    'lavandero', 'planchador',

    // ⚖️ Legal / Oficina
    'abogado', 'paralegal', 'asistente legal', 'procurador',
    'auxiliar jurídico', 'notario', 'archivista', 'gestor documental',

    // 💡 Otros no tecnológicos comunes
    'analista de crédito', 'banca', 'finanzas', 'seguro', 'atención médica',
    'ventas telefónicas', 'recepción', 'operaciones', 'servicios generales',
    'control de calidad', 'supervisor de producción',
];


    /**
     * 🔍 Retorna true si el título está relacionado con tecnología.
     */
    protected function isTechRelated(string $title): bool
    {
        $title = mb_strtolower($title);

        foreach ($this->jobTitleBlacklist as $word) {
            if (str_contains($title, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 🚫 Filtra un array de ofertas, dejando solo las tecnológicas.
     */
    protected function filterTechOffers(array $offers): array
    {
        return array_filter($offers, fn($offer) =>
            $this->isTechRelated($offer['title'] ?? '')
        );
    }
}
