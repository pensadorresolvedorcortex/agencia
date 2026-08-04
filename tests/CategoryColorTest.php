<?php
require_once __DIR__.'/../src/Taxonomy/CategoryColor.php';

use PGW\Taxonomy\CategoryColor;

$colors = new CategoryColor();
if ($colors->normalize('#39FF88') !== '#39ff88') throw new RuntimeException('Valid hex colors must be normalized.');
if ($colors->normalize('red') !== '' || $colors->normalize('#fff') !== '') throw new RuntimeException('Unsafe colors must be rejected.');
if ($colors->fallback(1) === $colors->fallback(2)) throw new RuntimeException('Adjacent categories need distinct defaults.');
if ($colors->ink('#ffe04b') !== '#102d19' || $colors->ink('#101020') !== '#ffffff') throw new RuntimeException('Chip contrast must remain readable.');
$php = (string) file_get_contents(__DIR__.'/../portal-grupos-whatsapp.php');
$css = (string) file_get_contents(__DIR__.'/../assets/dist/frontend.css');
foreach (["'show_more'=>0", 'pgw_category_color', 'data-pgw-category-toggle', 'category_color_style'] as $rule) if (strpos($php, $rule) === false) throw new RuntimeException('Missing category UI contract: '.$rule);
foreach (['padding:2px 0 42px', 'font-size:14px', '--pgw-category-color', '.pgw-category-toggle'] as $rule) if (strpos($css, $rule) === false) throw new RuntimeException('Missing category CSS contract: '.$rule);
echo "14 category color and selector checks passed.\n";
