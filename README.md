# ₿ MOJE KRYPTO — Bitcoin s plánem

Moderní responzivní investiční aplikace Jiřího Janouška, která spojuje evidenci kryptoměn, skutečné tržní grafy, modelové scénáře, plán na bydlení a diverzifikaci.

## Funkce
- 📊 Přehled BTC/ETH v Kč, historické kurzy a orientační zhodnocení podle zadaných pořizovacích nákladů.
- 📈 Historické grafy v rozsahu 1D–15Y s upozorněním na neúplná nebo nedostupná data.
- 💸 Evidence transakcí s příjmy, odesláním, nákupem a prodejem; vlastní stav peněženek a pořizovací náklady.
- 🔁 Simulátory pravidelného nakupování (DCA), Buy the Dip a budoucích scénářů.
- 🏡 Model „Bitcoin a moje nemovitost“: vývoj BTC, vklady, cena nemovitosti, finanční pokrytí cíle.
- 🧩 Interaktivní cílové rozložení Bitcoin / Ethereum / zlato / akcie / hotovost.
- 📗 Import XLSX/CSV s mapováním sloupců, export vícelistého XLSX, JSON a CSV, PDF přes tisk.
- 🔐 Registrace, e-mailové ověření do 24 h, obnovovací odkaz do 30 min, oznámení o registraci a administrace účtů. Vychází z Moje renta / Můj DIP.
- 🎨 Tmavý prémiový design, barevná grafika, responzivní menu pro telefony.

## Přihlášení a ochrana dat
Každá aplikace má **vlastní účty a vlastní databázi**. Existující hesla z Můj DIP nebo Moje renta se nepřenášejí. Správce používá ověřenou e-mailovou adresu vedenou v konfiguraci; hesla se nikdy nevkládají do GitHubu. Každý účet má oddělené portfolio i model bydlení. Přístup nového uživatele se aktivuje automaticky po ověření e-mailu, správce jej může později omezit nebo odebrat.

Web vyžaduje PHP 8.1+, PDO SQLite, správně nakonfigurované PHP mail(), HTTPS a Apache s .htaccess. Osobní data jsou na hostingu mimo veřejný kořen webu.

## Důležité
Tržní data dodává CryptoCompare / CoinDesk Data a mohou být zpožděná či nedostupná. Nemovitost, DCA, růst BTC a diverzifikace jsou **modely**, nikoli předpovědi, záruky či personalizovaná investiční doporučení. Převody na vlastní peněženku nejsou automaticky nové nákupy ani zisk. Aplikace neodesílá žádné pokyny k obchodování.

**GitHub neznamená automatické nasazení na Webkitty.** Podrobný postup je v [DEPLOYMENT.md](DEPLOYMENT.md).
