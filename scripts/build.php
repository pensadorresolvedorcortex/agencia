<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$out = $root . '/build/portal-grupos-whatsapp-1.0.0.zip';
if (!is_dir(dirname($out)) && !mkdir(dirname($out), 0775, true) && !is_dir(dirname($out))) {
    fwrite(STDERR, "Não foi possível criar o diretório de build.\n");
    exit(2);
}
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "A extensão ZipArchive é necessária.\n");
    exit(2);
}
$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) exit(1);
$prefix = 'portal-grupos-whatsapp/';
$files = ['portal-grupos-whatsapp.php', 'uninstall.php', 'readme.txt', 'README.md', 'CHANGELOG.md', 'LICENSE', 'composer.json', 'package.json'];
foreach ($files as $file) $zip->addFile($root . '/' . $file, $prefix . $file);

foreach (['src', 'data', 'assets', 'languages'] as $directory) {
    $path = $root . '/' . $directory;
    $zip->addEmptyDir($prefix . $directory);
    if (!is_dir($path)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $zip->addFile($file->getPathname(), $prefix . str_replace(DIRECTORY_SEPARATOR, '/', $relative));
    }
}
$zip->close();
echo $out . PHP_EOL;
