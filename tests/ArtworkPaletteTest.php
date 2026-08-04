<?php
require_once __DIR__.'/../src/Demo/ArtworkPalette.php';

$palette = new PGW\Demo\ArtworkPalette();
$first=$palette->for('Tecnologia');$second=$palette->for('Tecnologia');
if($first!==$second||count($first)!==3||count(array_unique($first))!==3)throw new RuntimeException('Palette determinism failed');
foreach($first as $color)if(!preg_match('/^#[0-9a-f]{6}$/',$color))throw new RuntimeException('Invalid color');
echo "4 artwork palette cases passed.\n";
