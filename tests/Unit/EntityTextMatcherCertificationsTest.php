<?php

namespace Tests\Unit;

use App\Services\Matching\AliasSpec;
use App\Services\Matching\EntityTextMatcher;
use Tests\TestCase;

/** Casos delicados con las reglas reales de certificaciones (database/data/entity_aliases/certifications.php). */
class EntityTextMatcherCertificationsTest extends TestCase
{
    private EntityTextMatcher $m;

    private array $id = [];

    protected function setUp(): void
    {
        parent::setUp();
        $slugs = ['aws-solutions-architect-–-associate', 'aws-solutions-architect-–-professional', 'microsoft-azure-administrator-(az-104)',
            'certified-scrum-master-(csm)', 'professional-scrum-master-(psm-i)', 'certified-information-systems-auditor-cisa',
            'cissp', 'comptia-security+', 'comptia-a-plus', 'cisco-ccna', 'cisco-certified-cyberops-associate', 'itil-4-foundation',
            'google-professional-data-engineer', 'aws-developer-–-associate', 'project-management-professional-pmp'];
        $spec = AliasSpec::load('certifications');
        $entities = [];
        foreach ($slugs as $i => $slug) {
            $this->id[$slug] = $i + 1;
            $entities[$i + 1] = ['name' => $slug, 'rules' => AliasSpec::toRows($spec[$slug] ?? [])];
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

    public function test_aws_y_azure(): void
    {
        $this->yes('aws-solutions-architect-–-associate', 'Cloud Engineer', 'AWS Certified Solutions Architect – Associate preferred');
        $this->yes('aws-solutions-architect-–-associate', 'Cloud Engineer', 'Certificación SAA-C03 deseable');
        $this->no('aws-solutions-architect-–-professional', 'Cloud Engineer', 'AWS Certified Solutions Architect – Associate preferred');
        $this->no('aws-solutions-architect-–-associate', 'Cloud Engineer', 'AWS experience; certifications a plus');
        $this->yes('microsoft-azure-administrator-(az-104)', 'Cloud Admin', 'AZ-104 or AZ-900 certification');
        $this->yes('aws-developer-–-associate', 'Dev', 'AWS Developer - Associate');
    }

    public function test_siglas_ambiguas(): void
    {
        $this->yes('certified-scrum-master-(csm)', 'Scrum Master', 'CSM or PSM certification, Agile');
        $this->no('certified-scrum-master-(csm)', 'CSM - Customer Success Manager', 'Own the renewal pipeline');
        $this->yes('professional-scrum-master-(psm-i)', 'Agile Coach', 'PSM I');
        $this->no('professional-scrum-master-(psm-i)', 'Chemical Engineer', 'PSM (Process Safety Management) programs');
        $this->yes('certified-information-systems-auditor-cisa', 'IT Auditor', 'CISA, CISSP or CISM');
        $this->no('certified-information-systems-auditor-cisa', 'Security Analyst', 'Track CISA KEV and advisories');
        $this->yes('itil-4-foundation', 'Service Desk', 'ITIL v4 Foundation');
        $this->yes('itil-4-foundation', 'Soporte', 'Certificación ITIL deseable');
        $this->no('itil-4-foundation', 'Service Manager', 'Processes aligned with ITIL practices');
        $this->yes('google-professional-data-engineer', 'Data Engineer', 'Google Professional Data Engineer certification');
        $this->no('google-professional-data-engineer', 'Professional Data Engineer', 'Build pipelines with Spark and Airflow');
        $this->yes('project-management-professional-pmp', 'Project Manager', 'PMP deseable');
    }

    public function test_comptia_y_cisco(): void
    {
        $this->yes('comptia-security+', 'SOC Analyst', 'Security+ or CySA+ required');
        $this->no('comptia-security+', 'SOC Analyst', 'security and compliance');
        $this->yes('comptia-a-plus', 'Help Desk', 'CompTIA A+ certification');
        $this->no('comptia-a-plus', 'Teacher', 'Students with A+ grades');
        $this->yes('cisco-ccna', 'Network Engineer', 'CCNA required');
        $this->no('cisco-ccna', 'SOC Analyst', 'CCNA CyberOps preferred');
        $this->yes('cisco-certified-cyberops-associate', 'SOC Analyst', 'CCNA CyberOps preferred');
        $this->yes('cissp', 'CISO', 'CISSP');
    }
}
