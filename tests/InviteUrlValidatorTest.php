<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Security/InviteUrlValidator.php';

use PGW\Security\InviteUrlValidator;

$validator = new InviteUrlValidator();
$cases = [
    ['https://chat.whatsapp.com/AbCdEf0123456789', 'https://chat.whatsapp.com/AbCdEf0123456789'],
    ['https://www.whatsapp.com/channel/0029VaExample', 'https://www.whatsapp.com/channel/0029VaExample'],
    ['https://whatsapp.com/community/Community_123/', 'https://whatsapp.com/community/Community_123'],
    ['http://chat.whatsapp.com/AbCdEf0123456789', ''],
    ['https://evil.example/AbCdEf0123456789', ''],
    ['https://chat.whatsapp.com.evil.example/AbCdEf0123456789', ''],
    ['https://user:password@chat.whatsapp.com/AbCdEf0123456789', ''],
    ['https://chat.whatsapp.com:443/AbCdEf0123456789', ''],
    ['https://chat.whatsapp.com/short', ''],
    ["https://chat.whatsapp.com/AbCdEf0123456789\n.evil", ''],
];

foreach ($cases as $index => [$input, $expected]) {
    $actual = $validator->normalize($input);
    if ($actual !== $expected) {
        fwrite(STDERR, sprintf("Case %d failed: expected %s, got %s\n", $index + 1, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
}

echo count($cases) . " invite URL cases passed.\n";
