<?php
declare(strict_types=1);

/**
 * Private CLI-only account health and one-time recovery for the configured owner.
 * NEVER expose this command over HTTP or commit an access token or password.
 *
 * php84 cron/account-doctor.php status
 * php84 cron/account-doctor.php issue-link
 *
 * No user data, password hash, or tokens are printed during the default check.
 * The optional one-time link is shown only in the hosting owner's CLI output;
 * do not share screenshots or logs containing it.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/auth/bootstrap.php';

$mode = $argv[1] ?? 'status';
if (!in_array($mode, ['status', 'issue-link'], true)) {
    fwrite(STDERR, "Use: php account-doctor.php [status|issue-link]\n");
    exit(2);
}
try {
    $db = auth_db();
    $stmt = $db->prepare('SELECT id,email,verified,approval_status FROM users WHERE email=? LIMIT 1');
    $stmt->execute([AUTH_APPROVER_EMAIL]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $accounts = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $verified = $admin !== false && (int)$admin['verified'] === 1;
    $allowed = $admin !== false && auth_approved($admin);
    $private = auth_dir();

    echo "Moje krypto / stav přihlášení (jen pro správce hostingu)\n";
    echo "Databáze účtů: dostupná\n";
    echo "Počet účtů: " . $accounts . "\n";
    echo "Účet správce: " . ($admin === false ? 'NENALEZEN' : 'existuje') . "\n";
    if ($admin !== false) {
        echo "E-mail potvrzen: " . ($verified ? 'ANO' : 'NE') . "\n";
        echo "Přístup povolen: " . ($allowed ? 'ANO' : 'NE') . "\n";
        echo "Soukromé portfolio správce: " .
            (is_file($private . '/portfolio-user-' . (int)$admin['id'] . '.php') ? 'uloženo' : 'zatím bez souboru') . "\n";
    }
    echo "Staré portfolio (migrace): " .
        (is_file(dirname(__DIR__) . '/storage/data.php') ? 'k dispozici' : 'nenalezeno') . "\n";

    if ($mode === 'issue-link') {
        if ($admin === false) {
            echo "Obnovu nelze spustit: účet správce neexistuje. Použijte standardní registraci "
               . "a potvrzení e-mailu. Staré portfolio nemažte.\n";
            exit(3);
        }
        $purpose = $verified ? 'reset' : 'verify';
        $view = $verified ? 'reset' : 'verify';
        $token = auth_token((int)$admin['id'], $purpose, '', 1800);
        echo "SOUKROMÝ JEDNORÁZOVÝ ODKAZ – nikomu neposílejte a nefotografujte:\n";
        echo AUTH_ORIGIN . '/account.php?view=' . $view . '#token=' . $token . "\n";
        echo "Platnost: 30 minut. Opakovaný příkaz předchozí odkaz zneplatní.\n";
        if ($purpose === 'verify') {
            echo "Po otevření odkazu se ověření e-mailu provede automaticky.\n";
        } else {
            echo "Otevřete odkaz v prohlížeči a bezpečně si nastavte nové heslo.\n";
        }
    }
} catch (Throwable $e) {
    error_log('MOJE KRYPTO account CLI diagnostic failed: ' . get_class($e));
    fwrite(STDERR, "Kontrolu nelze dokončit. Zkontrolujte oprávnění k soukromé SQLite a PHP CLI.\n");
    exit(1);
}
