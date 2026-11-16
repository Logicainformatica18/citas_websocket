<?php

namespace Tests\Unit;

use App\Services\Matching\AliasSpec;
use App\Services\Matching\EntityTextMatcher;
use Tests\TestCase;

/** Casos delicados con las reglas reales de tecnologías (database/data/entity_aliases/technologies.php). */
class EntityTextMatcherTechnologiesTest extends TestCase
{
    private EntityTextMatcher $m;

    private array $id = [];

    protected function setUp(): void
    {
        parent::setUp();
        $names = ['rest' => 'REST', 'soap' => 'SOAP', 'ajax' => 'AJAX', 'express' => 'Express', 'excel' => 'Excel',
            'apache' => 'Apache', 'apache-spark' => 'Apache Spark', 'unity' => 'Unity', 'amazon-s3' => 'Amazon S3',
            'microsoft-azure' => 'Microsoft Azure', 'reactjs' => 'React.js', 'docker' => 'Docker', 'teams' => 'Teams',
            'tableau' => 'Tableau', 'san' => 'SAN', 'transformers' => 'Transformers', 'net' => '.NET', 'linux' => 'Linux',
            'postman' => 'Postman', 'bootstrap' => 'Bootstrap'];
        $spec = AliasSpec::load('technologies');
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
        $this->assertTrue($this->m->confirms($this->id[$slug], $title, $body), "{$slug} debería confirmarse: {$title} | {$body}");
    }

    private function no(string $slug, string $title, ?string $body = null): void
    {
        $r = $this->m->explain($this->id[$slug], $title, $body);
        $this->assertNotSame(EntityTextMatcher::CONFIRMED, $r['verdict'], "{$slug} NO debería confirmarse ({$r['alias']}: {$r['snippet']})");
    }

    public function test_rest_soap_ajax(): void
    {
        $this->yes('rest', 'Backend Developer', 'Design REST APIs and microservices');
        $this->yes('rest', 'Integrador', 'Consumo de servicios RESTful');
        $this->no('rest', 'Nurse', 'Get plenty of rest between shifts');
        $this->no('rest', 'Sales', 'The REST of the team works remotely in sales');
        $this->yes('soap', 'Integration Engineer', 'SOAP and REST web services, WSDL');
        $this->no('soap', 'Medical Scribe', 'Write SOAP notes for every patient visit');
        $this->yes('ajax', 'Web Developer', 'jQuery, AJAX, PHP');
        $this->no('ajax', 'Football analyst', 'Ajax Amsterdam scouting reports');
    }

    public function test_palabras_comunes(): void
    {
        $this->yes('express', 'Node.js Developer', 'Express and MongoDB');
        $this->no('express', 'Customer Service', 'American Express card benefits');
        $this->no('express', 'Driver', 'Express delivery routes in Lima');
        $this->yes('excel', 'Analista', 'Manejo de Excel avanzado');
        $this->no('excel', 'Sales Rep', 'You will excel in a fast-paced team');
        $this->yes('teams', 'Soporte', 'Microsoft Teams y Office 365');
        $this->no('teams', 'Manager', 'Lead cross-functional Teams');
        $this->no('tableau', 'Contrôleur de gestion', 'Tableau de bord mensuel');
        $this->yes('tableau', 'BI Analyst', 'Dashboards in Tableau and Power BI');
        $this->no('san', 'Developer', 'Office in San Isidro, SAN ISIDRO');
        $this->yes('san', 'Storage Admin', 'SAN and NAS storage, Fibre Channel');
        $this->no('postman', 'Postman', 'Deliver mail in the neighborhood');
        $this->yes('postman', 'QA Engineer', 'API testing with Postman');
        $this->no('bootstrap', 'Founder', 'Bootstrapped startup looking for growth');
    }

    public function test_apache_unity_aws(): void
    {
        $this->no('apache', 'Data Engineer', 'Apache Spark and Apache Kafka pipelines');
        $this->yes('apache', 'Sysadmin', 'Apache and Nginx web servers');
        $this->yes('apache-spark', 'Data Engineer', 'PySpark on Databricks');
        $this->yes('apache-spark', 'Big data', 'Spark jobs in Scala on Hadoop');
        $this->yes('unity', 'Game Developer', 'Unity and C# for mobile games');
        $this->no('unity', 'Data Engineer', 'Databricks Unity Catalog governance');
        $this->yes('amazon-s3', 'Cloud Engineer', 'AWS Lambda, S3 buckets and DynamoDB');
        $this->yes('amazon-s3', 'Engineer', 'Amazon Simple Storage Service');
        $this->yes('microsoft-azure', 'Cloud', 'Azure DevOps and AKS');
        $this->no('microsoft-azure', 'Designer', 'azure blue palette');
        $this->yes('reactjs', 'Frontend', 'React, Redux and TypeScript');
        $this->no('reactjs', 'Support', 'React to customer incidents quickly');
    }

    public function test_transformers_y_otros(): void
    {
        $this->yes('transformers', 'NLP Engineer', 'Hugging Face Transformers, PyTorch');
        $this->yes('transformers', 'ML Engineer', 'Fine-tuning Transformers models with PyTorch');
        $this->no('transformers', 'Electrical Engineer', 'Maintenance of power transformers in substations');
        $this->yes('net', 'Desarrollador .NET', 'C# y .NET Core');
        $this->no('net', 'Developer', 'ASP.NET MVC');
        $this->yes('docker', 'DevOps', 'Docker and Kubernetes');
        $this->yes('linux', 'Sysadmin', 'Administración de servidores Linux');
    }
}
