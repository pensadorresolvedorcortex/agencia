<?php
require dirname(__DIR__) . '/src/Auth/PostOtpRedirectPolicy.php';

use PGW\Auth\PostOtpRedirectPolicy;

$policy = new PostOtpRedirectPolicy();
$cases = [
    ['https://site.test/entrar/', true],
    ['https://site.test/criar-conta/?x=1', true],
    ['https://site.test/confirmar-codigo/?pgw_flow=abc&pgw_redirect_to=https%3A%2F%2Fsite.test%2Fentrar%2F', true],
    ['https://site.test/grupo/design-digital/', false],
    ['https://site.test/ir/design-digital/', false],
    ['https://site.test/minha-conta/?pgw_flow=abc', true],
];
foreach ($cases as [$url, $expected]) {
    if ($policy->isAuthDestination($url) !== $expected) throw new RuntimeException('Unexpected auth destination result for ' . $url);
}
if (!$policy->allows('https://site.test/ir/design-digital/')) throw new RuntimeException('Expected group redirect to be allowed.');
if ($policy->allows('https://site.test/entrar/')) throw new RuntimeException('Expected auth redirect to be blocked.');
echo "PostOtpRedirectPolicyTest passed\n";
