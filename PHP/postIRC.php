<?php
// Posts Pres to IRC.
	const pre_settings = true;
	const post_bot_settings = true;
require_once('settings.php');
require_once('Classes/IRCServer.php');
new IRCServer();