<?php
	 declare(strict_types=1);
	 
	 require_once('DB.php');
	 require_once('IRCClient.php');
	 
	 /**
	  * Class IRCScraper
	  */
	 class IRCScraper extends IRCClient
	 {
	     /**
	      * Array of current pre info.
	      */
	     protected array $curPre = [];
	     
	     /**
	      * Previous pre information.
	      */
	     protected string|bool|array|\PDOStatement $oldPre = [];
	 
	     /**
	      * List of groups and their ID's
	      */
	     protected array $groupList = [];
	 
	     /**
	      * Current server.
	      * efnet | corrupt
	      */
	     protected string $serverType;
	 
	     /**
	      * Run this in silent mode (no text output).
	      */
	     protected bool $silent;
	 
	     /**
	      * Is this pre nuked or un nuked?
	      */
	     protected bool $nuked = false;
	 
	     /**
	      * Database connection.
	      */
	     protected readonly \nzedb\db\DB $db;
	 
	     /**
	      * Construct
	      *
	      * @param string $serverType efnet | corrupt
	      * @param bool $silent Run this in silent mode (no text output)
	      */
	     public function __construct(string $serverType, bool &$silent = false)
	     {
	         $this->db = new \nzedb\db\DB();
	         $this->groupList = [];
	         $this->serverType = $serverType;
	         $this->silent = $silent;
	         $this->debug = ((defined('EFNET_BOT_DEBUG') && EFNET_BOT_DEBUG) || (defined('CORRUPT_BOT_DEBUG') && CORRUPT_BOT_DEBUG));
	         $this->resetPreVariables();
	         $this->startScraping();
	     }
	 
	     /**
	      * Main method for scraping.
	      */
	     protected function startScraping(): void
	     {
	         $server = '';
	         $port = 0;
	         $nickname = '';
	         $username = '';
	         $realName = '';
	         $password = null;
	         $tls = false;
	         $channelList = [];
	         
	         switch($this->serverType) {
	             case 'efnet':
	                 $server   = EFNET_BOT_SERVER;
	                 $port     = EFNET_BOT_PORT;
	                 $nickname = EFNET_BOT_NICKNAME;
	                 $username = EFNET_BOT_USERNAME;
	                 $realName = EFNET_BOT_REALNAME;
	                 $password = EFNET_BOT_PASSWORD;
	                 $tls      = EFNET_BOT_ENCRYPTION;
	                 $channelList = unserialize(EFNET_BOT_CHANNELS);
	                 break;
	 
	             case 'corrupt':
	                 $server      = CORRUPT_BOT_HOST;
	                 $port        = CORRUPT_BOT_PORT;
	                 $nickname    = CORRUPT_BOT_NICKNAME;
	                 $username    = CORRUPT_BOT_USERNAME;
	                 $realName    = CORRUPT_BOT_REALNAME;
	                 $password    = CORRUPT_BOT_PASSWORD;
	                 $tls         = CORRUPT_BOT_ENCRYPTION;
	                 $channelList = ['#pre' => null];
	                 break;
	 
	             default:
	                 return;
	         }
	 
	         // Connect to IRC.
	         if (!$this->connect($server, $port, $tls)) {
	             exit(
	                 "Error connecting to ({$server}:{$port}). " .
	                 "Please verify your server information and try again." .
	                 PHP_EOL
	             );
	         }
	 
	         // Login to IRC.
	         if (!$this->login($nickname, $username, $realName, $password)) {
	             exit(
	                 "Error logging in to: ({$server}:{$port}) nickname: ({$nickname}). " .
	                 "Verify your connection information, you might also be banned from this server " .
	                 "or there might have been a connection issue." .
	                 PHP_EOL
	             );
	         }
	 
	         // Join channels.
	         $this->joinChannels($channelList);
	 
	         if (!$this->silent) {
	             echo "[" . date('r') . "] [Scraping of IRC channels for ({$server}:{$port}) ({$nickname}) started.]" . PHP_EOL;
	         }
	 
	         // Scan incoming IRC messages.
	         $this->readIncoming();
	     }
	 
	     /**
	      * Check the similarity between 2 words.
	      */
	     protected function checkSimilarity(string &$word1, string $word2, int $similarity = 49): bool
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
	         $poster  = strtolower($this->channelData['nickname']);
	 
	         switch($channel) {
	             case '#pre':
	                 if ($this->checkSimilarity($poster, 'pr3')) {
	                     $this->corrupt_pre();
	                 }
	                 break;
	 
	             case '#alt.binaries.inner-sanctum':
	                 if ($this->checkSimilarity($poster, 'sanctum')) {
	                     $this->inner_sanctum();
	                 }
	                 break;
	 
	             case '#alt.binaries.erotica':
	                 if ($this->checkSimilarity($poster, 'ginger') || $this->checkSimilarity($poster, 'g1nger')) {
	                     $this->ab_erotica();
	                 }
	                 break;
	 
	             case '#alt.binaries.flac':
	                 if ($this->checkSimilarity($poster, 'abflac')) {
	                     $this->ab_flac();
	                 }
	                 break;
	 
	             case '#alt.binaries.moovee':
	                 if ($this->checkSimilarity($poster, 'abking')) {
	                     $this->ab_moovee();
	                 }
	                 break;
	 
	             case '#alt.binaries.teevee':
	                 if ($this->checkSimilarity($poster, 'abgod')) {
	                     $this->ab_teevee();
	                 }
	                 break;
	 
	             case '#alt.binaries.foreign':
	                 if ($this->checkSimilarity($poster, 'abqueen')) {
	                     $this->ab_foreign();
	                 }
	                 break;
	 
	             case '#alt.binaries.console.ps3':
	                 if ($this->checkSimilarity($poster, 'binarybot')) {
	                     $this->ab_console_ps3();
	                 }
	                 break;
	 
	             case '#alt.binaries.games.nintendods':
	                 if ($this->checkSimilarity($poster, 'binarybot')) {
	                     $this->ab_games_nintendods();
	                 }
	                 break;
	 
	             case '#alt.binaries.games.wii':
	                 if ($this->checkSimilarity($poster, 'binarybot') || $this->checkSimilarity($poster, 'googlebot')) {
	                     $this->ab_games_wii($poster);
	                 }
	                 break;
	 
	             case '#alt.binaries.games.xbox360':
	                 if ($this->checkSimilarity($poster, 'binarybot') || $this->checkSimilarity($poster, 'googlebot')) {
	                     $this->ab_games_xbox360($poster);
	                 }
	                 break;
	 
	             case '#alt.binaries.sony.psp':
	                 if ($this->checkSimilarity($poster, 'googlebot')) {
	                     $this->ab_sony_psp();
	                 }
	                 break;
	 
	             case '#scnzb':
	                 if ($this->checkSimilarity($poster, 'nzbs')) {
	                     $this->scnzb();
	                 }
	                 break;
	 
	             case '#tvnzb':
	                 if ($this->checkSimilarity($poster, 'tweetie')) {
	                     $this->tvnzb();
	                 }
	                 break;
	 
	             default:
	                 if ($this->checkSimilarity($poster, 'alt-bin')) {
	                     $this->alt_bin($channel);
	                 }
	         }
	     }
	 
	     /**
	      * Get pre date from wD xH yM zS ago string.
	      */
	     protected function getTimeFromAgo(string $agoString): void
	     {
	         $predate = 0;
	         // Get pre date from this format : 10m 54s
	         if (preg_match('/((?P<day>\d+)d)?\s*((?P<hour>\d+)h)?\s*((?P<min>\d+)m)?\s*((?P<sec>\d+)s)?/i', $agoString, $matches)) {
	             $predate += !empty($matches['day']) ? ((int)($matches['day']) * 86400) : 0;
	             $predate += !empty($matches['hour']) ? ((int)($matches['hour']) * 3600) : 0;
	             $predate += !empty($matches['min']) ? ((int)($matches['min']) * 60) : 0;
	             $predate += !empty($matches['sec']) ? (int)$matches['sec'] : 0;
	             
	             if ($predate !== 0) {
	                 $predate = (time() - $predate);
	             }
	         }
	         $this->curPre['predate'] = ($predate === 0 ? '' : $this->db->from_unixtime($predate));
	     }
	 
	     /**
	      * Go through regex matches, find PRE info.
	      */
	     protected function siftMatches(array &$matches): void
	     {
	         $this->curPre['md5'] = $this->db->escapeString(md5($matches['title']));
	         $this->curPre['sha1'] = $this->db->escapeString(sha1($matches['title']));
	         $this->curPre['title'] = $matches['title'];
	 
	         if (isset($matches['reqid'])) {
	             $this->curPre['reqid'] = $matches['reqid'];
	         }
	         if (isset($matches['size'])) {
	             $this->curPre['size'] = $matches['size'];
	         }
	         if (isset($matches['predago'])) {
	             $this->getTimeFromAgo($matches['predago']);
	         }
	         if (isset($matches['category'])) {
	             $this->curPre['category'] = $matches['category'];
	         }
	         if (isset($matches['nuke'])) {
	             $this->nuked = true;
	             $this->curPre['nuked'] = match ($matches['nuke']) {
	                 'NUKE' => 2,
	                 'UNNUKE' => 1,
	                 'MODNUKE' => 3,
	                 'RENUKE' => 4,
	                 'OLDNUKE' => 5,
	                 default => 0,
	             };
	         }
	         if (isset($matches['reason'])) {
	             $this->curPre['reason'] = substr($matches['reason'], 0, 255);
	         }
	         if (isset($matches['files'])) {
	             $this->curPre['files'] = substr($matches['files'], 0, 50);
	 
	             // If the pre has no size, try to get one from files.
	             if (empty($this->oldPre['size']) && empty($this->curPre['size'])) {
	                 if (preg_match('/(?P<files>\d+)x(?P<size>\d+)\s*(?P<ext>[KMGTP]?B)\s*$/i', $matches['files'], $match)) {
	                     $this->curPre['size'] = ((int)$match['files'] * (int)$match['size']) . $match['ext'];
	                 }
	             }
	         }
	         if (isset($matches['filename'])) {
	             $this->curPre['filename'] = substr($matches['filename'], 0, 255);
	         }
	         $this->checkForDupe();
	     }
	 
	     /**
	      * Gets new PRE from #a.b.erotica
	      */
	     protected function ab_erotica(): void
	     {
	         //That was awesome [*Anonymous*] Shall we do it again? ReqId:[326264] [HD-Clip] [FULL 16x50MB TeenSexMovs.14.03.30.Daniela.XXX.720p.WMV-iaK] Filenames:[iak-teensexmovs-140330] Comments:[0] Watchers:[0] Total Size:[753MB] Points Earned:[54] [Pred 3m 20s ago]
	         if (preg_match('/ReqId:\[(?P<reqid>\d+)\]\s+\[.+?\]\s+\[FULL\s+(?P<files>\d+x\d+[KMGTP]?B)\s+(?P<title>.+?)\].+?Filenames:\[(?P<filename>.+?)\].+?Size:\[(?P<size>.+?)\](.+?\[Pred\s+(?P<predago>.+?)\s+ago\])?(.+?\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)D\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.erotica';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.erotica');
	             $this->curPre['category'] = 'XXX';
	             $this->siftMatches($matches);
	         }
	         //[NUKE] ReqId:[326663] [Young.Ripe.Mellons.10.XXX.720P.WEBRIP.X264-GUSH] Reason:[selfdupe.2014-03-09]
	         elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)]/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.erotica';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.erotica');
	             $this->curPre['category'] = 'XXX';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.flac
	      */
	     protected function ab_flac(): void
	     {
	         //Thank You [*Anonymous*] Request Filled! ReqId:[42614] [FULL 10x15MB You_Blew_It-Keep_Doing_What_Youre_Doing-CD-FLAC-2014-WRE] Requested by:[*Anonymous* 21s ago] Comments:[0] Watchers:[0] Points Earned:[10] [Pred 3m 16s ago]
	         if (preg_match('/Request\s+Filled!\s+ReqId:\[(?P<reqid>\d+)\]\s+\[FULL\s+(?P<files>\d+x\d+[KMGTP]?B)\s+(?P<title>.+?)\].*?(\[Pred\s+(?P<predago>.+?)\s+ago\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.flac';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.sounds.flac');
	             $this->curPre['category'] = 'FLAC';
	             $this->siftMatches($matches);
	         }
	         //[NUKE] ReqId:[67048] [A.Certain.Justice.2014.FRENCH.BDRip.x264-COUAC] Reason:[pred.without.proof]
	         elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)\]/', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.flac';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.sounds.flac');
	             $this->curPre['category'] = 'FLAC';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.moovee
	      */
	     protected function ab_moovee(): void
	     {
	         //Thank You [*Anonymous*] Request Filled! ReqId:[140445] [FULL 94x50MB Burning.Daylight.2010.720p.BluRay.x264-SADPANDA] Requested by:[*Anonymous* 3h 29m ago] Comments:[0] Watchers:[0] Points Earned:[314] [Pred 4h 29m ago]
	         if (preg_match('/ReqId:\[(?P<reqid>\d+)\]\s+\[FULL\s+(?P<files>\d+x\d+[MGPTK]?B)\s+(?P<title>.+?)\]\s+.*?(\[Pred\s+(?P<predago>.+?)\s+ago\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.moovee';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.moovee');
	             $this->curPre['category'] = 'Movies';
	             $this->siftMatches($matches);
	         }
	         //[NUKE] ReqId:[130274] [NOVA.The.Bibles.Buried.Secrets.2008.DVDRip.XviD-FiCO] Reason:[field.shifted_oi47.tinypic.com.24evziv.jpg]
	         elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)\]/', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.moovee';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.moovee');
	             $this->curPre['category'] = 'Movies';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.foreign
	      */
	     protected function ab_foreign(): void
	     {
	         //Thank You [*Anonymous*] Request Filled! ReqId:[61525] [Movie] [FULL 95x50MB Wadjda.2012.PAL.MULTI.DVDR-VIAZAC] Requested by:[*Anonymous* 5m 13s ago] Comments:[0] Watchers:[0] Points Earned:[317] [Pred 8m 27s ago]
	         if (preg_match('/ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<category>.+?)\]\s+\[FULL\s+(?P<files>\d+x\d+[MGPTK]?B)\s+(?P<title>.+?)\]\s+.*?(\[Pred\s+(?P<predago>.+?)\s+ago\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']  = '#a.b.foreign';
	             $this->curPre['groupid'] = $this->getGroupID('alt.binaries.mom');
	             $this->siftMatches($matches);
	         }
	         //[NUKE] ReqId:[67048] [A.Certain.Justice.2014.FRENCH.BDRip.x264-COUAC] Reason:[pred.without.proof]
	         elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)\]/', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.foreign';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.mom');
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.teevee
	      */
	     protected function ab_teevee(): void
	     {
	         //Thank You [*Anonymous*] Request Filled! ReqId:[183520] [FULL 19x50MB Louis.Therouxs.LA.Stories.S01E02.720p.HDTV.x264-FTP] Requested by:[*Anonymous* 53s ago] Comments:[0] Watchers:[0] Points Earned:[64] [Pred 3m 45s ago]
	         if (preg_match('/Request\s+Filled!\s+ReqId:\[(?P<reqid>\d+)\]\s+\[FULL\s+(?P<files>\d+x\d+[KMGPT]?B)\s+(?P<title>.+?)\].*?(\[Pred\s+(?P<predago>.+?)\s+ago\])?/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.teevee';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.teevee');
	             $this->curPre['category'] = 'TV';
	             $this->siftMatches($matches);
	         }
	         //[NUKE] ReqId:[183497] [From.Dusk.Till.Dawn.S01E01.720p.HDTV.x264-BATV] Reason:[bad.ivtc.causing.jerky.playback.due.to.dupe.and.missing.frames.in.segment.from.16m.to.30m]
	         //[UNNUKE] ReqId:[183449] [The.Biggest.Loser.AU.S09E29.PDTV.x264-RTA] Reason:[get.samplefix]
	         elseif (preg_match('/\[(?P<nuke>(MOD|OLD|RE|UN)?NUKE)\]\s+ReqId:\[(?P<reqid>\d+)\]\s+\[(?P<title>.+?)\]\s+Reason:\[(?P<reason>.+?)\]/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.teevee';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.teevee');
	             $this->curPre['category'] = 'TV';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.console.ps3
	      */
	     protected function ab_console_ps3(): void
	     {
	         //[Anonymous person filling request for: FULL 56 Ragnarok.Odyssey.ACE.PS3-iMARS NTSC BLURAY imars-ragodyace-ps3 56x100MB by Khaine13 on 2014-03-29 13:14:12][ReqID: 4888][You get a bonus of 6 for a total points earning of: 62 for filling with 10% par2s!][Your score will be adjusted once you have -filled 4888]
	         if (preg_match('/\s+FULL\s+\d+\s+(?P<title>.+?)\s+.+\s+(?P<filename>.+?)\s+(?P<files>\d+x\d+[KMGTP]?B)\s+.+?\]\[ReqID:\s+(?P<reqid>\d+)\]\[/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.console.ps3';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.console.ps3');
	             $this->curPre['category'] = 'PS3';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.games.wii
	      */
	     protected function ab_games_wii(string &$poster): void
	     {
	         //A new NZB has been added: Go_Diego_Go_Great_Dinosaur_Rescue_PAL_WII-ZER0 PAL DVD5 zer0-gdggdr 93x50MB - To download this file: -sendnzb 12811
	         if ($this->checkSimilarity($poster, 'googlebot') && preg_match('/A\s+new\s+NZB\s+has\s+been\s+added:\s+(?P<title>.+?)\s+.+\s+(?P<filename>.+?)\s+(?P<files>\d+x\d+[KMGTP]?B)\s+-\s+To.+?file:\s+-sendnzb\s+(?P<reqid>\d+)\s*/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.games.wii';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.games.wii');
	             $this->curPre['category'] = 'WII';
	             $this->siftMatches($matches);
	         }
	         //[kiczek added reason info for: Samurai_Shodown_IV_-_Amakusas_Revenge_USA_VC_NEOGEO_Wii-OneUp][VCID: 5027][Value: bad.dirname_bad.filenames_get.repack]
	         elseif ($this->checkSimilarity($poster, 'binarybot') && preg_match('/added\s+(nuke|reason)\s+info\s+for:\s+(?P<title>.+?)\]\[VCID:\s+(?P<reqid>\d+)\]\[Value:\s+(?P<reason>.+?)\]/i', $this->channelData['message'], $matches)) {
	             $matches['nuke']          = 'NUKE';
	             $this->curPre['source']   = '#a.b.games.wii';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.games.wii');
	             $this->curPre['category'] = 'WII';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.games.xbox360
	      */
	     protected function ab_games_xbox360(string &$poster): void
	     {
	         //A new NZB has been added: South.Park.The.Stick.of.Truth.PAL.XBOX360-COMPLEX PAL DVD9 complex-south.park.sot 74x100MB - To download this file: -sendnzb 19909
	         if ($this->checkSimilarity($poster, 'googlebot') && preg_match('/A\s+new\s+NZB\s+has\s+been\s+added:\s+(?P<title>.+?)\s+.+\s+(?P<filename>.+?)\s+(?P<files>\d+x\d+[KMGTP]?B)\s+-\s+To.+?file:\s+-sendnzb\s+(?P<reqid>\d+)\s*/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.games.xbox360';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.games.xbox360');
	             $this->curPre['category'] = 'XBOX360';
	             $this->siftMatches($matches);
	         }
	         //[egres added nuke info for: Injustice.Gods.Among.Us.XBOX360-SWAG][GameID: 7088][Value: Y]
	         elseif ($this->checkSimilarity($poster, 'binarybot') && preg_match('/added\s+(nuke|reason)\s+info\s+for:\s+(?P<title>.+?)\]\[VCID:\s+(?P<reqid>\d+)\]\[Value:\s+(?P<reason>.+?)\]/i', $this->channelData['message'], $matches)) {
	             $matches['nuke']          = 'NUKE';
	             $this->curPre['source']   = '#a.b.games.xbox360';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.games.xbox360');
	             $this->curPre['category'] = 'XBOX360';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.sony.psp
	      */
	     protected function ab_sony_psp(): void
	     {
	         //A NZB is available: Satomi_Hakkenden_Hachitama_no_Ki_JPN_PSP-MOEMOE JAP UMD moe-satomi 69x20MB - To download this file: -sendnzb 21924
	         if (preg_match('/A NZB is available:\s(?P<title>.+?)\s+.+\s+(?P<filename>.+?)\s+(?P<files>\d+x\d+[KMGPT]?B)\s+-.+?file:\s+-sendnzb\s+(?P<reqid>\d+)\s*/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.sony.psp';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.sony.psp');
	             $this->curPre['category'] = 'PSP';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.games_nintendods
	      */
	     protected function ab_games_nintendods(): void
	     {
	         //NEW [NDS] PRE: Honda_ATV_Fever_USA_NDS-EXiMiUS
	         if (preg_match('/NEW\s+\[NDS\]\s+PRE:\s+(?P<title>.+)/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']   = '#a.b.games.nintendods';
	             $this->curPre['groupid']  = $this->getGroupID('alt.binaries.games.nintendods');
	             $this->curPre['category'] = 'NDS';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #scnzb (boneless)
	      */
	     protected function scnzb(): void
	     {
	         //[Complete][512754] Formula1.2014.Malaysian.Grand.Prix.Team.Principals.Press.Conference.720p.HDTV.x264-W4F  NZB: http://scnzb.eu/1pgOmwj
	         if (preg_match('/\[Complete\]\[(?P<reqid>\d+)\]\s*(?P<title>.+?)\s+NZB:/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']  = '#scnzb';
	             $this->curPre['groupid'] = $this->getGroupID('alt.binaries.boneless');
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #tvnzb (sickbeard)
	      */
	     protected function tvnzb(): void
	     {
	         //[SBINDEX] Rev.S03E02.HDTV.x264-TLA :: TV > HD :: 210.13 MB :: Aired: 31/Mar/2014 :: http://lolo.sickbeard.com/getnzb/aa10bcef235c604612dd61b0627ae25f.nzb
	         if (preg_match('/\[SBINDEX\]\s+(?P<title>.+?)\s+::\s+(?P<sbcat>.+?)\s+::\s+(?P<size>.+?)\s+::\s+Aired/i', $this->channelData['message'], $matches)) {
	             if (preg_match('/^(?P<first>.+?)\s+>\s+(?P<last>.+?)$/', $matches['sbcat'], $match)) {
	                 $matches['category'] = "{$match['first']}-{$match['last']}";
	             }
	             $this->curPre['source'] = '#tvnzb';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #pre on Corrupt-net
	      */
	     protected function corrupt_pre(): void
	     {
	         //PRE: [TV-X264] Tinga.Tinga.Fabeln.S02E11.Warum.Bienen.stechen.GERMAN.WS.720p.HDTV.x264-RFG
	         if (preg_match('/^PRE:\s+\[(?P<category>.+?)\]\s+(?P<title>.+)$/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = '#pre@corrupt';
	             $this->siftMatches($matches);
	         }
	         //NUKE: Miclini-Sunday_Morning_P1-DIRFIX-DAB-03-30-2014-G4E [dirfix.must.state.name.of.release.being.fixed] [EthNet]
	         //UNNUKE: Youssoupha-Sur_Les_Chemins_De_Retour-FR-CD-FLAC-2009-0MNi [flac.rule.4.12.states.ENGLISH.artist.and.title.must.be.correct.and.this.is.not.ENGLISH] [LocalNet]
	         //MODNUKE: Miclini-Sunday_Morning_P1-DIRFIX-DAB-03-30-2014-G4E [nfo.must.state.name.of.release.being.fixed] [EthNet]
	         elseif (preg_match('/(?P<nuke>(MOD|OLD|RE|UN)?NUKE):\s+(?P<title>.+?)\s+\[(?P<reason>.+?)\]/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source'] = '#pre@corrupt';
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Gets new PRE from #a.b.inner-sanctum.
	      */
	     protected function inner_sanctum(): void
	     {
	         //[FILLED] [ 341953 | Emilie_Simon-Mue-CD-FR-2014-JUST | 16x79 | MP3 | *Anonymous* ] [ Pred 10m 54s ago ]
	         if (preg_match('/FILLED\]\s+\[\s+(?P<reqid>\d+)\s+\|\s+(?P<title>.+?)\s+\|\s+(?P<files>\d+x\d+)\s+\|\s+(?P<category>.+?)\s+\|\s+.+?\s+\]\s+\[\s+Pred\s+(?P<predago>.+?)\s+ago\s+\]/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']  = '#a.b.inner-sanctum';
	             $this->curPre['groupid'] = $this->getGroupID('alt.binaries.inner-sanctum');
	             $this->siftMatches($matches);
	         }
	     }
	 
	     /**
	      * Get new PRE from Alt-Bin groups.
	      */
	     protected function alt_bin(string &$channel): void
	     {
	         //Thank you<Bijour> Req Id<137732> Request<The_Blueprint-Phenomenology-(Retail)-2004-KzT *Pars Included*> Files<19> Dates<Req:2014-03-24 Filling:2014-03-29> Points<Filled:1393 Score:25604>
	         if (preg_match('/Req.+?Id.*?<.*?(?P<reqid>\d+).*?>.*?Request.*?<\d{0,2}(?P<title>.+?)(\s+\*Pars\s+Included\*\d{0,2}>|\d{0,2}>)\s+Files<(?P<files>\d+)>/i', $this->channelData['message'], $matches)) {
	             $this->curPre['source']  = str_replace('#alt.binaries', '#a.b', $channel);
	             $this->curPre['groupid'] = $this->getGroupID(str_replace('#', '', $channel));
	             $this->siftMatches($matches);
	         }
	     }
	     
	     /**
	      * Check for duplicate entries
	      */
	     protected function checkForDupe(): void
	     {
	         $this->oldPre = $this->db->queryOneRow(sprintf('SELECT category, size FROM predb WHERE md5 = %s', $this->curPre['md5']));
	         
	         if ($this->oldPre === false) {
	             $this->insertNewPre();
	         } else {
	             $this->updatePre();
	         }
	     }
	 
	     /**
	      * Insert new PRE into the DB.
	      */
	     protected function insertNewPre(): void
	     {
	         if (empty($this->curPre['title'])) {
	             return;
	         }
	 
	         $fields = [];
	         $values = [];
	 
	         if (!empty($this->curPre['size'])) {
	             $fields[] = 'size';
	             $values[] = $this->db->escapeString($this->curPre['size']);
	         }
	         
	         if (!empty($this->curPre['category'])) {
	             $fields[] = 'category';
	             $values[] = $this->db->escapeString($this->curPre['category']);
	         }
	         
	         if (!empty($this->curPre['source'])) {
	             $fields[] = 'source';
	             $values[] = $this->db->escapeString($this->curPre['source']);
	         }
	         
	         if (!empty($this->curPre['reason'])) {
	             $fields[] = 'nukereason';
	             $values[] = $this->db->escapeString($this->curPre['reason']);
	         }
	         
	         if (!empty($this->curPre['files'])) {
	             $fields[] = 'files';
	             $values[] = $this->db->escapeString($this->curPre['files']);
	         }
	         
	         if (!empty($this->curPre['reqid'])) {
	             $fields[] = 'requestid';
	             $values[] = $this->curPre['reqid'];
	         }
	         
	         if (!empty($this->curPre['groupid'])) {
	             $fields[] = 'groupid';
	             $values[] = $this->curPre['groupid'];
	         }
	         
	         if (!empty($this->curPre['nuked'])) {
	             $fields[] = 'nuked';
	             $values[] = $this->curPre['nuked'];
	         }
	         
	         if (!empty($this->curPre['filename'])) {
	             $fields[] = 'filename';
	             $values[] = $this->db->escapeString($this->curPre['filename']);
	         }
	         
	         // Add required fields
	         $fields[] = 'predate';
	         $values[] = !empty($this->curPre['predate']) ? $this->curPre['predate'] : 'NOW()';
	         
	         $fields[] = 'md5';
	         $values[] = $this->curPre['md5'];
	         
	         $fields[] = 'sha1';
	         $values[] = $this->curPre['sha1'];
	         
	         $fields[] = 'title';
	         $values[] = $this->db->escapeString($this->curPre['title']);
	 
	         $query = 'INSERT INTO predb (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
	 
	         $this->db->ping(true);
	         $this->db->queryExec($query);
	 
	         $this->doEcho(true);
	         $this->resetPreVariables();
	     }
	 
	     /**
	      * Updates PRE data in the DB.
	      */
	     protected function updatePre(): void
	     {
	         if (empty($this->curPre['title'])) {
	             return;
	         }
	 
	         $updates = [];
	 
	         if (!empty($this->curPre['size'])) {
	             $updates[] = 'size = ' . $this->db->escapeString($this->curPre['size']);
	         }
	         
	         if (!empty($this->curPre['source'])) {
	             $updates[] = 'source = ' . $this->db->escapeString($this->curPre['source']);
	         }
	         
	         if (!empty($this->curPre['files'])) {
	             $updates[] = 'files = ' . $this->db->escapeString($this->curPre['files']);
	         }
	         
	         if (!empty($this->curPre['reason'])) {
	             $updates[] = 'nukereason = ' . $this->db->escapeString($this->curPre['reason']);
	         }
	         
	         if (!empty($this->curPre['reqid'])) {
	             $updates[] = 'requestid = ' . $this->curPre['reqid'];
	         }
	         
	         if (!empty($this->curPre['groupid'])) {
	             $updates[] = 'groupid = ' . $this->curPre['groupid'];
	         }
	         
	         if (!empty($this->curPre['predate'])) {
	             $updates[] = 'predate = ' . $this->curPre['predate'];
	         }
	         
	         if (!empty($this->curPre['nuked'])) {
	             $updates[] = 'nuked = ' . $this->curPre['nuked'];
	         }
	         
	         if (!empty($this->curPre['filename'])) {
	             $updates[] = 'filename = ' . $this->db->escapeString($this->curPre['filename']);
	         }
	         
	         if (empty($this->oldPre['category']) && !empty($this->curPre['category'])) {
	             $updates[] = 'category = ' . $this->db->escapeString($this->curPre['category']);
	         }
	 
	         if (empty($updates)) {
	             return;
	         }
	 
	         $updates[] = 'title = ' . $this->db->escapeString($this->curPre['title']);
	         $updates[] = 'shared = -1';
	 
	         $query = 'UPDATE predb SET ' . implode(', ', $updates) . ' WHERE md5 = ' . $this->curPre['md5'];
	 
	         $this->db->ping(true);
	         $this->db->queryExec($query);
	 
	         $this->doEcho(false);
	         $this->resetPreVariables();
	     }
	 
	     /**
	      * Echo new or update pre to CLI.
	      */
	     protected function doEcho(bool $new = true): void
	     {
	         if ($this->silent) {
	             return;
	         }
	 
	         $nukeString = '';
	         if ($this->nuked) {
	             $nukeString = match ((int)$this->curPre['nuked']) {
	                 2 => '[ NUKED ] ',
	                 1 => '[UNNUKED] ',
	                 3 => '[MODNUKE] ',
	                 5 => '[OLDNUKE] ',
	                 4 => '[RENUKED] ',
	                 default => '',
	             };
	             
	             if (!empty($nukeString)) {
	                 $nukeString .= "[{$this->curPre['reason']}] ";
	             }
	         }
	 
	         $category = !empty($this->curPre['category'])
	             ? " [{$this->curPre['category']}]"
	             : (!empty($this->oldPre['category']) ? " [{$this->oldPre['category']}]" : '');
	             
	         $size = !empty($this->curPre['size']) ? " [{$this->curPre['size']}]" : '';
	 
	         echo "[" . date('r') .
	              "] [" . ($new ? "Added Pre" : "Updated Pre") .
	              "] [{$this->curPre['source']}] " .
	              $nukeString .
	              "[{$this->curPre['title']}]" .
	              $category .
	              $size .
	              PHP_EOL;
	     }
	 
	     /**
	      * Get a group ID for a group name.
	      */
	     protected function getGroupID(string $groupName): mixed
	     {
	         if (!isset($this->groupList[$groupName])) {
	             $group = $this->db->queryOneRow(sprintf('SELECT id FROM groups WHERE name = %s', $this->db->escapeString($groupName)));
	             $this->groupList[$groupName] = $group['id'] ?? null;
	         }
	         return $this->groupList[$groupName];
	     }
	 
	     /**
	      * After updating or inserting new PRE, reset these.
	      */
	     protected function resetPreVariables(): void
	     {
	         $this->nuked = false;
	         $this->oldPre = [];
	         $this->curPre = [
	             'title'    => '',
	             'md5'      => '',
	             'sha1'     => '',
	             'size'     => '',
	             'predate'  => '',
	             'category' => '',
	             'source'   => '',
	             'groupid'  => '',
	             'reqid'    => '',
	             'nuked'    => '',
	             'reason'   => '',
	             'files'    => '',
	             'filename' => ''
	         ];
	     }
	 }
