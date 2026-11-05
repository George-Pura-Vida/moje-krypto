# Nasazení Moje krypto na Webkitty

## 1) Existující infrastruktura
- GitHub: `George-Pura-Vida/moje-krypto`, větev `main`.
- Doména: `https://moje-krypto.jirijanousek.cz/`
- Ve Webkitty je již vytvořena složka domény `moje-krypto` (dle snímku od vlastníka).

**Pozor:** adresář ve Webkitty není automaticky propojen s GitHubem. Bez nahrání aktuálních souborů nemůže web zobrazovat tuto aplikaci. Na hostingu je nutný PHP 8.1+ (doporučeno 8.2+).

## 2) Nahrát kód na server
Přes správce souborů Webkitty, SFTP nebo Git:
1. Nahraj soubory `index.php`, `style.css`, `charts.js`, `models.js`, `app.js`, `favicon.svg` a `.htaccess` přímo do kořenového adresáře domény `moje-krypto`.
2. Nahraj `storage/.htaccess` do podsložky `storage`.
3. V kořenovém adresáři musí být `index.php` – ne ve vnořené složce `moje-krypto/moje-krypto`. Pokud existuje starý výchozí `index.html`, odstraň nebo přejmenuj jej; na Apache přepíná `.htaccess` prioritu na `index.php`.
4. Ve Webkitty nastav dokumentový kořen přesně na složku s `index.php` (již vytvořená složka se musí použít správně).
5. Ověř, že na doméně běží PHP a platný certifikát HTTPS.

Pro pravidelné aktualizace můžeš ve Webkitty propojit Git, pokud to panel podporuje. **Nastavení synchronizace jsem na tvém Webkitty účtu nemohla provést.**

## 3) Zabezpečení: první administrátor
V kořenovém adresáři na serveru vytvoř `config.local.php` s obsahem:

```php
<?php
return [
    'setup_token' => 'SEM_NAPIŠ_NOVÝ_TAJNÝ_KLÍČ_ALESPOŇ_20_ZNAKŮ',
];
```

Řetězec `SEM_NAPIŠ_NOVÝ...` **nahraď skutečným, náhodně vytvořeným klíčem**, nejlépe 32–64 znaky. Nikdy tento soubor nenahrávej do veřejného GitHubu. V souboru `config.example.php` je klíč záměrně prázdný.

Po prvním otevření domény zadáš tento klíč, vlastní e-mail a heslo s minimálně 12 znaky. Vytvoří se jediný účet. Registrace dalších osob není možná.

**Nepoužívej heslo, které už bylo někde zveřejněno nebo posláno v chatu.** Použij unikátní, dlouhé heslo.

## 4) Data, ukládání, import
- Vytvoř podsložku `storage` a povol její zápis uživateli PHP. Bez toho první instalace a ukládání selžou.
- Apache: pravidla `storage/.htaccess` blokují přímý přístup.
- Nginx: nastav samostatné blokovací pravidlo přístupu ke `storage`, protože Nginx `.htaccess` nepoužívá.
- Veřejný přístup ke zdrojovému kódu PHP se nesmí zobrazovat jako text.
- Server uchovává soukromá data v `storage/data.php` a hash hesla v `storage/admin.php` (obsah mají PHP ochrannou hlavičku).
- V aplikaci otevři **Nastavení a data → Import JSON nebo CSV** a nahraj soukromý importní JSON z chatu. Import přepíše dosavadní evidenci. 
- Aktuální množství BTC/ETH z výpisu je uvedeno v soukromém importu. Nákupní náklady nejsou známé, proto zisk aplikace zatím neukáže.

## 5) Online kurzy
Zdroj: `https://min-api.cryptocompare.com`; API volá výhradně serverové PHP (neprochází do něj privátní evidence).
- `?api=prices` načítá aktuální BTC/ETH v CZK (cache 90 sekund).
- `?api=history&coin=BTC&period=15Y` načítá historickou řadu BTC. K dispozici jsou rozsahy `1D, 7D, 1M, 3M, 6M, 1Y, 5Y, 15Y`.
- 15letá řada se skládá ze 3 historických bloků. Dostupnost delších dat může být omezená tarifem nebo limitech API. Aplikace tento výpadek označí.
- Ceny v CZK jsou od externího agregátoru, nemusí souhlasit s Trezorem ani nákupním kurzem burzy.

## 6) Kontrola funkčnosti po nasazení
1. Při prvním otevření uvidíš uzamčenou přihlašovací stránku.
2. Vytvoř administrátora a ověř odhlášení / opětovné přihlášení.
3. Importuj JSON, ověř přijaté převody za roky 2025 a 2026.
4. Zkontroluj aktuální ceny BTC a ETH a přepínání grafů 1D / 1Y / 5Y / 15Y.
5. Ověř křivky inflace, vlastní výnos a DCA kalkulaci, včetně hranice pro Buy the Dip.
6. Ulož nové portfolio, obnov stránku a zkontroluj, že hodnoty přetrvaly.
7. Proveď export JSON i CSV a test importu, ověř mobilní menu.
8. Ověř ochranu `/storage/data.php` před veřejným přístupem.

## Omezení
- Není propojena skutečná Trezor peněženka ani burza. Žádné obchodní příkazy se nezadávají.
- DCA a 112% scénáře nejsou investičním doporučením.
- Neposkytuje daňové výpočty a nezohledňuje poplatky ani spread.
- Bez explicitní synchronizace GitHub → Webkitty není možné tvrdit, že doména již běží na této nové verzi.
