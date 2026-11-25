<?php

namespace Tests\Unit;

use App\Services\AiClassification\OfferClassifier;
use App\Services\AiClassification\TitleKey;
use App\Services\AiClassification\TrendClassifier;
use Tests\TestCase;

/** Clave de título, títulos genéricos y reglas de la clasificación con IA (sin base ni IA). */
class AiClassificationRulesTest extends TestCase
{
    public function test_clave_sin_parentesis_ni_genero(): void
    {
        $this->assertSame('senior c++ software developer linux / application',
            TitleKey::normalize('Senior C++ Software Developer (m/w/d) Linux / Application'));
        $this->assertSame(TitleKey::key('Java Developer (m/w/d)'), TitleKey::key('JAVA DEVELOPER'));
        $this->assertSame(TitleKey::key('Técnico de Redes'), TitleKey::key('tecnico de redes [Lima]'));
        $this->assertSame('', TitleKey::key('(m/w/d)'));
    }

    public function test_titulos_genericos(): void
    {
        foreach (['Senior Consultant', 'Product Manager', 'Business Analyst II', 'Ingeniero de Proyectos', 'Técnico',
            'Project Manager', 'Engineer', 'Operations Manager', 'Desarrollador'] as $t) {
            $this->assertTrue(TitleKey::isGeneric($t), $t);
        }
        foreach (['Sales Engineer', 'Data Analyst', 'Java Developer - Remote', 'Customer Success Manager', 'Analista Programador',
            'Desarrollador de Mercado', 'Enfermera', 'Software Developer'] as $t) {
            $this->assertFalse(TitleKey::isGeneric($t), $t);
        }
    }

    public function test_reglas_de_tendencias(): void
    {
        $this->assertSame('generado_por_ia', TrendClassifier::ruleFor('gpt-search', 'https://www.gartner.com/en/documents/1234567'));
        $this->assertSame('perfil', TrendClassifier::ruleFor('tavily-search', 'https://www.linkedin.com/in/alguien'));
        $this->assertSame('curso', TrendClassifier::ruleFor('tavily-search', 'https://www.udemy.com/course/aws'));
        $this->assertSame('portada', TrendClassifier::ruleFor('tavily-search', 'https://techtarget.com/'));
        $this->assertSame('sitemap', TrendClassifier::ruleFor('tavily-search', 'https://www.computerworld.com/sitemap-articles-en-1.xml'));
        $this->assertNull(TrendClassifier::ruleFor('tavily-search', 'https://aws.amazon.com/blogs/database/valkey-turns-two'));
        $this->assertNull(TrendClassifier::ruleFor('search-gpt', 'https://www.linkedin.com/pulse/ssh-market-2026'));
    }

    public function test_clases_normalizadas(): void
    {
        $this->assertSame('no_ti', OfferClassifier::cls(' NO_TI '));
        $this->assertSame('dudosa', OfferClassifier::cls('quizas'));
        // dudosa solo para la categoría inmediata (decisión 8); con otra categoría es ruido
        $this->assertSame('ruido', TrendClassifier::cls('dudosa', 'mencion_de_paso'));
        $this->assertSame('dudosa', TrendClassifier::cls('dudosa', 'categoria'));
        $this->assertSame('relevante', TrendClassifier::cls('relevante', 'tendencia'));
    }
}
