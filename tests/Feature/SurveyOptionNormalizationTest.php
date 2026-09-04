<?php

namespace Tests\Feature;

use App\Http\Controllers\SurveyClientController;
use Tests\TestCase;

class SurveyOptionNormalizationTest extends TestCase
{
    public function test_it_parses_only_the_first_hyphen_and_keeps_human_label(): void
    {
        $controller = new SurveyClientController();
        $method = new \ReflectionMethod($controller, 'normalizarRespuestaOpcion');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '3-Ni de acuerdo ni en desacuerdo');

        $this->assertSame('3', $result['option']);
        $this->assertSame('Ni de acuerdo ni en desacuerdo', $result['answer']);

        $resultWithHyphenInLabel = $method->invoke($controller, '5-Marketing - Comunicaciones');

        $this->assertSame('5', $resultWithHyphenInLabel['option']);
        $this->assertSame('Marketing - Comunicaciones', $resultWithHyphenInLabel['answer']);
    }
}
