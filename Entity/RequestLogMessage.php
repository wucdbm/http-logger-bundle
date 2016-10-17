<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

class RequestLogMessage {

    protected $id;

    /** @var string */
    protected $content;

    /** @var RequestLog */
    protected $requestTo;

    /** @var RequestLog */
    protected $responseTo;

    /** @var RequestLogMessageType */
    protected $type;

    /** @var \DateTime */
    protected $date;

    /**
     * @return mixed
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getContent(): string {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content) {
        $this->content = $content;
    }

    /**
     * @return RequestLog
     */
    public function getRequestTo() {
        return $this->requestTo;
    }

    /**
     * @param RequestLog $requestTo
     */
    public function setRequestTo(RequestLog $requestTo) {
        $this->requestTo = $requestTo;
    }

    /**
     * @return RequestLog
     */
    public function getResponseTo() {
        return $this->responseTo;
    }

    /**
     * @param RequestLog $responseTo
     */
    public function setResponseTo(RequestLog $responseTo) {
        $this->responseTo = $responseTo;
    }

    /**
     * @return RequestLogMessageType
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param RequestLogMessageType $type
     */
    public function setType(RequestLogMessageType $type) {
        $this->type = $type;
    }

    /**
     * @return \DateTime
     */
    public function getDate(): \DateTime {
        return $this->date;
    }

    /**
     * @param \DateTime $date
     */
    public function setDate(\DateTime $date) {
        $this->date = $date;
    }

    public function __construct() {
        $this->date = new \DateTime();
    }

}
