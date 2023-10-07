<?php
// Scrape Request ID's from EFNet IRC.
$silent = isset($argv[1]) && $argv[1] === 'true';
	const req_settings = true;
require_once('settings.php');
require_once('Classes/ReqIRCScraper.php');
new ReqIRCScraper($silent);