<?php
$root = dirname(__DIR__) . '/laravel/resources/views';
$map = [
    '#0F1939' => '#0B1B3D',
    '#26428B' => '#0F3C8A',
    '#E3AF64' => '#EBA83A',
    '#0F3277' => '#0F3C8A',
    '#0B255B' => '#0B1B3D',
    '#F8FAFC' => '#F8F7F5',
    '#F2B84B' => '#EBA83A',
    '#E09F2B' => '#D8942A',
];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$changed = [];
foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    if ($file->getFilename() === 'welcome.blade.php') continue;
    $path = $file->getPathname();
    $contents = file_get_contents($path);
    $new = strtr($contents, $map);
    if ($new !== $contents) {
        file_put_contents($path, $new);
        $changed[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    }
}
echo "changed: " . count($changed) . PHP_EOL;
foreach ($changed as $c) echo " - $c" . PHP_EOL;
