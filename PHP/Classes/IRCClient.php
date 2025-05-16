<?php

declare(strict_types=1);

/**
 * Basic IRC client for fetching IRCScraper.
 *
 * Class IRCClient
 */
class IRCClient
{
    /**
     * Hostname IRC server used when connecting.
     */
    protected string $remoteHost = '';

    /**
     * Port number IRC server.
     */
    protected int $remotePort = 6667;

    /**
     * Socket transport type for the IRC server.
     */
    protected string $remoteTransport = 'tcp';

    /**
     * Hostname the IRC server sent us back.
     */
    protected string $remoteHostReceived = '';

    /**
     * String used when creating the stream socket.
     */
    protected string $remoteSocketString = '';

    /**
     * Are we using tls/ssl?
     */
    protected bool $remoteTls = false;

    /**
     * Time in seconds to timeout on connecting.
     */
    protected int $remoteConnectionTimeout = 30;

    /**
     * Time in seconds before we time out when sending/receiving a command.
     */
    protected int $socketTimeout = 180;

    /**
     * How many times to retry when connecting to IRC.
     */
    protected int $reconnectRetries = 3;

    /**
     * Seconds to delay when reconnecting fails.
     */
    protected int $reconnectDelay = 5;

    /**
     * Stream socket client.
     * @var resource|null
     */
    protected $socket = null;

    /**
     * Buffer contents.
     */
    protected ?string $buffer = null;

    /**
     * When someone types something into a channel, buffer it.
     * [
     *     'nickname' => string(The nickname of the person who posted.),
     *     'channel'  => string(The channel name.),
     *     'message'  => string(The message the person posted.)
     * ]
     * @note Used with the processChannelMessages() function.
     */
    protected array $channelData = [];

    /**
     * Nickname when we log in.
     */
    protected string $nickName = '';

    /**
     * Username when we log in.
     */
    protected string $userName = '';

    /**
     * "Real" name when we log in.
     */
    protected string $realName = '';

    /**
     * Password when we log in.
     */
    protected ?string $password = null;

    /**
     * List of channels and passwords to join.
     */
    protected array $channels = [];

    /**
     * Last time we received a ping or sent a ping to the server.
     */
    protected int $lastPing = 0;

    /**
     * How many times we've tried to reconnect to IRC.
     */
    protected int $currentRetries = 0;

    /**
     * Turns on or off debugging.
     */
    protected bool $debug = true;

    /**
     * Are we already logged in to IRC?
     */
    protected bool $alreadyLoggedIn = false;

    /**
     * How many attempts have we tried to write to the socket?
     */
    protected int $writeAttempts = 0;

    /**
     * Disconnect from IRC.
     */
    public function __destruct()
    {
        $this->quit();
    }

    /**
     * Time before giving up when trying to read or write to the IRC server.
     * The default is fine, it will ping the server if the server does not ping us
     * within this time to keep the connection alive.
     */
    public function setSocketTimeout(int $timeout): void
    {
        if (!is_numeric($timeout)) {
            echo 'ERROR: IRC socket timeout must be a number!' . PHP_EOL;
        } else {
            $this->socketTimeout = $timeout;
        }
    }

    /**
     * Amount of time to wait before giving up when connecting.
     */
    public function setConnectionTimeout(int $timeout): void
    {
        if (!is_numeric($timeout)) {
            echo 'ERROR: IRC connection timeout must be a number!' . PHP_EOL;
        } else {
            $this->remoteConnectionTimeout = $timeout;
        }
    }

    /**
     * Number of times to retry before giving up when connecting.
     */
    public function setConnectionRetries(int $retries): void
    {
        if (!is_numeric($retries)) {
            echo 'ERROR: IRC connection retries must be a number!' . PHP_EOL;
        } else {
            $this->reconnectRetries = $retries;
        }
    }

    /**
     * Amount of time to wait between failed connecting.
     */
    public function setReConnectDelay(int $delay): void
    {
        if (!is_numeric($delay)) {
            echo 'ERROR: IRC reconnect delay must be a number!' . PHP_EOL;
        } else {
            $this->reconnectDelay = $delay;
        }
    }

    /**
     * Connect to an IRC server.
     *
     * @param string $hostname Host name of the IRC server (can be a IP or a name).
     * @param int    $port     Port number of the IRC server.
     * @param bool   $tls      Use encryption for the socket transport? (make sure the port is right).
     */
    public function connect(string $hostname, int $port = 6667, bool $tls = false): bool
    {
        $this->alreadyLoggedIn = false;
        $transport = $tls ? 'tls' : 'tcp';

        $socketString = "{$transport}://{$hostname}:{$port}";
        if ($socketString !== $this->remoteSocketString || !$this->isConnected()) {
            if ($hostname === '') {
                echo 'ERROR: IRC host name must not be empty!' . PHP_EOL;
                return false;
            }

            if (!is_numeric($port)) {
                echo 'ERROR: IRC port must be a number!' . PHP_EOL;
                return false;
            }

            $this->remoteHost = $hostname;
            $this->remotePort = $port;
            $this->remoteTransport = $transport;
            $this->remoteTls = $tls;
            $this->remoteSocketString = $socketString;

            // Try to connect until we run out of retries.
            while ($this->reconnectRetries >= $this->currentRetries++) {
                $this->initiateStream();
                if ($this->isConnected()) {
                    break;
                } else {
                    // Sleep between retries.
                    sleep($this->reconnectDelay);
                }
            }
        } else {
            $this->alreadyLoggedIn = true;
        }

        // Set the last ping time to current time.
        $this->lastPing = time();
        // Reset retries.
        $this->currentRetries = $this->reconnectRetries;
        return $this->isConnected();
    }

    /**
     * Log in to an IRC server.
     *
     * @param string      $nickName The nickname - visible in the channel.
     * @param string      $userName The username - visible in the host name.
     * @param string      $realName The real name - visible in the WhoIs.
     * @param string|null $password The password - some servers require a password.
     */
    public function login(string $nickName, string $userName, string $realName, ?string $password = null): bool
    {
        if (!$this->isConnected()) {
            echo 'ERROR: You must connect to IRC first!' . PHP_EOL;
            return false;
        }

        if (empty($nickName) || empty($userName) || empty($realName)) {
            echo 'ERROR: nick/user/real name must not be empty!' . PHP_EOL;
            return false;
        }

        $this->nickName = $nickName;
        $this->userName = $userName;
        $this->realName = $realName;
        $this->password = $password;

        if (!empty($password) && !$this->writeSocket('PASS ' . $password)) {
            return false;
        }

        if (!$this->writeSocket('NICK ' . $nickName)) {
            return false;
        }

        if (!$this->writeSocket('USER ' . $userName . ' 0 * :' . $realName)) {
            return false;
        }

        // Loop over the socket buffer until we find "001".
        while (true) {
            $this->readSocket();

            // We got pinged, reply with a pong.
            if (preg_match('/^PING\s*:(.+?)$/', $this->buffer, $matches)) {
                $this->pong($matches[1]);
            } elseif (preg_match('/^:(.*?)\s+(\d+).*?(:.+?)?$/', $this->buffer, $matches)) {
                // We found 001, which means we are logged in.
                if ($matches[2] == 001) {
                    $this->remoteHostReceived = $matches[1];
                    break;
                // We got 464, which means we need to send a password.
                } elseif ($matches[2] == 464 || preg_match('/irc.znc.in.*?You need to send your password/i', $this->buffer)) {
                    // Before the lower check, set the password : username:password
                    $tempPass = "{$userName}:{$password}";

                    // Check if the user has his password in this format: username/server:password
                    if ($password !== null && preg_match('/^.+?\/.+?:.+?$/', $password)) {
                        $tempPass = $password;
                    }

                    if ($password !== null && !$this->writeSocket('PASS ' . $tempPass)) {
                        return false;
                    } elseif (isset($matches[3]) && str_contains(strtolower($matches[3]), 'invalid password')) {
                        echo "Invalid password or username for ({$this->remoteHost}).";
                        return false;
                    }
                }
            } elseif (preg_match('/^ERROR\s*:/', $this->buffer)) {
                echo $this->buffer . PHP_EOL;
                return false;
            }
        }
        return true;
    }

    /**
     * Quit from IRC.
     *
     * @param string|null $message Optional disconnect message.
     */
    public function quit(?string $message = null): bool
    {
        if ($this->isConnected()) {
            $this->writeSocket('QUIT' . ($message === null ? '' : ' :' . $message));
        }
        $this->closeStream();
        return $this->isConnected();
    }

    /**
     * Read the incoming buffer in a loop.
     */
    public function readIncoming(): void
    {
        while (true) {
            $this->readSocket();

            // If the server pings us, return it a pong.
            if (preg_match('/^PING\s*:(.+?)$/', $this->buffer, $matches)) {
                if ($matches[1] === $this->remoteHostReceived) {
                    $this->pong($matches[1]);
                }
            // Check for a channel message.
            } elseif (
                preg_match(
                    '/^:(?P<nickname>.+?)\!.+?\s+PRIVMSG\s+(?P<channel>#.+?)\s+:\s*(?P<message>.+?)\s*$/',
                    $this->stripControlCharacters($this->buffer),
                    $matches
                )
            ) {
                $this->channelData = [
                    'nickname' => $matches['nickname'],
                    'channel'  => $matches['channel'],
                    'message'  => $matches['message']
                ];

                $this->processChannelMessages();
            }

            // Ping the server if it has not sent us a ping in a while.
            if ((time() - $this->lastPing) > ($this->socketTimeout / 2)) {
                $this->ping($this->remoteHostReceived);
            }
        }
    }

    /**
     * Join a channel or multiple channels.
     *
     * @param array $channels Array of channels with their passwords (null if the channel doesn't need a password).
     *                        ['#exampleChannel' => 'thePassword', '#exampleChan2' => null]
     */
    public function joinChannels(array $channels = []): bool
    {
        $this->channels = $channels;

        if (!$this->isConnected()) {
            echo 'ERROR: You must connect to IRC first!' . PHP_EOL;
            return false;
        }

        if (!empty($channels)) {
            foreach ($channels as $channel => $password) {
                $this->joinChannel($channel, $password);
            }
            return true;
        }

        return false;
    }

    /**
     * Implementation.
     * Extended classes will use this function to parse the messages in the channel using $this->channelData.
     */
    protected function processChannelMessages(): void
    {
        // To be implemented in child classes
    }

    /**
     * Join a channel.
     */
    protected function joinChannel(string $channel, ?string $password = ''): void
    {
        if ($password === '') {
            $password = null;
        }
        $this->writeSocket('JOIN ' . $channel . ($password === null ? '' : ' ' . $password));
    }

    /**
     * Send PONG to a host.
     */
    protected function pong(string $host): void
    {
        if ($this->writeSocket('PONG ' . $host) === false) {
            $this->reconnect();
        }

        // If we got a ping from the IRC server, set the last ping time to now.
        if ($host === $this->remoteHostReceived) {
            $this->lastPing = time();
        }
    }

    /**
     * Send PING to a host.
     */
    protected function ping(string $host): void
    {
        $pong = $this->writeSocket('PING ' . $host);

        // Check if there's a connection error.
        if ($pong === false || ((time() - $this->lastPing) > ($this->socketTimeout / 2) && !preg_match('/^PONG/', $this->buffer))) {
            $this->reconnect();
        }

        // If sent a ping from the IRC server, set the last ping time to now.
        if ($host === $this->remoteHostReceived) {
            $this->lastPing = time();
        }
    }

    /**
     * Attempt to reconnect to IRC.
     */
    protected function reconnect(): void
    {
        if (!$this->connect($this->remoteHost, $this->remotePort, $this->remoteTls)) {
            exit("FATAL: Could not reconnect to ({$this->remoteHost}) after ({$this->reconnectRetries}) tries." . PHP_EOL);
        }

        if ($this->alreadyLoggedIn === false) {
            if (!$this->login($this->nickName, $this->userName, $this->realName, $this->password)) {
                exit("FATAL: Could not log in to ({$this->remoteHost})!" . PHP_EOL);
            }

            $this->joinChannels($this->channels);
        }
    }

    /**
     * Read the response from the IRC server.
     */
    protected function readSocket(): void
    {
        $buffer = '';
        do {
            stream_set_timeout($this->socket, $this->socketTimeout);
            $buffer .= fgets($this->socket, 1024);
        } while (!empty($buffer) && !preg_match('/\v+$/', $buffer));
        $this->buffer = trim($buffer);

        if ($this->debug && $this->buffer !== '') {
            echo 'RECV ' . $this->buffer . PHP_EOL;
        }
    }

    /**
     * Send a command to the IRC server.
     */
    protected function writeSocket(string $command): bool
    {
        $command .= "\r\n";
        for ($written = 0; $written < strlen($command); $written += $fWrite) {
            stream_set_timeout($this->socket, $this->socketTimeout);
            $fWrite = $this->writeSocketChar(substr($command, $written));

            // http://www.php.net/manual/en/function.fwrite.php#96951 | fwrite can return 0 causing an infinite loop.
            if ($fWrite === false || $fWrite <= 0) {
                // If it failed, try a second time.
                $fWrite = $this->writeSocketChar(substr($command, $written));
                if ($fWrite === false || $fWrite <= 0) {
                    if (++$this->writeAttempts == 10) {
                        echo 'ERROR: Failed to send 10 consecutive messages to IRC, reconnecting to IRC.' . PHP_EOL;
                        sleep(10);
                        $this->writeAttempts = 0;
                        $this->closeStream();
                        $this->reconnect();
                        return false;
                    }
                    echo 'ERROR: Could not write to socket! (the IRC server might have closed the connection)' . PHP_EOL;
                    return false;
                }
            }
        }

        if ($this->debug) {
            echo 'SEND :' . $command;
        }
        $this->writeAttempts = 0;
        return true;
    }

    /**
     * Write a single character to the socket.
     *
     * @param string $character A portion of text to write
     */
    protected function writeSocketChar(string $character): bool|int
    {
        return @fwrite($this->socket, $character);
    }

    /**
     * Initiate stream socket to IRC server.
     */
    protected function initiateStream(): void
    {
        $this->closeStream();

        $socket = stream_socket_client(
            $this->remoteSocketString,
            $errorNumber,
            $errorString,
            $this->remoteConnectionTimeout
        );

        if ($socket === false) {
            echo "ERROR: {$errorString} ({$errorNumber})" . PHP_EOL;
        } else {
            $this->socket = $socket;
        }
    }

    /**
     * Close the socket.
     */
    protected function closeStream(): void
    {
        if ($this->socket !== null) {
            $this->socket = null;
        }
    }

    /**
     * Check if we are connected to the IRC server.
     */
    protected function isConnected(): bool
    {
        return (is_resource($this->socket) && !feof($this->socket));
    }

    /**
     * Strips control characters from a IRC message.
     */
    protected function stripControlCharacters(string $text): string
    {
        return preg_replace(
            [
                '/(\x03(?:\d{1,2}(?:,\d{1,2})?)?)/',    // Color code
                '/\x02/',                               // Bold
                '/\x0F/',                               // Escaped
                '/\x16/',                               // Italic
                '/\x1F/',                               // Underline
                '/\x12/'                                // Device control 2
            ],
            '',
            $text
        );
    }
}
