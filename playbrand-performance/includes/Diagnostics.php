<?php
namespace PlayBrand\Performance;
if (!defined('ABSPATH')) exit;
final class Diagnostics {
 public static function report(): array { $rocket=defined('WP_ROCKET_VERSION')?WP_ROCKET_VERSION:'não detectado'; $large=[]; foreach((array)wp_scripts()->queue as $h){$r=wp_scripts()->registered[$h]??null;if($r&&isset($r->src))$large[$h]=$r->src;} return ['wp'=>get_bloginfo('version'),'php'=>PHP_VERSION,'rocket'=>$rocket,'queued_scripts'=>$large,'safe_context'=>Plugin::safe_context()]; }
}
