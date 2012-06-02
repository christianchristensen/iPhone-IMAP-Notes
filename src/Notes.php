<?php
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

    function __construct($user = NULL, $pass = NULL) {
        if (!is_null($user) && !is_null($pass)) {
            $this->_imap = new \Zend\Mail\Protocol\Imap('mail.messagingengine.com', NULL, FALSE);
            $this->_imap->login($user, $pass);
            $this->_storage = new Imap($this->_imap);
        }
    }

    public function get() {
        return $this->_storage->getFolders();
    }

    public function create($msg = '') {

    }
}