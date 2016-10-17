<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

class RequestLog {

    /** @var integer */
    protected $id;

    /** @var integer */
    protected $statusCode;

    /** @var string */
    protected $url;

    /** @var string */
    protected $method;

    /** @var \DateTime */
    protected $date;

    /** @var string */
    protected $message;

    /** @var string */
    protected $debug;

    /** @var RequestLogMessage|null */
    protected $request;

    /** @var RequestLogMessage|null */
    protected $response;

    /** @var RequestLogException|null */
    protected $exception;

    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id) {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int {
        return $this->statusCode;
    }

    /**
     * @param int $statusCode
     */
    public function setStatusCode(int $statusCode) {
        $this->statusCode = $statusCode;
    }

    /**
     * @return string
     */
    public function getUrl(): string {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url) {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getMethod(): string {
        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method) {
        $this->method = $method;
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
    public function getDebug(): string {
        return $this->debug;
    }

    /**
     * @param string $debug
     */
    public function setDebug(string $debug) {
        $this->debug = $debug;
    }

    /**
     * @return null|RequestLogMessage
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * @param null|RequestLogMessage $request
     */
    public function setRequest($request) {
        $this->request = $request;
    }

    /**
     * @return null|RequestLogMessage
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * @param null|RequestLogMessage $response
     */
    public function setResponse($response) {
        $this->response = $response;
    }

    /**
     * @return null|RequestLogException
     */
    public function getException() {
        return $this->exception;
    }

    /**
     * @param null|RequestLogException $exception
     */
    public function setException($exception) {
        $this->exception = $exception;
    }

    public function __construct() {
        $this->date = new \DateTime();
    }

}
