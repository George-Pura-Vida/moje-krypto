<?php
declare(strict_types=1);
// This directory is denied by Apache; public entry points explicitly include this file.
const AUTH_ORIGIN = 'https://moje-krypto.jirijanousek.cz';
const AUTH_APPROVER_EMAIL = 'jgjanousek@gmail.com';
const AUTH_IDLE = 1800;
const AUTH_ABSOLUTE = 43200;
require_once __DIR__.'/mail.php';

function auth_test(): bool {
    return PHP_SAPI === 'cli-server' && getenv('MOJEKRYPTO_TEST_MODE') === '1'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}
function auth_dir(): string {
    // Outside dist/, which is the document root. Never store credentials in Git.
    $dir = dirname(__DIR__, 2) . '/var/moje-krypto-accounts';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Storage unavailable');
    return $dir;
}
function auth_db(): PDO {
    static $db;
    if ($db instanceof PDO) return $db;
    $db = new PDO('sqlite:' . auth_dir() . '/accounts.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON;');
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL, first_name TEXT NOT NULL, last_name TEXT NOT NULL,
        company TEXT NOT NULL DEFAULT "", ico TEXT NOT NULL DEFAULT "", dic TEXT NOT NULL DEFAULT "",
        phone TEXT NOT NULL, address TEXT NOT NULL, verified INTEGER NOT NULL DEFAULT 0,
        session_version INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL
    ); CREATE TABLE IF NOT EXISTS tokens (
        hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        purpose TEXT NOT NULL, value TEXT NOT NULL DEFAULT "", expires INTEGER NOT NULL
    ); CREATE TABLE IF NOT EXISTS limits (key TEXT PRIMARY KEY, count INTEGER NOT NULL, expires INTEGER NOT NULL);');
    // Preserve explicit administrator restrictions; remove the former manual waiting gate.
    $db->exec('BEGIN IMMEDIATE');
    try {
        $columns = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach (['approval_status'=>"TEXT NOT NULL DEFAULT 'pending'", 'approved_by'=>'INTEGER', 'approved_at'=>'INTEGER'] as $name=>$definition) {
            if (!in_array($name, $columns, true)) $db->exec('ALTER TABLE users ADD COLUMN '.$name.' '.$definition);
        }
        $db->exec('CREATE TABLE IF NOT EXISTS approval_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, actor_id INTEGER NOT NULL, user_id INTEGER NOT NULL, decision TEXT NOT NULL, created_at INTEGER NOT NULL)');
        $db->exec("UPDATE users SET approval_status='approved' WHERE approval_status='pending' AND approved_by IS NULL");
        $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    @chmod(auth_dir() . '/accounts.sqlite', 0600);
    return $db;
}
function auth_query(string $sql, array $params = []): PDOStatement {
    $q = auth_db()->prepare($sql); $q->execute($params); return $q;
}
function auth_headers(): void {
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache'); header('Vary: Cookie');
    header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY');
    // Native POST forms use an opaque Origin under no-referrer; keep the real
    // origin on this site while suppressing referrers to other sites.
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self' https://jirijanousek.cz");
}
function auth_boot(): void {
    auth_headers();
    ini_set('display_errors', '0'); ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
    session_name(auth_test() ? 'mojekrypto_test' : '__Host-mojekrypto');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !auth_test(), 'httponly' => true, 'samesite' => 'Lax']);
    if (!session_start()) throw new RuntimeException('Session unavailable');
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function auth_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $now = time();
    if ($now - ($_SESSION['seen'] ?? 0) > AUTH_IDLE || $now - ($_SESSION['started'] ?? 0) > AUTH_ABSOLUTE) {
        auth_logout(); return null;
    }
    $u = auth_query('SELECT * FROM users WHERE id=?', [$_SESSION['uid']])->fetch(PDO::FETCH_ASSOC);
    if (!$u || !$u['verified'] || !auth_approved($u) || (int)$u['session_version'] !== (int)($_SESSION['version'] ?? 0)) {
        auth_logout(); return null;
    }
    $_SESSION['seen'] = $now; return $u;
}
function auth_is_admin(array $u): bool {
    return (bool)$u['verified'] && strtolower($u['email']) === AUTH_APPROVER_EMAIL;
}
function auth_approved(array $u): bool {
    return auth_is_admin($u) || ($u['approval_status'] ?? 'pending') === 'approved';
}
function auth_login(array $u): void {
    session_regenerate_id(true);
    $_SESSION = ['uid' => (int)$u['id'], 'version' => (int)$u['session_version'],
        'started' => time(), 'seen' => time(), 'csrf' => bin2hex(random_bytes(32))];
}
function auth_logout(): void {
    $_SESSION = []; session_regenerate_id(true); $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function auth_redirect(string $view = 'login', string $message = ''): never {
    if ($message !== '') $_SESSION['flash'] = $message;
    header('Location: ' . ($view === 'approvals' ? '/admin.php' : '/account.php?view=' . rawurlencode($view)), true, 303); exit;
}
function auth_csrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); throw new DomainException('Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.');
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $expected = auth_test() ? 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') : AUTH_ORIGIN;
    if ($origin !== '' && $origin !== $expected) { http_response_code(403); throw new DomainException('Požadavek nelze ověřit.'); }
}
function auth_limit(string $scope, string $value, int $max, int $seconds): void {
    $key = hash('sha256', $scope . ':' . $value); $now = time(); $db = auth_db();
    auth_query('DELETE FROM limits WHERE expires < ?', [$now]);
    auth_query('INSERT INTO limits(key,count,expires) VALUES(?,1,?) ON CONFLICT(key) DO UPDATE SET count=count+1', [$key, $now+$seconds]);
    $row = auth_query('SELECT count FROM limits WHERE key=?', [$key])->fetch(PDO::FETCH_ASSOC);
    if ((int)$row['count'] > $max) { http_response_code(429); throw new DomainException('Příliš mnoho pokusů. Zkuste to později.'); }
}
function auth_text(string $key, int $max, bool $required = false): string {
    $v = $_POST[$key] ?? ''; if (!is_string($v)) throw new DomainException('Neplatný formulář.');
    $v = trim($v);
    if (($required && $v === '') || strlen($v) > $max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $v)) throw new DomainException('Vyplňte správně všechna povinná pole.');
    return $v;
}
function auth_email(string $key = 'email'): string {
    $e = strtolower(auth_text($key, 254, true));
    if (!filter_var($e, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $e)) throw new DomainException('Zadejte platnou e-mailovou adresu.');
    return $e;
}
function auth_profile(): array {
    $p = [auth_text('first_name', 150, true), auth_text('last_name', 150, true), auth_text('company', 200),
        auth_text('ico', 8), strtoupper(auth_text('dic', 20)), auth_text('phone', 40, true), auth_text('address', 500)];
    if ($p[3] !== '' && !preg_match('/^\d{8}$/', $p[3])) throw new DomainException('IČ musí mít 8 číslic.');
    if ($p[4] !== '' && !preg_match('/^[A-Z]{2}[A-Z0-9]{2,18}$/', $p[4])) throw new DomainException('DIČ zadejte s předponou země, například CZ.');
    if (!preg_match('/^\+?[0-9 ()\-]{6,40}$/', $p[5]) || !preg_match('/^[0-9]{6,15}$/', preg_replace('/\D/', '', $p[5]))) throw new DomainException('Zadejte platné telefonní číslo.');
    return $p;
}
function auth_password(): string {
    $p = $_POST['password'] ?? ''; $confirm = $_POST['password_confirm'] ?? '';
    if (!is_string($p) || preg_match_all('/./us', $p) < 7 || strlen($p) > 72 || str_contains($p, "\0")) throw new DomainException('Heslo musí mít alespoň 7 znaků a nejvýše 72 bajtů.');
    if (!is_string($confirm) || !hash_equals($p, $confirm)) throw new DomainException('Zadaná hesla se neshodují.');
    return $p;
}
function auth_hash(string $p): string { return password_hash($p, PASSWORD_DEFAULT); }
function auth_check_current(array $u): void {
    $p = $_POST['current_password'] ?? ($_POST['current_password_change'] ?? '');
    if (!is_string($p) || strlen($p)>72 || !password_verify($p, $u['password_hash'])) throw new DomainException('Současné heslo není správné.');
}
function auth_token(int $uid, string $purpose, string $value = '', ?int $ttl = null): string {
    $ttl ??= $purpose === 'verify' ? 86400 : 1800;
    auth_query('DELETE FROM tokens WHERE expires < ? OR (user_id=? AND purpose=?)', [time(),$uid,$purpose]);
    $token = bin2hex(random_bytes(32));
    auth_query('INSERT INTO tokens(hash,user_id,purpose,value,expires) VALUES(?,?,?,?,?)', [hash('sha256',$token),$uid,$purpose,$value,time()+$ttl]);
    return $token;
}
function auth_consume(string $purpose, callable $fn): void {
    $token = auth_text('token',64,true);
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) throw new DomainException('Odkaz je neplatný nebo již vypršel.');
    $db = auth_db(); $db->exec('BEGIN IMMEDIATE');
    try {
        $t = auth_query('SELECT * FROM tokens WHERE hash=? AND purpose=? AND expires>?', [hash('sha256',$token),$purpose,time()])->fetch(PDO::FETCH_ASSOC);
        if (!$t) throw new DomainException('Odkaz je neplatný, použitý nebo již vypršel. Vyžádejte si nový.');
        $fn($t); auth_query('DELETE FROM tokens WHERE hash=?',[$t['hash']]); $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
}
function auth_mail(string $to, string $purpose, string $token): bool {
    return auth_send_process_mail($to, $purpose, ['token'=>$token]);
}
function auth_notify_registration(string $email, string $firstName, string $lastName, string $company, string $phone): void {
    try {
        $sent = auth_send_process_mail(AUTH_APPROVER_EMAIL, 'admin-registration', ['email'=>$email, 'first_name'=>$firstName, 'last_name'=>$lastName, 'company'=>$company, 'phone'=>$phone]);
        if (!$sent) error_log('MOJE KRYPTO admin registration notification transport failed');
    } catch (Throwable $e) {
        error_log('MOJE KRYPTO admin registration notification failed');
    }
}
function auth_process(?array $u): void {
    auth_csrf(); $action = auth_text('action',30,true); $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    auth_limit('post-ip',$ip,150,900);
    if ($action === 'register') {
        auth_limit('register-ip',$ip,10,3600);
        $email=auth_email(); $p=auth_profile(); $password=auth_password();
        if (auth_text('privacy',3) !== 'yes') throw new DomainException('Potvrďte seznámení se zpracováním údajů.');
        $existing=auth_query('SELECT * FROM users WHERE email=?',[$email])->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $now=time();
            try { auth_query('INSERT INTO users(email,password_hash,first_name,last_name,company,ico,dic,phone,address,created_at,updated_at,approval_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',array_merge([$email,auth_hash($password)],$p,[$now,$now,'approved']));
                $uid=(int)auth_db()->lastInsertId();
                $token=auth_token($uid,'verify');
                if (!auth_mail($email,'verify',$token)) { auth_query('DELETE FROM users WHERE id=? AND verified=0',[$uid]); throw new DomainException('Ověřovací e-mail se nyní nepodařilo odeslat. Zkuste registraci později nebo nás kontaktujte.'); }
                auth_notify_registration($email,$p[0],$p[1],$p[2],$p[5]);
            } catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }
        }
        auth_redirect('login','Pokud je adresa nová, odeslali jsme ověřovací odkaz. Nejprve potvrďte svůj e-mail odkazem ve zprávě. Po potvrzení e-mailu se přístup aktivuje automaticky, bez čekání na správce. Pokud účet už máte, přihlaste se nebo obnovte heslo.');
    }
    if ($action === 'login') {
        $email=auth_email(); auth_limit('login-email',$email,10,900); auth_limit('login-ip',$ip,40,900);
        $p=$_POST['password']??'';
        $found=auth_query('SELECT * FROM users WHERE email=?',[$email])->fetch(PDO::FETCH_ASSOC);
        // Valid dummy bcrypt hash also makes unknown-account failures run password_verify.
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $ok=is_string($p)&&strlen($p)<=72&&password_verify($p,$found['password_hash']??$dummy);
        if (!$ok || !$found || !$found['verified']) throw new DomainException('E-mail nebo heslo není správné, případně e-mail ještě nebyl potvrzen.');
        if (!auth_approved($found)) throw new DomainException(($found['approval_status'] ?? '') === 'rejected' ? 'Přístup k tomuto účtu nebyl schválen. Kontaktujte správce na info@jirijanousek.cz.' : 'E-mail je ověřen. Účet čeká na schválení přístupu provozovatelem aplikace z bezpečnostních důvodů.');
        if (password_needs_rehash($found['password_hash'],PASSWORD_DEFAULT)) auth_query('UPDATE users SET password_hash=? WHERE id=?',[auth_hash($p),$found['id']]);
        auth_login($found); header('Location: /',true,303);exit;
    }
    if (in_array($action,['forgot','resend'],true)) {
        $email=auth_email();auth_limit('recovery-ip',$ip,15,3600);auth_limit('recovery-email',$email,3,1800);
        $found=auth_query('SELECT * FROM users WHERE email=?',[$email])->fetch(PDO::FETCH_ASSOC);
        if ($found) { $purpose=$found['verified']?'reset':'verify';$token=auth_token((int)$found['id'],$purpose);if(!auth_mail($email,$purpose,$token))error_log('MOJE KRYPTO account mail transport failed'); }
        auth_redirect('login','Pokud pro tuto adresu existuje účet, odeslali jsme odkaz k obnově hesla nebo potvrzení registrace. Zkontrolujte i spam.');
    }
    if ($action === 'verify') {
        $verifiedId = null;
        auth_consume('verify',function($t)use(&$verifiedId){auth_query('UPDATE users SET verified=1,updated_at=? WHERE id=?',[time(),$t['user_id']]);$verifiedId=(int)$t['user_id'];});
        $verifiedUser=auth_query('SELECT * FROM users WHERE id=?',[$verifiedId])->fetch(PDO::FETCH_ASSOC);
        if($verifiedUser && auth_approved($verifiedUser)) {
            if(!auth_is_admin($verifiedUser)) auth_notify_access($verifiedUser,'approved');
            auth_login($verifiedUser);
            header('Location: /',true,303);exit;
        }
        auth_redirect('login','E-mail byl ověřen, ale přístup k účtu je omezen správcem. Kontaktujte nás na info@jirijanousek.cz.');
    }
    if ($action === 'reset') {
        auth_limit('reset-ip',$ip,20,1800);$hash=auth_hash(auth_password());
        auth_consume('reset',function($t)use($hash){auth_query('UPDATE users SET password_hash=?,session_version=session_version+1,updated_at=? WHERE id=?',[$hash,time(),$t['user_id']]);auth_query('DELETE FROM tokens WHERE user_id=?',[$t['user_id']]);});
        auth_logout();auth_redirect('login','Heslo bylo změněno a všechna přihlášení ukončena. Přihlaste se novým heslem.');
    }
    if (!$u) { http_response_code(401);throw new DomainException('Nejprve se přihlaste.'); }
    if ($action === 'delete-account') {
        if (!auth_is_admin($u)) { http_response_code(403); throw new DomainException('Účet může smazat pouze správce.'); }
        $target=auth_text('user_id',20,true);
        if (!ctype_digit($target)) throw new DomainException('Neplatný účet.');
        if (auth_text('confirm_delete',3) !== 'yes') throw new DomainException('Potvrďte nevratné smazání účtu.');
        $confirmation=strtolower(auth_text('confirm_email',254,true));
        $db=auth_db(); $db->exec('BEGIN IMMEDIATE');
        try {
            $targetUser=auth_query('SELECT * FROM users WHERE id=?',[(int)$target])->fetch(PDO::FETCH_ASSOC);
            if (!$targetUser) throw new DomainException('Tento účet již neexistuje.');
            if (strtolower($targetUser['email'])===AUTH_APPROVER_EMAIL || (int)$target===(int)$u['id']) throw new DomainException('Správcovský ani vlastní účet nelze smazat.');
            if (!hash_equals(strtolower($targetUser['email']),$confirmation)) throw new DomainException('Pro potvrzení opište přesnou e-mailovou adresu účtu.');
            auth_query('DELETE FROM approval_audit WHERE user_id=? OR actor_id=?',[(int)$target,(int)$target]);
            foreach (['login-email','recovery-email'] as $scope) auth_query('DELETE FROM limits WHERE key=?',[hash('sha256',$scope.':'.$targetUser['email'])]);
            // Foreign-key cascade removes verification, reset and email-change tokens.
            auth_query('DELETE FROM users WHERE id=?',[(int)$target]);
            $db->exec('COMMIT');
        } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
        foreach (['portfolio-user-'.(int)$target.'.php','goals-user-'.(int)$target.'.php'] as $privateFile) {
            $targetFile=auth_dir().'/'.$privateFile;
            if (is_file($targetFile) && !@unlink($targetFile)) error_log('MOJE KRYPTO failed to remove private data for deleted account');
        }
        auth_redirect('approvals','Účet a jeho údaje byly trvale smazány. Původní přihlášení a ověřovací odkazy již nelze použít.');
    }
    if ($action === 'approval') {
        if (!auth_is_admin($u)) { http_response_code(403); throw new DomainException('Tuto akci může provést pouze správce.'); }
        $target=auth_text('user_id',20,true); $decision=auth_text('decision',20,true);
        if (!ctype_digit($target) || !in_array($decision,['approved','rejected','pending'],true)) throw new DomainException('Neplatná změna přístupu.');
        $db=auth_db(); $db->exec('BEGIN IMMEDIATE');
        try {
            $targetUser=auth_query('SELECT * FROM users WHERE id=?',[(int)$target])->fetch(PDO::FETCH_ASSOC);
            if (!$targetUser || auth_is_admin($targetUser) || (int)$target===(int)$u['id']) throw new DomainException('Tento účet nelze takto upravit.');
            auth_query('UPDATE users SET approval_status=?,approved_by=?,approved_at=?,session_version=session_version+1,updated_at=? WHERE id=?',[$decision,$u['id'],time(),time(),(int)$target]);
            auth_query('INSERT INTO approval_audit(actor_id,user_id,decision,created_at) VALUES(?,?,?,?)',[$u['id'],(int)$target,$decision,time()]);
            $db->exec('COMMIT');
        } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
        if (($targetUser['approval_status'] ?? '') !== $decision) auth_notify_access($targetUser, $decision);
        auth_redirect('approvals','Přístup byl aktualizován. Předchozí relace tohoto účtu byly ukončeny.');
    }
    if ($action === 'logout') { auth_logout();auth_redirect('login','Byli jste odhlášeni.'); }
    if ($action === 'profile') {
        $p=auth_profile();$email=auth_email();
        if ($email!==$u['email']) {
            if (auth_is_admin($u)) throw new DomainException('E-mail správce nelze změnit v profilu. Kontaktujte provozovatele aplikace.');
            auth_check_current($u);
            if(auth_query('SELECT id FROM users WHERE email=? AND id<>?',[$email,$u['id']])->fetch())throw new DomainException('Tuto e-mailovou adresu nelze použít.');
            $token=auth_token((int)$u['id'],'email',$email);
            if(!auth_mail($email,'email',$token))throw new DomainException('Potvrzovací e-mail se nepodařilo odeslat. Adresa nebyla změněna.');
        }
        auth_query('UPDATE users SET first_name=?,last_name=?,company=?,ico=?,dic=?,phone=?,address=?,updated_at=? WHERE id=?',array_merge($p,[time(),$u['id']]));
        auth_redirect('profile',$email!==$u['email']?'Údaje uloženy. Změnu e-mailu dokončete odkazem na nové adrese; do té doby platí původní přihlášení.':'Údaje byly uloženy.');
    }
    if ($action === 'confirm-email') {
        auth_consume('email',function($t)use($u){
            if((int)$t['user_id']!==(int)$u['id'])throw new DomainException('Přihlaste se k účtu, pro který byl odkaz vystaven.');
            if(auth_query('SELECT id FROM users WHERE email=? AND id<>?',[$t['value'],$u['id']])->fetch())throw new DomainException('Tuto e-mailovou adresu již nelze použít.');
            auth_query('UPDATE users SET email=?,session_version=session_version+1,updated_at=? WHERE id=?',[$t['value'],time(),$u['id']]);
            auth_query('DELETE FROM tokens WHERE user_id=?',[$u['id']]);
        });auth_logout();auth_redirect('login','E-mail byl změněn. Přihlaste se novou adresou a stávajícím heslem.');
    }
    if ($action === 'password') {
        auth_check_current($u);$hash=auth_hash(auth_password());
        auth_query('UPDATE users SET password_hash=?,session_version=session_version+1,updated_at=? WHERE id=?',[$hash,time(),$u['id']]);
        auth_query('DELETE FROM tokens WHERE user_id=?',[$u['id']]);auth_logout();auth_redirect('login','Heslo bylo změněno. Přihlaste se znovu.');
    }
    throw new DomainException('Neznámá akce.');
}
function auth_require(): array {
    try { $u=auth_user();if(!$u)auth_redirect('login');return $u; }
    catch(Throwable $e){http_response_code(503);exit('Přihlášení je dočasně nedostupné. Kontakt: info@jirijanousek.cz');}
}
function h(string $v): string { return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }


