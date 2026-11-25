<?php
// Solo lectura: arma la muestra del piloto
require 'D:/github3/citas_websocket/vendor/autoload.php';
$app = require 'D:/github3/citas_websocket/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
mt_srand(20261125);
$plain = fn($t) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<[^>]+>/', ' ', (string) $t)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
$quota = ['valid' => ['Adzuna'=>30,'LinkedIn'=>25,'reed'=>20,'Jooble'=>15,'Arbeitnow'=>10,'Computrabajo'=>8,'GetOnBoard'=>5,'Greenhouse'=>3,'Remotive'=>2,'usajobs'=>2],
          'excl'  => ['LinkedIn'=>10,'Adzuna'=>8,'reed'=>6,'Jooble'=>3,'Arbeitnow'=>3]];
$offers = [];
foreach ($quota as $kind => $q) foreach ($q as $src => $n) {
  $cond = $kind === 'valid' ? 'jo.title_exclusion_id IS NULL' : 'jo.title_exclusion_id IS NOT NULL';
  $ids = DB::select("SELECT id FROM job_offers jo WHERE source=? AND $cond AND (published_at>='2025-07-01' OR source='LinkedIn')", [$src]);
  $ids = array_column($ids, 'id'); shuffle($ids); $ids = array_slice($ids, 0, $n);
  foreach (DB::table('job_offers as jo')->leftJoin('job_title_exclusion_keywords as k','k.id','=','jo.title_exclusion_id')
     ->whereIn('jo.id',$ids)->get(['jo.id','jo.title','jo.company','jo.source','jo.description','jo.requirements','jo.functions_headline','k.pattern']) as $r) {
    $txt = $plain($r->functions_headline ?: ($r->description ?: $r->requirements));
    $offers[] = ['id'=>$r->id,'title'=>$r->title,'company'=>$r->company,'source'=>$r->source,'excerpt'=>mb_substr($txt,0,250),'excluded_by'=>$r->pattern];
  }
}
$tq = [['tavily-search','technology',50],['gpt-search','technology',30],['search-gpt','technology',10],['tavily-search','certification',25],['gpt-search','certification',5],['tavily-search','language',10],['gpt-search','language',8],['gpt-search','methodology',12]];
$trends = [];
foreach ($tq as [$by,$type,$n]) {
  $ids = array_column(DB::select("SELECT et.id FROM entity_trends et JOIN market_entities me ON me.id=et.market_entity_id WHERE et.discovered_by=? AND me.entity_type=?", [$by,$type]), 'id');
  shuffle($ids);
  foreach (DB::table('entity_trends as et')->join('market_entities as me','me.id','=','et.market_entity_id')->whereIn('et.id', array_slice($ids,0,$n))
    ->get(['et.id','me.name','me.entity_type','me.is_active','et.trend_name','et.source_title','et.source_url','et.source_type','et.discovered_by']) as $r) $trends[] = (array) $r;
}
file_put_contents(__DIR__.'/pilot_sample.json', json_encode(['offers'=>$offers,'trends'=>$trends], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo count($offers)." ofertas, ".count($trends)." tendencias\n";
