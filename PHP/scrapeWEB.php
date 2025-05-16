<?php

declare(strict_types=1);

// Download Pres from the Web.
const pre_settings = true;
const web_settings = true;

require_once(__DIR__ . '/settings.php');
require_once(__DIR__ . '/Classes/fetchWeb.php');

// Initialize and run the web fetcher
(new fetchWeb());
