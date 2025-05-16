<?php

declare(strict_types=1);

require_once('DB.php');

class fetchWeb
{
    /**
     * @var \nzedb\db\DB Database connection
     */
    protected readonly \nzedb\db\DB $db;

    /**
     * @var int Count of processed entries
     */
    protected int $done = 0;

    /**
     * Number of active Web Sources.
     */
    protected int $activeSources = 0;

    /**
     * The sleep time in between sources is totalSleepTime divided by activeSources.
     */
    protected float $sleepTime;

    public function __construct()
    {
        if (FETCH_SRRDB) {
            $this->activeSources++;
        }
        if (FETCH_XREL) {
            $this->activeSources++;
        }
        if (FETCH_XREL_P2P) {
            $this->activeSources++;
        }

        if (!$this->activeSources) {
            sleep(WEB_SLEEP_TIME);
            return;
        }

        $this->sleepTime = WEB_SLEEP_TIME / $this->activeSources;
        $this->db = new \nzedb\db\DB();
        $this->start();
    }

    protected function start(): void
    {
        while (true) {
            if (FETCH_SRRDB) {
                $this->retrieveSrr();
            }
            if (FETCH_XREL) {
                $this->retrieveXrel();
            }
            if (FETCH_XREL_P2P) {
                $this->retrieveXrelP2P();
            }
        }
    }

    protected function echoDone(): void
    {
        echo "Fetched {$this->done} PREs. Sleeping {$this->sleepTime} seconds.\n";
        $this->done = 0;
        sleep((int)$this->sleepTime);
    }

    /**
     * Get pre from SrrDB.
     */
    protected function retrieveSrr(): void
    {
        echo "Fetching SrrDB\n";
        $data = $this->getUrl("https://www.srrdb.com/feed/srrs");

        if ($data === false) {
            echo "Update from Srr failed.\n";
            return;
        }

        $xml = @simplexml_load_string($data);
        if ($xml === false) {
            echo "Update from Srr failed: Invalid XML.\n";
            return;
        }

        $this->db->ping(true);
        foreach ($xml->channel->item as $release) {
            $result = [
                'title' => (string)$release->title,
                'date' => strtotime((string)$release->pubDate),
                'source' => 'srrdb'
            ];
            $this->verifyPreData($result);
        }
        $this->echoDone();
    }

    /**
     * Get pre from Xrel.
     */
    protected function retrieveXrel(): void
    {
        echo "Fetching Xrel\n";
        $data = $this->getUrl("https://api.xrel.to/v2/release/latest.json?per_page=100");

        if ($data === false) {
            echo "Update from Xrel failed.\n";
            return;
        }

        $json = json_decode($data);
        if (!$json) {
            echo "Update from Xrel failed: Invalid JSON.\n";
            return;
        }

        $this->db->ping(true);
        foreach ($json->list as $release) {
            $result = [
                'title' => trim((string)$release->dirname),
                'date' => (int)trim((string)$release->time),
                'source' => 'xrel'
            ];

            if (isset($release->size->number, $release->size->unit)) {
                $result['size'] = trim((string)$release->size->number) . trim((string)$release->size->unit);
            }

            $this->verifyPreData($result);
        }
        $this->echoDone();
    }

    /**
     * Get pre from XrelP2P.
     */
    protected function retrieveXrelP2P(): void
    {
        echo "Fetching XrelP2P\n";
        $data = $this->getUrl("https://api.xrel.to/v2/p2p/releases.json?per_page=100");

        if ($data === false) {
            echo "Update from XrelP2P failed.\n";
            return;
        }

        $json = json_decode($data);
        if (!$json) {
            echo "Update from XrelP2P failed: Invalid JSON.\n";
            return;
        }

        $this->db->ping(true);
        foreach ($json->list as $release) {
            $result = [
                'title' => trim((string)$release->dirname),
                'date' => (int)trim((string)$release->pub_time),
                'source' => 'xrelp2p'
            ];

            if (isset($release->size_mb)) {
                $result['size'] = trim((string)$release->size_mb) . "MB";
            }

            if (isset($release->category->meta_cat, $release->category->sub_cat)) {
                $result['category'] = ucfirst(trim((string)$release->category->meta_cat)) .
                                     " " . trim((string)$release->category->sub_cat);
            }

            $this->verifyPreData($result);
        }
        $this->echoDone();
    }

    /**
     * Verify and insert/update pre data
     */
    protected function verifyPreData(array &$matches): void
    {
        // If the title is too short, don't bother.
        if (strlen($matches['title']) < 15) {
            return;
        }

        $matches['title'] = str_replace(["\r", "\n"], '', $matches['title']);

        $duplicateCheck = $this->db->queryOneRow(
            sprintf('SELECT * FROM predb WHERE title = %s', $this->db->escapeString($matches['title']))
        );

        if (!is_numeric($matches['date']) || $matches['date'] < (time() - 31536000)) {
            return;
        }

        if ($duplicateCheck === false) {
            $this->db->queryExec(
                sprintf(
                    '
                    INSERT INTO predb (title, size, category, predate, source, requestid, groupid, files, filename, nuked, nukereason, shared)
                    VALUES (%s, %s, %s, %s, %s, %d, %d, %s, %s, %d, %s, -1)',
                    $this->db->escapeString($matches['title']),
                    (isset($matches['size']) && $matches['size'] !== '') ? $this->db->escapeString($matches['size']) : 'NULL',
                    (isset($matches['category']) && $matches['category'] !== '') ? $this->db->escapeString($matches['category']) : 'NULL',
                    $this->db->from_unixtime($matches['date']),
                    $this->db->escapeString($matches['source']),
                    (isset($matches['requestid']) && is_numeric($matches['requestid'])) ? (int)$matches['requestid'] : 0,
                    (isset($matches['groupid']) && is_numeric($matches['groupid'])) ? (int)$matches['groupid'] : 0,
                    (!empty($matches['files'])) ? $this->db->escapeString($matches['files']) : 'NULL',
                    isset($matches['filename']) ? $this->db->escapeString($matches['filename']) : $this->db->escapeString(''),
                    (isset($matches['nuked']) && is_numeric($matches['nuked'])) ? (int)$matches['nuked'] : 0,
                    (isset($matches['reason']) && !empty($matches['nukereason'])) ? $this->db->escapeString($matches['nukereason']) : 'NULL'
                )
            );
            $this->done++;
        } else {
            $query = 'UPDATE predb SET ';

            $query .= $this->updateString('size', $duplicateCheck['size'], $matches['size'] ?? null);
            $query .= $this->updateString('files', $duplicateCheck['files'], $matches['files'] ?? null);
            $query .= $this->updateString('nukereason', $duplicateCheck['nukereason'], $matches['reason'] ?? null);
            $query .= $this->updateString('requestid', $duplicateCheck['requestid'], $matches['requestid'] ?? null, false);
            $query .= $this->updateString('groupid', $duplicateCheck['groupid'], $matches['groupid'] ?? null, false);
            $query .= $this->updateString('nuked', $duplicateCheck['nuked'], $matches['nuked'] ?? null, false);
            $query .= $this->updateString('filename', $duplicateCheck['filename'], $matches['filename'] ?? null);
            $query .= $this->updateString('category', $duplicateCheck['category'], $matches['category'] ?? null);

            if ($query === 'UPDATE predb SET ') {
                return;
            }

            $this->done++;

            $query .= 'predate = ' . $this->db->from_unixtime($matches['date']) . ', ';
            $query .= 'source = ' . $this->db->escapeString($matches['source']) . ', ';
            $query .= 'title = ' . $this->db->escapeString($matches['title']);
            $query .= ', shared = -1';
            $query .= ' WHERE title = ' . $this->db->escapeString($matches['title']);

            $this->db->queryExec($query);
        }
    }

    /**
     * Update SQL string for changed values
     */
    protected function updateString(string $sqlKey, mixed $oldValue, mixed $newValue, bool $escape = true): string
    {
        if (empty($oldValue) && !empty($newValue)) {
            return $sqlKey . ' = ' . ($escape ? $this->db->escapeString($newValue) : $newValue) . ', ';
        }

        return '';
    }

    /**
     * Use cURL To download a web page into a string.
     *
     * @param string $url       The URL to download
     * @param string $method    get/post
     * @param string $postdata  If using POST, post your POST data here
     * @param string $language  Use alternate language in header
     * @param bool   $debug     Show debug info
     * @param string $userAgent User agent
     * @param string $cookie    Cookie
     *
     * @return string|false Downloaded content or false on failure
     */
    protected function getUrl(
        string $url,
        string $method = 'get',
        string $postdata = '',
        string $language = 'en',
        bool $debug = false,
        string $userAgent = 'Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10',
        string $cookie = 'foo=bar'
    ): string|false {
        $language = match ($language) {
            'fr', 'fr-fr' => 'fr-fr',
            'de', 'de-de' => 'de-de',
            'en' => 'en',
            default => 'en-us',
        };

        $header = ["Accept-Language: $language"];

        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        curl_setopt_array($ch, $options);

        if ($userAgent !== '') {
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        }

        if ($cookie !== '') {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }

        if ($method === 'post') {
            curl_setopt_array($ch, [
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $postdata
            ]);
        }

        if ($debug) {
            curl_setopt_array($ch, [
                CURLOPT_HEADER => true,
                CURLINFO_HEADER_OUT => true,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_VERBOSE => true
            ]);
        }

        $buffer = curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);

        if ($err !== 0 || $buffer === false) {
            return false;
        }

        return $buffer;
    }
}
