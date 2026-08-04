<?php
require_once __DIR__.'/../src/Images/GooglePicturePolicy.php';

$policy = new PGW\Images\GooglePicturePolicy();
$cases = [
    ['https://lh3.googleusercontent.com/a/photo=s512-c', true],
    ['https://lh6.googleusercontent.com/a-/safe', true],
    ['http://lh3.googleusercontent.com/a/photo', false],
    ['https://evil.example/a/photo', false],
    ['https://user@lh3.googleusercontent.com/a/photo', false],
    ['https://lh3.googleusercontent.com/a/../secret', false],
];
foreach ($cases as [$url, $valid]) if (($policy->normalize($url) !== '') !== $valid) throw new RuntimeException('Google picture policy failed: '.$url);
echo count($cases)." google picture cases passed.\n";
