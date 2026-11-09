<?php
declare(strict_types=1);
require __DIR__.'/auth/bootstrap.php';
auth_boot();
$user=auth_user();
$logged=$user!==null;
$api=(string)($_GET['api']??'');
$dir=__DIR__.'/storage';
$dataFile=$logged?auth_dir().'/portfolio-user-'.((int)$user['id']).'.php':'';
function readStore(string $file,mixed $default):mixed{
 if(!is_file($file))return $default;
 $s=@file_get_contents($file);$prefix="<?php exit; ?>\n";
 if(!is_string($s)||!str_starts_with($s,$prefix))return $default;
 $out=json_decode(substr($s,strlen($prefix)),true);
 return json_last_error()===JSON_ERROR_NONE?$out:$default;
}
function writeStore(string $file,array $data):bool{
 $folder=dirname($file);
 if(!is_dir($folder) && !@mkdir($folder,0700,true) && !is_dir($folder))return false;
 $str="<?php exit; ?>\n".json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
 return @file_put_contents($file,$str,LOCK_EX)!==false;
}
function reply(mixed $data,int $status=200):never{
 http_response_code($status);header('Content-Type: application/json; charset=utf-8');
 echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
function csrf():void{
 $v=(string)($_POST['csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if(!hash_equals((string)($_SESSION['csrf']??''),$v)){
  if(isset($_GET['api']))reply(['error'=>'Relace vypršela. Obnov stránku.'],403);
  http_response_code(403);exit('Neplatný bezpečnostní token.');
 }
}
function fetchJson(string $url):?array{
 $ctx=stream_context_create(['http'=>[
  'timeout'=>12,
  'follow_location'=>0,
  'header'=>"Accept: application/json\r\nUser-Agent: MojeKrypto/1.0 (private CZK analysis)\r\n",
 ]]);
 $s=@file_get_contents($url,false,$ctx);
 if(!is_string($s)||!preg_match('~^HTTP/\S+ 200(?: |$)~',$http_response_header[0]??''))return null;
 $j=json_decode($s,true);
 return is_array($j)?$j:null;
}
function cached(string $key,int $ttl,callable $fetch):array{
 global $dir;
 $file=$dir.'/cache-'.preg_replace('/[^a-z0-9-]/','',$key).'.php';
 $old=readStore($file,[]);
 if(isset($old['saved'],$old['data'])&&time()-(int)$old['saved']<$ttl)return ['data'=>$old['data'],'updated_at'=>$old['saved'],'stale'=>false];
 $data=$fetch();
 if(is_array($data)){writeStore($file,['saved'=>time(),'data'=>$data]);return ['data'=>$data,'updated_at'=>time(),'stale'=>false];}
 if(isset($old['data']))return ['data'=>$old['data'],'updated_at'=>$old['saved']??null,'stale'=>true];
 return ['error'=>'Tržní data nyní nejsou dostupná; možný limit externí API.'];
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['form_action'])){
 csrf();
 if(($_POST['form_action']??'')==='logout' && $logged){
   auth_logout();header('Location: /account.php?view=login',true,303);exit;
 }
 http_response_code(400);exit('Nepovolená akce.');
}
if(!$logged && $api===''){header('Location: /account.php?view=login',true,303);exit;}
require_once __DIR__.'/market-data.php';
if($api!==''){
 if(!$logged)reply(['error'=>'Přihlas se prosím.'],401);
 if($api==='data'){
  if($_SERVER['REQUEST_METHOD']==='GET'){
   if(auth_is_admin($user)&&!is_file($dataFile)&&is_file($dir.'/data.php')){
     $legacy=readStore($dir.'/data.php',['assets'=>[],'transactions'=>[]]);
     if(is_array($legacy)&&isset($legacy['assets'],$legacy['transactions']))writeStore($dataFile,$legacy);
   }
   reply(['data'=>readStore($dataFile,['assets'=>[],'transactions'=>[]])]);
  }
  if($_SERVER['REQUEST_METHOD']==='POST'){
   csrf();$raw=file_get_contents('php://input',false,null,0,2500001);
   if(!is_string($raw)||strlen($raw)>2500000)reply(['error'=>'Data přesahují limit 2,5 MB.'],413);
   $j=json_decode($raw,true);
   if(!is_array($j)||!isset($j['assets'],$j['transactions'])||!is_array($j['assets'])||!is_array($j['transactions'])||count($j['assets'])>40||count($j['transactions'])>10000)reply(['error'=>'Neplatný formát dat.'],422);
   foreach($j['assets'] as $x)if(!is_array($x)||!isset($x['symbol'],$x['quantity'])||!array_key_exists('costBasis',$x)||!in_array($x['symbol'],['BTC','ETH'],true)||!is_numeric($x['quantity'])||(float)$x['quantity']<0||($x['costBasis']!==null&&(!is_numeric($x['costBasis'])||(float)$x['costBasis']<0)))reply(['error'=>'Neplatná položka portfolia.'],422);
   foreach($j['transactions'] as $x)if(!is_array($x)||!isset($x['date'],$x['symbol'],$x['type'],$x['quantity'],$x['czk']))reply(['error'=>'Neplatná transakce.'],422);
   if(!writeStore($dataFile,['assets'=>$j['assets'],'transactions'=>$j['transactions']]))reply(['error'=>'Úložiště není zapisovatelné.'],500);
   reply(['ok'=>true]);
  }
 }
 if($api==='prices'){
  $ret=cached('prices-v2',90,'marketPrices');reply($ret,isset($ret['error'])?503:200);
 }
 if($api==='history'){
  $coin=(string)($_GET['coin']??'BTC');$period=(string)($_GET['period']??'1Y');
  if(!in_array($coin,['BTC','ETH'],true)||!in_array($period,['1D','7D','1M','3M','6M','1Y','5Y','15Y'],true))reply(['error'=>'Nepodporovaný graf.'],400);
  $res=cached(strtolower('history-v2-'.$coin.'-'.$period),$period==='1D'?300:1800,static fn()=>marketHistory($coin,$period));reply($res,isset($res['error'])?503:200);
 }
 reply(['error'=>'Neznámá akce.'],404);
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#102c58"><meta name="csrf-token" content="<?=htmlspecialchars((string)($_SESSION['csrf']??''),ENT_QUOTES)?>">
<meta name="description" content="Moje krypto – soukromá evidence BTC a ETH, online kurzy v CZK, analýza, DCA a simulace.">
<title>Moje krypto | Jiří Janoušek</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="style.css?v=20261106-mobile-header-v2">
<?php if($logged):?><script src="charts.js?v=20261105d" defer></script><script src="models.js" defer></script><script src="data-tools.js?v=20261105e" defer></script><script src="cost-utils.js?v=20261105e" defer></script><script src="app.js?v=20261106-mobile-header-v2" defer></script><?php endif;?>
<link rel="stylesheet" href="/privacy.css?v=2"><script type="module" src="/privacy.js?v=2"></script></head><body>
<?php if($logged):?>
<div class="app-layout"><div id="mobile-shade"></div><aside id="sidebar" class="sidebar">
<div class="brand"><div class="brand-icon">₿</div><div><b>Moje krypto</b><small>investiční centrum</small></div><button type="button" class="sidebar-close" id="sidebar-close" aria-label="Zavřít nabídku">✕</button></div>
<div class="nav-label">MOJE ANALYTIKA</div><nav id="navigation" aria-label="Navigace">
<button class="nav-link active" data-page="dashboard"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z"/></svg> <span>Přehled</span></button>
<button class="nav-link" data-page="markets"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 19h18 M4 15l5-6 4 3 7-9"/></svg> <span>Trhy & grafy</span></button>
<button class="nav-link" data-page="transactions"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h15m-4-4 4 4-4 4 M20 17H5m4-4-4 4 4 4"/></svg> <span>Transakce</span></button>
<button class="nav-link" data-page="strategies"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h13l-3-3 M20 17H7l3 3 M20 7v5 M4 17v-5"/></svg> <span>DCA & Buy the Dip</span></button>
<button class="nav-link" data-page="forecast"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V9m8 11V4m8 16v-8 M2 21h20"/></svg> <span>Predikce & scénáře</span></button>
<button class="nav-link" data-page="goals"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9 M12 7a5 5 0 1 0 5 5 M12 12l9-9 M16 3h5v5"/></svg> <span>Cesta k cíli</span></button>
<button class="nav-link" data-page="settings"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16 M4 12h16 M4 18h16 M8 3v6 M16 9v6 M9 15v6"/></svg> <span>Nastavení & data</span></button>
<a href="/kontakt.html" class="nav-link">✉️ <span>Kontakt</span></a><a href="/account.php?view=profile" class="nav-link">👤 <span>Můj účet</span></a><?php if(auth_is_admin($user)):?><a href="/admin.php" class="nav-link">🛡️ <span>Správa uživatelů</span></a><?php endif;?><form class="sidebar-logout-form" method="post"><input type="hidden" name="form_action" value="logout"><input type="hidden" name="csrf" value="<?=htmlspecialchars((string)($_SESSION['csrf']??''),ENT_QUOTES)?>"><button type="submit" class="nav-link">🚪 <span>Odhlásit se</span></button></form><a class="nav-support" href="/support.html">❤️ <span>Podpořte projekt Moje krypto</span></a></nav><div class="sidebar-bottom"><p>🔐 Soukromá evidence. Kurzy: CoinGecko / Coinbase; grafy také Kraken.</p><a href="https://jirijanousek.cz/" target="_blank" rel="noopener noreferrer">jirijanousek.cz ↗</a></div>
</aside>
<div class="workspace">
<header class="header"><div class="header-left"><button class="burger" id="burger" aria-controls="sidebar" aria-label="Otevřít menu" aria-expanded="false">☰</button><div><div class="eyebrow">JIŘÍ JANOUŠEK / INVESTICE</div><h1 id="page-title">Přehled kryptoměn</h1></div></div>
<div class="header-actions"><span id="online-status" class="online-status">● Načítání cen</span><a class="btn support-cta" href="/support.html">❤️ Podpořte projekt</a><button class="btn gold" id="add-transaction" aria-label="Přidat převod"><span class="add-transaction-icon" aria-hidden="true">＋</span><span class="add-transaction-text">Převod</span></button>
<a href="/account.php?view=profile" class="logout" title="Můj účet" aria-label="Můj účet">👤</a><?php if(auth_is_admin($user)):?><a href="/admin.php" class="logout" title="Správa uživatelů" aria-label="Správa uživatelů">🛡️</a><?php endif;?><form method="post"><input type="hidden" name="form_action" value="logout"><input type="hidden" name="csrf" value="<?=htmlspecialchars((string)($_SESSION['csrf']??''),ENT_QUOTES)?>"><button class="logout" title="Odhlásit se" aria-label="Odhlásit se" type="submit">⎋</button></form></div></header>
<main>
<section id="page-dashboard" class="page active">
<div id="info-banner" class="banner">💡 Nejdříve doplň stav portfolia nebo importuj data. Předchozí převody do peněženky samy o sobě neurčují skutečný zisk.</div>
<div class="stat-grid">
<div class="stat accent"><small>💼 Hodnota portfolia</small><strong id="portfolio-value">—</strong><span id="portfolio-caption">Přepočet podle cen CZK</span></div>
<div class="stat"><small>🟠 Bitcoin · 1 BTC</small><strong id="btc-price">—</strong><span id="btc-change">Živá cena · CZK</span></div>
<div class="stat"><small>💠 Ethereum · 1 ETH</small><strong id="eth-price">—</strong><span id="eth-change">Živá cena · CZK</span></div>
<div class="stat"><small id="portfolio-roi-label">📊 Moje skutečné zhodnocení</small><strong id="portfolio-roi">—</strong><span id="portfolio-roi-info">Doplň pořizovací náklady</span></div>
</div>
<div class="two-cols">
<article class="panel wide-chart"><div class="panel-head"><div><h2>📈 Vývoj cen v Kč</h2><p>Online historická data · kurzové změny v %</p></div><div class="segmented" id="quick-symbol"><button class="selected" data-symbol="BTC">₿ BTC</button><button data-symbol="ETH">◆ ETH</button></div></div>
<div class="periods" data-periods></div><label class="chart-kind">Typ grafu <select data-chart-mode><option value="line">Čárový</option><option value="area">Plošný</option><option value="bar">Sloupcový</option></select></label><div id="home-chart" class="graph" role="img" aria-label="Vývoj historické ceny"></div><div class="graph-foot" id="home-chart-footer">Připravuji graf…</div></article>
<article class="panel"><div class="panel-head"><div><h2>🧩 Moje portfolio</h2><p>Aktuální podíly aktiv</p></div></div><label class="chart-kind">Typ grafu <select id="allocation-type"><option value="donut">Prstencový</option><option value="pie">Koláčový</option></select></label><div class="donut-area"><div class="donut" id="donut"><div class="donut-core"><small>CELKEM</small><b id="donut-amount">—</b></div></div><div id="donut-legend" class="donut-legend"></div></div>
<div class="summary" id="portfolio-summary"></div></article></div>
<div class="two-cols lower"><article class="panel"><div class="panel-head"><div><h2>📅 Převody po letech</h2><p>Přijaté hodnoty transakcí, nejsou pořizovacími náklady</p></div></div><label class="chart-kind">Typ grafu <select id="annual-type"><option value="grouped">Seskupené sloupce BTC / ETH</option><option value="stacked">Skládané sloupce</option><option value="pie">Koláč podle let</option></select></label><div id="annual-bars" class="annual-bars"></div><div id="annual-totals" class="summary"></div></article>
<article class="panel"><div class="panel-head"><div><h2>💸 Poslední transakce</h2><p>Převody v CZK a BTC / ETH</p></div><button class="link-btn" data-go="transactions">Všechny →</button></div><div id="recent-transactions"></div></article></div>
</section>
<section id="page-markets" class="page">
<div class="panel"><div class="panel-head"><div><h2>📈 Historický vývoj trhu</h2><p>BTC až 15 let, ETH od svého uvedení. CZK, růst v %, reálná kupní síla.</p></div><div class="segmented" id="market-symbol"><button class="selected" data-symbol="BTC">₿ Bitcoin</button><button data-symbol="ETH">◆ Ethereum</button></div></div>
<div class="periods" data-periods></div><label class="chart-kind">Typ grafu <select data-chart-mode><option value="line">Čárový</option><option value="area">Plošný</option><option value="bar">Sloupcový</option></select></label>
<div class="four-metrics"><div><small>Start kurzu</small><b id="market-start">—</b></div><div><small>Poslední kurz</small><b id="market-end">—</b></div><div><small>Změna období</small><b id="market-growth">—</b></div><div><small>Průměr ročně (CAGR)</small><b id="market-cagr">—</b></div></div>
<div class="chart-options"><label><input type="checkbox" id="show-inflation" checked> 🔴 Inflace (srovnávací křivka)</label><label><input type="checkbox" id="show-real" checked> 🟢 Reálná kupní síla</label><label>📉 Vlastní roční výnos <input id="benchmark-return" type="number" step="0.1" value="12" class="mini-input"> %</label><label>Inflace <input id="market-inflation" type="number" step="0.1" value="2" class="mini-input"> % ročně</label></div>
<div id="market-chart" class="graph tall"></div><div class="graph-foot" id="market-chart-footer"></div><div class="legend-row"><span>🔵 Trh</span><span>🟠 Vlastní strategie</span><span>🔴 Inflace</span><span>🟢 Reálně po inflaci</span></div>
<div class="note">📚 Roční výnosy se počítají z dostupných historických uzavíracích cen v CZK. Starší data mohou být poskytovatelem omezena; skutečně načtené období se uvádí u grafu. Historická výkonnost nezaručuje budoucí výsledek.</div></div>
</section>
<section id="page-transactions" class="page">
<article class="panel"><div class="panel-head"><div><h2>💸 Historie nákupů a převodů</h2><p>Cena za jednotku = částka převodu / množství. Dole je součet Kč a vážený průměr pro BTC a ETH. Skutečná pořizovací cena může být jiná.</p></div><div class="filters"><select id="filter-year"><option value="all">Všechny roky</option></select><select id="filter-symbol"><option value="all">BTC + ETH</option><option>BTC</option><option>ETH</option></select></div></div>
<div class="table-wrap"><table class="transactions-table"><thead><tr><th>Datum</th><th>Typ</th><th>Aktivum</th><th>Množství</th><th>Částka při převodu</th><th title="Orientační cena za 1 BTC/ETH, vypočtená jako CZK převodu děleno množstvím">Odhad ceny za 1 BTC / ETH</th><th>Akce</th></tr></thead><tbody id="transactions-body"></tbody><tfoot id="transactions-summary"></tfoot></table></div><p class="table-caption" id="transactions-footer"></p>
</article></section>
<section id="page-strategies" class="page">
<div class="two-cols"><article class="panel"><h2>🔁 Historické DCA</h2><p>Simulace měsíčního průměrování nákupní ceny na skutečné historické řadě.</p>
<div class="form-columns"><label>Aktivum<select id="dca-coin"><option value="BTC">₿ Bitcoin</option><option value="ETH">◆ Ethereum</option></select></label>
<label>Období<select id="dca-period"><option value="1Y">1 rok</option><option value="5Y" selected>5 let</option><option value="15Y">15 let (dle dostupnosti)</option></select></label>
<label>Měsíční vklad Kč<input type="number" id="dca-monthly" value="3000" min="0" step="100"></label>
<label>Jednorázový první vklad Kč<input type="number" id="dca-initial" value="0" min="0" step="100"></label>
<label>Extra nákup při propadu Kč<input type="number" id="dip-extra" value="1000" min="0" step="100"></label>
<label>Pokles za posledních 30 dní (%)<input type="number" id="dip-threshold" value="15" min="1" max="99" step="1"></label></div>
<button id="run-dca" class="btn gold">▶ Spočítat DCA & Buy the Dip</button><div class="note">DCA: nákup každý měsíc za pevnou částku. Buy the Dip: navíc až jeden extra nákup za kalendářní měsíc při poklesu o nastavené procento z 30denního maxima. Bez poplatků a daní. Extra nákupy vyžadují dodatečný kapitál; výsledky proto porovnáváme s vloženou částkou.</div></article>
<article class="panel"><h2>💰 Výsledek strategie</h2><label class="chart-kind">Typ grafu <select data-chart-mode><option value="line">Čárový</option><option value="area">Plošný</option><option value="bar">Sloupcový</option></select></label><div class="result-grid" id="dca-results"><p class="muted">Vyber parametry a stiskni „Spočítat“.</p></div><div id="dca-chart" class="graph compact"></div><div class="graph-foot" id="dca-foot"></div><h2 style="margin-top:25px">🛒 Průměrná cena nakupovaných jednotek v čase</h2><p>Spotová cena versus průběžná průměrná nákupní cena DCA a Buy the Dip</p><div id="dca-price-chart" class="graph compact"></div></article></div>
</section>
<section id="page-forecast" class="page">
<div class="two-cols"><article class="panel"><h2>🔮 Model budoucí hodnoty</h2><p>Libovolné roční zhodnocení včetně 112 %, vlastní inflace, pravidelných vkladů a doby investování.</p>
<div class="form-columns"><label>Počáteční investice Kč<input type="number" id="f-initial" value="100000" min="0" step="100"></label><label>Měsíční investice Kč<input type="number" id="f-monthly" value="3000" min="0" step="100"></label>
<label>Doba investování (roky)<input type="number" id="f-years" value="10" min="1" max="50"></label><label>Očekávaná roční inflace %<input type="number" id="f-inflation" value="2" step="0.1" min="-20" max="100"></label>
<label>🔴 Pesimistický výnos % p. a.<input type="number" id="f-low" value="-15" min="-99" max="1000" step="0.1"></label>
<label>🔵 Vlastní výnos % p. a.<input type="number" id="f-mid" value="12" min="-99" max="1000" step="0.1"></label>
<label>🟢 Optimistický výnos % p. a.<input type="number" id="f-high" value="25" min="-99" max="1000" step="0.1"></label>
<label>Ukázková hodnota <button class="inline-btn" id="set-112" type="button">Nastavit 112 %</button><span class="field-hint">112 % p. a. je extrémní ilustrativní scénář, ne předpověď.</span></label></div><div class="note">📚 Převzít z historických dat: <select id="f-history-coin" aria-label="Historické aktivum"><option value="BTC">₿ BTC</option><option value="ETH">◆ ETH</option></select> <button class="btn" id="use-history" type="button">Použít CAGR z historie (až 15 let)</button><span id="f-history-note"></span></div><button class="btn gold" id="calculate-forecast">✦ Přepočítat model</button>
<div class="note">Výpočty používají konstantní modelové roční zhodnocení, měsíční složené úročení a vklady na konci měsíce. Inflace snižuje kupní sílu nominální částky. Nezohledňují volatilitu, daně ani poplatky.</div>
</article><article class="panel"><h2>📊 Srovnání scénářů</h2><div class="local-graph-mode"><label class="chart-kind">Typ grafu <select data-chart-mode><option value="line">Čárový</option><option value="area">Plošný</option><option value="bar">Sloupcový</option></select></label></div><div id="forecast-summary" class="result-grid"></div><div id="forecast-chart" class="graph compact"></div><div id="forecast-table"></div></article></div>
</section>
<section id="page-goals" class="page">
<article class="panel goal-hero"><div class="eyebrow">MŮJ DALŠÍ MILNÍK</div><h2>🎯 Cesta k 0,1 BTC nebo 1 ETH</h2><p>Vlastní cíl, pravidelné vklady a přehledný plán. Vychází z tvého uloženého portfolia.</p>
<div class="form-columns"><label>Aktivum<select id="g-coin"><option>BTC</option><option>ETH</option></select></label><label>Cílové množství<input id="g-target" type="number" min="0.00000001" step="any" value="0.1"></label><label>Měsíční rozpočet (Kč)<input id="g-monthly" type="number" min="0" step="100" value="3000"></label><label>Za kolik měsíců chci cíl splnit<input id="g-months" type="number" min="1" max="600" value="24"></label><label>Modelový růst ceny (% ročně)<input id="g-growth" type="number" min="-90" max="200" step="1" value="0"></label><label>Vlastní cena 1 mince v Kč (volitelné)<input id="g-price" type="number" min="0" step="any" placeholder="Automaticky aktuální kurz"></label></div>
<button class="btn gold" id="calculate-goal">Spočítat můj plán</button><div id="goal-results" class="result-grid"></div><progress id="goal-progress" max="100" value="0" aria-label="Dosavadní splnění cíle"></progress><div id="goal-chart" class="graph tall"></div><p id="goal-foot"></p></article>
<article class="panel bottom-panel"><h2>₿ Kalkulačka satoshi</h2><p>1 BTC = 100 000 000 satoshi. Přepočet podle aktuálního kurzu, případně vlastní zadané ceny BTC.</p><div class="form-columns"><label>Částka v Kč<input id="sat-czk" type="number" min="0" value="1000"></label><label>Vlastní kurz BTC v Kč (volitelné)<input id="sat-price" type="number" min="0" placeholder="Aktuální cena BTC"></label></div><div id="sat-result" class="result-grid"></div></article>
<div class="note">Plán je model, nikoli pokyn k obchodování. Pravidelné vklady rozloží nákupy v čase. Při růstu ceny získáš za stejný vklad méně mincí, při poklesu více. Model nezahrnuje poplatky, spread ani daně; nákupy při poklesu vyžadují samostatnou rezervu.</div></section>
<section id="page-settings" class="page">
<div class="two-cols"><article class="panel appearance-panel"><h2>🎨 Vzhled aplikace</h2><p>Vyber barevné schéma nebo vlastní pozadí a text. Nastavení se pamatuje v tomto prohlížeči.</p><div class="form-columns"><label>Barevné schéma<select id="theme-preset"><option value="ocean">Modrý oceán</option><option value="emerald">Smaragdová</option><option value="amethyst">Ametystová</option><option value="pearl">Světlá perla</option><option value="custom">Vlastní barvy</option></select></label><label>Barva pozadí<input type="color" id="theme-background" value="#0d2450"></label><label>Barva textu<input type="color" id="theme-text" value="#eaf0ff"></label></div><div class="theme-swatches"><button class="theme-swatch ocean" data-theme="ocean">Oceán</button><button class="theme-swatch emerald" data-theme="emerald">Smaragd</button><button class="theme-swatch amethyst" data-theme="amethyst">Ametyst</button><button class="theme-swatch pearl" data-theme="pearl">Perla</button></div><p id="theme-contrast" aria-live="polite"></p><button class="btn" id="theme-reset">Obnovit modré schéma</button></article><article class="panel"><h2>💼 Stav portfolia</h2><p>Zadej aktuální množství BTC/ETH a pořizovací náklady aktuálně držených jednotek. Cena trhu se doplňuje automaticky online.</p>
<div class="form-columns"><label>🟠 BTC (množství)<input type="number" id="qty-btc" step="0.00000001" min="0"></label><label>💠 ETH (množství)<input type="number" id="qty-eth" step="0.00000001" min="0"></label>
<label>Nákupní náklady BTC (Kč)<input type="number" id="cost-btc" min="0" step="1" placeholder="Nevím – nechat prázdné"></label><label>Nákupní náklady ETH (Kč)<input type="number" id="cost-eth" min="0" step="1" placeholder="Nevím – nechat prázdné"></label></div>
<button class="btn gold" id="save-portfolio">💾 Uložit portfolio</button><div class="note">Když ponecháš náklady prázdné, aplikace může zobrazit pouze <strong>odhad</strong> podle CZK hodnot přijatých převodů – pokud souhlasí celá historie s aktuálním zůstatkem. Přijatá hodnota BTC či ETH do peněženky není doložená nákupní cena. Skutečné pořizovací náklady lze zadat ručně; mají přednost. Případné poplatky a převody mezi peněženkami mohou odhad změnit.</div>
</article>
<article class="panel"><h2>📁 Import / export / tisk</h2><p>Bez připojení k privátním klíčům. Importuj výpis transakcí, zálohuj údaje a vytiskni report.</p>
<div class="button-stack"><label class="btn gold file-btn">⬆ Import Excel / Trezor CSV / JSON<input id="import-file" type="file" accept=".xlsx,.json,.csv" multiple hidden></label><button class="btn" id="paste-import">📋 Vložit data JSON / CSV</button><button class="btn" id="export-xlsx">⬇ Export do Excelu (.xlsx)</button><button class="btn" id="export-json">⬇ Export všeho do JSON</button><button class="btn" id="export-csv">⬇ Export převodů do CSV</button><button class="btn" id="print">⬇ Export PDF / tisk</button><button class="btn danger" id="erase">🗑 Smazat evidenci</button></div><div class="note">Excel (.xlsx) z této aplikace obsahuje portfolio i transakce. Trezor CSV lze vybrat pro BTC a ETH současně; duplicitní ID se přeskočí a odchozí množství zahrnuje poplatek. Import JSON / Excel nahradí evidenci po potvrzení. Staré .xls nejprve ulož jako .xlsx. PDF otevře tiskový náhled pro uložení do PDF.</div>
</article></div>
<div class="panel bottom-panel"><h2>ℹ️ O datech a predikcích</h2><p>Tržní zdroje: CoinGecko, Coinbase, Kraken; dříve <a href="https://www.cryptocompare.com/" target="_blank" rel="noopener noreferrer">CryptoCompare / CoinDesk Data</a>. Historie z CoinGecko je v CZK. Záložní historie Kraken je v USD přepočtených dnešním USD/CZK, nikoli historickým kurzem. Datum, zdroj a neúplné pokrytí jsou uvedené u grafu. Staré úseky, zvláště před vznikem ETH, nejsou automaticky vyplňovány smyšlenými hodnotami. Údaje se mohou lišit od kurzu tvé burzy. Tato aplikace neposkytuje investiční doporučení.</p><p>🛡️ Přihlášení, registrace a ověření e-mailu používají zabezpečený účtový systém. Správce může účty blokovat a spravovat.</p></div>
</section>
</main><footer class="site-common-footer"><div class="site-footer-top"><strong>Moje krypto · Jiří Janoušek</strong><a class="site-footer-support" href="/support.html">💛 Podpořte projekt Moje krypto</a></div><nav class="footer-legal" aria-label="Právní informace"><a href="/legal.php?view=terms">Podmínky používání</a><a href="/legal.php?view=legal">Právní upozornění</a><a href="/legal.php?view=privacy">Ochrana údajů / GDPR</a><a href="/legal.php?view=cookies">Cookies</a><button type="button" data-cookie-settings>Nastavení cookies</button><a href="https://jirijanousek.cz/" target="_blank" rel="noopener noreferrer">jirijanousek.cz ↗</a></nav><p>© 2026 Jiří Janoušek · Moje krypto</p></footer>
</div></div>
<dialog id="transaction-dialog"><form id="transaction-form" method="dialog"><div class="modal-top"><h2 id="modal-title">Nová transakce</h2><button id="close-modal" type="button" class="logout" aria-label="Zavřít">✕</button></div><div class="form-columns">
<label>Datum<input type="date" id="tx-date" required></label><label>Aktivum<select id="tx-symbol"><option>BTC</option><option>ETH</option></select></label>
<label>Typ<select id="tx-type"><option value="received">📥 Přijato</option><option value="sent">📤 Odesláno</option><option value="buy">🛒 Nákup</option><option value="sell">💵 Prodej</option></select></label>
<label>Množství v kryptoměně<input id="tx-qty" type="number" min="0" step="0.00000001" required></label>
<label>Částka v Kč při transakci<input type="number" id="tx-czk" min="0" step="0.01" required></label><label>Poznámka<input type="text" id="tx-note" maxlength="160" placeholder="Volitelné"></label></div>
<div class="modal-buttons"><button class="btn" type="button" id="cancel-modal">Zrušit</button><button class="btn gold" type="submit">💾 Uložit transakci</button></div></form></dialog>
<dialog id="paste-dialog"><h2>Vložit soukromá data</h2><p>Vlož JSON zálohu nebo obsah CSV. Údaje se uloží pouze do tvé aplikace.</p><textarea id="paste-data" rows="12" aria-label="Data JSON nebo CSV" spellcheck="false"></textarea><div class="modal-buttons"><button class="btn" id="paste-cancel">Zrušit</button><button class="btn gold" id="paste-confirm">Zkontrolovat a importovat</button></div></dialog><section id="print-report"></section>
<dialog id="import-review"><h2>Potvrdit import</h2><p id="import-review-summary"></p><div class="modal-buttons"><button class="btn" id="import-review-no">Zrušit</button><button class="btn gold" id="import-review-yes">Uložit do mé evidence</button></div></dialog><div id="toast" aria-live="polite" role="status"></div>
<?php endif; ?>
</body></html>
