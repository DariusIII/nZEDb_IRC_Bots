<?php

declare(strict_types=1);

namespace App\Service;

enum WorkerType: string
{
    case Post = 'post';
    case ScrapeEfnet = 'scrape-efnet';
    case ScrapeCorrupt = 'scrape-corrupt';
    case ScrapeReqId = 'scrape-reqid';
    case FetchWeb = 'fetch-web';

    public static function usageList(): string
    {
        return implode(', ', array_map(
            static fn (self $worker): string => $worker->value,
            self::cases()
        ));
    }
}
