# Moje Krypto – účty a e-maily

Tato větev přidává přihlašování, registraci, ověření e-mailu a procesní zprávy podle **Moje renta**.
**Nekopíruje existující hesla ani databáze**. Stejný e-mail lze použít k nové registraci, ale účet má vlastní hash hesla, soukromou session a úložiště.

## Provoz
- PHP 8.1+; rozšíření PDO SQLite; PHP mail() pro procesní zprávy; Apache s .htaccess; HTTPS.
- Doména očekávaná v auth/bootstrap.php: https://moje-krypto.jirijanousek.cz.
- Konkrétní DNS/FTP nasazení a doručení e-mailů na hostingu je nutné ověřit zvlášť.
- Databáze uživatelů a soubory portfolií jsou mimo veřejný document root: var/moje-krypto-accounts/.
- Admin je ověřený účet jgjanousek@gmail.com. Žádné výchozí heslo ani heslo v GitHubu.
- 24h jednorázová verifikace; 30min reset a změna e-mailu; upozornění adminovi, přístupové zprávy.
- Současná implementace, stejně jako nejnovější Moje renta, **automaticky povoluje přístup po ověření e-mailu**. Admin může účet následně pozastavit, zamítnout nebo odstranit.
- Procesní e-maily od info@jirijanousek.cz; zkontrolovat SPF, DKIM a reálné doručování.
- Původní data administrátora ze storage/data.php se migrují do privátního úložiště po prvním úspěšném přihlášení; soubor se automaticky nemaže.
- Každý nově registrovaný uživatel má soukromou evidenci (oddělená data).
- Pro produkci zkontrolujte cookie nastavení a serverový DocumentRoot. Žádné přístupové údaje do repozitáře.

## Testovací scénáře
1. Nepřihlášené GET / → přesměrování na /account.php?view=login.
2. Registrace → doručení potvrzovacího e-mailu, do 24 h jednorázově aktivovat.
3. Login, logout, profil, reset hesla, invalidní token a ochrana CSRF.
4. Dva oddělené účty: GET/POST ?api=data pracují s rozdílnými soubory.
5. Test výpadku zdroje cen: osobní evidence se nesmí smazat.
6. Ověřit že /auth/, /storage/, /config.local.php jsou nepřístupné z webu.
7. Admin účet: seznam, blokace a smazání účtu, ukončení sessions.

Modely Bitcoinu nejsou investiční doporučení a nenabízejí zaručený výnos.
