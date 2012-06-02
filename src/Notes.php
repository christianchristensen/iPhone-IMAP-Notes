<?php
use Zend\Mail\Storage;

use Zend\Mail\Storage\Imap;
use Zend\Mail;

class Notes
{
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

    function __construct($user = NULL, $pass = NULL, $basefolder = NULL) {
        if (!is_null($user) && !is_null($pass)) {
            $this->_imap = new \Zend\Mail\Protocol\Imap('mail.messagingengine.com', NULL, FALSE);
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

    public function index() {
        // TODO: Schema-ize this
        $index = array();
        foreach ($this->_storage as $messageNum => $message) {
            if (!$message->hasFlag(Storage::FLAG_DELETED)) {
                $headers = $message->getHeaders();
                $index[] = array(
                  'num' => $messageNum,
                  'uuid' => $headers['x-universally-unique-identifier'],
                  'subject' => $message->subject,
                  'body' => $message->getContent(),
                );
            }
        }
        return $index;
    }

    public function create($msg = '') {
        $uuid = UUID();
    }

    public function retrieve($msgid = NULL) {
    }

    public function update($msgid = NULL) {
    }

    public function delete($msgid = NULL) {
    }
}