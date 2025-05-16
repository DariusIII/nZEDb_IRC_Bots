<?php
declare(strict_types=1);

// Download Pres from IRC.
if (!isset($argv[1]) || !in_array($argv[1], ['efnet', 'corrupt'])) {
    exit();
}

$silent = isset($argv[2]) && $argv[2] === 'true';
const pre_settings = true;

if ($argv[1] === 'efnet') {
    define('efnet_bot_settings', true);
} else {
    define('corrupt_bot_settings', true);
}

require_once(__DIR__ . '/settings.php');
require_once(__DIR__ . '/Classes/IRCScraper.php');

// Initialize and run the IRC scraper
(new IRCScraper($argv[1], $silent));
