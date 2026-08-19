<?php
$base = 'C:/Users/HP/Desktop/xampp/htdocs/HostelHub-main';
$results = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
foreach ($iterator as $file) {
    if ($file->getFilename() === 'db.php') {
        $results[] = $file->getPathname();
    }
}
echo '<pre>' . implode("\n", $results) . '</pre>';