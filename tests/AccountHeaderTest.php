<?php
$php=(string)file_get_contents(__DIR__.'/../portal-grupos-whatsapp.php');
$css=(string)file_get_contents(__DIR__.'/../assets/dist/frontend.css');
foreach (["'minha-conta-header'=>'account_header'", 'pgw_frontend_auth', 'admin_post_nopriv_pgw_frontend_auth', '$endpoint=esc_url(admin_url', 'data-pgw-auth-redirect', '<h3><a href=', 'pgw-account-menu__avatar', '#pgw-password', '#pgw-sessions'] as $rule) if (strpos($php,$rule)===false) throw new RuntimeException('Missing account/auth contract: '.$rule);
if (strpos($php,"REQUEST_METHOD']!=='POST'||is_admin()")!==false) throw new RuntimeException('admin-post authentication must not be skipped.');
foreach (['.pgw-account-menu{', '.pgw-account-menu nav{', '.pgw-card h3 a{'] as $rule) if (strpos($css,$rule)===false) throw new RuntimeException('Missing account menu CSS: '.$rule);
echo "13 account header and reliable authentication checks passed.\n";
