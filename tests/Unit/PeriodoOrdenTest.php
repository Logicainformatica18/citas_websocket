<?php

namespace Tests\Unit;

use App\Support\PeriodoOrden;
use Tests\TestCase;

class PeriodoOrdenTest extends TestCase
{
    public function test_normaliza_los_formatos_de_periodo(): void
    {
        config(['silabos_v2.tbd_position' => 'last']);

        $this->assertSame(202510, PeriodoOrden::from('202510'));
        $this->assertSame(202500, PeriodoOrden::from('2025'));
        $this->assertTrue(PeriodoOrden::from('2025') < PeriodoOrden::from('202510'));
        $this->assertSame(PeriodoOrden::MAX, PeriodoOrden::from('TBD'));
        $this->assertSame(PeriodoOrden::MAX, PeriodoOrden::from('tbd_ee'));
        $this->assertSame(0, PeriodoOrden::from('raro'));
    }

    public function test_tbd_al_principio_si_se_configura(): void
    {
        config(['silabos_v2.tbd_position' => 'first']);

        $this->assertSame(0, PeriodoOrden::from('TBD_EE'));
        $this->assertTrue(PeriodoOrden::from('TBD') < PeriodoOrden::from('202310'));
    }

    public function test_reconoce_formatos_conocidos(): void
    {
        $this->assertTrue(PeriodoOrden::isKnownFormat('202610'));
        $this->assertTrue(PeriodoOrden::isKnownFormat('2026'));
        $this->assertTrue(PeriodoOrden::isKnownFormat('TBD_EE'));
        $this->assertFalse(PeriodoOrden::isKnownFormat('2026-I'));
    }
}
