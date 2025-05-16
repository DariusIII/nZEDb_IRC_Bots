<?php

declare(strict_types=1);

// Scrape Request ID's from EFNet IRC.
const req_settings = true;

$silent = isset($argv[1]) && $argv[1] === 'true';

require_once(__DIR__ . '/settings.php');
require_once(__DIR__ . '/Classes/ReqIRCScraper.php');

// Initialize and run the IRC scraper
(new ReqIRCScraper($silent));
