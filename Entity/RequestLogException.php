<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

class RequestLogException {

    protected $id;

    /** @var string */
    protected $message;

    /** @var string */
    protected $code;

    /** @var string */
    protected $file;

    /** @var integer */
    protected $line;

    /** @var string */
    protected $stackTraceString;

    /** @var string */
    protected $json;

    /** @var string */
    protected $extraData;

    /** @var RequestLog */
    protected $log;

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
    public function getMessage(): string {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message) {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getCode(): string {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code) {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getFile(): string {
        return $this->file;
    }

    /**
     * @param string $file
     */
    public function setFile(string $file) {
        $this->file = $file;
    }

    /**
     * @return int
     */
    public function getLine(): int {
        return $this->line;
    }

    /**
     * @param int $line
     */
    public function setLine(int $line) {
        $this->line = $line;
    }

    /**
     * @return string
     */
    public function getStackTraceString(): string {
        return $this->stackTraceString;
    }

    /**
     * @param string $stackTraceString
     */
    public function setStackTraceString(string $stackTraceString) {
        $this->stackTraceString = $stackTraceString;
    }

    /**
     * @return string
     */
    public function getJson(): string {
        return $this->json;
    }

    /**
     * @param string $json
     */
    public function setJson(string $json) {
        $this->json = $json;
    }

    /**
     * @return string
     */
    public function getExtraData(): string {
        return $this->extraData;
    }

    /**
     * @param string $extraData
     */
    public function setExtraData(string $extraData) {
        $this->extraData = $extraData;
    }

    /**
     * @return RequestLog
     */
    public function getLog() {
        return $this->log;
    }

    /**
     * @param RequestLog $log
     */
    public function setLog(RequestLog $log) {
        $this->log = $log;
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
