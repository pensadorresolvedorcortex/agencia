<?php
require_once __DIR__.'/../src/Accounts/RegistrationPolicy.php';

$policy = new PGW\Accounts\RegistrationPolicy();
$base = ['first_name'=>'Maria','last_name'=>'Silva','email'=>'MARIA@example.test','phone'=>'(11) 99999-9999','password'=>'senha-segura','confirmation'=>'senha-segura','terms'=>1,'privacy'=>1];
$result = $policy->normalize($base);
if (!$result['valid'] || $result['email'] !== 'maria@example.test' || $result['phone'] !== '+5511999999999') throw new RuntimeException('Valid registration normalization failed');
foreach ([['confirmation'=>'diferente'],['terms'=>0],['privacy'=>0],['email'=>'invalid']] as $change) if ($policy->normalize(array_replace($base,$change))['valid']) throw new RuntimeException('Invalid registration accepted');
$invalidPhone=$policy->normalize(array_replace($base,['phone'=>'123'])); if(!$invalidPhone['valid']||$invalidPhone['phone']!=='')throw new RuntimeException('Invalid optional phone should not block registration');
$international=$policy->normalize(array_replace($base,['phone'=>'+351 912 345 678'])); if(!$international['valid']||$international['phone']!=='+351912345678')throw new RuntimeException('International phone failed');
echo "8 registration policy cases passed.\n";
