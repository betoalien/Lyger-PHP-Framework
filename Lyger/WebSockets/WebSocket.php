<?php

declare(strict_types=1);

namespace Lyger\WebSockets;

/**
 * WebSocket - WebSocket server implementation
 */
class WebSocket
{
    private ?Socket $socket = null;
    private array $clients = [];
    private array $channels = [];
    private bool $running = false;
    private int $port = 8080;

    public function __construct(int $port = 8080)
    {
        $this->port = $port;
    }

    public function start(): void
    {
        $this->socket = new Socket($this->port);
        $this->running = true;

        echo "WebSocket server started on port {$this->port}\n";

        while ($this->running) {
            $read = [$this->socket->getResource()];
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 1) > 0) {
                if (in_array($this->socket->getResource(), $read)) {
                    $client = $this->socket->accept();

                    if ($client) {
                        $this->clients[(int) $client->getResource()] = $client;
                        $this->onConnect($client);
                    }
                }

                foreach ($this->clients as $key => $client) {
                    try {
                        $data = $client->receive();

                        if ($data === '' || $data === false) {
                            $this->onClose($client);
                            unset($this->clients[$key]);
                            continue;
                        }

                        $this->handleMessage($client, $data);
                    } catch (\Exception $e) {
                        $this->onError($client, $e);
                        unset($this->clients[$key]);
                    }
                }
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;

        foreach ($this->clients as $client) {
            $client->close();
        }

        if ($this->socket) {
            $this->socket->close();
        }
    }

    private function handleMessage(Client $client, string $data): void
    {
        $message = Frame::unmask($data);

        if ($message['opcode'] === Frame::OP_TEXT) {
            $this->onMessage($client, $message['payload']);
        } elseif ($message['opcode'] === Frame::OP_PING) {
            $client->send(Frame::createPong());
        } elseif ($message['opcode'] === Frame::OP_CLOSE) {
            $this->onClose($client);
            unset($this->clients[(int) $client->getResource()]);
        }
    }

    public function broadcast(string $message, ?string $channel = null): void
    {
        $data = Frame::create($message);

        if ($channel !== null) {
            $this->sendToChannel($channel, $data);
        } else {
            foreach ($this->clients as $client) {
                $client->send($data);
            }
        }
    }

    public function sendToChannel(string $channel, string $message): void
    {
        if (isset($this->channels[$channel])) {
            foreach ($this->channels[$channel] as $client) {
                $client->send(Frame::create($message));
            }
        }
    }

    public function joinChannel(Client $client, string $channel): void
    {
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }

        if (!in_array($client, $this->channels[$channel], true)) {
            $this->channels[$channel][] = $client;
        }
    }

    public function leaveChannel(Client $client, string $channel): void
    {
        if (isset($this->channels[$channel])) {
            $key = array_search($client, $this->channels[$channel], true);
            if ($key !== false) {
                unset($this->channels[$channel][$key]);
            }
        }
    }

    protected function onConnect(Client $client): void
    {
        // Override in subclass
    }

    protected function onClose(Client $client): void
    {
        // Override in subclass
    }

    protected function onMessage(Client $client, string $message): void
    {
        // Override in subclass
    }

    protected function onError(Client $client, \Exception $e): void
    {
        // Override in subclass
    }
}

/**
 * Socket - Raw socket wrapper
 */
class Socket
{
    private $resource;
    private int $port;

    public function __construct(int $port)
    {
        $this->port = $port;
        $this->resource = stream_socket_server("tcp://0.0.0.0:{$port}", $errno, $errstr);

        if (!$this->resource) {
            throw new \RuntimeException("Failed to create socket: {$errstr}");
        }

        stream_set_blocking($this->resource, false);
    }

    public function accept(): ?Client
    {
        $client = @stream_socket_accept($this->resource, 5);

        if ($client) {
            stream_set_blocking($client, false);
            return new Client($client);
        }

        return null;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function close(): void
    {
        if ($this->resource) {
            fclose($this->resource);
        }
    }
}

/**
 * Client - WebSocket client connection
 */
class Client
{
    private $resource;
    private array $headers = [];
    private string $path = '/';

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    public function handshake(string $host): bool
    {
        $headers = fgets($this->resource);
        if (!$headers) {
            return false;
        }

        // Parse headers
        $lines = explode("\r\n", $headers);
        foreach ($lines as $line) {
            if (preg_match('/^(\w+): (.*)$/', $line, $matches)) {
                $this->headers[strtolower($matches[1])] = $matches[2];
            }
        }

        if (!isset($this->headers['upgrade']) || strtolower($this->headers['upgrade']) !== 'websocket') {
            return false;
        }

        // Get WebSocket key
        $key = $this->headers['sec-websocket-key'] ?? '';
        $accept = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$accept}\r\n";
        $response .= "\r\n";

        fwrite($this->resource, $response);

        return true;
    }

    public function send(string $data): int
    {
        return fwrite($this->resource, $data);
    }

    public function receive(): string
    {
        $data = fread($this->resource, 4096);
        return $data ?: '';
    }

    public function close(): void
    {
        if ($this->resource) {
            fclose($this->resource);
        }
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}

/**
 * Frame - WebSocket frame handling
 */
class Frame
{
    public const OP_CONTINUE = 0x0;
    public const OP_TEXT = 0x1;
    public const OP_BINARY = 0x2;
    public const OP_CLOSE = 0x8;
    public const OP_PING = 0x9;
    public const OP_PONG = 0xA;

    public static function create(string $message): string
    {
        $length = strlen($message);
        $frame = '';

        // Opcode
        $frame .= chr(0x81);

        // Payload length
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126);
            $frame .= pack('n', $length);
        } else {
            $frame .= chr(127);
            $frame .= pack('J', $length);
        }

        $frame .= $message;
        return $frame;
    }

    public static function createPong(): string
    {
        return chr(0x8A) . chr(0x00);
    }

    public static function unmask(string $data): array
    {
        $length = ord($data[1]) & 0x7F;
        $opcode = ord($data[0]) & 0x0F;

        $offset = 2;

        if ($length === 126) {
            $length = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            $length = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }

        $mask = substr($data, $offset, 4);
        $offset += 4;

        $payload = substr($data, $offset);
        $message = '';

        for ($i = 0; $i < $length; $i++) {
            $message .= $payload[$i] ^ $mask[$i % 4];
        }

        return [
            'opcode' => $opcode,
            'payload' => $message,
        ];
    }
}

/**
 * WebSocketClient - Client-side WebSocket
 */
class WebSocketClient
{
    private $socket;
    private bool $connected = false;

    public function connect(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? 80;
        $path = $parsed['path'] ?? '/';

        $context = stream_context_create();
        stream_context_set_option($context, 'ssl', 'verify_peer', false);
        stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

        $this->socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            return false;
        }

        // Send handshake
        $key = base64_encode(random_bytes(16));

        $request = "GET {$path} HTTP/1.1\r\n";
        $request .= "Host: {$host}\r\n";
        $request .= "Upgrade: websocket\r\n";
        $request .= "Connection: Upgrade\r\n";
        $request .= "Sec-WebSocket-Key: {$key}\r\n";
        $request .= "Sec-WebSocket-Version: 13\r\n";
        $request .= "\r\n";

        fwrite($this->socket, $request);

        $response = fgets($this->socket, 1024);
        if (strpos($response, '101') === false) {
            return false;
        }

        $this->connected = true;
        return true;
    }

    public function send(string $message): int
    {
        if (!$this->connected) {
            return 0;
        }

        $frame = Frame::create($message);
        return fwrite($this->socket, $frame);
    }

    public function receive(): ?string
    {
        if (!$this->connected) {
            return null;
        }

        $data = fread($this->socket, 4096);
        if ($data === '' || $data === false) {
            return null;
        }

        $message = Frame::unmask($data);

        if ($message['opcode'] === Frame::OP_CLOSE) {
            $this->connected = false;
            return null;
        }

        return $message['payload'];
    }

    public function close(): void
    {
        if ($this->socket) {
            fwrite($this->socket, chr(0x88) . chr(0x00));
            fclose($this->socket);
        }
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }
}
