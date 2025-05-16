<?php

declare(strict_types=1);

use JetBrains\PhpStorm\NoReturn;

require_once('DB.php');
require_once('IRCClient.php');

class IRCServer extends IRCClient
{
    /**
     * Last ping time.
     */
    protected int $lastPingTime = 0;

    /**
     * Counter for database optimization.
     */
    protected int $optimizeIterations = 0;

    /**
     * Days to keep records before cleanup.
     */
    protected int $cleanupTime;

    /**
     * IRC color for message boxes.
     */
    protected string $boxColor;

    /**
     * IRC color for end of message segments.
     */
    protected string $endColor;

    /**
     * IRC color for inner message content.
     */
    protected string $innerColor;

    /**
     * List of channels and passwords to join.
     */
    protected array $channels;

    /**
     * String to ping.
     */
    protected string $pingString;

    /**
     * Database connection.
     */
    protected readonly \nzedb\db\DB $db;

    /**
     * Constructor - initializes the server connection and settings.
     */
    public function __construct()
    {
        $this->debug = POST_BOT_DEBUG;
        $this->cleanupTime = POST_BOT_CLEANUP;
        $this->pingString = POST_BOT_PING_STRING;

        if (POST_BOT_BOX_COLOR === '') {
            $this->boxColor = '[';
            $this->endColor = '] ';
        } else {
            $this->boxColor = "\x03" . POST_BOT_BOX_COLOR;
            $this->endColor = "\x0f" . $this->boxColor . "]\x0f ";
            $this->boxColor .= "[\x0f";
        }

        $this->innerColor = POST_BOT_INNER_COLOR === '' ? ' ' : "\x03" . POST_BOT_INNER_COLOR . ' ';

        $this->db = new \nzedb\db\DB();
        $this->initiateServer();
        $this->startSniffing();
    }

    /**
     * Initialize server connection and join channels.
     */
    protected function initiateServer(): void
    {
        // Connect to IRC
        if (!$this->connect(POST_BOT_HOST, POST_BOT_PORT, POST_BOT_TLS)) {
            exit('Error connecting to IRC!' . PHP_EOL);
        }

        if (empty($this->pingString)) {
            $this->pingString = $this->remoteHostReceived;
        }

        // Login to IRC
        if (!$this->login(POST_BOT_NICKNAME, POST_BOT_REALNAME, POST_BOT_REALNAME, POST_BOT_PASSWORD)) {
            exit('Error logging in to IRC!' . PHP_EOL);
        }

        // Join channels
        $this->channels = [POST_BOT_CHANNEL => POST_BOT_CHANNEL_PASSWORD];
        if (str_contains(POST_BOT_CHANNEL, ",#")) {
            $this->channels = [];
            $passwords = explode(',', POST_BOT_CHANNEL_PASSWORD);
            foreach (explode(',', POST_BOT_CHANNEL) as $key => $channel) {
                $this->channels[$channel] = $passwords[$key] ?? '';
            }
        }
        $this->joinChannels($this->channels);

        echo '[' . date('r') . '] [Connected to IRC!]' . PHP_EOL;
    }

    /**
     * Start sniffing for pre messages to post.
     */
    #[NoReturn]
    protected function startSniffing(): void
    {
        $time = time();
        while (true) {
            if ($this->optimizeIterations++ === 300) {
                $this->optimizeIterations = 0;
                if (!empty($this->cleanupTime)) {
                    $this->db->queryExec(sprintf(
                        'DELETE FROM predb WHERE shared = 1 AND predate < NOW() - INTERVAL %d DAY',
                        $this->cleanupTime
                    ));
                }
                $this->db->optimise(false, 'full');
                echo PHP_EOL;
            }

            if ((time() - $this->lastPingTime) > 60) {
                $this->ping($this->pingString);
                $this->lastPingTime = time();
            }

            $allPre = $this->db->query(
                'SELECT p.*, UNIX_TIMESTAMP(p.predate) AS ptime, groups.name AS gname FROM predb p LEFT JOIN groups ON groups.id = p.groupid WHERE p.shared in (-1, 0)'
            );

            if ($allPre) {
                $time = time();
                foreach ($allPre as $pre) {
                    if ($this->formatMessage($pre)) {
                        echo "Posted [{$pre['title']}]" . PHP_EOL;
                        $this->db->queryExec("UPDATE predb SET shared = 1 WHERE id = {$pre['id']}");
                    } else {
                        echo "Error posting [{$pre['title']}]" . PHP_EOL;
                        $this->reconnect();
                        if (!$this->isConnected()) {
                            exit('IRC Error: The connection was lost and we could not reconnect.' . PHP_EOL);
                        }
                    }
                    sleep(POST_BOT_POST_DELAY);
                }
            } elseif ((time() - $time > 60)) {
                $time = time();
                foreach ($this->channels as $channel => $password) {
                    $this->writeSocket("PRIVMSG {$channel} :INFO: [" . gmdate('Y-m-d H:i:s') . ' This message is to confirm I am still active.]');
                }
            }

            sleep(POST_BOT_SCAN_DELAY);
        }
    }

    /**
     * Format a pre message for IRC output.
     *
     * @param array $pre Pre information to format
     * @return bool Whether message was successfully sent
     */
    protected function formatMessage(array $pre): bool
    {
        //DT: PRE Time(UTC) | TT: Title | SC: Source | CT: Category | RQ: Requestid | SZ: Size | FL: Files
        $string = match (true) {
            $pre['nuked'] > 0 => 'NUK: ',
            $pre['shared'] === '0' => 'NEW: ',
            default => 'UPD: '
        };

        $string .=
            "{$this->boxColor}DT:{$this->innerColor}" .
            gmdate('Y-m-d H:i:s', $pre['ptime']) .
            $this->endColor .
            "{$this->boxColor}TT:{$this->innerColor}" .
            $pre['title'] .
            $this->endColor .
            "{$this->boxColor}SC:{$this->innerColor}" .
            $pre['source'] .
            $this->endColor .
            "{$this->boxColor}CT:{$this->innerColor}" .
            ($pre['category'] ?? 'N/A') .
            $this->endColor .
            "{$this->boxColor}RQ:{$this->innerColor}" .
            ((isset($pre['requestid']) && $pre['requestid'] > 0) ? "{$pre['requestid']}:{$pre['gname']}" : 'N/A') .
            $this->endColor .
            "{$this->boxColor}SZ:{$this->innerColor}" .
            ($pre['size'] ?? 'N/A') .
            $this->endColor .
            "{$this->boxColor}FL:{$this->innerColor}" .
            ($pre['files'] ?? 'N/A') .
            $this->endColor .
            "{$this->boxColor}FN:{$this->innerColor}" .
            (!empty($pre['filename']) ? $pre['filename'] : 'N/A') .
            $this->endColor;

        if (isset($pre['nuked'])) {
            $string .= match ((int)$pre['nuked']) {
                0 => '',
                1 => "{$this->boxColor}UNNUKED:{$this->innerColor}{$pre['nukereason']}{$this->endColor}",
                2 => "{$this->boxColor}NUKED:{$this->innerColor}{$pre['nukereason']}{$this->endColor}",
                3 => "{$this->boxColor}MODNUKED:{$this->innerColor}{$pre['nukereason']}{$this->endColor}",
                4 => "{$this->boxColor}RENUKED:{$this->innerColor}{$pre['nukereason']}{$this->endColor}",
                5 => "{$this->boxColor}OLDNUKE:{$this->innerColor}{$pre['nukereason']}{$this->endColor}",
                default => ''
            };
        }

        if (strlen($string) > 500) {
            $string = substr($string, 0, 500) . $this->endColor;
        }

        $success = true;
        foreach ($this->channels as $channel => $password) {
            if (!$this->writeSocket("PRIVMSG {$channel} :{$string}")) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Set the channels to join.
     *
     * @param array $channels List of channels and passwords to join
     * @return self For method chaining
     */
    public function setChannels(array $channels): self
    {
        $this->channels = $channels;

        return $this;
    }
}
