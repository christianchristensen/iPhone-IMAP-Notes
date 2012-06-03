<?php
use Zend\Mail\Headers;

use Zend\Mail\Message;

use Zend\Mail\Storage;

use Zend\Mail\Storage\Imap;
use Zend\Mail;

class Notes
{
    const xUUID = 'X-Universally-Unique-Identifier';
    const xUType = 'X-Uniform-Type-Identifier';
    const xUTypeField = 'com.apple.mail-note';

    /**
     * IMAP Protocol
     * @var \Zend\Mail\Protocol\Imap
     */
    protected $_imap;

    /**
     * IMAP storage interaction
     * @var Imap
     */
    protected $_storage;

    function __construct($host = NULL, $user = NULL, $pass = NULL, $basefolder = NULL, $port = NULL, $ssl = FALSE) {
        if (!is_null($host) && !is_null($user) && !is_null($pass)) {
            $this->_imap = new \Zend\Mail\Protocol\Imap($host, $port, $ssl);
            $this->_imap->login($user, $pass);
            $this->_storage = new Imap($this->_imap);
        }
        // Get a list of folders, look for Notes; if not then add it and be ready to go!
        if (!is_null($basefolder)) {
            $this->_storage->selectFolder($basefolder . '.Notes');
        }
        else {
            $this->_storage->selectFolder('Notes');
        }
    }

    function __destruct() {
        $this->_storage->close();
        $this->_imap->logout();
    }

    public function index() {
        // TODO: Schema-ize this
        $index = array();
        foreach ($this->_storage as $messageNum => $message) {
            if (!$message->hasFlag(Storage::FLAG_DELETED)) {
                $headers = $message->getHeaders();
                $index[] = array(
                  'num' => $messageNum,
                  'uuid' => $headers[strtolower(self::xUUID)],
                  'subject' => $message->subject,
                  'body' => $message->getContent(),
                );
            }
        }
        return $index;
    }

    public function create($msg = '') {
        $uuid = UUID::mint()->string;
        $subj = trim(strtok($msg, "\n"));
        if (empty($subj)) {
            throw new Exception('Empty message creation is not permitted.');
        }
        $body = '';
        foreach (explode("\n", $msg) as $line) {
            if (empty($line)) {
                $line = '<br>';
            }
            $body .= '<div>' . trim(str_replace("\n", "", $line)) . '</div>';
        }
        $message = new Message();
        $message->setSubject($subj)
        ->setBody($body);
        //->addFrom('Notes App');
        //->setEncoding("UTF-8");
        $message->headers()->addHeaderLine(self::xUUID, $uuid);
        $message->headers()->addHeaderLine(self::xUType, self::xUTypeField);
        $message->headers()->addHeaderLine('Content-Type', 'text/html');
        // TODO: catch what might go wrong here...
        $this->_storage->appendMessage($message->toString(), NULL, array(Storage::FLAG_SEEN));
        return $uuid;
    }

    public function retrieve($msgid = NULL) {
        $messageNum = $this->getMessageNum($msgid);
        $message = $this->_storage[$messageNum];
        $headers = $message->getHeaders();
        return array(
            'num' => $messageNum,
            'uuid' => $headers[strtolower(self::xUUID)],
            'subject' => $message->subject,
            'body' => $message->getContent(),
        );
    }

    public function update($msgid = NULL, $msg = '') {
        $uuid = $this->create($msg);
        $this->delete($msgid);
        return $uuid;
    }

    public function delete($msgid = NULL) {
        $messageNum = $this->getMessageNum($msgid);
        $this->_storage->setFlags($messageNum, array(Storage::FLAG_DELETED, Storage::FLAG_SEEN));
        return TRUE;
    }

    private function getMessageNum($msgid) {
        // TODO: Better detection of UUID's
        if (strpos($msgid, '-') && strlen($msgid) > 30) {
            // NOTE: search() is marked as "internal" as it may be unstable
            // http://tools.ietf.org/html/rfc3501#section-6.4.4
            $search = $this->_imap->search(array('HEADER '.self::xUUID.' '.$msgid));
            return $search[0];
        }
        else {
            return $msgid;
        }
    }
}