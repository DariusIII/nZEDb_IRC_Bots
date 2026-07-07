<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\Env;
use App\Config\LegacySettings;
use PHPUnit\Framework\TestCase;

final class LegacySettingsTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLoadsPrePostConstantsFromEnv(): void
    {
        define('pre_settings', true);
        define('post_bot_settings', true);

        LegacySettings::load(Env::fromArray([
            'PRE_DB_HOST' => 'db-pre',
            'PRE_DB_NAME' => 'ircpredb_test',
            'POST_BOT_NICKNAME' => 'poster',
            'POST_BOT_TLS' => 'false',
        ]));

        self::assertSame('db-pre', DB_HOST);
        self::assertSame('ircpredb_test', DB_NAME);
        self::assertSame('poster', POST_BOT_NICKNAME);
        self::assertFalse(POST_BOT_TLS);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLoadsRequestIdConstantsAndJsonChannelsFromEnv(): void
    {
        define('req_settings', true);

        LegacySettings::load(Env::fromArray([
            'REQ_DB_HOST' => 'db-req',
            'REQ_DB_NAME' => 'reqpredb_test',
            'REQID_BOT_CHANNELS' => '{"#requests":null,"#private":"password"}',
        ]));

        self::assertSame('db-req', DB_HOST);
        self::assertSame('reqpredb_test', DB_NAME);
        self::assertSame([
            '#requests' => null,
            '#private' => 'password',
        ], unserialize(REQID_BOT_CHANNELS, ['allowed_classes' => false]));
    }
}
