<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
session_name('MOKRYPTOSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(24));
$dir=__DIR__.'/storage';
$adminFile=$dir.'/admin.php';
$dataFile=$dir.'/data.php';
$hasAdmin=is_file($adminFile);
$logged=($_SESSION['admin']??false)===true;
$api=(string)($_GET['api']??'');
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
function secret():string{
 $v=getenv('MOJE_KRYPTO_SETUP_TOKEN');
 if(is_string($v)&&strlen($v)>=20)return $v;
 $f=__DIR__.'/config.local.php';
 if(!is_file($f))return '';
 $config=include $f;
 $v=is_array($config)?($config['setup_token']??''):'';
 return is_string($v)&&strlen($v)>=20?$v:'';
}
function fetchJson(string $url):?array{
 if(function_exists('curl_init')){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>13,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_USERAGENT=>'MojeKrypto/1.0 (private CZK analysis)',CURLOPT_HTTPHEADER=>['Accept: application/json']]);
  $s=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if($status!==200||!is_string($s))return null;
 }else{
  $ctx=stream_context_create(['http'=>['timeout'=>12,'header'=>"Accept: application/json\r\nUser-Agent: MojeKrypto/1.0\r\n"]]);
  $s=@file_get_contents($url,false,$ctx);
  if(!is_string($s))return null;
 }
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
 csrf();$a=(string)$_POST['form_action'];
 if($a==='setup'&&!$hasAdmin){
  $token=secret();$email=strtolower(trim((string)($_POST['email']??'')));$pwd=(string)($_POST['password']??'');
  if($token===''||!hash_equals($token,(string)($_POST['setup_token']??'')))$formError='Neplatný nebo nenastavený instalační klíč.';
  elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$formError='Nesprávný e-mail.';
  elseif(strlen($pwd)<12)$formError='Heslo musí mít alespoň 12 znaků.';
  elseif(!writeStore($adminFile,['email'=>$email,'hash'=>password_hash($pwd,PASSWORD_DEFAULT)]))$formError='Úložiště není zapisovatelné. Zkontroluj práva storage.';
  else{session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['csrf']=bin2hex(random_bytes(24));header('Location: ./');exit;}
 }elseif($a==='login'&&$hasAdmin){
  $limitFile=$dir.'/login-attempts.php';$limits=readStore($limitFile,[]);$ip=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));$now=time();
  $attempt=$limits[$ip]??['n'=>0,'until'=>$now+900];
  if($now>($attempt['until']??0))$attempt=['n'=>0,'until'=>$now+900];
  $rec=readStore($adminFile,[]);$email=strtolower(trim((string)($_POST['email']??'')));$pwd=(string)($_POST['password']??'');
  if(($attempt['n']??0)>=8){$formError='Příliš mnoho pokusů. Zkus přihlášení za 15 minut.';}
  elseif(hash_equals((string)($rec['email']??''),$email)&&password_verify($pwd,(string)($rec['hash']??''))){
   unset($limits[$ip]);writeStore($limitFile,$limits);
   session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['csrf']=bin2hex(random_bytes(24));header('Location: ./');exit;
  }else{$limits[$ip]=['n'=>($attempt['n']??0)+1,'until'=>$attempt['until']];writeStore($limitFile,$limits);usleep(250000);$formError='Nesprávný e-mail nebo heslo.';}
 }elseif($a==='logout'&&$logged){
  $_SESSION=[];session_regenerate_id(true);header('Location: ./');exit;
 }
}
require_once __DIR__.'/market-data.php';
if($api!==''){
 if(!$logged)reply(['error'=>'Přihlas se prosím.'],401);
 if($api==='data'){
  if($_SERVER['REQUEST_METHOD']==='GET')reply(['data'=>readStore($dataFile,['assets'=>[],'transactions'=>[]])]);
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
<title>Moje krypto | Jiří Janoušek</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="style.css?v=20261105b">
<?php if($logged):?><script src="charts.js?v=20261105b" defer></script><script src="models.js" defer></script><script src="data-tools.js?v=20261105b" defer></script><script src="app.js?v=20261105b" defer></script><?php endif;?>
</head><body>
<?php if(!$logged):?>
<div class="auth-scene"><div class="auth-card"><div class="mark">₿</div>
<div class="eyebrow">JIŘÍ JANOUŠEK · OSOBNÍ FINANCE</div><h1><?= $hasAdmin?'Vítej v Moje krypto':'První nastavení přístupu' ?></h1>
<p class="subtle"><?= $hasAdmin?'Přihlášení do tvého soukromého finančního dashboardu.':'Pouze jeden účet administrátora. Bez veřejných registrací.' ?></p>
<?php if(isset($formError)):?><p class="form-error"><?=htmlspecialchars($formError,ENT_QUOTES)?></p><?php endif;?>
<?php if(!$hasAdmin && secret()===''):?>
<p class="form-help">🔐 Pro první aktivaci nejprve na Webkitty vytvoř soubor <code>config.local.php</code> podle <code>config.example.php</code> a nastav vlastní instalační klíč (alespoň 20 znaků). Pak obnov stránku.</p>
<?php else:?>
<form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?=htmlspecialchars((string)($_SESSION['csrf']??''),ENT_QUOTES)?>">
<input type="hidden" name="form_action" value="<?=$hasAdmin?'login':'setup'?>">
<?php if(!$hasAdmin):?><label>Instalační klíč<input name="setup_token" type="password" required autocomplete="off"></label><?php endif;?>
<label>E-mail<input type="email" name="email" required autocomplete="username" placeholder="e-mail administrátora"></label>
<label>Heslo<input type="password" name="password" required autocomplete="<?=$hasAdmin?'current-password':'new-password'?>" minlength="<?=$hasAdmin?'1':'12'?>"></label>
<button class="btn gold wide" type="submit"><?=$hasAdmin?'Přihlásit se →':'Vytvořit administrátora →'?></button></form>
<?php endif;?>
<div class="auth-footer">🔒 Bez veřejné registrace · Údaje na tvém serveru</div></div></div>
<?php else:?>
<div class="app-layout"><div id="mobile-shade"></div><aside id="sidebar" class="sidebar">
<div class="brand"><div class="brand-icon">₿</div><div><b>Moje krypto</b><small>investiční centrum</small></div></div>
<div class="nav-label">MOJE ANALYTIKA</div><nav id="navigation" aria-label="Navigace">
<button class="nav-link active" data-page="dashboard"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z"/></svg> <span>Přehled</span></button>
<button class="nav-link" data-page="markets"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 19h18 M4 15l5-6 4 3 7-9"/></svg> <span>Trhy & grafy</span></button>
<button class="nav-link" data-page="transactions"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h15m-4-4 4 4-4 4 M20 17H5m4-4-4 4 4 4"/></svg> <span>Transakce</span></button>
<button class="nav-link" data-page="strategies"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h13l-3-3 M20 17H7l3 3 M20 7v5 M4 17v-5"/></svg> <span>DCA & Buy the Dip</span></button>
<button class="nav-link" data-page="forecast"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V9m8 11V4m8 16v-8 M2 21h20"/></svg> <span>Predikce & scénáře</span></button>
<button class="nav-link" data-page="goals"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9 M12 7a5 5 0 1 0 5 5 M12 12l9-9 M16 3h5v5"/></svg> <span>Cesta k cíli</span></button>
<button class="nav-link" data-page="settings"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16 M4 12h16 M4 18h16 M8 3v6 M16 9v6 M9 15v6"/></svg> <span>Nastavení & data</span></button>
</nav><div class="sidebar-bottom"><p>🔐 Soukromá evidence. Kurzy: CoinGecko / Coinbase; grafy také Kraken.</p><a href="https://jirijanousek.cz/" target="_blank" rel="noopener noreferrer">jirijanousek.cz ↗</a></div>
</aside>
<div class="workspace">
<header class="header"><div class="header-left"><button class="burger" id="burger" aria-label="Otevřít menu" aria-expanded="false">☰</button><div><div class="eyebrow">JIŘÍ JANOUŠEK / INVESTICE</div><h1 id="page-title">Přehled kryptoměn</h1></div></div>
<div class="header-actions"><span id="online-status" class="online-status">● Načítání cen</span><button class="btn gold" id="add-transaction">＋ Převod</button>
<form method="post"><input type="hidden" name="form_action" value="logout"><input type="hidden" name="csrf" value="<?=htmlspecialchars((string)($_SESSION['csrf']??''),ENT_QUOTES)?>"><button class="logout" title="Odhlásit se" aria-label="Odhlásit se" type="submit">⎋</button></form></div></header>
<main>
<section id="page-dashboard" class="page active">
<div id="info-banner" class="banner">💡 Nejdříve doplň stav portfolia nebo importuj data. Předchozí převody do peněženky samy o sobě neurčují skutečný zisk.</div>
<div class="stat-grid">
<div class="stat accent"><small>💼 Hodnota portfolia</small><strong id="portfolio-value">—</strong><span id="portfolio-caption">Přepočet podle cen CZK</span></div>
<div class="stat"><small>🟠 Bitcoin · 1 BTC</small><strong id="btc-price">—</strong><span id="btc-change">Živá cena · CZK</span></div>
<div class="stat"><small>💠 Ethereum · 1 ETH</small><strong id="eth-price">—</strong><span id="eth-change">Živá cena · CZK</span></div>
<div class="stat"><small>📊 Moje skutečné zhodnocení</small><strong id="portfolio-roi">—</strong><span id="portfolio-roi-info">Doplň pořizovací náklady</span></div>
</div>
<div class="two-cols">
<article class="panel wide-chart"><div class="panel-head"><div><h2>📈 Vývoj cen v Kč</h2><p>Online historická data · kurzové změny v %</p></div><div class="segmented" id="quick-symbol"><button class="selected" data-symbol="BTC">₿ BTC</button><button data-symbol="ETH">◆ ETH</button></div></div>
<div class="periods" data-periods></div><div id="home-chart" class="graph" role="img" aria-label="Vývoj historické ceny"></div><div class="graph-foot" id="home-chart-footer">Připravuji graf…</div></article>
<article class="panel"><div class="panel-head"><div><h2>🧩 Moje portfolio</h2><p>Aktuální podíly aktiv</p></div></div><div class="donut-area"><div class="donut" id="donut"><div class="donut-core"><small>CELKEM</small><b id="donut-amount">—</b></div></div><div id="donut-legend" class="donut-legend"></div></div>
<div class="summary" id="portfolio-summary"></div></article></div>
<div class="two-cols lower"><article class="panel"><div class="panel-head"><div><h2>📅 Převody po letech</h2><p>Přijaté hodnoty transakcí, nejsou pořizovacími náklady</p></div></div><div id="annual-bars" class="annual-bars"></div><div id="annual-totals" class="summary"></div></article>
<article class="panel"><div class="panel-head"><div><h2>💸 Poslední transakce</h2><p>Převody v CZK a BTC / ETH</p></div><button class="link-btn" data-go="transactions">Všechny →</button></div><div id="recent-transactions"></div></article></div>
</section>
<section id="page-markets" class="page">
<div class="panel"><div class="panel-head"><div><h2>📈 Historický vývoj trhu</h2><p>BTC až 15 let, ETH od svého uvedení. CZK, růst v %, reálná kupní síla.</p></div><div class="segmented" id="market-symbol"><button class="selected" data-symbol="BTC">₿ Bitcoin</button><button data-symbol="ETH">◆ Ethereum</button></div></div>
<div class="periods" data-periods></div>
<div class="four-metrics"><div><small>Start kurzu</small><b id="market-start">—</b></div><div><small>Poslední kurz</small><b id="market-end">—</b></div><div><small>Změna období</small><b id="market-growth">—</b></div><div><small>Průměr ročně (CAGR)</small><b id="market-cagr">—</b></div></div>
<div class="chart-options"><label><input type="checkbox" id="show-inflation" checked> 🔴 Inflace (srovnávací křivka)</label><label><input type="checkbox" id="show-real" checked> 🟢 Reálná kupní síla</label><label>📉 Vlastní roční výnos <input id="benchmark-return" type="number" step="0.1" value="12" class="mini-input"> %</label><label>Inflace <input id="market-inflation" type="number" step="0.1" value="2" class="mini-input"> % ročně</label></div>
<div id="market-chart" class="graph tall"></div><div class="graph-foot" id="market-chart-footer"></div><div class="legend-row"><span>🔵 Trh</span><span>🟠 Vlastní strategie</span><span>🔴 Inflace</span><span>🟢 Reálně po inflaci</span></div>
<div class="note">📚 Roční výnosy se počítají z dostupných historických uzavíracích cen v CZK. Starší data mohou být poskytovatelem omezena; skutečně načtené období se uvádí u grafu. Historická výkonnost nezaručuje budoucí výsledek.</div></div>
</section>
<section id="page-transactions" class="page">
<article class="panel"><div class="panel-head"><div><h2>💸 Historie nákupů a převodů</h2><p>Každou transakci lze přidat, upravit a vymazat.</p></div><div class="filters"><select id="filter-year"><option value="all">Všechny roky</option></select><select id="filter-symbol"><option value="all">BTC + ETH</option><option>BTC</option><option>ETH</option></select></div></div>
<div class="table-wrap"><table><thead><tr><th>Datum</th><th>Typ</th><th>Aktivum</th><th>Množství</th><th>Částka při převodu</th><th>Akce</th></tr></thead><tbody id="transactions-body"></tbody></table></div><p class="table-caption" id="transactions-footer"></p>
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
<article class="panel"><h2>💰 Výsledek strategie</h2><div class="result-grid" id="dca-results"><p class="muted">Vyber parametry a stiskni „Spočítat“.</p></div><div id="dca-chart" class="graph compact"></div><div class="graph-foot" id="dca-foot"></div><h2 style="margin-top:25px">🛒 Průměrná cena nakupovaných jednotek v čase</h2><p>Spotová cena versus průběžná průměrná nákupní cena DCA a Buy the Dip</p><div id="dca-price-chart" class="graph compact"></div></article></div>
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
</article><article class="panel"><h2>📊 Srovnání scénářů</h2><div id="forecast-summary" class="result-grid"></div><div id="forecast-chart" class="graph compact"></div><div id="forecast-table"></div></article></div>
</section>
<section id="page-goals" class="page">
<article class="panel goal-hero"><div class="eyebrow">MŮJ DALŠÍ MILNÍK</div><h2>🎯 Cesta k 0,1 BTC nebo 1 ETH</h2><p>Vlastní cíl, pravidelné vklady a přehledný plán. Vychází z tvého uloženého portfolia.</p>
<div class="form-columns"><label>Aktivum<select id="g-coin"><option>BTC</option><option>ETH</option></select></label><label>Cílové množství<input id="g-target" type="number" min="0.00000001" step="any" value="0.1"></label><label>Měsíční rozpočet (Kč)<input id="g-monthly" type="number" min="0" step="100" value="3000"></label><label>Za kolik měsíců chci cíl splnit<input id="g-months" type="number" min="1" max="600" value="24"></label><label>Modelový růst ceny (% ročně)<input id="g-growth" type="number" min="-90" max="200" step="1" value="0"></label><label>Vlastní cena 1 mince v Kč (volitelné)<input id="g-price" type="number" min="0" step="any" placeholder="Automaticky aktuální kurz"></label></div>
<button class="btn gold" id="calculate-goal">Spočítat můj plán</button><div id="goal-results" class="result-grid"></div><progress id="goal-progress" max="100" value="0" aria-label="Dosavadní splnění cíle"></progress><div id="goal-chart" class="graph tall"></div><p id="goal-foot"></p></article>
<article class="panel bottom-panel"><h2>₿ Kalkulačka satoshi</h2><p>1 BTC = 100 000 000 satoshi. Přepočet podle aktuálního kurzu, případně vlastní zadané ceny BTC.</p><div class="form-columns"><label>Částka v Kč<input id="sat-czk" type="number" min="0" value="1000"></label><label>Vlastní kurz BTC v Kč (volitelné)<input id="sat-price" type="number" min="0" placeholder="Aktuální cena BTC"></label></div><div id="sat-result" class="result-grid"></div></article>
<div class="note">Plán je model, nikoli pokyn k obchodování. Pravidelné vklady rozloží nákupy v čase. Při růstu ceny získáš za stejný vklad méně mincí, při poklesu více. Model nezahrnuje poplatky, spread ani daně; nákupy při poklesu vyžadují samostatnou rezervu.</div></section>
<section id="page-settings" class="page">
<div class="two-cols"><article class="panel"><h2>💼 Stav portfolia</h2><p>Zadej aktuální množství BTC/ETH a pořizovací náklady aktuálně držených jednotek. Cena trhu se doplňuje automaticky online.</p>
<div class="form-columns"><label>🟠 BTC (množství)<input type="number" id="qty-btc" step="0.00000001" min="0"></label><label>💠 ETH (množství)<input type="number" id="qty-eth" step="0.00000001" min="0"></label>
<label>Nákupní náklady BTC (Kč)<input type="number" id="cost-btc" min="0" step="1" placeholder="Nevím – nechat prázdné"></label><label>Nákupní náklady ETH (Kč)<input type="number" id="cost-eth" min="0" step="1" placeholder="Nevím – nechat prázdné"></label></div>
<button class="btn gold" id="save-portfolio">💾 Uložit portfolio</button><div class="note">Přijatá hodnota BTC do Trezoru není automaticky tvá nákupní cena. Pokud pořizovací náklady neznáš, pole nech prázdné a výsledek správně zobrazíme jako neznámý.</div>
</article>
<article class="panel"><h2>📁 Import / export / tisk</h2><p>Bez připojení k privátním klíčům. Importuj výpis transakcí, zálohuj údaje a vytiskni report.</p>
<div class="button-stack"><label class="btn gold file-btn">⬆ Import Excel / Trezor CSV / JSON<input id="import-file" type="file" accept=".xlsx,.json,.csv" multiple hidden></label><button class="btn" id="paste-import">📋 Vložit data JSON / CSV</button><button class="btn" id="export-xlsx">⬇ Export do Excelu (.xlsx)</button><button class="btn" id="export-json">⬇ Export všeho do JSON</button><button class="btn" id="export-csv">⬇ Export převodů do CSV</button><button class="btn" id="print">⬇ Export PDF / tisk</button><button class="btn danger" id="erase">🗑 Smazat evidenci</button></div><div class="note">Excel (.xlsx) z této aplikace obsahuje portfolio i transakce. Trezor CSV lze vybrat pro BTC a ETH současně; duplicitní ID se přeskočí a odchozí množství zahrnuje poplatek. Import JSON / Excel nahradí evidenci po potvrzení. Staré .xls nejprve ulož jako .xlsx. PDF otevře tiskový náhled pro uložení do PDF.</div>
</article></div>
<div class="panel bottom-panel"><h2>ℹ️ O datech a predikcích</h2><p>Tržní zdroje: CoinGecko, Coinbase, Kraken; dříve <a href="https://www.cryptocompare.com/" target="_blank" rel="noopener noreferrer">CryptoCompare / CoinDesk Data</a>. Historie z CoinGecko je v CZK. Záložní historie Kraken je v USD přepočtených dnešním USD/CZK, nikoli historickým kurzem. Datum, zdroj a neúplné pokrytí jsou uvedené u grafu. Staré úseky, zvláště před vznikem ETH, nejsou automaticky vyplňovány smyšlenými hodnotami. Údaje se mohou lišit od kurzu tvé burzy. Tato aplikace neposkytuje investiční doporučení.</p><p>🛡️ Registrace je vypnutá. Přihlášení je možné pouze přes vytvořený administrátorský účet. Heslo není možné měnit z aplikace; správa probíhá na serveru.</p></div>
</section>
</main><footer>© 2026 Jiří Janoušek · Moje krypto · <a href="https://jirijanousek.cz/" target="_blank" rel="noopener noreferrer">jirijanousek.cz ↗</a><span>Historické výsledky nejsou zárukou budoucích výnosů.</span></footer>
</div></div>
<dialog id="transaction-dialog"><form id="transaction-form" method="dialog"><div class="modal-top"><h2 id="modal-title">Nová transakce</h2><button id="close-modal" type="button" class="logout" aria-label="Zavřít">✕</button></div><div class="form-columns">
<label>Datum<input type="date" id="tx-date" required></label><label>Aktivum<select id="tx-symbol"><option>BTC</option><option>ETH</option></select></label>
<label>Typ<select id="tx-type"><option value="received">📥 Přijato</option><option value="sent">📤 Odesláno</option><option value="buy">🛒 Nákup</option><option value="sell">💵 Prodej</option></select></label>
<label>Množství v kryptoměně<input id="tx-qty" type="number" min="0" step="0.00000001" required></label>
<label>Částka v Kč při transakci<input type="number" id="tx-czk" min="0" step="0.01" required></label><label>Poznámka<input type="text" id="tx-note" maxlength="160" placeholder="Volitelné"></label></div>
<div class="modal-buttons"><button class="btn" type="button" id="cancel-modal">Zrušit</button><button class="btn gold" type="submit">💾 Uložit transakci</button></div></form></dialog>
<dialog id="paste-dialog"><h2>Vložit soukromá data</h2><p>Vlož JSON zálohu nebo obsah CSV. Údaje se uloží pouze do tvé aplikace.</p><textarea id="paste-data" rows="12" aria-label="Data JSON nebo CSV" spellcheck="false"></textarea><div class="modal-buttons"><button class="btn" id="paste-cancel">Zrušit</button><button class="btn gold" id="paste-confirm">Zkontrolovat a importovat</button></div></dialog><section id="print-report"></section>
<div id="toast" aria-live="polite" role="status"></div>
<?php endif; ?>
</body></html>
