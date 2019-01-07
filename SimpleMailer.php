<?php
/**
 * Created by PhpStorm.
 * User: lyx
 * Date: 19-1-4
 * Time: 下午1:15
 */

namespace SimpleMailer;


class SimpleMailer
{
    protected $server;
    protected $port;
    protected $ssl;
    protected $startSSL;

    protected $username;
    protected $password;

    protected $mailFrom;

    protected $conn    = null;
    protected $sending = false;

    public function __construct($option)
    {
        $server   = $option['server'] ?? '';
        $ssl      = $option['ssl'] ?? false;
        $startSSL = $option['startSSL'] ?? false;
        $port     = $option['port'] ?? ($ssl ? 465 : ($startSSL ? 587 : 25));
        $username = $option['username'] ?? '';
        $password = $option['password'] ?? '';
        $mailFrom = $option['mailFrom'] ?? '';

        $this->setServer($server);
        $this->setPort($port);
        $this->setSSL($ssl);
        $this->setStartSSL($startSSL);
        $this->setUsername($username);
        $this->setPassword($password);
        $this->setMailFrom($mailFrom);
    }

    public function setServer(string $server, bool $reConnect = true)
    {
        $this->server = $server;
    }

    public function setPort(int $port, bool $reConnect = true)
    {
        $this->port = $port;
    }

    public function setSSL(bool $ssl)
    {
        $this->ssl = $ssl;
        if ($ssl) $this->startSSL = false;
    }

    public function setStartSSL(bool $startSSL)
    {
        $this->startSSL = $startSSL;
        if ($startSSL) $this->ssl = false;
    }

    public function setUsername(string $username)
    {
        $this->username = base64_encode($username);
    }

    public function setPassword(string $password)
    {
        $this->password = base64_encode($password);
    }

    public function setMailFrom(string $mailFrom)
    {
        $this->mailFrom = $mailFrom;
    }

    private function connect() : bool
    {
        $type = $this->ssl ? SWOOLE_TCP | SWOOLE_SSL : SWOOLE_TCP;
        $this->conn = new \Swoole\Coroutine\Client($type);

        if ($this->conn->connect($this->server, $this->port))
        {
            $recv = $this->recv();
            if (!$recv || strpos($recv, '220 ') === false) goto error;

            $host = (explode(' ', $recv))[1];

            $this->conn->set([
                'open_eof_check' => true,
                'package_eof' => "\r\n",
                'package_max_length' => 1024 * 1024 * 2,
            ]);

            if (!$this->send('ehlo ' . $host)) goto error;

            if (!$this->wait('250')) goto error;

            if ($this->startSSL)
            {
                if (!$this->send('starttls')) goto error;
                if (!$this->wait('220')) goto error;
                $this->conn->enableSSL();
                if (!$this->send('ehlo ' . $host)) goto error;
                if (!$this->wait('250')) goto error;
            }

            return true;
            error:
            $this->conn->close();
            return false;
        }

        return false;
    }

    private function send($msg) : bool
    {
        return $this->conn->send($msg . "\r\n");
    }

    private function recv() : ?string
    {
        $msg = $this->conn->recv();
        if ($msg == '' || $msg === false) {
            return null;
        }
        return $msg;
    }

    private function wait($wait_msg) : bool
    {
        for (;;) {
            $msg = $this->recv();
            if (!$msg) return false;
            if (strpos($msg, $wait_msg) !== false) return true;
        }

        return true;
    }

    public function mail($option) : bool
    {
        $this->sending = true;
        $result = false;

        do
        {
            if (!$this->connect()) break;

            // auth
            if ($this->username != '' && $this->password != '')
            {
                if (!$this->send('auth login')) break;
                if (!$this->wait('334')) break;
                if (!$this->send($this->username)) break;
                if (!$this->wait('334')) break;
                if (!$this->send($this->password)) break;
                if (!$this->wait('235')) break;
            }

            if (!$this->send('mail from:<' . $this->mailFrom . '>')) break;
            if (!$this->wait('250')) break;
            if (!$this->send('rcpt to:<' . $option['mailTo'] . '>')) break;
            if (!$this->wait('250')) break;

            if (!$this->send('data')) break;
            if (!$this->wait('354')) break;

            // send mail
            $contentType = (isset($option['html']) && $option['html']) ? 'text/html' : 'text/plain';
            $mail = "MIME-Version: 1.0\r\nFrom: $this->mailFrom\r\nTo: " . $option['mailTo']
                . "\r\nSubject: " . $option['subject'] . "\r\nContent-type: $contentType\r\n\r\n" . $option['body'];
            if (!$this->send($mail)) break;

            if (!$this->send('.')) break;
            if (!$this->wait('250')) break;
            $this->send('quit');
            $this->wait('221');
            $this->conn->close();
            $result = true;
        } while (0);

        $this->sending = false;
        $this->conn->close();
        return $result;
    }
}