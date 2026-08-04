<?php
require_once __DIR__.'/../src/Accounts/PasswordResetLink.php';

$link = new PGW\Accounts\PasswordResetLink();
$result = $link->build('https://example.test/recuperar-senha/', 'abc&123', 'nome+teste@example.test');
if ($result !== 'https://example.test/recuperar-senha/?key=abc%26123&login=nome%2Bteste%40example.test') throw new RuntimeException('Reset URL encoding failed');
if ($link->build('https://example.test/', "bad\nkey", 'user') !== '') throw new RuntimeException('Control character accepted');
if ($link->build('', 'key', 'user') !== '') throw new RuntimeException('Empty base accepted');
echo "3 password reset link cases passed.\n";
