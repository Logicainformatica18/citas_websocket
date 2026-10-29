<?php

namespace Tests\Feature;

use App\Events\SyllabusProcessed;
use App\Models\Course;
use App\Models\Language;
use App\Models\Methodology;
use App\Models\Syllabus;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use App\Services\Catalog\CatalogPrompt;
use App\Services\Catalog\CatalogResolver;
use App\Services\Syllabus\SyllabusExtractionService;
use App\Support\CatalogName;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private string $catalogLog;

    protected function setUp(): void
    {
        parent::setUp();

        // En la base real existen market_entities y la columna market_entity_id; en la de tests, no.
        foreach (['technologies', 'languages', 'methodologies'] as $table) {
            if (!Schema::hasColumn($table, 'market_entity_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('market_entity_id')->nullable());
            }
        }
        if (!Schema::hasTable('market_entities')) {
            Schema::create('market_entities', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('entity_type');
            });
        }

        $this->catalogLog = storage_path('framework/testing/catalogo-' . getmypid() . '.log');
        @unlink($this->catalogLog);
        config(['logging.channels.catalogo.path' => $this->catalogLog, 'catalogo.prompt.cache_segundos' => 0]);
        CatalogPrompt::forget();

        foreach (['other', 'framework', 'concept', 'metodology', 'methodology'] as $name) {
            TechnologyCategory::create(['name' => $name]);
        }

        Event::fake([SyllabusProcessed::class]);
    }

    protected function tearDown(): void
    {
        @unlink($this->catalogLog);
        CatalogPrompt::forget();
        parent::tearDown();
    }

    // ── Normalización y alias ───────────────────────────────────

    public function test_la_clave_normalizada_ignora_mayusculas_espacios_signos_y_tildes(): void
    {
        $this->assertSame(CatalogName::key('IoT'), CatalogName::key('IOT'));
        $this->assertSame(CatalogName::key('IoT'), CatalogName::key('I.o.T'));
        $this->assertSame(CatalogName::key('Big Data'), CatalogName::key('Bigdata'));
        $this->assertSame(CatalogName::key('Node.js'), CatalogName::key('node-js'));
        $this->assertSame('arbolesdedecision', CatalogName::key('Árboles de decisión'));
        $this->assertNotSame(CatalogName::key('C++'), CatalogName::key('C#'));
        $this->assertNotSame(CatalogName::key('C'), CatalogName::key('C#'));
    }

    public function test_las_claves_de_los_alias_estan_normalizadas(): void
    {
        foreach (config('catalogo.alias') as $type => $aliases) {
            foreach ($aliases as $key => $canonical) {
                $this->assertSame(CatalogName::key($key), $key, "Alias '$key' de $type no está normalizado");
                $this->assertNotSame('', CatalogName::clean($canonical), "Alias '$key' de $type sin nombre canónico");
            }
        }
    }

    public function test_un_nombre_que_normaliza_igual_usa_el_existente(): void
    {
        // Power BI no tiene alias: entra solo por la normalización.
        $powerBi = Technology::create(['name' => 'Power BI']);
        $resolver = new CatalogResolver();

        $this->assertSame($powerBi->id, $resolver->resolve('technologies', 'POWERBI')->id);
        $this->assertSame($powerBi->id, $resolver->resolve('technologies', ' Power-BI ')->id);
        $this->assertSame(1, Technology::count());
        $this->assertSame('normalizado', $resolver->report()['technologies'][0]['via']);
    }

    public function test_la_coincidencia_exacta_no_se_registra(): void
    {
        Technology::create(['name' => 'Laravel']);
        $resolver = new CatalogResolver();

        $resolver->resolve('technologies', 'Laravel');

        $this->assertSame([], $resolver->report());
    }

    public function test_un_alias_lleva_al_nombre_canonico_existente(): void
    {
        $cloud = Technology::create(['name' => 'Cloud']);
        $resolver = new CatalogResolver();

        $this->assertSame($cloud->id, $resolver->resolve('technologies', 'Cloud Computing')->id);
        $this->assertSame(['recibido' => 'Cloud Computing', 'id' => $cloud->id, 'nombre' => 'Cloud', 'via' => 'alias'],
            $resolver->report()['technologies'][0]);
    }

    public function test_un_alias_sin_canonico_lo_crea_con_la_escritura_canonica_y_lo_registra(): void
    {
        $resolver = new CatalogResolver();

        $bigData = $resolver->resolve('technologies', 'Bigdata');
        $again = $resolver->resolve('technologies', 'BIG DATA');

        $this->assertSame('Big Data', $bigData->name);
        $this->assertSame($bigData->id, $again->id);
        $this->assertSame(1, Technology::count());
        $this->assertSame('creado', $resolver->report()['technologies'][0]['via']);
        $this->assertStringContainsString('Big Data', file_get_contents($this->catalogLog));
    }

    public function test_no_hay_coincidencia_por_parecido(): void
    {
        $azure = Technology::create(['name' => 'Azure Machine Learning']);

        $ml = (new CatalogResolver())->resolve('technologies', 'Machine Learning');

        $this->assertNotSame($azure->id, $ml->id);
        $this->assertSame('Machine Learning', $ml->name);
    }

    public function test_entre_duplicados_gana_el_ligado_al_mercado(): void
    {
        Technology::create(['name' => 'JupyterLab']);
        $linked = Technology::create(['name' => 'Jupyter Lab']);
        DB::table('technologies')->where('id', $linked->id)->update(['market_entity_id' => 7]);

        $this->assertSame($linked->id, (new CatalogResolver())->resolve('technologies', 'jupyter lab')->id);
    }

    public function test_el_alias_elige_por_nombre_exacto_entre_duplicados_con_la_misma_clave(): void
    {
        // Las dos ligadas al mercado: sin alias ganaría la de id menor ("VirtualBox").
        $junta = Technology::create(['name' => 'VirtualBox']);
        $separada = Technology::create(['name' => 'Virtual Box']);
        DB::table('technologies')->whereIn('id', [$junta->id, $separada->id])->update(['market_entity_id' => 9]);
        $unity = Technology::create(['name' => 'Unity']);
        Technology::create(['name' => 'Unity3D']);
        Technology::create(['name' => 'Unity 3D']);
        $resolver = new CatalogResolver();

        $this->assertSame($separada->id, $resolver->resolve('technologies', 'VirtualBox')->id);
        $this->assertSame($separada->id, $resolver->resolve('technologies', 'virtual box')->id);
        $this->assertSame($unity->id, $resolver->resolve('technologies', 'Unity3D')->id);
        $this->assertSame($unity->id, $resolver->resolve('technologies', 'Unity 3D')->id);
        $this->assertSame(5, Technology::count());
        $this->assertSame(['alias', 'alias', 'alias', 'alias'], array_column($resolver->report()['technologies'], 'via'));
    }

    public function test_los_duplicados_del_catalogo_van_al_de_mas_ofertas(): void
    {
        foreach (['Azure', 'Microsoft Azure', 'Adobe Photoshop', 'Photoshop', 'SQL Server', 'Microsoft SQL Server',
                     'Power BI', 'Microsoft Power BI', 'NOSQL', 'Bases de datos NoSQL'] as $name) {
            Technology::create(['name' => $name]);
        }
        $resolver = new CatalogResolver();

        foreach (['Microsoft Azure' => 'Azure', 'Photoshop' => 'Adobe Photoshop', 'Microsoft SQL Server' => 'SQL Server',
                     'Microsoft Power BI' => 'Power BI', 'Bases de datos NoSQL' => 'NOSQL'] as $received => $canonical) {
            $this->assertSame($canonical, $resolver->resolve('technologies', $received)->name);
        }
        $this->assertSame(10, Technology::count());
    }

    public function test_lenguajes_y_metodologias_usan_sus_propios_alias(): void
    {
        $js = Language::create(['name' => 'JavaScript']);
        $agile = Methodology::create(['name' => 'Agile']);
        $resolver = new CatalogResolver();

        $this->assertSame($js->id, $resolver->resolve('languages', 'JS')->id);
        $this->assertSame($agile->id, $resolver->resolve('methodologies', 'Metodologías ágiles')->id);
        $this->assertSame('C#', $resolver->resolve('languages', 'CSharp')->name);
    }

    public function test_nombres_vacios_se_ignoran(): void
    {
        $resolver = new CatalogResolver();

        $this->assertNull($resolver->resolve('technologies', '  '));
        $this->assertNull($resolver->resolve('technologies', '...'));
        $this->assertNull($resolver->resolve('languages', null));
        $this->assertSame(0, Technology::count());
    }

    // ── Categorías ──────────────────────────────────────────────

    public function test_una_categoria_desconocida_va_a_other_sin_crearse(): void
    {
        $resolver = new CatalogResolver();

        $category = $resolver->resolveCategory('gadget');

        $this->assertSame('other', $category->name);
        $this->assertSame(5, TechnologyCategory::count());
        $this->assertSame('other', $resolver->report()['categories'][0]['via']);
        $this->assertStringContainsString('gadget', file_get_contents($this->catalogLog));
    }

    public function test_el_alias_de_una_categoria_duplicada_lleva_a_la_canonica(): void
    {
        $resolver = new CatalogResolver();

        $this->assertSame('methodology', $resolver->resolveCategory('metodology')->name);
        $this->assertSame('framework', $resolver->resolveCategory('Framework')->name);
        $this->assertSame('concept', $resolver->resolveCategory('concepto')->name);
    }

    public function test_sin_other_en_la_base_no_se_asigna_categoria(): void
    {
        TechnologyCategory::where('name', 'other')->delete();

        $this->assertNull((new CatalogResolver())->resolveCategory('gadget'));
        $this->assertSame(4, TechnologyCategory::count());
    }

    // ── Prompt ──────────────────────────────────────────────────

    public function test_el_prompt_lleva_el_catalogo_existente(): void
    {
        Technology::create(['name' => 'Laravel']);
        Language::create(['name' => 'PHP']);
        Methodology::create(['name' => 'Scrum']);
        $this->fakeOpenAi([], [], []);

        app(SyllabusExtractionService::class)->extract('texto');

        Http::assertSent(function (Request $request) {
            $prompt = $request['messages'][1]['content'];

            return str_contains($prompt, 'Lenguajes: PHP')
                && str_contains($prompt, 'Tecnologías: Laravel')
                && str_contains($prompt, 'Metodologías: Scrum')
                && str_contains($prompt, "Categorías de tecnología (campo 'tipo'): concept; framework; methodology; metodology; other")
                && str_contains($prompt, 'usa EXACTAMENTE el nombre del catálogo');
        });
    }

    public function test_con_limite_el_prompt_lleva_las_tecnologias_mas_usadas(): void
    {
        config(['catalogo.prompt.max_tecnologias' => 1]);
        $course = Course::create(['name' => 'Curso']);
        Technology::create(['name' => 'Poco usada']);
        $course->technologies()->attach(Technology::create(['name' => 'Muy usada']));

        $text = app(CatalogPrompt::class)->text();

        $this->assertStringContainsString('Tecnologías: Muy usada', $text);
        $this->assertStringNotContainsString('Poco usada', $text);
    }

    // ── Extracción completa (PDF y silabos_v2 comparten el camino) ──

    public function test_un_pdf_usa_el_catalogo_y_registra_lo_que_no_fue_exacto(): void
    {
        $cloud = Technology::create(['name' => 'Cloud']);
        $concept = TechnologyCategory::where('name', 'concept')->first();
        DB::table('technologies')->where('id', $cloud->id)->update(['category_id' => $concept->id]);
        $this->fakeOpenAi(['JS'], [
            ['nombre' => 'Cloud Computing', 'tipo' => 'cloud'],
            ['nombre' => 'Bigdata', 'tipo' => 'gadget'],
            ['nombre' => 'Big Data', 'tipo' => 'concept'],
        ], ['Waterfall'], curso: 'Transformación Digital');

        $pdf = Syllabus::create(['filename' => 'td.pdf', 'path' => 'syllabus/td.pdf', 'raw_text' => 'texto del pdf']);
        app(SyllabusExtractionService::class)->process($pdf);

        $pdf->refresh();
        $course = Course::sole();
        $this->assertSame('processed', $pdf->status);
        $this->assertEqualsCanonicalizing(['Cloud', 'Big Data'], $course->technologies->pluck('name')->all());
        $this->assertSame(['JavaScript'], $course->languages->pluck('name')->all());
        $this->assertSame(['Cascada'], $course->methodologies->pluck('name')->all());
        // La categoría existente de Cloud no cambia; Big Data recibe 'other' por el primer tipo desconocido.
        $this->assertSame($concept->id, $cloud->fresh()->category_id);
        $this->assertSame('other', Technology::where('name', 'Big Data')->first()->category->name);

        $catalogo = $pdf->structured_data['catalogo'];
        $this->assertSame(['alias', 'creado'], array_column($catalogo['technologies'], 'via'));
        $this->assertSame('creado', $catalogo['languages'][0]['via']);
        $this->assertSame('Cascada', $catalogo['methodologies'][0]['nombre']);
        $this->assertSame('gadget', $catalogo['categories'][0]['recibido']);
        // Lo devuelto por la IA sigue guardado tal cual.
        $this->assertSame('Bigdata', $pdf->structured_data['tecnologias'][1]['nombre']);
    }

    public function test_un_silabo_de_silabos_v2_usa_el_mismo_catalogo(): void
    {
        $iot = Technology::create(['name' => 'IoT']);
        $course = Course::create(['name' => 'Internet de las Cosas', 'code' => 'IS-200']);
        $this->fakeOpenAi([], [['nombre' => 'Internet of Things', 'tipo' => 'concept']], []);

        $syllabus = Syllabus::create([
            'source' => Syllabus::SOURCE_SILABOS_V2, 'external_model' => 'isil', 'external_id' => 99,
            'codigo_familia' => 'IS-200', 'external_version' => 1, 'course_code' => 'IS-200',
            'course_name' => 'INTERNET DE LAS COSAS', 'raw_text' => 'texto', 'status' => 'processing',
        ]);
        app(SyllabusExtractionService::class)->process($syllabus);

        $this->assertSame([$iot->id], $course->technologies()->pluck('technologies.id')->all());
        $this->assertSame('alias', $syllabus->fresh()->structured_data['catalogo']['technologies'][0]['via']);
    }

    // ── catalogo:nuevas ─────────────────────────────────────────

    public function test_catalogo_nuevas_lista_lo_creado_con_sugerencias(): void
    {
        Technology::create(['name' => 'Azure Machine Learning']);
        DB::table('market_entities')->insert([
            ['name' => 'Data Mesh', 'entity_type' => 'technology'],
            ['name' => 'Azure Machine Learning', 'entity_type' => 'technology'],
        ]);
        $this->fakeOpenAi([], [
            ['nombre' => 'Machine Learning', 'tipo' => 'concept'],
            ['nombre' => 'Data Mesh', 'tipo' => 'gadget'],
            ['nombre' => 'Quantum Foo', 'tipo' => 'concept'],
        ], []);
        $pdf = Syllabus::create(['filename' => 'x.pdf', 'path' => 'syllabus/x.pdf', 'raw_text' => 'texto']);
        app(SyllabusExtractionService::class)->process($pdf);
        Technology::where('name', 'Quantum Foo')->delete();

        $this->artisan('catalogo:nuevas')
            ->expectsOutputToContain('Tecnologías creadas por sílabos: 3')
            ->expectsOutputToContain('parecida a «Azure Machine Learning»')
            ->expectsOutputToContain('market:fix-null-entities la vincula con «Data Mesh»')
            ->expectsOutputToContain('(ya no existe)')
            ->expectsOutputToContain('Categorías desconocidas asignadas a other')
            ->expectsOutputToContain('ESCRIBE en la base')
            ->assertSuccessful();
    }

    public function test_catalogo_nuevas_filtra_por_origen_y_no_escribe(): void
    {
        $this->fakeOpenAi([], [['nombre' => 'Quantum Foo', 'tipo' => 'concept']], []);
        $pdf = Syllabus::create(['filename' => 'x.pdf', 'path' => 'syllabus/x.pdf', 'raw_text' => 'texto']);
        app(SyllabusExtractionService::class)->process($pdf);
        $before = Technology::orderBy('id')->get()->toArray();

        $this->artisan('catalogo:nuevas', ['--origen' => 'silabos_v2'])
            ->expectsOutputToContain('Ningún sílabo con registro de catálogo')
            ->assertSuccessful();
        $this->artisan('catalogo:nuevas', ['--origen' => 'upload'])
            ->expectsOutputToContain('Quantum Foo')
            ->assertSuccessful();

        $this->assertSame($before, Technology::orderBy('id')->get()->toArray());
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function fakeOpenAi(array $lenguajes, array $tecnologias, array $metodologias, string $curso = 'Curso detectado'): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'curso' => $curso,
                    'lenguajes' => $lenguajes,
                    'tecnologias' => $tecnologias,
                    'metodologias' => $metodologias,
                ])]]],
            ]),
        ]);
    }
}
