<?php

declare(strict_types=1);

// Posts Pres to IRC.
const pre_settings = true;
const post_bot_settings = true;

require_once(__DIR__ . '/../vendor/autoload.php');
\App\Config\LegacySettings::load();
require_once(__DIR__ . '/Classes/IRCServer.php');

// Initialize and run the IRC server
(new IRCServer());
