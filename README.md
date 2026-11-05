# ₿ Moje krypto – Bitcoin a Ethereum

Osobní webová aplikace Jiřího Janouška pro BTC/ETH. PHP 8.1+ na Webkitty, vlastní administrátorské přihlášení (bez registrace), tmavý/zlatý responzivní design.

### Funkce
- 📊 Dashboard: online kurzy BTC/ETH v CZK, změna za 24 hodin, hodnoty držených aktiv, donut graf a roční příjmy.
- 📈 Interaktivní historické grafy 1D, 7D, 1M, 3M, 6M, 1Y, 5Y, 15Y; dostupnost 15leté historie závisí na datovém zdroji.
- 💹 Kumulativní výnosy, CAGR, vlastní scénář zhodnocení, inflace a kupní síla.
- 🔁 Historická simulace DCA a Buy the Dip s nastavitelnými vklady, nákupním prahem a průměrnou cenou.
- 🔮 Budoucí modely investic s měnitelnou inflací a ročním výnosem (včetně ilustrativních 112 %).
- 💸 Evidence převodů, úpravy, filtry, export CSV a soukromá JSON záloha i import.
- 🔒 Jediný administrátor, heslo hashované na serveru, session cookies a CSRF ochrana. Bez externích fontů a bez publikovaných osobních dat.

### Nasazení na Webkitty
**Vložení do GitHubu samo o sobě nenasazuje web na doménu.** Postup krok za krokem je v [DEPLOYMENT.md](DEPLOYMENT.md). Web je určen pro `https://moje-krypto.jirijanousek.cz/`.

### Kurzy a výnosy
Tržní data jsou čerpána z CryptoCompare / CoinDesk Data. Starší historie a aktuální data mohou být dočasně omezená, proto aplikace při selhání zdroje nevymýšlí hodnoty. Převod do Trezoru není důkazem nákupu za zobrazenou cenu. Skutečný zisk se počítá až po zadání nákupních nákladů aktuálně drženého portfolia. Predikce jsou výhradně **scénáře**, nikoli investiční doporučení či garance.

### Soukromí
Repozitář je veřejný. **Do repozitáře nepatří hesla, instalační klíče, transakce ani privátní klíče peněženek.** Osobní importní soubor se nahrává až do zabezpečené aplikace. Nikdy nezadávej seed nebo recovery phrase.

### Automatická kontrola
GitHub Actions provádí PHP a JavaScript syntaktickou kontrolu při aktualizaci větve main.
