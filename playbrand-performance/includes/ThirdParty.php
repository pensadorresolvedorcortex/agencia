<?php
namespace PlayBrand\Performance;
if (!defined('ABSPATH')) exit;
final class ThirdParty {
 public static function init(): void { add_action('wp_head',[self::class,'guard'],1); }
 public static function guard(): void {
  $o=get_option(PBPF_OPTION,[]); if(empty($o['enabled'])||empty($o['third_party'])) return;
  echo '<script id="pbpf-third-party">(function(){var ok=false;var allow=function(){ok=true;window.dispatchEvent(new Event("pbpf:interaction"));};["click","touchstart","keydown"].forEach(function(e){addEventListener(e,allow,{once:true,passive:true});});var f=window.fetch;if(f)window.fetch=function(i){var u=String(i&&i.url||i);if(!ok&&u.indexOf("ipinfo.io/json")!==-1)return Promise.reject(new Error("PlayBrand third-party gate"));return f.apply(this,arguments)};var o=XMLHttpRequest.prototype.open;XMLHttpRequest.prototype.open=function(m,u){if(!ok&&String(u).indexOf("ipinfo.io/json")!==-1){this.__pbpfBlocked=true;return o.call(this,m,"data:application/json,%7B%7D",true)}return o.apply(this,arguments)};})();</script>';
 }
}
