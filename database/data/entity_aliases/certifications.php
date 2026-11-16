<?php

/*
|--------------------------------------------------------------------------
| Reglas del matcher para las entidades de tipo 'certification' (por slug)
|--------------------------------------------------------------------------
| Mismo formato que languages.php / technologies.php. Criterio:
|   - nombre oficial y código de examen (AZ-104, SAA-C03…) = alias fuerte;
|   - siglas inequívocas (CISSP, CCNA, CKA…) = fuerte y sensible a mayúsculas;
|   - siglas o nombres que también son otra cosa (CSM = Customer Success Manager, CISA = la agencia
|     de EE. UU., "Professional Data Engineer" = un puesto) = débiles: necesitan contexto de
|     certificación o de su proveedor cerca.
|   - No se infiere el nivel: "AWS Solutions Architect" sin Associate/Professional no cuenta.
| Las alias antiguas de certification_aliases ("AWS", "AZURE", "SCRUM" para todas las de su
| proveedor) quedan sin uso: asociaban cada oferta a todas las certificaciones del proveedor.
| Incluye como alias de su principal los 12 duplicados desactivados (config entity_rankings.certifications.duplicates).
*/

$a = fn (string $p, array $o = []) => ['alias', $p, $o];
$n = fn (string $p, array $o = []) => ['negative', $p, $o];
$ctx = fn (array $words, ?int $window = 80) => array_map(fn ($w) => ['context', $w, $window ? ['window' => $window] : []], $words);
$cs = ['cs' => true];
$weak = ['weak' => true];
$csWeak = ['cs' => true, 'weak' => true];

$cert = ['certification', 'certifications', 'certificación', 'certificaciones', 'certificate', 'certificado',
    'certified', 'certificada', 'certificado/a', 'credential', 'exam', 'examen', 'accreditation'];

return [

    // ----- AWS --------------------------------------------------------------------------------
    'aws-certified-cloud-practitioner' => [$a('AWS Certified Cloud Practitioner'), $a('AWS Cloud Practitioner'),
        $a('CLF-C01', $cs), $a('CLF-C02', $cs)],
    'aws-solutions-architect-–-associate' => [$a('AWS Certified Solutions Architect Associate'), $a('AWS Solutions Architect Associate'),
        $a('AWS SAA', $cs), $a('SAA-C02', $cs), $a('SAA-C03', $cs)],
    'aws-solutions-architect-–-professional' => [$a('AWS Certified Solutions Architect Professional'), $a('AWS Solutions Architect Professional'),
        $a('SAP-C01', $cs), $a('SAP-C02', $cs)],
    'aws-developer-–-associate' => [$a('AWS Certified Developer Associate'), $a('AWS Developer Associate'), $a('DVA-C01', $cs), $a('DVA-C02', $cs)],
    'aws-sysops-administrator-–-associate' => [$a('AWS Certified SysOps Administrator'), $a('AWS SysOps Administrator'),
        $a('AWS Certified SysOps'), $a('SOA-C01', $cs), $a('SOA-C02', $cs)],
    'aws-devops-engineer-–-professional' => [$a('AWS Certified DevOps Engineer Professional'), $a('AWS DevOps Engineer Professional'),
        $a('DOP-C01', $cs), $a('DOP-C02', $cs)],
    'aws-security-–-specialty' => [$a('AWS Certified Security Specialty'), $a('AWS Security Specialty'), $a('SCS-C01', $cs), $a('SCS-C02', $cs)],
    'aws-advanced-networking-–-specialty' => [$a('AWS Certified Advanced Networking'), $a('AWS Advanced Networking'), $a('ANS-C01', $cs)],
    'aws-machine-learning-–-specialty' => [$a('AWS Certified Machine Learning Specialty'), $a('AWS Machine Learning Specialty'),
        $a('AWS Certified Machine Learning'), $a('MLS-C01', $cs)],
    'aws-certified-database-specialty' => [$a('AWS Certified Database Specialty'), $a('AWS Database Specialty'), $a('DBS-C01', $cs)],
    'aws-certified-data-analytics-specialty' => [$a('AWS Certified Data Analytics'), $a('AWS Data Analytics Specialty'), $a('DAS-C01', $cs)],

    // ----- Microsoft Azure (código del examen o nombre oficial del rol) -------------------------
    'microsoft-azure-fundamentals-(az-900)' => [$a('AZ 900', $cs), $a('AZ900', $cs), $a('Azure Fundamentals')],
    'microsoft-azure-administrator-(az-104)' => [$a('AZ 104', $cs), $a('AZ104', $cs), $a('Azure Administrator Associate'),
        $a('Microsoft Certified Azure Administrator')],
    'microsoft-azure-solutions-architect-(az-305)' => [$a('AZ 305', $cs), $a('AZ305', $cs), $a('AZ 303', $cs), $a('AZ 304', $cs),
        $a('Azure Solutions Architect Expert'), $a('Microsoft Certified Azure Solutions Architect')],
    'microsoft-azure-developer-(az-204)' => [$a('AZ 204', $cs), $a('AZ204', $cs), $a('Azure Developer Associate')],
    'microsoft-azure-devops-engineer-(az-400)' => [$a('AZ 400', $cs), $a('AZ400', $cs), $a('Azure DevOps Engineer Expert')],
    'microsoft-azure-security-engineer-(az-500)' => [$a('AZ 500', $cs), $a('AZ500', $cs), $a('Azure Security Engineer Associate')],
    'microsoft-azure-data-engineer-(dp-203)' => [$a('DP 203', $cs), $a('DP203', $cs), $a('Azure Data Engineer Associate')],
    'microsoft-azure-ai-engineer-(ai-102)' => [$a('AI 102', $cs), $a('AI102', $cs), $a('Azure AI Engineer Associate')],
    'microsoft-certified-azure-ai-fundamentals-ai-900' => [$a('AI 900', $cs), $a('AI900', $cs), $a('Azure AI Fundamentals')],
    'microsoft-certified-azure-data-fundamentals-dp-900' => [$a('DP 900', $cs), $a('DP900', $cs), $a('Azure Data Fundamentals')],
    'microsoft-certified-azure-data-scientist-associate' => [$a('DP 100', $cs), $a('DP100', $cs), $a('Azure Data Scientist Associate')],

    // ----- Google Cloud (requieren Google/GCP o contexto de certificación) -----------------------
    'google-associate-cloud-engineer' => [$a('Associate Cloud Engineer'), $a('Google ACE', $cs)],
    'google-cloud-digital-leader' => [$a('Cloud Digital Leader')],
    'google-professional-cloud-architect' => [$a('Google Professional Cloud Architect'), $a('Google Cloud Professional Cloud Architect'),
        $a('GCP Professional Cloud Architect'), $a('Professional Cloud Architect', $weak), ...$ctx(['Google', 'GCP', ...$cert])],
    'google-professional-cloud-devops-engineer' => [$a('Professional Cloud DevOps Engineer', $weak), ...$ctx(['Google', 'GCP', ...$cert])],
    'google-professional-cloud-network-engineer' => [$a('Professional Cloud Network Engineer', $weak), ...$ctx(['Google', 'GCP', ...$cert])],
    'google-professional-cloud-security-engineer' => [$a('Professional Cloud Security Engineer', $weak), ...$ctx(['Google', 'GCP', ...$cert])],
    'google-professional-data-engineer' => [$a('Google Professional Data Engineer'), $a('Google Cloud Professional Data Engineer'),
        $a('GCP Professional Data Engineer'), $a('Professional Data Engineer', $weak), ...$ctx(['Google', 'GCP', ...$cert], 40)],
    'google-professional-machine-learning-engineer' => [$a('Google Professional Machine Learning Engineer'),
        $a('Google Cloud Professional Machine Learning Engineer'), $a('Professional Machine Learning Engineer', $weak),
        ...$ctx(['Google', 'GCP', ...$cert], 40)],

    // ----- Kubernetes, Docker, HashiCorp, Databricks, Linux Foundation ---------------------------
    'certified-kubernetes-administrator-(cka)' => [$a('Certified Kubernetes Administrator'), $a('CKA', $cs)],
    'certified-kubernetes-application-developer-(ckad)' => [$a('Certified Kubernetes Application Developer'), $a('CKAD', $cs)],
    'certified-kubernetes-security-specialist-(cks)' => [$a('Certified Kubernetes Security Specialist'), $a('CKS', $cs)],
    'docker-certified-associate-(dca)' => [$a('Docker Certified Associate'), $a('DCA', $csWeak), ...$ctx(['Docker'], 40)],
    'hashicorp-terraform-associate' => [$a('Terraform Associate'), $a('HashiCorp Certified Terraform')],
    'hashicorp-vault-associate' => [$a('Vault Associate'), $a('HashiCorp Certified Vault')],
    'databricks-data-engineer-associate' => [$a('Databricks Certified Data Engineer Associate'), $a('Databricks Data Engineer Associate'),
        $a('Databricks Certified Data Engineer')],
    'linux-foundation-certified-sysadmin-(lfcs)' => [$a('Linux Foundation Certified System Administrator'), $a('Linux Foundation Certified SysAdmin'), $a('LFCS', $cs)],
    'linux-foundation-certified-engineer-(lfce)' => [$a('Linux Foundation Certified Engineer'), $a('LFCE', $cs)],

    // ----- Seguridad ---------------------------------------------------------------------------
    'cissp' => [$a('CISSP', $cs), $a('Certified Information Systems Security Professional')],
    'cism' => [$a('CISM', $cs), $a('Certified Information Security Manager')],
    // CISA también es la agencia de ciberseguridad de EE. UU.
    'certified-information-systems-auditor-cisa' => [$a('Certified Information Systems Auditor'), $a('CISA', $csWeak),
        $n('CISA KEV'), $n('CISA advisory'), $n('CISA advisories'), ...$ctx(['ISACA', 'CISSP', 'CISM', 'audit', 'auditor', 'auditoría', ...$cert], 60)],
    'certified-cloud-security-professional-ccsp' => [$a('Certified Cloud Security Professional'), $a('CCSP', $cs)],
    'certified-cloud-security-specialist-ccss' => [$a('Certified Cloud Security Specialist')],
    'certified-ethical-hacker-(ceh)' => [$a('Certified Ethical Hacker'), $a('CEH', $cs)],
    'certified-information-privacy-professional-cipp' => [$a('Certified Information Privacy Professional'), $a('CIPP', $cs)],
    'certified-information-privacy-technologist-cipt' => [$a('Certified Information Privacy Technologist'), $a('CIPT', $cs)],
    'comptia-security+' => [$a('CompTIA Security+'), $a('Security+', $cs), $a('Sec+', $cs)],
    'comptia-network+' => [$a('CompTIA Network+'), $a('Network+', $cs)],
    'comptia-cysa+' => [$a('CompTIA CySA+'), $a('CySA+'), $a('CySA', $cs)],
    'comptia-pentest+' => [$a('CompTIA PenTest+'), $a('PenTest+')],
    'comptia-a-plus' => [$a('CompTIA A+'), $a('A+ certification'), $a('A+ certified'), $a('A+ certificate')],

    // ----- Redes (Cisco) -----------------------------------------------------------------------
    'cisco-ccna' => [$a('CCNA', $cs), $a('Cisco Certified Network Associate'), $n('CCNA CyberOps'), $n('CCNA Cyber Ops')],
    'cisco-ccnp' => [$a('CCNP', $cs), $a('Cisco Certified Network Professional')],
    'cisco-certified-internetwork-expert-ccie' => [$a('CCIE', $cs), $a('Cisco Certified Internetwork Expert')],
    'cisco-certified-cyberops-associate' => [$a('CyberOps Associate'), $a('CCNA CyberOps'), $a('CCNA Cyber Ops'), $a('Cisco CyberOps')],
    'cisco-certified-devnet-professional' => [$a('DevNet Professional'), $a('Cisco Certified DevNet Professional')],

    // ----- Gestión y agilidad ------------------------------------------------------------------
    'project-management-professional-pmp' => [$a('PMP', $cs), $a('Project Management Professional')],
    'pmi-acp-(agile-certified-practitioner)' => [$a('PMI-ACP', $cs), $a('PMI ACP', $cs), $a('Agile Certified Practitioner')],
    // CSM también es Customer Success Manager
    'certified-scrum-master-(csm)' => [$a('Certified Scrum Master'), $a('Certified ScrumMaster'), $a('CSM', $csWeak),
        $n('Customer Success Manager'), ...$ctx(['Scrum', 'Agile', 'ágil', 'Scrum Alliance', 'PSM', 'SAFe', 'Kanban'], 60)],
    // PSM también es Process Safety Management
    'professional-scrum-master-(psm-i)' => [$a('Professional Scrum Master'), $a('PSM I', $cs), $a('PSM 1', $cs), $a('PSM-I', $cs),
        $a('PSM', $csWeak), $n('Process Safety Management'), ...$ctx(['Scrum', 'Scrum.org', 'Agile', 'ágil', 'CSM', 'SAFe'], 60)],
    'safe-agilist-(sa)' => [$a('SAFe Agilist'), $a('Leading SAFe'), $a('SAFe SA', $cs), $a('Certified SAFe Agilist')],
    'safe-devops-practitioner' => [$a('SAFe DevOps Practitioner'), $a('SAFe DevOps', $cs)],
    // ITIL suelto es el marco; cuenta como certificación solo con contexto de certificación o "Foundation"/"v4"
    'itil-4-foundation' => [$a('ITIL 4 Foundation'), $a('ITIL Foundation'), $a('ITIL v4', $cs), $a('ITIL 4', $cs), $a('ITIL v3', $cs),
        $a('ITIL certified'), $a('ITIL certification'), $a('certificación ITIL'), $a('ITIL', $csWeak), ...$ctx($cert, 40)],
    'certified-data-management-professional-cdmp' => [$a('Certified Data Management Professional'), $a('CDMP', $cs)],
    'certified-data-scientist-dasca' => [$a('Certified Data Scientist DASCA'), $a('DASCA', $cs)],

];
