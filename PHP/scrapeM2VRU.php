<?php
	declare(strict_types=1);
	
	const pre_settings = true;
	const m2v_settings = true;
	
	require_once(__DIR__ . '/settings.php');
	require_once(__DIR__ . '/Classes/DB.php');
	
	$db = new \nzedb\db\DB();
	
	for (;;) {
	    $db->ping(true);
	    $rssData = getUrl(M2VRU_RSS_LINK);
	    
	    if (!$rssData) {
	        echo "Error downloading '" . M2VRU_RSS_LINK . "'\n";
	        sleepPrintout(M2V_SLEEP_TIME);
	        continue;
	    }
	
	    $rssData = @simplexml_load_string($rssData);
	    if (!$rssData) {
	        echo "Error parsing XML data from M2V RSS.\n";
	        sleepPrintout(M2V_SLEEP_TIME);
	        continue;
	    }
	
	    echo "Downloaded RSS data from M2V.\n";
	    $items = 0;
	    
	    foreach ($rssData->channel->item as $item) {
	        if ($item->title == "m2v.ru") {
	            continue;
	        }
	
	        if ($db->queryOneRow("SELECT id FROM predb WHERE filename != '' AND title = " . $db->escapeString($item->title))) {
	            continue;
	        }
	
	        $itemData = getUrl($item->link);
	        if (!$itemData) {
	            echo "Error downloading page: '{$item->title}'\nSkipping.\n";
	            usleep(M2VRU_THROTTLE_USLEEP);
	            continue;
	        }
	
	        $fileName = $alternateFileName = '';
	        if (preg_match_all('#<DIV\s+class=links>(.+?)</DIV>#is', $itemData, $matches)) {
	            foreach ($matches[1] as $match) {
	                // <b>b8zkcy01.zip, <font color="silver">size: <font color="white">4,77 MB</font>
	                if (preg_match('#<b>\s*(?P<filename>.+?)\s*,#is', $match, $matches2)) {
	                    if (preg_match('#\.(nfo|sfv|mu3|txt|jpe?g|png|gif)$#', $matches2['filename'])) {
	                        $alternateFileName = $matches2['filename'];
	                        continue;
	                    }
	                    $fileName = $matches2['filename'];
	                    break;
	                }
	            }
	        }
	
	        if (!$fileName) {
	            if ($alternateFileName) {
	                $fileName = $alternateFileName;
	            } else {
	                echo "Could not find file name for '{$item->title}'.\nSkipping.\n";
	                usleep(M2VRU_THROTTLE_USLEEP);
	                continue;
	            }
	        } else {
	            echo "Found $fileName for '{$item->title}', updating PreDB table.\n";
	        }
	
	        $itemTitle = $db->escapeString($item->title);
	        $itemCategory = $db->escapeString($item->category);
	        $itemPubDate = strtotime((string)$item->pubDate);
	        $escapedFileName = $db->escapeString(preg_replace('#\..{0,5}$#', '', $fileName));
	        
	        $db->queryInsert(
	            sprintf(
	                "INSERT INTO predb (title, category, predate, filename, source)
	                VALUES (%s, %s, FROM_UNIXTIME(%d), %s, 'm2v.ru')
	                ON DUPLICATE KEY
	                UPDATE title = %s, category = %s, predate = FROM_UNIXTIME(%d), filename = %s, source = 'm2v.ru', id = LAST_INSERT_ID(id)",
	                $itemTitle, $itemCategory, $itemPubDate, $escapedFileName,
	                $itemTitle, $itemCategory, $itemPubDate, $escapedFileName
	            )
	        );
	
	        $items++;
	        echo "Sleeping " . M2VRU_THROTTLE_USLEEP . " microseconds to be kind on m2v.ru\n";
	        usleep(M2VRU_THROTTLE_USLEEP);
	    }
	
	    echo "Updated $items rows in PreDB\n";
	    sleepPrintout(M2V_SLEEP_TIME);
	}
	
	/**
	 * Sleep with countdown display.
	 *
	 * @param int $seconds Number of seconds to sleep
	 */
	function sleepPrintout(int $seconds): void
	{
	    for ($i = $seconds; $i > 0; $i--) {
	        echo "Sleeping $i seconds.\r";
	        sleep(1);
	    }
	    echo "\n";
	}
	
	/**
	 * Use cURL To download a web page into a string.
	 *
	 * @param string $url The URL to download
	 * @param bool $debug Show debug info
	 * @return string|bool Page content or false on failure
	 */
	function getUrl(string $url, bool $debug = false): string|bool
	{
	    $ch = curl_init();
	    $options = [
	        CURLOPT_URL => $url,
	        CURLOPT_HTTPHEADER => ["Accept-Language: en-us"],
	        CURLOPT_RETURNTRANSFER => 1,
	        CURLOPT_FOLLOWLOCATION => 1,
	        CURLOPT_TIMEOUT => 15,
	        CURLOPT_SSL_VERIFYPEER => false,
	        CURLOPT_SSL_VERIFYHOST => false,
	        CURLOPT_COOKIE => 'foo=bar',
	        CURLOPT_USERAGENT => 'Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10'
	    ];
	    curl_setopt_array($ch, $options);
	
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
	
	    if ($err !== 0) {
	        return false;
	    }
	    
	    return $buffer;
	}
