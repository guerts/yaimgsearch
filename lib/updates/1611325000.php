<?php

$files = array(
    '/css/justified.css',
    '/css/slick.css',
    '/css/yaimgs.css',
    '/js/justified.js',
    '/js/slick.js',
    '/js/yaimgs.js',
    '/js/mousewheel.js',
    '/js/zoom.js'
);
$plugin = wa()->getPlugin('yaimgsearch');
$plugin_path = $plugin->path;

foreach ($files as $file_name) {
    $file_path = $plugin_path . $file_name;
    if (is_file($file_path)) {
        unlink($file_path);
    }
}