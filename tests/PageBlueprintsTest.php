<?php
require_once __DIR__.'/../src/Activation/PageBlueprints.php';

$blueprints = new PGW\Activation\PageBlueprints();
$all = $blueprints->all();
if (count($all) !== 12 || count(array_unique(array_keys($all))) !== 12) throw new RuntimeException('Blueprint count failed');
foreach ($all as $slug=>$page) if (!preg_match('/^[a-z0-9-]+$/',$slug) || !preg_match('/\[pgw_[a-z_]+/', $page['content'])) throw new RuntimeException('Invalid blueprint');
$selected=$blueprints->selected(['entrar','grupos','invalid']); if(array_keys($selected)!==['entrar','grupos'])throw new RuntimeException('Selection allowlist failed');
echo "3 page blueprint cases passed.\n";
