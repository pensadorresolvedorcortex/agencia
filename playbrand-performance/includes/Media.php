<?php
namespace PlayBrand\Performance;
if (!defined('ABSPATH')) exit;
final class Media {
 public static function init(): void { add_filter('wp_video_shortcode',[self::class,'video'],10,3); add_filter('wp_get_attachment_image_attributes',[self::class,'img'],10,3); add_action('wp_footer',[self::class,'script'],99); }
 public static function video(string $html,array $atts,string $video): string { $o=get_option(PBPF_OPTION,[]); if(empty($o['enabled'])||empty($o['media'])||stripos($html,'autoplay')!==false||stripos($html,'data-pbpf-critical')!==false)return $html; $html=preg_replace_callback('/\s(src|srcset)=("|\')([^"\']+)\2/i',fn($m)=>' data-'.$m[1].'='.$m[2].esc_attr($m[3]).$m[2],$html)??$html; if(stripos($html,'preload=')===false)$html=preg_replace('/<video/i','<video preload="none"',$html,1)??$html; return $html; }
 public static function img(array $a): array { if(isset($a['loading']))$a['loading']='lazy'; if(isset($a['decoding']))$a['decoding']='async'; return $a; }
 public static function script(): void { $o=get_option(PBPF_OPTION,[]); if(empty($o['enabled'])||empty($o['media']))return; echo '<script id="pbpf-media">document.addEventListener("DOMContentLoaded",function(){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(!e.isIntersecting)return;var v=e.target;["src","srcset"].forEach(function(k){var d=v.getAttribute("data-"+k);if(d){v.setAttribute(k,d);v.removeAttribute("data-"+k);}});io.unobserve(v);});},{rootMargin:"300px"});document.querySelectorAll("video[data-src],video[data-srcset]").forEach(function(v){io.observe(v);});});</script>'; }
}
