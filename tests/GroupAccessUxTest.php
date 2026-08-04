<?php
$php=(string)file_get_contents(__DIR__.'/../portal-grupos-whatsapp.php');
$css=(string)file_get_contents(__DIR__.'/../assets/dist/frontend.css');
foreach (['group_join_button', 'pgw-open-login', 'data-pgw-auth-redirect', 'if(!is_user_logged_in()){$target=', 'suppress_theme_group_thumbnail', 'wp_get_attachment_image($thumbnail_id', 'self::group_join_button($id)'] as $rule) if (strpos($php,$rule)===false) throw new RuntimeException('Missing group access contract: '.$rule);
if (strpos($php,'data-pgw-category-toggle')!==false || strpos($php,'Ver mais categorias')!==false) throw new RuntimeException('Category expansion control must be removed.');
foreach (['.single-pgw_group .default-blog__item-single .blog__details_title', 'font-size:40px', 'text-align:center', '.pgw-single__image'] as $rule) if (strpos($css,$rule)===false) throw new RuntimeException('Missing single group CSS: '.$rule);
echo "13 group access and single view checks passed.\n";
