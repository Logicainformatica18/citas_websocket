<?php

namespace Database\Seeders\Concerns;

/**
 * Para seeders de datos falsos: solo se ejecutan en local/testing, aunque se
 * invoquen directamente con `php artisan db:seed --class=...`.
 */
trait OnlyInDemoEnvironments
{
    public const DEMO_ENVIRONMENTS = ['local', 'testing'];

    protected function skipOutsideDemo(): bool
    {
        if (app()->environment(self::DEMO_ENVIRONMENTS)) {
            return false;
        }

        $this->command?->warn(sprintf(
            '%s omitido: solo corre en %s (entorno actual: %s).',
            class_basename(static::class),
            implode('/', self::DEMO_ENVIRONMENTS),
            app()->environment()
        ));

        return true;
    }
}
