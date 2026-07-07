<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\WorkerType;
use PHPUnit\Framework\TestCase;

final class WorkerTypeTest extends TestCase
{
    public function testUsageListContainsEveryWorkerValue(): void
    {
        $usageList = WorkerType::usageList();

        foreach (WorkerType::cases() as $workerType) {
            self::assertStringContainsString($workerType->value, $usageList);
        }
    }

    public function testWorkerTypeCanBeResolvedFromCliValue(): void
    {
        self::assertSame(WorkerType::Post, WorkerType::tryFrom('post'));
        self::assertSame(WorkerType::ScrapeEfnet, WorkerType::tryFrom('scrape-efnet'));
        self::assertNull(WorkerType::tryFrom('missing-worker'));
    }
}
