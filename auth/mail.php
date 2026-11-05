<?php
declare(strict_types=1);

// Plain-text templates only. No credentials or user input are placed in headers.
function auth_email_template(string $purpose, string $base, array $data = []): array {
    static $catalog = null;
    $catalog ??= json_decode(file_get_contents(__DIR__.'/mail-templates.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!isset($catalog['templates'][$purpose])) throw new InvalidArgumentException('Unknown mail purpose');
    $base = rtrim($base, '/');
    $routes = ['verify'=>'verify', 'reset'=>'reset', 'email'=>'confirm-email'];
    if (isset($routes[$purpose])) {
        $token = $data['token'] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) throw new InvalidArgumentException('Invalid mail token');
        // Keep one-time tokens in the fragment: never in access logs or a referrer.
        $url = $base.'/account.php?view='.$routes[$purpose].'#token='.$token;
    } else {
        $url = match ($purpose) {
            'admin-registration' => $base.'/admin.php',
            'approved' => $base.'/account.php?view=login',
            default => 'https://jirijanousek.cz/#kontakt',
        };
    }
    $singleLine = static fn($value): string => trim(preg_replace('/\s+/u', ' ', (string)$value) ?? '');
    $company = $singleLine($data['company'] ?? '');
    $values = [
        '{action_url}'=>$url, '{home_url}'=>$base.'/',
        '{help_url}'=>$base.'/account.php?view=forgot',
        '{login_url}'=>$base.'/account.php?view=login',
        '{name}'=>$singleLine(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
        '{email}'=>$singleLine($data['email'] ?? ''),
        '{phone}'=>$singleLine($data['phone'] ?? ''),
        '{company_line}'=>$company !== '' ? '🏢 Firma: '.$company : '',
    ];
    $template = $catalog['templates'][$purpose];
    $body = strtr(implode("\n", array_merge(['Dobrý den,', ''], $template['lines'], [''], $catalog['footer']))."\n", $values);
    $body = preg_replace('/\n{3,}/', "\n\n", $body);
    return ['subject'=>$template['subject'], 'body'=>$body, 'url'=>$url];
}

function auth_email_header(string $text): string {
    if (strpbrk($text, "\r\n") !== false) throw new InvalidArgumentException('Invalid mail header');
    $parts = []; $part = '';
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        if (strlen($part.$char) > 42) { $parts[] = $part; $part = ''; }
        $part .= $char;
    }
    if ($part !== '') $parts[] = $part;
    return implode("\r\n ", array_map(static fn($value)=>'=?UTF-8?B?'.base64_encode($value).'?=', $parts));
}

function auth_send_process_mail(string $to, string $purpose, array $data = []): bool {
    $base = auth_test() ? 'http://'.$_SERVER['HTTP_HOST'] : AUTH_ORIGIN;
    $message = auth_email_template($purpose, $base, $data);
    if (auth_test()) {
        return file_put_contents(auth_dir().'/test-outbox.jsonl', json_encode(['to'=>$to, 'purpose'=>$purpose]+$message, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND|LOCK_EX) !== false;
    }
    $headers = 'From: '.auth_email_header('MOJE KRYPTO').' <info@jirijanousek.cz>'."\r\n"
        ."Reply-To: info@jirijanousek.cz\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    return mail($to, auth_email_header($message['subject']), str_replace("\n", "\r\n", $message['body']), $headers);
}

function auth_notify_access(array $user, string $decision): void {
    if (!(int)$user['verified'] || !in_array($decision, ['approved','rejected','pending'], true)) return;
    try {
        if (!auth_send_process_mail($user['email'], $decision)) error_log('MOJE KRYPTO account status mail transport failed');
    } catch (Throwable $e) {
        // A failed notification must not roll back an access or security decision.
        error_log('MOJE KRYPTO account status notification failed');
    }
}
