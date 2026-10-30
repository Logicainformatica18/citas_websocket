<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cualquier test que procese un sílabo escribe en el log del catálogo:
        // que no vaya al storage/logs/catalogo.log real.
        config(['logging.channels.catalogo.path' => storage_path('framework/testing/catalogo-' . getmypid() . '.log')]);
    }
}
