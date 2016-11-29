<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

class RequestLog {

    /** @var integer */
    protected $id;

    /** @var integer */
    protected $statusCode;

    /** @var string|null */
    protected $url;

    /** @var string|null */
    protected $urlHash;

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
     * No return type here, it may have not been set
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id) {
        $this->id = $id;
    }

    /**
     * No return type here, it may have not been set
     * @return int
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * @param int $statusCode
     */
    public function setStatusCode(int $statusCode) {
        $this->statusCode = $statusCode;
    }

    /**
     * No return type here, it may have not been set
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url) {
        $this->url = $url;
    }

    /**
     * No return type here, it may have not been set
     * @return string
     */
    public function getMethod() {
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
     * No return type here, it may have not been set
     * @return string
     */
    public function getDebug() {
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

    /**
     * @return string
     */
    public function getUrlHash() {
        return $this->urlHash;
    }

    /**
     * @param string $urlHash
     */
    public function setUrlHash(string $urlHash) {
        $this->urlHash = $urlHash;
    }

    public function __construct() {
        $this->date = new \DateTime();
    }

}
