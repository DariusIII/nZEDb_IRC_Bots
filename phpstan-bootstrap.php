<?php

declare(strict_types=1);

$string = static fn (string $name, string $default = ''): string => (string) ($_ENV[$name] ?? $default);
$int = static fn (string $name, int $default): int => (int) ($_ENV[$name] ?? $default);
$bool = static fn (string $name, bool $default = false): bool => filter_var(
    $_ENV[$name] ?? $default,
    FILTER_VALIDATE_BOOL
);

defined('DB_HOST') || define('DB_HOST', $string('PRE_DB_HOST', $string('DB_HOST', 'localhost')));
defined('DB_PORT') || define('DB_PORT', (string) $int('PRE_DB_PORT', $int('DB_PORT', 3306)));
defined('DB_NAME') || define('DB_NAME', $string('PRE_DB_NAME', $string('DB_NAME', 'ircpredb')));
defined('DB_USER') || define('DB_USER', $string('PRE_DB_USER', $string('DB_USER')));
defined('DB_PASSWORD') || define('DB_PASSWORD', $string('PRE_DB_PASSWORD', $string('DB_PASSWORD')));
defined('DB_SOCKET') || define('DB_SOCKET', $string('PRE_DB_SOCKET', $string('DB_SOCKET')));

defined('FETCH_SRRDB') || define('FETCH_SRRDB', $bool('FETCH_SRRDB'));
defined('FETCH_XREL') || define('FETCH_XREL', $bool('FETCH_XREL'));
defined('FETCH_XREL_P2P') || define('FETCH_XREL_P2P', $bool('FETCH_XREL_P2P'));
defined('WEB_SLEEP_TIME') || define('WEB_SLEEP_TIME', $int('WEB_SLEEP_TIME', 600));
defined('M2VRU_RSS_LINK') || define('M2VRU_RSS_LINK', $string('M2VRU_RSS_LINK', 'https://example.invalid/rss'));
defined('M2V_SLEEP_TIME') || define('M2V_SLEEP_TIME', $int('M2V_SLEEP_TIME', 600));
defined('M2VRU_THROTTLE_USLEEP') || define('M2VRU_THROTTLE_USLEEP', $int('M2VRU_THROTTLE_USLEEP', 250000));

defined('POST_BOT_HOST') || define('POST_BOT_HOST', $string('POST_BOT_HOST', 'localhost'));
defined('POST_BOT_PORT') || define('POST_BOT_PORT', (string) $int('POST_BOT_PORT', 6667));
defined('POST_BOT_TLS') || define('POST_BOT_TLS', $bool('POST_BOT_TLS'));
defined('POST_BOT_NICKNAME') || define('POST_BOT_NICKNAME', $string('POST_BOT_NICKNAME', 'nzedb-pre'));
defined('POST_BOT_REALNAME') || define('POST_BOT_REALNAME', $string('POST_BOT_REALNAME', 'nzedb-pre'));
defined('POST_BOT_USERNAME') || define('POST_BOT_USERNAME', $string('POST_BOT_USERNAME', 'nzedb-pre'));
defined('POST_BOT_PASSWORD') || define('POST_BOT_PASSWORD', $string('POST_BOT_PASSWORD'));
defined('POST_BOT_PING_STRING') || define('POST_BOT_PING_STRING', $string('POST_BOT_PING_STRING'));
defined('POST_BOT_DEBUG') || define('POST_BOT_DEBUG', $bool('POST_BOT_DEBUG'));
defined('POST_BOT_CHANNEL') || define('POST_BOT_CHANNEL', $string('POST_BOT_CHANNEL', '#nZEDbPRE'));
defined('POST_BOT_CHANNEL_PASSWORD') || define('POST_BOT_CHANNEL_PASSWORD', $string('POST_BOT_CHANNEL_PASSWORD'));
defined('POST_BOT_POST_DELAY') || define('POST_BOT_POST_DELAY', $int('POST_BOT_POST_DELAY', 2));
defined('POST_BOT_SCAN_DELAY') || define('POST_BOT_SCAN_DELAY', $int('POST_BOT_SCAN_DELAY', 10));
defined('POST_BOT_CLEANUP') || define('POST_BOT_CLEANUP', $int('POST_BOT_CLEANUP', 1));
defined('POST_BOT_BOX_COLOR') || define('POST_BOT_BOX_COLOR', $string('POST_BOT_BOX_COLOR'));
defined('POST_BOT_INNER_COLOR') || define('POST_BOT_INNER_COLOR', $string('POST_BOT_INNER_COLOR'));

defined('EFNET_BOT_SERVER') || define('EFNET_BOT_SERVER', $string('EFNET_BOT_SERVER', 'localhost'));
defined('EFNET_BOT_PORT') || define('EFNET_BOT_PORT', (string) $int('EFNET_BOT_PORT', 6667));
defined('EFNET_BOT_NICKNAME') || define('EFNET_BOT_NICKNAME', $string('EFNET_BOT_NICKNAME', 'nzedb-efnet'));
defined('EFNET_BOT_REALNAME') || define('EFNET_BOT_REALNAME', $string('EFNET_BOT_REALNAME', 'nzedb-efnet'));
defined('EFNET_BOT_USERNAME') || define('EFNET_BOT_USERNAME', $string('EFNET_BOT_USERNAME', 'nzedb-efnet'));
defined('EFNET_BOT_PASSWORD') || define('EFNET_BOT_PASSWORD', $string('EFNET_BOT_PASSWORD'));
defined('EFNET_BOT_ENCRYPTION') || define('EFNET_BOT_ENCRYPTION', $bool('EFNET_BOT_ENCRYPTION'));
defined('EFNET_BOT_DEBUG') || define('EFNET_BOT_DEBUG', $bool('EFNET_BOT_DEBUG'));
defined('EFNET_BOT_CHANNELS') || define('EFNET_BOT_CHANNELS', serialize(['#pre' => null]));

defined('CORRUPT_BOT_HOST') || define('CORRUPT_BOT_HOST', $string('CORRUPT_BOT_HOST', 'localhost'));
defined('CORRUPT_BOT_PORT') || define('CORRUPT_BOT_PORT', (string) $int('CORRUPT_BOT_PORT', 6667));
defined('CORRUPT_BOT_NICKNAME') || define('CORRUPT_BOT_NICKNAME', $string('CORRUPT_BOT_NICKNAME', 'nzedb-corrupt'));
defined('CORRUPT_BOT_REALNAME') || define('CORRUPT_BOT_REALNAME', $string('CORRUPT_BOT_REALNAME', 'nzedb-corrupt'));
defined('CORRUPT_BOT_USERNAME') || define('CORRUPT_BOT_USERNAME', $string('CORRUPT_BOT_USERNAME', 'nzedb-corrupt'));
defined('CORRUPT_BOT_PASSWORD') || define('CORRUPT_BOT_PASSWORD', $string('CORRUPT_BOT_PASSWORD'));
defined('CORRUPT_BOT_ENCRYPTION') || define('CORRUPT_BOT_ENCRYPTION', $bool('CORRUPT_BOT_ENCRYPTION'));
defined('CORRUPT_BOT_DEBUG') || define('CORRUPT_BOT_DEBUG', $bool('CORRUPT_BOT_DEBUG'));

defined('REQID_BOT_HOST') || define('REQID_BOT_HOST', $string('REQID_BOT_HOST', 'localhost'));
defined('REQID_BOT_PORT') || define('REQID_BOT_PORT', (string) $int('REQID_BOT_PORT', 6667));
defined('REQID_BOT_NICKNAME') || define('REQID_BOT_NICKNAME', $string('REQID_BOT_NICKNAME', 'nzedb-reqid'));
defined('REQID_BOT_REALNAME') || define('REQID_BOT_REALNAME', $string('REQID_BOT_REALNAME', 'nzedb-reqid'));
defined('REQID_BOT_USERNAME') || define('REQID_BOT_USERNAME', $string('REQID_BOT_USERNAME', 'nzedb-reqid'));
defined('REQID_BOT_PASSWORD') || define('REQID_BOT_PASSWORD', $string('REQID_BOT_PASSWORD'));
defined('REQID_BOT_ENCRYPTION') || define('REQID_BOT_ENCRYPTION', $bool('REQID_BOT_ENCRYPTION'));
defined('REQID_BOT_DEBUG') || define('REQID_BOT_DEBUG', $bool('REQID_BOT_DEBUG'));
defined('REQID_BOT_CHANNELS') || define('REQID_BOT_CHANNELS', serialize(['#alt.binaries.boneless' => null]));
