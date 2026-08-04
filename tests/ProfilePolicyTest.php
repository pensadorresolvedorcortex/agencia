<?php
require_once __DIR__.'/../src/Accounts/RegistrationPolicy.php';
require_once __DIR__.'/../src/Accounts/ProfilePolicy.php';

$policy = new PGW\Accounts\ProfilePolicy();
$valid=$policy->normalize(['first_name'=>' Ana ','last_name'=>'Silva','display_name'=>'Ana S.','bio'=>'<b>Texto</b> curto','phone'=>'11999999999']);
if(!$valid['valid']||$valid['firstName']!=='Ana'||$valid['bio']!=='Texto curto'||$valid['phone']!=='+5511999999999')throw new RuntimeException('Profile normalization failed');
if($policy->normalize(['first_name'=>'','display_name'=>'Ana'])['valid'])throw new RuntimeException('Empty first name accepted');
if($policy->normalize(['first_name'=>'Ana','display_name'=>'','phone'=>'123'])['valid'])throw new RuntimeException('Invalid profile accepted');
if($policy->normalize(['first_name'=>'Ana','display_name'=>'Ana','bio'=>str_repeat('a',301)])['valid'])throw new RuntimeException('Long bio accepted');
echo "4 profile policy cases passed.\n";
