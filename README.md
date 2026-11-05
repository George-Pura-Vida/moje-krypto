# Moje Krypto — profesionální sjednocená verze
Základem je aktuální analytická aplikace z větve main (BTC a ETH, online kurzy, časové grafy, DCA, Buy the Dip, inflace, PDF/Excel/CSV a importní nástroje). Přidává registraci a e-mailové šablony podle Moje renta, správu uživatelů a soukromou evidenci pro každý účet.

## Co se zachovalo
- Online BTC/ETH v CZK a historické grafy (podle dostupnosti poskytovatele)
- Grafy, výpočty, modely, exporty a importní dialogy z aktuálního main
- Moderní tmavý/zlatý design a responzivní rozhraní
- Serverové PHP rozhraní a datové validace

## Nový přístup
- Účet, registrace, potvrzení e-mailu do 24 hodin, obnovení hesla do 30 minut
- Administrátor: jgjanousek@gmail.com; heslo není uloženo v GitHubu a nelze je převzít z jiného webu
- Procesní e-maily a oznámení podle Moje renta, odesílání vyžaduje funkční PHP mail() a DNS SPF/DKIM
- Data oddělena podle ID uživatele v soukromém adresáři mimo document root
- Starý soubor storage/data.php se při prvním přihlášení administrátora zkopíruje do jeho soukromého úložiště; originál zůstane nedotčen
- Pozor: verze zdrojového účtového systému aktivuje běžné účty po ověření e-mailu automaticky. Správce může účty následně zablokovat.
- Server potřebuje PHP 8.1+, PDO SQLite, HTTPS a možnost zápisu do soukromého adresáře.

## Nasazení
GitHub commit neznamená nasazení na Webkitty. Přeneste všechny soubory z main včetně podsložky auth a aktualizovaného index.php na hosting; předem zálohujte data a konfiguraci. Testujte registraci, reset hesla, e-mailové doručení, import/export a dva oddělené účty. Nezveřejňujte hesla, přístupové tokeny ani soukromé klíče.

## Rizika
Při prvním přechodu je nutné založit účet správce v novém systému; stávající hash administrátora v původním storage/admin.php se automaticky nepřenáší. Pro administrátora je doporučeno před nasazením připravit bezpečný postup aktivace a zálohu. Investiční modely nejsou garancí výnosů.
