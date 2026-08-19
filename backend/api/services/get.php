<?php
function listFolder($path, $indent = 0) {
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        echo str_repeat('│  ', $indent) . '├── ' . $item . "\n";
        if (is_dir($path . '/' . $item)) {
            listFolder($path . '/' . $item, $indent + 1);
        }
    }
}

$base = 'C:/Users/HP/Desktop/xampp/htdocs/HostelHub-main';
echo "<pre>";
echo "HostelHub-main/\n";
listFolder($base);
echo "</pre>";