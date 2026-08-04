<?php
$php=(string)file_get_contents(__DIR__.'/../portal-grupos-whatsapp.php');
$css=(string)file_get_contents(__DIR__.'/../assets/dist/frontend.css');
$phpRules=['render_global_auth_modal', 'pgw-open-auth,.pgw-open-login,.pgw-open-register', "self::auth_modal('login','<p role=\"status\">Entre ou cadastre-se para visualizar seus grupos.</p>'", 'pgw_pending_redirect', "'registration',null,true", 'placeholder="Categoria"', "in_array('pgw_pending_member'"];
foreach($phpRules as $rule)if(strpos($php,$rule)===false)throw new RuntimeException('Missing authentication UX contract: '.$rule);
$cssRules=['.pgw-auth-modal{position:fixed', '.pgw-auth-modal[hidden]{display:none!important}', '.pgw-wrap{background:#fff}', '.pgw-filters{display:grid', 'font-size:16px', '.pgw-open-auth,.pgw-open-login,.pgw-open-register'];
foreach($cssRules as $rule)if(strpos($css,$rule)===false)throw new RuntimeException('Missing frontend UX rule: '.$rule);
echo "13 authentication and frontend UX checks passed.\n";
