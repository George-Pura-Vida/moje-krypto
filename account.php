<?php
declare(strict_types=1);
require __DIR__.'/auth/bootstrap.php';
// Self-contained privacy view for Moje krypto.
auth_boot();
$views=['login','register','forgot','reset','verify','profile','confirm-email','privacy','approvals'];
$view=is_string($_GET['view']??null)&&in_array($_GET['view'],$views,true)?$_GET['view']:'login';
$error='';$user=null;
try {
    auth_db();
    $user=auth_user();
    if($_SERVER['REQUEST_METHOD']==='POST')auth_process($user);
} catch(DomainException $e){$error=$e->getMessage();}
catch(Throwable $e){http_response_code(503);$error='Služba účtů je dočasně nedostupná. Zkuste to později nebo napište na info@jirijanousek.cz.';error_log('MOJE KRYPTO account request failed: '.get_class($e));}
if($view==='profile'&&!$user)auth_redirect('login','Pro úpravu údajů se přihlaste.');
if($user&&in_array($view,['login','register'],true))auth_redirect('profile');
if($view==='approvals'&&(!$user||!auth_is_admin($user))){http_response_code(403);exit('Přístup do správy uživatelů má pouze správce.');}
if($view==='approvals'&&!defined('AUTH_ADMIN_PAGE')){header('Location: /admin.php',true,303);exit;}
$flash=$_SESSION['flash']??'';unset($_SESSION['flash']);
$titles=['login'=>'Přihlášení','register'=>'Vytvořit účet','forgot'=>'Zapomenuté heslo','reset'=>'Nové heslo','verify'=>'Potvrdit e-mail','profile'=>'Nastavení účtu','confirm-email'=>'Potvrdit nový e-mail','privacy'=>'Údaje a soukromí','approvals'=>'Správa uživatelů'];
function field(string $name,string $label,string $type='text',bool $required=true,int $max=150,string $autocomplete=''):void{
    global $user,$view;
    $value=$_POST[$name]??($view==='profile'?($user[$name]??''):'');if(!is_string($value))$value='';
    echo '<label class="field"><span>'.h($label).($required?' <span class="required" aria-hidden="true">*</span>':'').'</span><input name="'.h($name).'" type="'.h($type).'" value="'.h($value).'" maxlength="'.$max.'" '.($required?'required ':'').'autocomplete="'.h($autocomplete).'" '.($name==='ico'?'inputmode="numeric" pattern="[0-9]{8}" ':'').'></label>';
}
function password_field(string $name,string $label,string $autocomplete='new-password',bool $required=true):void{
    echo '<label class="field"><span>'.h($label).($required?' <span class="required" aria-hidden="true">*</span>':'').'</span><span class="password-wrap"><input id="'.h($name).'" name="'.h($name).'" type="password" '.($required?'required ':'').'maxlength="72" '.($autocomplete==='new-password'?'minlength="7" ':'').'autocomplete="'.h($autocomplete).'"><button class="eye" type="button" data-password="'.h($name).'" aria-controls="'.h($name).'" aria-label="Zobrazit heslo: '.h($label).'" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button></span></label>';
}
function csrf_fields(string $action,bool $token=false):void{
    echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="action" value="'.h($action).'">';
    if($token){$v=$_POST['token']??'';echo '<input type="hidden" name="token" value="'.h(is_string($v)?$v:'').'">';}
}
function profile_fields():void{
    echo '<div class="fields-grid">';field('first_name','Jméno','text',true,150,'given-name');field('last_name','Příjmení','text',true,150,'family-name');
    field('company','Firma (nepovinné)','text',false,200,'organization');field('ico','IČ (nepovinné)','text',false,8);
    field('dic','DIČ (nepovinné, např. CZ12345678)','text',false,20);field('phone','Telefon pro kontaktní ověření','tel',true,40,'tel');
    echo '</div>';field('email','E-mail / přihlašovací jméno','email',true,254,'username');field('address','Adresa — ulice, číslo, PSČ a obec (nepovinné)','text',false,500,'street-address');
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="same-origin"><title><?= h($titles[$view]) ?> | Moje krypto</title><link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/account.css?v=1"><link rel="stylesheet" href="/account-ui.css?v=2"><script defer src="/account.js?v=1"></script></head>
<body>
<header class="account-header"><a class="brand" href="/"><img src="/favicon.svg" width="44" height="44" alt=""><span>MOJE <strong>KRYPTO</strong><small>Jiří Janoušek</small></span></a>
<nav aria-label="Navigace účtu" class="account-nav"><a href="/">📊 Aplikace</a>
<?php if($user): ?><a href="/account.php?view=profile">👤 Můj účet</a><?php if(auth_is_admin($user)): ?><a href="/admin.php">🛡️ Správa uživatelů</a><?php endif; ?>
<?php else: ?><a href="/account.php?view=login">🔐 Přihlášení</a><a href="/account.php?view=register">✨ Registrace</a><?php endif; ?>
<a class="account-nav-support" href="/support.html">❤️ Podpořte projekt</a></nav></header>
<main class="account-layout<?= $view==='approvals'?' admin-layout':'' ?>"><aside class="welcome">
<p class="eyebrow">MOJE KRYPTO · INVESTICE S PLÁNEM</p><h1>Vaše investice.<br><em>Vaše budoucnost s Bitcoinem.</em></h1>
<p>Vytvořte si osobní účet. Sledujte Bitcoin, Ethereum, své transakce a vývoj investic v přehledné aplikaci.</p>
<div class="account-benefits"><p>₿ Bitcoin, Ethereum a osobní portfolio</p><p>📈 Online kurzy, grafy a vlastní scénáře</p><p>🏡 Investiční cíle a budoucí bydlení</p></div>
<p class="welcome-note">Hesla uchováváme výhradně jako bezpečné hashe. Portfolio přihlášeného uživatele je uložené na serveru odděleně od ostatních uživatelů.</p></aside>
<section class="account-card" aria-labelledby="account-title"><p class="eyebrow">MŮJ ÚČET</p><h2 id="account-title"><span class="account-title-icon" aria-hidden="true"><?= in_array($view,['login','register'],true)?'👋':($view==='approvals'?'🛡️':'👤') ?></span><?= h($titles[$view]) ?></h2>
<?php if($error!==''):?><p class="message error" role="alert"><?= h($error) ?></p><?php endif;?>
<?php if($flash!==''):?><p class="message success" role="status"><?= h($flash) ?></p><?php endif;?>
<?php if($view==='login'): ?>
<p class="intro">Přihlaste se svým e-mailem a heslem. Nové účty se aktivují po ověření e-mailové adresy.</p><form method="post" action="/account.php?view=login"><?php csrf_fields('login');field('email','E-mail / login','email',true,254,'username');password_field('password','Heslo','current-password');?><button class="primary" type="submit">Přihlásit se →</button></form><div class="form-links"><a href="/account.php?view=forgot">Zapomenuté heslo / nový ověřovací odkaz</a></div><p class="divider">Ještě nemáte účet?</p><a class="secondary" href="/account.php?view=register">Zaregistrovat se</a>
<?php elseif($view==='register'): ?>
<p class="intro">Pole označená * jsou povinná, včetně telefonu pro následné kontaktní ověření. Zadání čísla nepotvrzuje jeho vlastnictví a aktivace nevyžaduje SMS kód. Adresa a firemní údaje jsou nepovinné.</p><div class="message" role="note"><strong>Aktivace bez čekání:</strong><ol><li>Po registraci otevřete ověřovací odkaz v doručeném e-mailu. Zkontrolujte i složku Spam.</li><li>E-mail se potvrdí, přístup se aktivuje a aplikace Vás automaticky přihlásí.</li></ol></div><form method="post" action="/account.php?view=register"><?php csrf_fields('register');profile_fields();password_field('password','Heslo');password_field('password_confirm','Heslo znovu');?><p class="hint">Použijte alespoň 7 znaků. Heslo můžete zobrazit ikonou oka.</p><label class="check"><input type="checkbox" name="privacy" value="yes" required> <span>Seznámil/a jsem se s <a href="/account.php?view=privacy" target="_blank" rel="noopener">informacemi o zpracování údajů</a>. Nejde o souhlas s marketingem.</span></label><button class="primary" type="submit">Vytvořit účet →</button></form><p class="form-links"><a href="/account.php?view=login">Už mám účet — přihlásit se</a></p>
<?php elseif($view==='forgot'): ?>
<p class="intro">Zadejte registrační e-mail. Odkaz pro obnovu hesla platí 30 minut. U nepotvrzeného účtu odešleme nový ověřovací odkaz.</p><form method="post" action="/account.php?view=forgot"><?php csrf_fields('forgot');field('email','E-mail','email',true,254,'email');?><button class="primary" type="submit">Poslat odkaz e-mailem →</button></form><p class="form-links"><a href="/account.php?view=login">Zpět k přihlášení</a></p>
<?php elseif($view==='reset'): ?>
<p class="intro">Nastavte nové heslo. Po změně se přihlásíte znovu; stará přihlášení budou ukončena.</p><form method="post" action="/account.php?view=reset" data-token-form><?php csrf_fields('reset',true);password_field('password','Nové heslo');password_field('password_confirm','Nové heslo znovu');?><button class="primary" type="submit">Uložit nové heslo →</button></form><p class="form-links"><a href="/account.php?view=forgot">Vyžádat nový odkaz</a></p>
<?php elseif($view==='verify'): ?>
<p class="intro">Ověřujeme Váš e-mail a přihlašujeme Vás do aplikace. Není třeba mačkat další tlačítko.</p><form method="post" action="/account.php?view=verify" data-token-form data-auto-verify><?php csrf_fields('verify',true);?><button class="primary auto-verify-button" type="submit">Potvrdit e-mail →</button><noscript><style>.auto-verify-button{display:flex!important}</style><p>Pro automatickou aktivaci zapněte JavaScript. Bez něj potvrďte e-mail tlačítkem.</p></noscript></form><p class="form-links"><a href="/account.php?view=forgot">Poslat nový ověřovací odkaz</a></p>
<?php elseif($view==='confirm-email'): ?>
<p class="intro">Změnu adresy potvrďte přihlášeni k původnímu účtu.</p><?php if(!$user):?><p class="message">Nejprve se <a href="/account.php?view=login" target="_blank" rel="noopener">přihlaste původním e-mailem v novém okně</a>, potom se vraťte a obnovte tento odkaz z e-mailu.</p><?php endif;?><form method="post" action="/account.php?view=confirm-email" data-token-form><?php csrf_fields('confirm-email',true);?><button class="primary" type="submit">Potvrdit změnu e-mailu →</button></form>
<?php elseif($view==='profile'): ?>
<?php if(auth_is_admin($user)):?><a class="secondary" href="/account.php?view=approvals"><span class="account-icon" aria-hidden="true">🛡️</span> Správa uživatelů →</a><?php endif;?><p class="intro">Jste přihlášeni jako <strong><?= h($user['email']) ?></strong>. Zde můžete upravit své údaje.</p><a class="secondary" href="/">Přejít do kalkulačky →</a><form method="post" action="/account.php?view=profile"><?php csrf_fields('profile');profile_fields();?><details><summary>Měním e-mailovou adresu</summary><p class="hint">Zadejte současné heslo. Novou adresu bude nutné potvrdit odkazem v e-mailu.</p><?php password_field('current_password','Současné heslo pro změnu e-mailu','current-password',false);?></details><button class="primary" type="submit">Uložit údaje</button></form><details class="password-change"><summary>Změnit heslo</summary><form method="post" action="/account.php?view=profile"><?php csrf_fields('password');password_field('current_password_change','Současné heslo','current-password');password_field('password','Nové heslo');password_field('password_confirm','Nové heslo znovu');?><button class="primary" type="submit">Změnit heslo</button></form></details><form class="logout" method="post" action="/account.php?view=profile"><?php csrf_fields('logout');?><button class="secondary" type="submit">Odhlásit se</button></form>
<?php elseif($view==='approvals'): ?>
<p class="intro">🛡️ Pouze pro správce. Nové účty se aktivují automaticky po ověření e-mailu. Zde můžete spravovat přístup nebo účet trvale smazat.</p>
<p class="form-links"><a href="/">← Kalkulačka</a> · <a href="/account.php?view=profile">Můj účet</a></p>
<?php
$perPage=25;
$page=max(1,min(100000,(int)($_GET['page']??1)));
$search=is_string($_GET['q']??null)?trim(substr($_GET['q'],0,150)):'';
$where='';$params=[];
if($search!==''){
    $where=' WHERE instr(lower(email),lower(?))>0 OR instr(lower(first_name||" "||last_name),lower(?))>0 OR instr(phone,?)>0 OR instr(lower(company),lower(?))>0';
    $params=[$search,$search,$search,$search];
}
$total=(int)auth_query('SELECT COUNT(*) FROM users'.$where,$params)->fetchColumn();
$page=min($page,max(1,(int)ceil($total/$perPage)));
$accounts=auth_query("SELECT id,email,first_name,last_name,company,ico,dic,phone,address,verified,approval_status,created_at FROM users".$where." ORDER BY created_at DESC,id DESC LIMIT ? OFFSET ?",array_merge($params,[$perPage,($page-1)*$perPage]))->fetchAll(PDO::FETCH_ASSOC);
$statusLabels=['pending'=>'⏸️ Pozastaveno','approved'=>'✅ Povoleno','rejected'=>'⛔ Zamítnuto'];
?>
<form method="get" action="/admin.php" class="admin-search"><label for="admin-search">Hledat podle jména, e-mailu, telefonu nebo firmy</label><div><input id="admin-search" name="q" value="<?= h($search) ?>" maxlength="150" type="search"><button class="primary" type="submit">🔎 Hledat</button><a href="/admin.php">Zrušit filtr</a></div></form>
<p>Počet účtů<?= $search!==''?' ve výběru':'' ?>: <strong><?= $total ?></strong> · Strana <?= $page ?> / <?= max(1,(int)ceil($total/$perPage)) ?></p>
<div class="admin-table-wrap" role="region" aria-label="Přehled uživatelů" tabindex="0">
<table class="admin-table"><caption>Registrovaní uživatelé · údaje jsou přístupné pouze správci</caption><thead><tr><th scope="col">Uživatel</th><th scope="col">Kontakt a firma</th><th scope="col">E-mail</th><th scope="col">Přístup</th><th scope="col">Registrace</th><th scope="col">Správa účtu</th></tr></thead><tbody>
<?php foreach($accounts as $account): $isAdmin=strtolower($account['email'])===AUTH_APPROVER_EMAIL; ?>
<tr><th scope="row"><?= h($account['first_name'].' '.$account['last_name']) ?><?= $isAdmin?' · Správce':'' ?><small>ID <?= (int)$account['id'] ?></small></th>
<td><a href="mailto:<?= h($account['email']) ?>"><?= h($account['email']) ?></a><br><?= h($account['phone'] ?: 'Telefon neuveden') ?><br><?= h($account['company']) ?><details><summary>Další údaje</summary><dl><?php foreach(['ico'=>'IČ','dic'=>'DIČ','address'=>'Adresa'] as $key=>$label): ?><dt><?= h($label) ?></dt><dd><?= h($account[$key] ?: '—') ?></dd><?php endforeach; ?></dl></details></td>
<td><?= $account['verified']?'✅ Ověřený':'✉️ Nepotvrzený' ?></td>
<td><?= $isAdmin?'🛡️ Správce':h($statusLabels[$account['approval_status']]??'Neznámý stav') ?><?php if(!$account['verified']): ?><small>Přihlášení vyžaduje potvrzení e-mailu.</small><?php endif; ?></td>
<td><?= h(gmdate('d. m. Y H:i',(int)$account['created_at'])) ?><small>UTC</small></td>
<td><?php if(!$isAdmin): ?><form method="post" action="/admin.php"><?php csrf_fields('approval'); ?><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><div class="admin-actions"><button name="decision" value="approved" type="submit">✅ Povolit</button><button name="decision" value="pending" type="submit">⏸️ Pozastavit</button><button name="decision" value="rejected" type="submit">⛔ Zamítnout</button></div></form>
<details class="delete-account"><summary>🗑️ Smazat účet</summary><p><strong>Nevratně smažete účet <?= h($account['email']) ?>.</strong> Odstraní se profil, ověřovací odkazy a historie změn přístupu. Předchozí přihlášení přestanou fungovat. Exporty a plány uložené v prohlížeči uživatele ani zálohy hostingu se tím nevymažou.</p><form method="post" action="/admin.php"><?php csrf_fields('delete-account'); ?><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><label class="field"><span>Pro potvrzení opište e-mail tohoto účtu</span><input type="email" name="confirm_email" required autocomplete="off" maxlength="254" placeholder="<?= h($account['email']) ?>"></label><label class="check"><input type="checkbox" name="confirm_delete" value="yes" required><span>Potvrzuji trvalé a nevratné smazání tohoto účtu.</span></label><button class="danger" type="submit">Trvale smazat účet</button></form></details>
<?php else: ?>Správcovský účet je chráněn před smazáním.<?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if(!$accounts): ?><tr><td colspan="6">Žádný účet neodpovídá zadanému hledání.</td></tr><?php endif; ?>
</tbody></table></div>
<nav class="form-links" aria-label="Stránkování uživatelů"><?php if($page>1): ?><a href="/admin.php?page=<?= $page-1 ?>&amp;q=<?= rawurlencode($search) ?>">← Předchozí</a><?php endif; ?> <?php if($page*$perPage<$total): ?><a href="/admin.php?page=<?= $page+1 ?>&amp;q=<?= rawurlencode($search) ?>">Další →</a><?php endif; ?></nav>
<?php elseif($view==='privacy'): ?>
<div class="privacy-copy"><p><strong>Správce osobních údajů:</strong> Jiří Janoušek, provozovatel aplikace Moje krypto. Kontakt pro uplatnění práv: <a href="mailto:info@jirijanousek.cz">info@jirijanousek.cz</a>.</p>
<h3>Jaké údaje a proč</h3><p>Pro registraci, přihlášení, správu profilu a bezpečnost účtu zpracováváme jméno, příjmení, e-mail, telefon, případně firmu, adresu, IČ a DIČ. Hesla uchováváme výhradně jako jednosměrné hashe; neposíláme je e-mailem. Údaje slouží pro provoz účtu, obnovu přístupu a nezbytná oznámení, nikoli automaticky pro marketing.</p>
<h3>Úložiště a nezbytné cookies</h3><p>Údaje účtů jsou uloženy mimo veřejný web na hostingu provozovatele. Pro přihlášení je nezbytná zabezpečená relační cookie a ochranný token formuláře; bez nich zabezpečený účet nefunguje. Zpracování vychází z poskytování požadované služby a oprávněného zájmu na zabezpečení.</p>
<h3>Doba uchování a Vaše práva</h3><p>Údaje vedeme po dobu existence účtu a případně déle v rozsahu právních povinností. Můžete požádat o přístup, opravu, výmaz, omezení zpracování či uplatnit další práva podle GDPR. Před vyřízením ověříme totožnost; stížnost lze podat Úřadu pro ochranu osobních údajů. Hosting a doručovatel e-mailů mohou údaje technicky zpracovávat v rozsahu potřebném pro provoz.</p>
<p>Investiční modely se počítají v prohlížeči, ale záznamy portfolia a transakcí se ukládají na server pod přihlášeným účtem. Používáme údaje z kryptoměnových trhů; budoucí výnos není zaručen.</p>
<p><a href="https://jirijanousek.cz/ochrana-osobnich-udaju/" target="_blank" rel="noopener noreferrer">Další informace o ochraně osobních údajů</a>.</p><p><strong>Dobrovolná podpora projektu:</strong> Pokud zadáte e-mail na stránce podpory a požádáte o poděkování, e-mail, případné jméno, částka a variabilní symbol se uloží do soukromé databáze společného platebního systému provozovatele aplikací (na doméně muj-dip.jirijanousek.cz). Použijí se pro spárování bankovní platby a jednorázové potvrzení. Údaje z formuláře se nezveřejňují a nepoužívají pro marketing. Anonymní QR platba nevyžaduje zadání e-mailu.</p></div>
<p class="form-links"><a href="/account.php?view=login">Přihlášení</a> · <a href="/account.php?view=register">Registrace</a></p>
<?php endif; ?>
<div class="footer-support"><a class="footer-support-button" href="/support.html"><span aria-hidden="true">❤️</span> Podpořte projekt Moje krypto</a><p>Dobrovolný příspěvek na provoz a rozvoj aplikace.</p></div>
<nav class="footer-legal" aria-label="Právní informace"><a href="/account.php?view=privacy">Ochrana osobních údajů</a><a href="https://jirijanousek.cz/" target="_blank" rel="noopener noreferrer">jirijanousek.cz ↗</a></nav>
<p class="account-footer">© <?= date('Y') ?> Jiří Janoušek · Moje krypto</p></section></main></body></html>


