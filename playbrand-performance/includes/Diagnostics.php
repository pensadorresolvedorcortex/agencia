<?php
namespace PlayBrand\Performance;
if (!defined('ABSPATH')) exit;
final class Diagnostics {
 public static function report(): array { $rocket=defined('WP_ROCKET_VERSION')?WP_ROCKET_VERSION:'não detectado'; $o=get_option(PBPF_OPTION,[]); $scripts=wp_scripts(); $queued=[]; foreach((array)$scripts->queue as $h){$r=$scripts->registered[$h]??null;if($r&&isset($r->src))$queued[$h]=$r->src;} return ['wp'=>get_bloginfo('version'),'php'=>PHP_VERSION,'rocket'=>$rocket,'queued_scripts'=>$queued,'safe_context'=>Plugin::safe_context(),'safe_mode'=>(defined('PBPF_SAFE_MODE')&&PBPF_SAFE_MODE),'dry_run'=>!empty($o['dry_run'])]; }
}
