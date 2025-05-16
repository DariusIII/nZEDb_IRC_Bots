<?php
	 declare(strict_types=1);
	 
	 require_once('DB.php');
	 require_once('IRCClient.php');
	 
	 /**
	  * Class ReqIRCScraper
	  */
	 class ReqIRCScraper extends IRCClient
	 {
	     /**
	      * Array of current pre info.
	      */
	     protected array $curPre;
	 
	     /**
	      * Run this in silent mode (no text output).
	      */
	     protected bool $silent;
	 
	     /**
	      * Database connection
	      */
	     protected readonly \nzedb\db\DB $db;
	 
	     /**
	      * Construct
	      */
	     public function __construct(bool &$silent = false)
	     {
	         $this->db = new \nzedb\db\DB();
	         $this->silent = $silent;
	         $this->debug = REQID_BOT_DEBUG;
	         $this->resetPreVariables();
	         $this->startScraping();
	     }
	 
	     /**
	      * Main method for scraping.
	      */
	     protected function startScraping(): void
	     {
	         // Connect to IRC.
	         if (!$this->connect(REQID_BOT_HOST, REQID_BOT_PORT, REQID_BOT_ENCRYPTION)) {
	             exit(
	                 "Error connecting to ({REQID_BOT_HOST}:{REQID_BOT_PORT}). " .
	                 "Please verify your server information and try again." .
	                 PHP_EOL
	             );
	         }
	 
	         // Login to IRC.
	         if (!$this->login(REQID_BOT_NICKNAME, REQID_BOT_USERNAME, REQID_BOT_REALNAME, REQID_BOT_PASSWORD)) {
	             exit(
	                 "Error logging in to: ({REQID_BOT_HOST}:{REQID_BOT_PORT}) nickname: ({REQID_BOT_NICKNAME}). " .
	                 "Verify your connection information, you might also be banned from this server " .
	                 "or there might have been a connection issue." .
	                 PHP_EOL
	             );
	         }
	 
	         // Join channels.
	         $this->joinChannels(unserialize(REQID_BOT_CHANNELS));
	 
	         if (!$this->silent) {
	             echo
	                 "[" .
	                 date('r') .
	                 "] [Scraping of IRC channels for ({REQID_BOT_HOST}:{REQID_BOT_PORT}) " .
	                 "({REQID_BOT_NICKNAME}) started.]" .
	                 PHP_EOL;
	         }
	 
	         // Scan incoming IRC messages.
	         $this->readIncoming();
	     }
	 
	     /**
	      * Check the similarity between 2 words.
	      */
	     protected function checkSimilarity(string $word1, string $word2, int $similarity = 49): bool
	     {
	         similar_text($word1, $word2, $percent);
	         return $percent > $similarity;
	     }
	 
	     /**
	      * Check channel and poster, send message to right method.
	      */
	     protected function processChannelMessages(): void
	     {
	         $channel = strtolower($this->channelData['channel']);
	         $poster = strtolower($this->channelData['nickname']);
	 
	         match ($channel) {
	             '#alt.binaries.inner-sanctum' => $this->checkSimilarity($poster, 'sanctum') ? $this->inner_sanctum() : null,
	             '#alt.binaries.erotica' => ($this->checkSimilarity($poster, 'ginger') || $this->checkSimilarity($poster, 'g1nger')) ? $this->ab_erotica() : null,
	             '#alt.binaries.flac' => $this->checkSimilarity($poster, 'abflac') ? $this->ab_flac() : null,
	             '#alt.binaries.moovee' => $this->checkSimilarity($poster, 'abking') ? $this->ab_moovee() : null,
	             '#alt.binaries.teevee' => $this->checkSimilarity($poster, 'abgod') ? $this->ab_teevee() : null,
	             '#alt.binaries.foreign' => $this->checkSimilarity($poster, 'abqueen') ? $this->ab_foreign() : null,
	             '#alt.binaries.console.ps3' => $this->checkSimilarity($poster, 'binarybot') ? $this->ab_console_ps3() : null,
	             '#alt.binaries.games.nintendods' => $this->checkSimilarity($poster, 'binarybot') ? $this->ab_games_nintendods() : null,
	             '#alt.binaries.games.wii' => ($this->checkSimilarity($poster, 'binarybot') || $this->checkSimilarity($poster, 'googlebot')) ? $this->ab_games_wii($poster) : null,
	             '#alt.binaries.games.xbox360' => ($this->checkSimilarity($poster, 'binarybot') || $this->checkSimilarity($poster, 'googlebot')) ? $this->ab_games_xbox360($poster) : null,
	             '#alt.binaries.sony.psp' => $this->checkSimilarity($poster, 'googlebot') ? $this->ab_sony_psp() : null,
	             '#scnzb' => $this->checkSimilarity($poster, 'nzbs') ? $this->scnzb() : null,
	             default => $this->checkSimilarity($poster, 'alt-bin') ? $this->alt_bin($channel) : null,
	         };
	     }
	 
	     /**
	      * Gets new PRE from #a.b.erotica
	      */
	     protected function ab_erotica(): void
	     {
	         if (preg_match('/ReqId:\[(?P<reqid>\d+)\]\s+\[.+?\]\s+\[FULL\s+(?P<files>\d+x\d+[KMGTP]?B)\s+(?P<title>.+?)\].+?Size:\[(?P<size>.+?)\](.+?\[Pred\s+(?P<predago>.+?)\s+ago\])?(.+?\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)D\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.erotica';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         } elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)]/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.erotica';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.flac
	      */
	     protected function ab_flac(): void
	     {
	         if (preg_match('/Request\s+Filled!\s+ReqId:\[(?P<reqid>\d+)\]\s+\[FULL\s+(?P<files>\d+x\d+[KMGTP]?B)\s+(?P<title>.+?)\].*?(\[Pred\s+(?P<predago>.+?)\s+ago\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.sounds.flac';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         } elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)\]/', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.sounds.flac';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.moovee
	      */
	     protected function ab_moovee(): void
	     {
	         if (preg_match('/ReqId:\[(?P<reqid>\d+)\]\s+\[FULL\s+(?P<files>\d+x\d+[MGPTK]?B)\s+(?P<title>.+?)\]\s+.*?(\[Pred\s+(?P<predago>.+?)\s+ago\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.moovee';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         } elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)\]/', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.moovee';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.foreign
	      */
	     protected function ab_foreign(): void
	     {
	         if (preg_match('/ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<category>.+?)\]\s+\[FULL\s+(?P<files>\d+x\d+[MGPTK]?B)\s+(?P<title>.+?)\]\s+.*?(\[Pred\s+(?P<predago>.+?)\s+ago\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.mom';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         } elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)\]/', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.mom';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.teevee
	      */
	     protected function ab_teevee(): void
	     {
	         if (preg_match('/Request\s+Filled!\s+ReqId:\[(?P<reqid>\d+)\]\s+\[FULL\s+(?P<files>\d+x\d+[KMGPT]?B)\s+(?P<title>.+?)\].*?(\[Pred\s+(?P<predago>.+?)\s+ago\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.teevee';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         } elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)\]/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.teevee';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.console.ps3
	      */
	     protected function ab_console_ps3(): void
	     {
	         if (preg_match('/\s+FULL\s+\d+\s+(?P<title>.+?)\s+(?P<files>\d+x\d+[KMGTP]?B)\s+.+?\]\[ReqID:\s+(?P<reqid>\d+)\]\[/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.console.ps3';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.games.wii
	      */
	     protected function ab_games_wii(string $poster): void
	     {
	         if ($this->checkSimilarity($poster, 'googlebot') && preg_match('/A\s+new\s+NZB\s+has\s+been\s+added:\s+(?P<title>.+?)\s+.+?(?P<files>\d+x\d+[KMGTP]?B)\s+-\s+To.+?file:\s+-sendnzb\s+(?P<reqid>\d+)\s*/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.games.wii';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         } elseif ($this->checkSimilarity($poster, 'binarybot') && preg_match('/added\s+(nuke|reason)\s+info\s+for:\s+(?P<title>.+?)\]\[VCID:\s+(?P<reqid>\d+)\]\[Value:\s+(?P<reason>.+?)\]/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.games.wii';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.games.xbox360
	      */
	     protected function ab_games_xbox360(string $poster): void
	     {
	         if ($this->checkSimilarity($poster, 'googlebot') && preg_match('/A\s+new\s+NZB\s+has\s+been\s+added:\s+(?P<title>.+?)\s+.+?(?P<files>\d+x\d+[KMGTP]?B)\s+-\s+To.+?file:\s+-sendnzb\s+(?P<reqid>\d+)\s*/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.games.xbox360';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         } elseif ($this->checkSimilarity($poster, 'binarybot') && preg_match('/added\s+(nuke|reason)\s+info\s+for:\s+(?P<title>.+?)\]\[VCID:\s+(?P<reqid>\d+)\]\[Value:\s+(?P<reason>.+?)\]/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.games.xbox360';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.sony.psp
	      */
	     protected function ab_sony_psp(): void
	     {
	         if (preg_match('/A NZB is available:\s(?P<title>.+?)\s+.+?(?P<files>\d+x\d+[KMGPT]?B)\s+-.+?file:\s+-sendnzb\s+(?P<reqid>\d+)\s*/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.sony.psp';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.games_nintendods
	      */
	     protected function ab_games_nintendods(): void
	     {
	         if (preg_match('/NEW\s+\[NDS\]\s+PRE:\s+(?P<title>.+)/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.games.nintendods';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'] ?? '';
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #scnzb (boneless)
	      */
	     protected function scnzb(): void
	     {
	         if (preg_match('/\[Complete\]\[(?P<reqid>\d+)\]\s*(?P<title>.+?)\s+NZB:/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.boneless';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.inner-sanctum.
	      */
	     protected function inner_sanctum(): void
	     {
	         if (preg_match('/FILLED\]\s+\[\s+(?P<reqid>\d+)\s+\|\s+(?P<title>.+?)\s+\|\s+(?P<files>\d+x\d+)\s+\|\s+(?P<category>.+?)\s+\|\s+.+?\s+\]\s+\[\s+Pred\s+(?P<predago>.+?)\s+ago\s+\]/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = 'alt.binaries.inner-sanctum';
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Get new PRE from Alt-Bin groups.
	      */
	     protected function alt_bin(string $channel): void
	     {
	         if (preg_match('/Req.+?Id.*?<.*?(?P<reqid>\d+).*?>.*?Request.*?<\d{0,2}(?P<title>.+?)(\s+\*Pars\s+Included\*\d{0,2}>|\d{0,2}>)\s+Files<(?P<files>\d+)>/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = str_replace('#alt.binaries', 'alt.binaries', $channel);
	             $this->curPre['title'] = $this->db->escapeString($matches['title']);
	             $this->curPre['reqid'] = $matches['reqid'];
	             $this->checkForDupe();
	         }
	     }
	 
	     /**
	      * Check if we already have the PRE.
	      */
	     protected function checkForDupe(): bool
	     {
	         if ($this->db->queryOneRow(sprintf('SELECT reqid FROM predb WHERE title = %s', $this->curPre['title'])) === false) {
	             $this->insertNewPre();
	         } else {
	             $this->updatePre();
	         }
	         
	         return true;
	     }
	 
	     /**
	      * Insert new PRE into the DB.
	      */
	     protected function insertNewPre(): void
	     {
	         $this->db->ping(true);
	 
	         $this->db->queryExec(
	             sprintf(
	                 'INSERT INTO predb (groupname, reqid, title) VALUES (%s, %s, %s)',
	                 $this->db->escapeString($this->curPre['source']),
	                 $this->curPre['reqid'],
	                 $this->curPre['title']
	             )
	         );
	 
	         $this->doEcho(true);
	         $this->resetPreVariables();
	     }
	 
	     /**
	      * Updates PRE data in the DB.
	      */
	     protected function updatePre(): void
	     {
	         $this->db->ping(true);
	 
	         $this->db->queryExec(
	             sprintf(
	                 'UPDATE predb SET groupname = %s, reqid = %s WHERE title = %s',
	                 $this->db->escapeString($this->curPre['source']),
	                 $this->curPre['reqid'],
	                 $this->curPre['title']
	             )
	         );
	 
	         $this->doEcho(false);
	         $this->resetPreVariables();
	     }
	 
	     /**
	      * Echo new or update pre to CLI.
	      */
	     protected function doEcho(bool $new = true): void
	     {
	         if (!$this->silent) {
	             echo
	                 '[' .
	                 date('r') .
	                 ($new ? '] [ Added Pre ] [' : '] [Updated Pre] [') .
	                 $this->curPre['source'] .
	                 '] [' .
	                 $this->curPre['reqid'] .
	                 '] [' .
	                 $this->curPre['title'] .
	                 ']' .
	                 PHP_EOL;
	         }
	     }
	 
	     /**
	      * After updating or inserting new PRE, reset these.
	      */
	     protected function resetPreVariables(): void
	     {
	         $this->curPre = [
	             'title'   => '',
	             'source'  => '',
	             'reqid'   => ''
	         ];
	     }
	 }
