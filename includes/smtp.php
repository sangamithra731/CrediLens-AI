<?php
/**
 * CrediLens AI — Native PHP Socket SMTP Client
 * High-performance, zero-dependency SMTP implementation.
 * Supports TLS (587), SSL (465), AUTH LOGIN, and plain SMTP.
 */

class SimpleSMTP {
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $timeout;
    private $socket = null;
    private $lastError = '';
    private $debugLog = [];

    public function __construct(array $config = []) {
        $this->host       = $config['host'] ?? 'smtp.gmail.com';
        $this->port       = (int)($config['port'] ?? 587);
        $this->encryption = strtolower($config['encryption'] ?? 'tls');
        $this->username   = trim($config['username'] ?? '');
        $this->password   = trim($config['password'] ?? '');
        $this->timeout    = (int)($config['timeout'] ?? 10);
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getDebugLog() {
        return $this->debugLog;
    }

    /**
     * Send email via SMTP
     */
    public function send($to, $subject, $bodyHtml, $fromEmail, $fromName, $plainText = '') {
        $this->debugLog = [];
        $this->lastError = '';

        if (empty($this->username) || empty($this->password)) {
            $this->lastError = 'SMTP username or password is not configured in config/mail.php';
            return false;
        }

        // 1. Establish connection
        $protocol = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $target = $protocol . $this->host . ':' . $this->port;
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ]);

        $this->log("Connecting to $target...");
        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            $this->lastError = "Connection failed to $target: ($errno) $errstr";
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Read server greeting
        $reply = $this->readReply();
        if (substr($reply, 0, 3) !== '220') {
            $this->lastError = "Invalid server greeting: $reply";
            $this->close();
            return false;
        }

        // 2. Handshake (EHLO)
        $this->sendCommand("EHLO localhost");
        $reply = $this->readReply();

        // 3. STARTTLS if required on port 587
        if ($this->encryption === 'tls' && $this->port !== 465) {
            $this->sendCommand("STARTTLS");
            $reply = $this->readReply();
            if (substr($reply, 0, 3) !== '220') {
                $this->lastError = "STARTTLS failed: $reply";
                $this->close();
                return false;
            }

            // Enable TLS encryption on the open socket
            $crypto = @stream_socket_enable_crypto(
                $this->socket,
                true,
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
            );

            if (!$crypto) {
                // Fallback crypto flag
                $crypto = @stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }

            if (!$crypto) {
                $this->lastError = "Failed to establish TLS encryption";
                $this->close();
                return false;
            }

            // Re-send EHLO after TLS handshake
            $this->sendCommand("EHLO localhost");
            $this->readReply();
        }

        // 4. Authenticate
        $this->sendCommand("AUTH LOGIN");
        $reply = $this->readReply();
        if (substr($reply, 0, 3) !== '334') {
            $this->lastError = "AUTH LOGIN rejected: $reply";
            $this->close();
            return false;
        }

        // Send base64 username
        $this->sendCommand(base64_encode($this->username));
        $reply = $this->readReply();
        if (substr($reply, 0, 3) !== '334') {
            $this->lastError = "Username rejected: $reply";
            $this->close();
            return false;
        }

        // Send base64 password
        $this->sendCommand(base64_encode(str_replace(' ', '', $this->password)));
        $reply = $this->readReply();
        if (substr($reply, 0, 3) !== '235') {
            $this->lastError = "Authentication failed (check credentials or App Password): $reply";
            $this->close();
            return false;
        }

        // 5. Envelope
        $this->sendCommand("MAIL FROM:<$fromEmail>");
        $reply = $this->readReply();
        if (substr($reply, 0, 3) !== '250') {
            $this->lastError = "MAIL FROM rejected: $reply";
            $this->close();
            return false;
        }

        $this->sendCommand("RCPT TO:<$to>");
        $reply = $this->readReply();
        if (substr($reply, 0, 3) !== '250') {
            $this->lastError = "Recipient address rejected for $to: $reply";
            $this->close();
            return false;
        }

        // 6. Data
        $this->sendCommand("DATA");
        $reply = $this->readReply();
        if (substr($reply, 0, 3) !== '354') {
            $this->lastError = "DATA command rejected: $reply";
            $this->close();
            return false;
        }

        // Build RFC-compliant email message
        $boundary = "----=_CrediLens_" . md5(uniqid(time(), true));
        $message  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\n";
        $message .= "To: <$to>\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "Date: " . date('r') . "\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $message .= "X-Mailer: CrediLens-AI-Platform/1.0\r\n";
        $message .= "\r\n";

        // Plain text version
        $plain = $plainText ?: strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], ["\n", "\n", "\n", "\n\n"], $bodyHtml));
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode(trim($plain))) . "\r\n";

        // HTML version
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($bodyHtml)) . "\r\n";
        $message .= "--$boundary--\r\n";

        // End of data marker
        $message .= "\r\n.\r\n";

        $this->sendRaw($message);
        $reply = $this->readReply();

        if (substr($reply, 0, 3) !== '250') {
            $this->lastError = "Message delivery rejected by server: $reply";
            $this->close();
            return false;
        }

        // 7. Quit
        $this->sendCommand("QUIT");
        $this->close();

        $this->log("Email successfully sent via SMTP to $to");
        return true;
    }

    private function sendCommand($cmd) {
        $this->log("> " . (strpos($cmd, 'AUTH') === false && !base64_decode($cmd, true) ? $cmd : '***'));
        @fwrite($this->socket, $cmd . "\r\n");
    }

    private function sendRaw($data) {
        @fwrite($this->socket, $data);
    }

    private function readReply() {
        $reply = '';
        while (!feof($this->socket)) {
            $line = @fgets($this->socket, 512);
            if ($line === false) break;
            $reply .= $line;
            // Multiline replies have a '-' after the 3-digit code
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $this->log("< " . trim($reply));
        return trim($reply);
    }

    private function log($msg) {
        $this->debugLog[] = date('H:i:s') . ' ' . $msg;
    }

    private function close() {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
