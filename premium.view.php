<?php
declare(strict_types=1);
if (!defined('MOJE_KRYPTO_AUTH_VIEW')) { http_response_code(403); exit('Přístup odepřen.'); }
?>
<section id="page-property" class="page">
 <div class="pro-intro pro-property"><div><div class="eyebrow">🏡 BTC DREAM BUILDER / MOJE NEMOVITOST</div><h2>Kolik Bitcoinu stojí moje budoucí bydlení?</h2><p>Propojte své skutečné BTC s modelovou cenou nemovitosti. Měňte cenu Bitcoinu, délku investice i pravidelné nákupy.</p></div><div class="pro-hero-icon" aria-hidden="true">🏠<span>₿</span></div></div>
 <div class="pro-layout">
 <article class="panel pro-form"><h2>🎯 Nastavte si vlastní scénář</h2><div class="pro-fields">
 <label>Cena nemovitosti dnes (Kč)<input id="pro-home-price" type="number" min="100000" max="100000000000" step="100000" value="8000000"></label>
 <label>Horizont (roky)<input id="pro-years" type="number" min="1" max="40" step="1" value="10"></label>
 <label>Roční růst ceny nemovitosti (%)<input id="pro-home-inflation" type="number" min="-20" max="30" step=".5" value="3"></label>
 <label>Modelová roční změna BTC (%)<input id="pro-btc-growth" type="number" min="-80" max="100" step="1" value="10"></label>
 <label>Nové nákupy Bitcoinu (Kč/měs.)<input id="pro-monthly" type="number" min="0" max="10000000" step="500" value="5000"></label>
 <label>Vlastní modelová cena BTC (Kč)<input id="pro-manual-price" type="number" min="0" max="100000000000" step="1000" value="0" placeholder="0 = kurz z trhu"></label></div>
 <div class="pro-scenario-buttons"><button type="button" data-pro-rate="-20">🐻 −20 %</button><button type="button" data-pro-rate="0">⚖️ 0 %</button><button type="button" data-pro-rate="10">🌱 +10 %</button><button type="button" data-pro-rate="25">🚀 +25 %</button></div>
 <div class="pro-inline-note" id="pro-price-source">🟠 Načítám kurz BTC…</div></article>
 <article class="panel pro-result"><span class="eyebrow">MODEL VAŠEHO BUDOUCÍHO DOMOVA</span><div class="pro-giant" id="pro-result-home">—</div><p>Modelová cena nemovitosti za zvolenou dobu</p><div class="pro-progress"><span id="pro-progress-fill"></span></div>
 <div class="pro-result-grid"><div><small>Hodnota Vašeho BTC</small><b id="pro-btc-value">—</b></div><div><small>Pokrytí ceny bydlení</small><b id="pro-covered">—</b></div><div><small>Kolik BTC by bylo potřeba</small><b id="pro-needed">—</b></div><div><small>Zbývá k financování</small><b id="pro-gap">—</b></div></div>
 <div class="pro-callout">✨ Bitcoin může být součástí plánu na bydlení. Jeho cena ale může výrazně klesnout, proto porovnejte i nepříznivé scénáře.</div></article></div>
 <div class="panel"><h2>📈 Vaše cesta k bydlení</h2><div id="pro-housing-chart" class="pro-bars" role="img" aria-label="Porovnání modelové hodnoty BTC a ceny nemovitosti"></div><p class="pro-caption">Výpočet předpokládá konstantní procentní změnu ceny BTC a nemovitosti a měsíční nákupy podle modelové ceny. Nejde o predikci. Nezahrnuje daně, poplatky, hypotéku ani kurzové spready.</p></div>
</section>
<section id="page-diversify" class="page">
 <div class="pro-intro pro-diversify"><div><div class="eyebrow">🧩 PORTFOLIO COMPASS / DIVERZIFIKACE</div><h2>Váš majetek. Vaše pravidla.</h2><p>Vytvořte cílové rozložení prostředků mezi kryptoměny, zlato, akcie a hotovost. Výsledek měníte v reálném čase.</p></div><div class="pro-hero-icon" aria-hidden="true">🧩</div></div>
 <div class="pro-layout">
 <article class="panel"><h2>⚙️ Cílové složení</h2><div id="pro-alloc-inputs" class="pro-alloc-inputs"></div><p id="pro-alloc-total" class="pro-caption"></p><div class="pro-scenario-buttons"><button type="button" id="pro-balanced">⚖️ Rovnoměrně</button><button type="button" id="pro-conservative">🛡️ Příklad opatrnějšího rozložení</button><button type="button" id="pro-aggressive">🚀 Příklad vyšší koncentrace BTC</button></div></article>
 <article class="panel pro-alloc-display"><h2>📊 Cílové portfolio</h2><div class="pro-pie" id="pro-pie"><div><small>VAŠE PORTFOLIO</small><b id="pro-pie-total">100 %</b></div></div><div id="pro-alloc-legend" class="pro-alloc-legend"></div></article></div>
 <article class="panel pro-risk"><h2>🛡️ Co pro Vás rozložení znamená?</h2><p id="pro-risk-text">Diverzifikace může snížit koncentraci do jednoho aktiva, ale nezaručuje ochranu proti ztrátám.</p><p class="pro-caption">Jde o ilustrativní váhy, ne o doporučení konkrétních produktů nebo měření jejich korelací. Skutečné portfolio ve výchozí záložce není těmito vahami automaticky změněno.</p></article>
</section>
<section id="page-exports" class="page">
 <div class="pro-intro pro-exports"><div><div class="eyebrow">📤 DATA STUDIO / REPORTY</div><h2>Váš investiční příběh, profesionálně.</h2><p>Podklady pro analýzu, exporty pro konzultaci a přehledný import z Excelu.</p></div><div class="pro-hero-icon" aria-hidden="true">📊</div></div>
 <div class="pro-export-grid">
 <article class="panel pro-export-card"><div class="pro-export-icon p-green">📗</div><h2>Import Excelu</h2><p>Načtěte XLSX nebo CSV. Sloupce pohodlně přiřadíte v průvodci. Import nenastavuje automaticky stav peněženky.</p><label class="btn gold pro-file">📥 Vybrat Excel / CSV<input id="pro-import-file" type="file" accept=".xlsx,.csv" hidden></label></article>
 <article class="panel pro-export-card"><div class="pro-export-icon p-blue">📘</div><h2>Excel report</h2><p>Transakce, portfolio, model bydlení a cílové rozložení přehledně na několika listech.</p><button type="button" class="btn" id="pro-export-xlsx">📤 Stáhnout .xlsx</button></article>
 <article class="panel pro-export-card"><div class="pro-export-icon p-orange">📄</div><h2>PDF report</h2><p>Profesionálně upravený report s výsledky a scénáři. V tiskovém dialogu vyberte Uložit jako PDF.</p><button type="button" class="btn" id="pro-export-pdf">📄 Připravit PDF report</button></article>
 <article class="panel pro-export-card"><div class="pro-export-icon p-violet">🧰</div><h2>Další formáty</h2><p>JSON záloha, import a export CSV i stávající nastavení z původní aplikace zůstávají dostupné.</p><button type="button" class="btn" id="pro-open-settings">⚙️ Nastavení & data</button></article></div>
 <article class="panel" id="pro-mapping" hidden><h2>🔎 Průvodce importem</h2><p class="pro-caption" id="pro-mapping-help"></p><div id="pro-mapping-controls" class="pro-fields"></div><div id="pro-import-preview" class="pro-import-preview"></div><div class="pro-scenario-buttons"><button type="button" class="btn gold" id="pro-import-confirm">✅ Převzít záznamy</button><button type="button" class="btn" id="pro-import-cancel">Zrušit</button></div></article>
 <div class="pro-inline-note">🔐 Výpisy obsahují Vaše finanční údaje. Nikdy neimportujte seed fráze, soukromé klíče ani hesla k burzám.</div>
</section>
