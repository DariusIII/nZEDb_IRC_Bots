<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\LegacySettings;

final class LegacyIrcService
{
    public function run(WorkerType $worker, bool $silent = false): int
    {
        $this->loadSettings($worker);
        $this->loadWorkerClass($worker);

        match ($worker) {
            WorkerType::Post => new \IRCServer(),
            WorkerType::ScrapeEfnet => new \IRCScraper('efnet', $silent),
            WorkerType::ScrapeCorrupt => new \IRCScraper('corrupt', $silent),
            WorkerType::ScrapeReqId => new \ReqIRCScraper($silent),
            WorkerType::FetchWeb => new \fetchWeb(),
        };

        return 0;
    }

    private function loadSettings(WorkerType $worker): void
    {
        match ($worker) {
            WorkerType::Post => $this->defineConstants(['pre_settings', 'post_bot_settings']),
            WorkerType::ScrapeEfnet => $this->defineConstants(['pre_settings', 'efnet_bot_settings']),
            WorkerType::ScrapeCorrupt => $this->defineConstants(['pre_settings', 'corrupt_bot_settings']),
            WorkerType::ScrapeReqId => $this->defineConstants(['req_settings']),
            WorkerType::FetchWeb => $this->defineConstants(['pre_settings', 'web_settings']),
        };

        LegacySettings::load();
    }

    /**
     * @param list<string> $constants
     */
    private function defineConstants(array $constants): void
    {
        foreach ($constants as $constant) {
            if (!defined($constant)) {
                define($constant, true);
            }
        }
    }

    private function loadWorkerClass(WorkerType $worker): void
    {
        $classFile = match ($worker) {
            WorkerType::Post => dirname(__DIR__) . '/Classes/IRCServer.php',
            WorkerType::ScrapeEfnet, WorkerType::ScrapeCorrupt => dirname(__DIR__) . '/Classes/IRCScraper.php',
            WorkerType::ScrapeReqId => dirname(__DIR__) . '/Classes/ReqIRCScraper.php',
            WorkerType::FetchWeb => dirname(__DIR__) . '/Classes/fetchWeb.php',
        };

        require_once $classFile;
    }
}
