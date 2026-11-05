# Nasazení Moje Krypto Pro – Webkitty

## 1. Co musí hosting splnit
- Doména: https://moje-krypto.jirijanousek.cz/
- PHP 8.1 nebo novější, PDO SQLite, PHP session, HTTPS, Apache s aktivním .htaccess.
- Odesílání procesních e-mailů (PHP mail()); ověřit doručení, SPF/DKIM a odesílatele info@jirijanousek.cz.
- Práva k zápisu do soukromého adresáře **mimo document root**.

## 2. Nasazení
Synchronizujte aktuální větev main na Webkitty do kořene domény s index.php. Všechny soubory musí být na serveru, včetně:
- index.php, style.css, charts.js, models.js, app.js, premium.js, premium.css, premium.view.php, favicon.svg;
- account.php, account.css, account-ui.css, account.js, admin.php, celý adresář auth/;
- souborů .htaccess a storage/.htaccess.

Nepublikujte přes GitHub Pages – ten neumí vykonávat PHP. V souběhu s index.php nedržte výchozí index.html. .htaccess nastavuje index.php jako vstupní bod.

## 3. Účty a zabezpečení
- Při prvním přístupu přesměruje index.php na /account.php?view=login.
- Vytvořte účet na /account.php?view=register, potvrďte adresu odkazem do 24 h. Účet se aktivuje po ověření automaticky.
- Správcem je pouze ověřený účet jgjanousek@gmail.com; správa: /admin.php.
- Nepřenášejte existující hesla ani starý administrátorský hash. Nastavte nové heslo pouze přes přihlašovací formulář.
- Soukromá databáze a účty: var/moje-krypto-accounts/ **nad kořenem veřejného webu**. Soubory portfolií a plánů jsou oddělené podle ID přihlášeného uživatele.
- Pokud existuje starší storage/data.php z původního single-admin provozu, po přihlášení ověřeného správce se jeho kopie jednorázově převede do nového soukromého úložiště. Zálohujte stará data před migrací.
- Nemějte v repozitáři soukromé transakce, hesla, seed fráze, databáze ani přístupové tokeny.

Dřívější setup_token v config.example.php se již **nepoužívá**. Není nutné vytvářet config.local.php.

## 4. Tržní data a výpočty
Tržní API volá server: ?api=prices (BTC/ETH CZK), ?api=history (historie). Při nedostupnosti API aplikace nesmí vykreslovat vymyšlené ceny. V modelu bydlení lze ručně zadat ilustrační cenu BTC. Modelové nákupy, budoucí ceny nemovitosti a podíly portfolia se ukládají přes ?api=goals.

## 5. Kontroly před komerčním provozem
1. PHP a JavaScript syntaktické testy.
2. Ověřit registraci, 24h e-mail, login, reset hesla, odhlášení a zamítnutí neplatných tokenů.
3. Ověřit dva oddělené uživatelské účty, že se nemísí transakce, portfolia a cíle.
4. Projít záznamy BTC/ETH, online a historické grafy, DCA, Buy the Dip, scénáře a model bydlení.
5. Vyzkoušet import skutečného XLSX/CSV a následný export XLSX v Excelu a PDF na počítači i mobilu.
6. Ověřit, že /auth/, /storage/, config.local.php a soukromé databáze nejsou dostupné bez autorizace.
7. Otestovat smazání účtu v administraci a odstranění jeho soukromých dat.
8. Ověřit doručování e-mailů a zálohování.

## Omezení
- Samotný commit na GitHubu nenasazuje změny na Webkitty, pokud není synchronizace nakonfigurovaná.
- Žádné připojení na Trezor/burzu ani obchodní příkazy.
- Excel import má mapování a podporuje XLSX/CSV, nikoliv starší binární XLS.
- PDF vzniká přes systémový tiskový dialog.
- Tržní i modelové výnosy jsou orientační, ne zaručené.
