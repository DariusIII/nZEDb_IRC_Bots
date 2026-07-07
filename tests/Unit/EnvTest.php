<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\Env;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvTest extends TestCase
{
    public function testReadsTypedValues(): void
    {
        $env = Env::fromArray([
            'NAME' => 'bot',
            'PORT' => '6697',
            'TLS' => 'true',
            'EMPTY' => '',
        ]);

        self::assertSame('bot', $env->requiredString('NAME'));
        self::assertSame(6697, $env->int('PORT', 6667));
        self::assertTrue($env->bool('TLS'));
        self::assertNull($env->nullableString('EMPTY'));
    }

    public function testParsesJsonChannelMap(): void
    {
        $env = Env::fromArray([
            'CHANNELS' => '{"#public":null,"#private":"secret"}',
        ]);

        self::assertSame([
            '#public' => null,
            '#private' => 'secret',
        ], $env->jsonMap('CHANNELS'));
    }

    public function testRejectsInvalidBoolean(): void
    {
        $this->expectException(RuntimeException::class);

        Env::fromArray(['DEBUG' => 'maybe'])->bool('DEBUG');
    }

    public function testRejectsInvalidJsonChannelMap(): void
    {
        $this->expectException(RuntimeException::class);

        Env::fromArray(['CHANNELS' => '[]'])->jsonMap('CHANNELS');
    }
}
