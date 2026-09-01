<?php
namespace PlayBrand\Performance;
if (!defined('ABSPATH')) exit;
final class Assets {
 public static function init(): void { add_action('wp_enqueue_scripts',[self::class,'filter'],1000); }
 public static function filter(): void { $o=get_option(PBPF_OPTION,[]); if(empty($o['enabled'])||!empty($o['dry_run']))return; $raw=(string)($o['denylist']??''); foreach(preg_split('/\R+/',$raw,-1,PREG_SPLIT_NO_EMPTY) as $h){$h=sanitize_key($h); if(!$h)continue; $scripts=wp_scripts(); $styles=wp_styles(); $deps=$scripts->registered[$h]->deps??$styles->registered[$h]->deps??[]; if(!$deps && !in_array($h,['jquery','elementor-frontend','swiper','gsap','scrolltrigger'],true)){wp_dequeue_script($h);wp_dequeue_style($h);wp_deregister_script($h);wp_deregister_style($h);} } }
}
