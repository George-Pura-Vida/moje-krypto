<?php
declare(strict_types=1);
/** Public terms, privacy and cookie information; does not access account records. */
$allowed=['terms'=>'Podmínky používání','legal'=>'Právní upozornění','privacy'=>'Ochrana osobních údajů (GDPR)','cookies'=>'Cookies a místní úložiště'];
$view=(string)($_GET['view']??'terms');
if(!isset($allowed[$view])){http_response_code(404);$view='terms';}
$h=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($allowed[$view]) ?> · Moje krypto</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/privacy.css?v=2">
<script type="module" src="/privacy.js?v=2"></script></head>
<body class="legal-background">
<main class="legal-page">
<p><a href="/">← Zpět do Moje krypto</a></p>
<h1><?= $h($allowed[$view]) ?></h1>
<p class="legal-version">Jiří Janoušek · Informace k webové aplikaci Moje krypto</p>
<?php if($view==='terms'): ?>
<h2>Poskytovaná služba</h2>
<p>Aplikace Moje krypto slouží k osobnímu používání, přehledům a modelování. Při používání dodržujte právní předpisy a nezasahujte do bezpečnosti, účtů či dat jiných osob.</p>
<p>Dostupnost jednotlivých funkcí se může měnit. Provozovatel se snaží o správnost, nemůže však zaručit nepřetržitý provoz ani dosažení investičního výsledku. Příspěvek na podporu je dobrovolný a neaktivuje další oprávnění.</p>
<?php elseif($view==='legal'): ?>
<h2>Informativní obsah, nikoli investiční rada</h2>
<p>Výpočty, statistiky, hodnocení portfolia a scénáře jsou pouze orientační. Nejde o individualizované investiční doporučení ani daňové, účetní či právní poradenství. Výsledky nemusí zahrnovat poplatky, daně, měnová rizika, inflaci a jiné náklady. Minulá výkonnost nezaručuje budoucí výnos. Investice mohou způsobit ztrátu kapitálu.</p>
<p>Před rozhodnutím ověřte údaje z nezávislých zdrojů a příslušné smluvní a zákonné podmínky.</p>
<?php elseif($view==='privacy'): ?>
<h2>Správce osobních údajů</h2>
<p>Jiří Janoušek. Kontakt: <a href="mailto:info@jirijanousek.cz">info@jirijanousek.cz</a>.</p>
<h2>Co se zpracovává a proč</h2>
<p>U přihlašovaných účtů jde zejména o kontaktní údaje, zabezpečené přihlašovací údaje (nikoli čitelné heslo), identifikátory relací a technické bezpečnostní údaje. Používají se pro provoz služby, bezpečnost a vyřizování požadavků uživatelů.</p>
<p>Záznamy BTC a ETH a historie převodů jsou uložené pod přihlášeným účtem na serveru. V prohlížeči se ukládá volba grafického motivu (`moje-krypto-appearance`).</p>
<h2>Dobrovolná finanční podpora</h2><p>Pokud na stránce podpory vyplníte e-mail a požádáte o poděkování, e-mail, případné jméno, částka a jedinečný variabilní symbol se předají zabezpečenému serverovému systému Můj DIP na stejného provozovatele. Tam se spojí s potvrzenou příchozí platbou z Fio banky a odešle se jednorázové poděkování. Tyto údaje nepoužíváme pro marketing. Anonymní platba nevyžaduje e-mail.</p>
<h2>Doba uchování a práva</h2>
<p>Účetní údaje se běžně uchovávají po dobu existence účtu a dále jen v nezbytném rozsahu podle právních povinností. Bankovní a související záznamy mohou podléhat zákonným dobám uchování. O přístup, opravu, výmaz nebo omezení zpracování lze požádat na uvedeném kontaktu. Stížnost lze podat u Úřadu pro ochranu osobních údajů.</p>
<?php else: ?>
<h2>Pouze nezbytné technologie</h2>
<p>Web nepoužívá reklamní ani analytické cookies. K bezpečnému přihlášení potřebuje relační cookie a ochranu proti podvržení formulářů. Jejich blokování může zabránit přihlášení. Informační lišta slouží k informování, nikoliv ke sběru marketingového souhlasu.</p>
<div class="legal-table"><table><thead><tr><th>Technologie</th><th>Účel</th><th>Uložení</th></tr></thead><tbody>
<tr><td><code>__Host-mojekrypto</code></td><td>Přihlášení a ochrana přístupu (Secure, HttpOnly, SameSite=Lax)</td><td>Relační cookie; serverová relace se uzavírá po nečinnosti nebo odhlášení</td></tr>
<tr><td><code>moje-krypto-cookie-notice-v1</code> (localStorage)</td><td>Zapamatování zavření informační lišty</td><td>180 dní; obnovení lze provést v patičce</td></tr>
<tr><td><code>moje-krypto-appearance</code> (localStorage)</td><td>Zvolený vzhled aplikace</td><td>Do vymazání dat webu nebo změny nastavení</td></tr>
</tbody></table></div>
<h2>Jak změnit nastavení</h2>
<p>Pouze nezbytné cookies nelze v aplikaci vypnout bez narušení bezpečného přihlášení. Záznam informační lišty lze smazat v úložišti prohlížeče. Nastavení otevřete také z patičky.</p>
<button type="button" data-cookie-settings>Nastavení cookies</button>
<?php endif; ?>
<footer class="site-common-footer"><div class="site-footer-top"><strong>Moje krypto · Jiří Janoušek</strong><a class="site-footer-support" href="/support.html">💛 Podpořte projekt Moje krypto</a></div>
<nav class="footer-legal" aria-label="Právní informace">
<a href="/legal.php?view=terms">Podmínky používání</a><a href="/legal.php?view=legal">Právní upozornění</a><a href="/legal.php?view=privacy">Ochrana údajů / GDPR</a><a href="/legal.php?view=cookies">Cookies</a><button type="button" data-cookie-settings>Nastavení cookies</button><a href="https://jirijanousek.cz/" target="_blank" rel="noopener noreferrer">jirijanousek.cz ↗</a></nav>
<p>© <?= date('Y') ?> Jiří Janoušek · Moje krypto</p></footer>
</main></body></html>