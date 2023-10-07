<?php
// Download Pres from the Web.
	const pre_settings = true;
	const web_settings = true;
require_once('settings.php');
require_once('Classes/fetchWeb.php');
new fetchWeb();