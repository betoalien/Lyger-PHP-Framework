<?php

declare(strict_types=1);

namespace Lyger\Mail;

/**
 * Mail - Simple mailer implementation
 */
class Mail
{
    private static ?string $driver = 'sendmail';
    private static array $config = [];
    private static array $from = ['address' => 'noreply@localhost', 'name' => 'Lyger'];

    public static function setDriver(string $driver): void
    {
        self::$driver = $driver;
    }

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function setFrom(string $address, ?string $name = null): void
    {
        self::$from = ['address' => $address, 'name' => $name ?? $address];
    }

    public static function send(Mailable $mailable): bool
    {
        $message = new Message();

        // Set from
        $message->setFrom(self::$from['address'], self::$from['name']);

        // Build message from mailable
        $mailable->build($message);

        return self::deliver($message);
    }

    public static function sendLater(Mailable $mailable, int $delay = 0): void
    {
        // In production, this would queue the email
        if ($delay > 0) {
            sleep($delay);
        }
        self::send($mailable);
    }

    private static function deliver(Message $message): bool
    {
        switch (self::$driver) {
            case 'smtp':
                return self::sendViaSmtp($message);
            case 'sendmail':
                return self::sendViaSendmail($message);
            case 'log':
                return self::sendViaLog($message);
            default:
                return self::sendViaSendmail($message);
        }
    }

    private static function sendViaSmtp(Message $message): bool
    {
        $config = self::$config;

        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 25;
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $encryption = $config['encryption'] ?? null;

        // Simple SMTP connection
        $socket = @fsockopen(
            ($encryption === 'ssl' ? 'ssl://' : '') . $host,
            $port,
            $errno,
            $errstr,
            30
        );

        if (!$socket) {
            return false;
        }

        $response = fgets($socket, 515);

        // HELO
        fputs($socket, "HELO " . gethostname() . "\r\n");
        fgets($socket, 515);

        // AUTH LOGIN if credentials provided
        if ($username && $password) {
            fputs($socket, "AUTH LOGIN\r\n");
            fgets($socket, 515);
            fputs($socket, base64_encode($username) . "\r\n");
            fgets($socket, 515);
            fputs($socket, base64_encode($password) . "\r\n");
            $response = fgets($socket, 515);
            if (strpos($response, '235') === false) {
                fclose($socket);
                return false;
            }
        }

        // MAIL FROM
        fputs($socket, "MAIL FROM: <" . $message->getFrom() . ">\r\n");
        fgets($socket, 515);

        // RCPT TO
        foreach ($message->getTo() as $to) {
            fputs($socket, "RCPT TO: <{$to}>\r\n");
            fgets($socket, 515);
        }

        // DATA
        fputs($socket, "DATA\r\n");
        fgets($socket, 515);

        $headers = $message->getHeaders();
        $headers .= "From: " . $message->getFromName() . " <" . $message->getFrom() . ">\r\n";

        if (!empty($message->getCc())) {
            $headers .= "Cc: " . implode(', ', $message->getCc()) . "\r\n";
        }
        if (!empty($message->getBcc())) {
            $headers .= "Bcc: " . implode(', ', $message->getBcc()) . "\r\n";
        }

        fputs($socket, "Subject: " . $message->getSubject() . "\r\n");
        fputs($socket, $headers . "\r\n");
        fputs($socket, $message->getBody() . "\r\n");
        fputs($socket, ".\r\n");
        fgets($socket, 515);

        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    private static function sendViaSendmail(Message $message): bool
    {
        $sendmail = ini_get('sendmail_path') ?: '/usr/sbin/sendmail';
        $from = $message->getFrom();

        $headers = "From: " . $message->getFromName() . " <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: " . $message->getContentType() . "\r\n";

        if (!empty($message->getCc())) {
            $headers .= "Cc: " . implode(', ', $message->getCc()) . "\r\n";
        }
        if (!empty($message->getBcc())) {
            $headers .= "Bcc: " . implode(', ', $message->getBcc()) . "\r\n";
        }

        $to = implode(', ', $message->getTo());

        return mail($to, $message->getSubject(), $message->getBody(), $headers);
    }

    private static function sendViaLog(Message $message): bool
    {
        $logPath = dirname(__DIR__, 2) . '/storage/logs/mail.log';
        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $log = sprintf(
            "[%s] To: %s | Subject: %s | Body: %s\n",
            date('Y-m-d H:i:s'),
            implode(', ', $message->getTo()),
            $message->getSubject(),
            substr($message->getBody(), 0, 100)
        );

        file_put_contents($logPath, $log, FILE_APPEND);
        return true;
    }
}

/**
 * Mailable - Base class for mailables
 */
abstract class Mailable
{
    protected string $subject = '';
    protected array $to = [];
    protected array $cc = [];
    protected array $bcc = [];
    protected ?string $from = null;
    protected ?string $fromName = null;
    protected string $body = '';
    protected string $contentType = 'text/plain';
    protected array $attachments = [];

    public function to(string $address, ?string $name = null): self
    {
        $this->to[] = $name ? "{$name} <{$address}>" : $address;
        return $this;
    }

    public function cc(string $address): self
    {
        $this->cc[] = $address;
        return $this;
    }

    public function bcc(string $address): self
    {
        $this->bcc[] = $address;
        return $this;
    }

    public function from(string $address, ?string $name = null): self
    {
        $this->from = $address;
        $this->fromName = $name;
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $body): self
    {
        $this->body = $body;
        $this->contentType = 'text/html';
        return $this;
    }

    public function text(string $body): self
    {
        $this->body = $body;
        $this->contentType = 'text/plain';
        return $this;
    }

    public function view(string $view, array $data = []): self
    {
        // In production, this would render a view
        $this->body = "View: {$view}";
        return $this;
    }

    public function attach(string $path, ?string $name = null): self
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name ?? basename($path),
        ];
        return $this;
    }

    abstract public function build(Message $message): void;

    public function send(): bool
    {
        return Mail::send($this);
    }

    public function sendLater(int $delay = 0): void
    {
        Mail::sendLater($this, $delay);
    }
}

/**
 * Message - Email message
 */
class Message
{
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private string $from = '';
    private string $fromName = '';
    private string $subject = '';
    private string $body = '';
    private string $contentType = 'text/plain';
    private array $attachments = [];
    private string $headers = '';

    public function to(string $address, ?string $name = null): self
    {
        $this->to[] = $name ? "{$name} <{$address}>" : $address;
        return $this;
    }

    public function cc(string $address): self
    {
        $this->cc[] = $address;
        return $this;
    }

    public function bcc(string $address): self
    {
        $this->bcc[] = $address;
        return $this;
    }

    public function setFrom(string $address, ?string $name = null): void
    {
        $this->from = $address;
        $this->fromName = $name ?? $address;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function html(string $body): self
    {
        $this->body = $body;
        $this->contentType = 'text/html';
        return $this;
    }

    public function text(string $body): self
    {
        $this->body = $body;
        $this->contentType = 'text/plain';
        return $this;
    }

    public function attach(string $path, ?string $name = null): self
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name ?? basename($path),
        ];
        return $this;
    }

    // Getters
    public function getTo(): array
    {
        return $this->to;
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getHeaders(): string
    {
        return $this->headers;
    }
}
