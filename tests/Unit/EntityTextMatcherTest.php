<?php

namespace Tests\Unit;

use App\Services\Matching\AliasSpec;
use App\Services\Matching\EntityTextMatcher;
use Tests\TestCase;

/**
 * Casos del matcher con las reglas reales de lenguajes (database/data/entity_aliases/languages.php).
 * No usa la base de datos: los ids son los índices del arreglo de slugs.
 */
class EntityTextMatcherTest extends TestCase
{
    private EntityTextMatcher $m;

    /** @var array<string, int> */
    private array $id = [];

    protected function setUp(): void
    {
        parent::setUp();

        $names = ['sql' => 'SQL', 'java' => 'Java', 'javascript' => 'JavaScript', 'typescript' => 'TypeScript',
            'python' => 'Python', 'c' => 'C#', 'c-plus-plus' => 'C++', 'php' => 'PHP', 'css' => 'CSS', 'html' => 'HTML',
            'r' => 'R', 'go' => 'Go', 'swift' => 'Swift', 'dax' => 'DAX', 'ruby' => 'Ruby', 'rust' => 'Rust',
            'fsharp' => 'F#', 'scala' => 'Scala', 'shell' => 'Shell', 'ada' => 'Ada', 'kotlin' => 'Kotlin'];
        $spec = AliasSpec::load('languages');

        $entities = [];
        $i = 1;
        foreach ($names as $slug => $name) {
            $this->id[$slug] = $i;
            $entities[$i++] = ['name' => $name, 'rules' => AliasSpec::toRows($spec[$slug] ?? [])];
        }
        $this->m = new EntityTextMatcher($entities);
    }

    private function yes(string $slug, string $title, ?string $body = null): void
    {
        $r = $this->m->explain($this->id[$slug], $title, $body);
        $this->assertSame(EntityTextMatcher::CONFIRMED, $r['verdict'], "{$slug} debería confirmarse en: {$title} | {$body}");
    }

    private function no(string $slug, string $title, ?string $body = null): void
    {
        $r = $this->m->explain($this->id[$slug], $title, $body);
        $this->assertNotSame(EntityTextMatcher::CONFIRMED, $r['verdict'], "{$slug} NO debería confirmarse ({$r['alias']}: {$r['snippet']})");
    }

    public function test_simbolos_csharp_cpp_fsharp(): void
    {
        $this->yes('c', 'Desarrollador C# .NET');
        $this->yes('c', 'Backend dev', 'Experience with C sharp and Azure');
        $this->yes('c-plus-plus', 'Embedded C++ Engineer');
        $this->yes('fsharp', 'Functional developer', 'We use F# and OCaml');
        $this->no('c', 'C++ developer');
        $this->no('c-plus-plus', 'C# developer');
        $this->yes('c', 'Dev', 'Lenguajes: C&#35;, Java'); // entidad HTML
    }

    public function test_limites_de_palabra(): void
    {
        $this->no('java', 'JavaScript Developer');
        $this->yes('javascript', 'JavaScript Developer');
        $this->no('sql', 'MySQL DBA', 'PostgreSQL y NoSQL');
        $this->yes('sql', 'Analista', 'Conocimientos de SQL Server y T-SQL');
        $this->yes('css', 'Frontend', 'HTML5, CSS3 y JavaScript');
        $this->yes('html', 'Frontend', 'HTML5, CSS3 y JavaScript');
        $this->no('java', 'Developer', 'Contact: java@empresa.com');
        $this->yes('python', 'Data Engineer', '<p>Python-based pipelines</p>');
        $this->no('java', 'Frontend', 'Java Script avanzado');
    }

    public function test_r(): void
    {
        $this->yes('r', 'Data Scientist', 'Python, R and SQL required');
        $this->yes('r', 'Analista estadístico', 'Manejo de R');
        $this->yes('r', 'Researcher', 'Experience with RStudio');
        $this->no('r', 'R&D Engineer', 'Hardware data acquisition');
        $this->no('r', 'Manager, TAM (Greater China)');
        $this->no('r', 'Sales Manager', 'Salary R$ 5.000 por mês');
        $this->no('r', 'Marketing lead', 'Grade R position for our sales team');
        $this->no('r', 'Python Developer', 'Salary ranging from R 30,000.00 per month');
        $this->no('r', 'Data Engineering Software Developer', 'R-00193023 Description: data pipelines');
        $this->no('r', 'SAP Berater', 'Erfahrung mit SAP R/3 und Datenmigration');
        $this->no('r', 'Technician', 'experience in R&DA degree in physics, data acquisition');
        $this->no('r', 'Steuerfachangestellte', 'Antwort i.d.R. schneller, Daten');
        $r = $this->m->explain($this->id['r'], 'r&d', null);
        $this->assertSame(EntityTextMatcher::REJECTED, $r['verdict']);
    }

    public function test_go(): void
    {
        $this->yes('go', 'Go Developer');
        $this->yes('go', 'Backend Engineer', 'We build microservices in Go and Kubernetes');
        $this->yes('go', 'Platform team', 'Golang experience');
        $this->no('go', 'Account Executive', 'Go-to-market strategy for enterprise customers');
        $this->no('go', 'Sales', 'Be ready to go the extra mile');
        $this->no('go', 'Store manager', 'Go beyond customer expectations every day');
        $this->no('go', 'Software Engineer', 'Go to the office twice a week');
        $this->yes('go', 'Technical Leader, SRE/Go development');
        $this->yes('go', 'Développeur Fullstack Senior', 'Marketplace interne (Go / React)');
        $this->no('go', 'Plombier (H/F)', "L'agence de Go Intérim recherche un développeur de carrière");
        $this->no('go', 'Steuerberater', 'solange Sie uns nicht das „Go“ geben, Software');
        $r = $this->m->explain($this->id['go'], 'Sales Rep', 'Go and win new accounts');
        $this->assertSame(EntityTextMatcher::REASON_NO_CONTEXT, $r['reason']);
    }

    public function test_swift_y_dax(): void
    {
        $this->yes('swift', 'iOS Developer', 'Swift, SwiftUI');
        $this->yes('swift', 'Mobile Engineer (Swift)');
        $this->no('swift', 'Treasury analyst', 'SWIFT payments and MT103 messages');
        $this->no('swift', 'Operations', 'Swift code required for international payments');
        $this->no('swift', 'Customer support', 'We value swift responses');
        $this->yes('swift', 'Senior iOS Engineer', 'write and maintain high-performance Swift code');
        $this->yes('scala', 'Big Data Engineer', 'Spark/Scala and HDFS');
        $this->yes('scala', 'Développeur Front React SCALA (IT)');
        $this->yes('scala', 'Développeur big data', 'environnement big data – spark / scala et/ou java');
        $this->no('scala', 'Consultant', 'integreren met een breed scala aan ERP-systemen');
        $this->yes('rust', 'Ingénieur RUST embarqué - H/F');
        $this->yes('go', 'Développeur(se) Go & IA');
        $this->yes('swift', 'Stage : DÉVELOPPEMENT MOBILE iOS NATIF – SWIFT (H/F)');
        $this->no('swift', 'Software Engineer - SWIFT messaging');
        $this->no('swift', 'Product Director, Core Banking', 'payment scheme integrations (SWIFT, SEPA, FPS, ACH) for engineers');
        $this->yes('dax', 'Data & BI Support Analyst', 'Business Intelligence Development: data modelling, DAX development');
        $this->no('scala', '.NET Developer', 'trasformazione digitale su larga scala');
        $this->no('scala', 'Head of GTM', 'build a pipeline and establish scala…');
        $this->yes('dax', 'BI Analyst', 'Power BI, DAX y Power Query');
        $this->no('dax', 'Account executive', 'Find the right people at leading DAX companies. Excel skills.');
        $this->no('dax', 'Equity trader', 'Follow the DAX index and Euro Stoxx');
    }

    public function test_ruby_rust_shell_ada(): void
    {
        $this->yes('ruby', 'Ruby on Rails Developer');
        $this->yes('ruby', 'Backend Engineer', 'Ruby and PostgreSQL');
        $this->no('ruby', 'Office manager', 'Report to Ruby Smith, head of HR');
        $this->yes('rust', 'Systems Engineer', 'Rust and C++');
        $this->no('rust', 'Plant manager', 'Rust Belt manufacturing site');
        $this->no('rust', 'Painter', 'Remove rust from metal surfaces');
        $this->yes('shell', 'DevOps', 'Bash scripting and Python');
        $this->no('shell', 'Sysadmin', 'PowerShell and Active Directory');
        $this->no('shell', 'Driver', 'Shell gas station');
        $this->yes('ada', 'Embedded Engineer', 'Ada and SPARK for avionics');
        $this->no('ada', 'HR Specialist', 'ADA compliance and accommodations');
    }

    public function test_match_devuelve_todas_las_entidades(): void
    {
        $found = $this->m->match('Full Stack Developer', 'Stack: TypeScript, Python, SQL. Go-to person for the team.');
        $ids = array_keys($found);
        sort($ids);
        $expected = [$this->id['typescript'], $this->id['python'], $this->id['sql']];
        sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertSame('body', $found[$this->id['python']]['field']);
    }

    public function test_entidad_sin_reglas_usa_su_nombre(): void
    {
        $m = new EntityTextMatcher([1 => ['name' => 'Kotlin Multiplatform']]);
        $this->assertTrue($m->confirms(1, 'Mobile', 'kotlin multiplatform y kotlin-multiplatform'));
        $this->assertFalse($m->confirms(1, 'Mobile', 'Kotlin'));
    }
}
