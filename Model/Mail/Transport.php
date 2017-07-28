<?php

namespace Dotdigitalgroup\Email\Model\Mail;

class Transport implements \Dotdigitalgroup\Email\Model\Mail\AdapterInterface
{
    /**
     * @var \Zend\Mail\Transport\Smtp
     */
    protected $smtp;

    /**
     * @param \Zend\Mail\Transport\Smtp $smtp
     */
    public function __construct(\Zend\Mail\Transport\Smtp $smtp) {
        $this->smtp = $smtp;;
    }

    /**
     * Send message
     *
     * @param \Magento\Framework\Mail\MessageInterface $message
     * @return void
     */
    public function send(\Magento\Framework\Mail\MessageInterface $message)
    {
        $this->smtp->send($message);
    }
}