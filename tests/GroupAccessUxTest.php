<?php
$php=(string)file_get_contents(__DIR__.'/../portal-grupos-whatsapp.php');
$css=(string)file_get_contents(__DIR__.'/../assets/dist/frontend.css');
foreach (['group_join_button', 'whatsapp_icon', 'pgw-button__icon', 'pgw-open-login', 'data-pgw-auth-redirect', 'rel="nofollow noopener noreferrer external"', 'wp_redirect(esc_url_raw($url),302', 'if(!is_user_logged_in()){$target=', 'suppress_theme_group_thumbnail', 'wp_get_attachment_image($thumbnail_id', 'self::group_join_button($id)'] as $rule) if (strpos($php,$rule)===false) throw new RuntimeException('Missing group access contract: '.$rule);
foreach (["add_meta_boxes_' . self::CPT", 'pgw-group-link', 'pgw_admin_invite_url', 'save_group_link_metabox', '_pgw_invite_hash'] as $rule) if (strpos($php,$rule)===false) throw new RuntimeException('Missing dashboard invite-link contract: '.$rule);
if (strpos($php,'wp_safe_redirect($url,302)')!==false) throw new RuntimeException('WhatsApp redirects must not use wp_safe_redirect fallback to wp-admin.');
if (strpos($php,'data-pgw-category-toggle')!==false || strpos($php,'Ver mais categorias')!==false) throw new RuntimeException('Category expansion control must be removed.');
foreach (['.single-pgw_group .default-blog__item-single .blog__details_title', 'font-size:40px', 'text-align:center', '.pgw-single__image', '.pgw-button__icon', '.single-pgw_group .pgw-auth>p .pgw-button', 'font-size:15px'] as $rule) if (strpos($css,$rule)===false) throw new RuntimeException('Missing single group CSS: '.$rule);
echo "25 group access and single view checks passed.\n";
