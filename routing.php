<?php

if (preg_match('/\.(?:png|jpg|jpeg|gif|mp4|css)$/', $_SERVER["REQUEST_URI"])) {
    return false;
} else {
    include __DIR__ . '/index.php';
}
