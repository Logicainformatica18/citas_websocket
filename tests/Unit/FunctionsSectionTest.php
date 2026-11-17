<?php

namespace Tests\Unit;

use App\Support\FunctionsSection;
use App\Support\JobFunctions;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures: respuestas reales de cada fuente guardadas el 2026-11-17
 * (tests/Fixtures/FunctionsSection). USAJOBS se arma con la forma documentada
 * de la API porque no hay clave para descargar una oferta real.
 */
class FunctionsSectionTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/FunctionsSection/' . $name);
    }

    private function lines(string $text): array
    {
        return explode("\n", $text);
    }

    // ---------------------------------------------------------------- Greenhouse

    public function test_greenhouse_escaped_html_stops_at_must_haves(): void
    {
        // content de Greenhouse viene escapado (&lt;p&gt;…); la sección termina en "Must Haves:"
        $s = FunctionsSection::extract($this->fixture('greenhouse_discord_technical_sourcer.txt'));

        $this->assertNotNull($s);
        $this->assertSame('Key Responsibilities', $s['headline']);
        $this->assertStringStartsWith('- Partner strategically with hiring managers', $s['text']);
        $this->assertStringNotContainsString('Must Haves', $s['text']);
        $this->assertStringNotContainsString('Excellent stakeholder management', $s['text']);   // primer requisito
        $this->assertStringNotContainsString('&lt;', $s['text']);
    }

    public function test_greenhouse_stops_at_what_we_look_for(): void
    {
        $s = FunctionsSection::extract($this->fixture('greenhouse_platform_security_manager.txt'));

        $this->assertNotNull($s);
        $this->assertSame("What you'll do", $s['headline']);
        $this->assertStringNotContainsString('What we look for', $s['text']);
        $this->assertStringNotContainsString('3+ years of experience', $s['text']);
    }

    public function test_greenhouse_prefers_bulleted_section_over_prose(): void
    {
        // Brex: "What you'll do" (párrafo) … "Responsibilities" (lista) … "Requirements"
        $s = FunctionsSection::extract($this->fixture('greenhouse_brex_frontend.txt'));

        $this->assertNotNull($s);
        $this->assertSame('Responsibilities', $s['headline']);
        $this->assertStringStartsWith('- Work with engineers across the company', $s['text']);
        $this->assertStringEndsWith('- Tune and polish features to a high-degree of excellence.', $s['text']);
        $this->assertStringNotContainsString('As a Senior Frontend Engineer', $s['text']);
        $this->assertStringNotContainsString('7+ years', $s['text']);
    }

    // ---------------------------------------------------------------- Jobicy

    public function test_jobicy_skips_metadata_lines_after_heading(): void
    {
        $s = FunctionsSection::extract($this->fixture('jobicy_partner_technical_manager.html'));

        $this->assertNotNull($s);
        $this->assertSame('What You’ll Do', $s['headline']);
        $this->assertStringStartsWith('- Serve as the technical lead', $s['text']);
        $this->assertStringNotContainsString('Location:', $s['text']);
        $this->assertStringNotContainsString('Reports to:', $s['text']);
    }

    // ---------------------------------------------------------------- RemoteOK

    public function test_remoteok_what_you_will_do(): void
    {
        $s = FunctionsSection::extract($this->fixture('remoteok_privacy_research_engineer.html'));

        $this->assertNotNull($s);
        $this->assertSame("What You'll Do", $s['headline']);
        $this->assertStringStartsWith('- Build systems to detect PII', $s['text']);
        foreach ($this->lines($s['text']) as $line) {
            $this->assertStringStartsWith('- ', $line);
        }
    }

    // ---------------------------------------------------------------- Selecta

    public function test_selecta_funciones_in_spanish(): void
    {
        $s = FunctionsSection::extract($this->fixture('selecta_jefe_construccion.html'));

        $this->assertNotNull($s);
        $this->assertSame('Funciones', $s['headline']);
        $this->assertStringStartsWith('- Liderar la gestión integral de las obras', $s['text']);
        $this->assertStringNotContainsString('Requisitos', $s['text']);
    }

    // ---------------------------------------------------------------- casos null (precisión)

    public function test_ambiguous_heading_your_impact_returns_null(): void
    {
        // "Your Impact" introduce prosa; no está en la lista estricta
        $this->assertNull(FunctionsSection::extract($this->fixture('jobicy_your_impact_only.html')));
    }

    public function test_truncated_description_without_heading_returns_null(): void
    {
        // Adzuna: description recortada a 500 caracteres, sin secciones
        $this->assertNull(FunctionsSection::extract($this->fixture('adzuna_truncated.txt')));
    }

    public function test_the_role_and_empty_inputs_return_null(): void
    {
        $this->assertNull(FunctionsSection::extract(null));
        $this->assertNull(FunctionsSection::extract(''));
        $this->assertNull(FunctionsSection::extract('<h3>The Role</h3><p>You will join a great team.</p><ul><li>Python</li></ul>'));
        $this->assertNull(FunctionsSection::extract('<p><strong>Responsibilities:</strong> build things and ship them to production every week.</p>'));
    }

    public function test_heading_without_list_and_without_closing_heading_returns_null(): void
    {
        $this->assertNull(FunctionsSection::extract('<h3>Responsibilities</h3><p>Lots of prose until the end of the posting with no structure at all</p>'));
    }

    // ---------------------------------------------------------------- JobFunctions (campo propio)

    public function test_wantedly_plain_text_field_keeps_lines(): void
    {
        $v = JobFunctions::fromField($this->fixture('wantedly_description.txt'), 'こんなことやります');

        $this->assertSame(JobFunctions::FIELD, $v['functions_origin']);
        $this->assertSame('こんなことやります', $v['functions_headline']);
        $this->assertStringStartsWith("✅ 副業OK！Web開発の経験を自社サイト構築に活かせる\n✅ Next.js", $v['functions']);
    }

    public function test_usajobs_major_duties_list(): void
    {
        $v = JobFunctions::fromList([
            'Develops and maintains enterprise applications & services.',
            'Performs <analysis> of system requirements.',
            '',
        ], 'Major Duties');

        $this->assertSame("- Develops and maintains enterprise applications & services.\n- Performs <analysis> of system requirements.", $v['functions']);
        $this->assertSame('Major Duties', $v['functions_headline']);
        $this->assertSame(JobFunctions::FIELD, $v['functions_origin']);
        $this->assertSame(JobFunctions::none(), JobFunctions::fromList([], 'Major Duties'));
        $this->assertSame(JobFunctions::none(), JobFunctions::fromList(null, 'Major Duties'));
    }

    public function test_usajobs_descriptor_mapping(): void
    {
        // Forma de SearchResult.SearchResultItems[].MatchedObjectDescriptor (API Search de USAJOBS)
        $descriptor = [
            'PositionTitle' => 'IT Specialist (APPSW)',
            'UserArea'      => ['Details' => [
                'JobSummary'  => 'This position is located in the Office of the CIO.',
                'MajorDuties' => [
                    'Designs, develops and maintains software applications.',
                    'Conducts code reviews and enforces secure coding standards.',
                ],
            ]],
        ];

        $v = JobFunctions::fromUsajobs($descriptor);
        $this->assertSame("- Designs, develops and maintains software applications.\n- Conducts code reviews and enforces secure coding standards.", $v['functions']);
        $this->assertSame('Major Duties', $v['functions_headline']);
        $this->assertSame('field', $v['functions_origin']);

        // Sin MajorDuties (o vacío) → todo null
        $this->assertSame(JobFunctions::none(), JobFunctions::fromUsajobs(['UserArea' => ['Details' => ['MajorDuties' => []]]]));
        $this->assertSame(JobFunctions::none(), JobFunctions::fromUsajobs([]));
    }

    public function test_wantedly_project_mapping(): void
    {
        $v = JobFunctions::fromWantedly(['id' => 1, 'description' => "【仕事内容】\nWebアプリの開発\n\n運用・改善"]);
        $this->assertSame("【仕事内容】\nWebアプリの開発\n運用・改善", $v['functions']);
        $this->assertSame('こんなことやります', $v['functions_headline']);
        $this->assertSame('field', $v['functions_origin']);

        $this->assertSame(JobFunctions::none(), JobFunctions::fromWantedly(['id' => 2, 'description' => '']));
        $this->assertSame(JobFunctions::none(), JobFunctions::fromWantedly(['id' => 3]));
    }

    public function test_from_description_sets_heuristic_origin_or_all_null(): void
    {
        $v = JobFunctions::fromDescription($this->fixture('selecta_jefe_construccion.html'));
        $this->assertSame(JobFunctions::HEURISTIC, $v['functions_origin']);
        $this->assertSame('Funciones', $v['functions_headline']);

        $this->assertSame(JobFunctions::none(), JobFunctions::fromDescription($this->fixture('adzuna_truncated.txt')));
    }

    public function test_with_origin_only_when_functions_present(): void
    {
        $this->assertSame('field', JobFunctions::withOrigin(['functions' => 'x', 'functions_headline' => null], 'field')['functions_origin']);
        $this->assertNull(JobFunctions::withOrigin(['functions' => null, 'functions_headline' => null], 'field')['functions_origin']);
    }
}
