<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Logger;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLogException;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLogMessage;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLogMessageType;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Repository\RequestLogExceptionRepository;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Repository\RequestLogMessageRepository;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Repository\RequestLogMessageTypeRepository;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Repository\RequestLogRepository;

abstract class AbstractLogger {

    /** @var RequestLogRepository */
    protected $logRepository;

    /** @var RequestLogExceptionRepository */
    protected $logExceptionRepository;

    /** @var RequestLogMessageRepository */
    protected $logMessageRepository;

    /** @var RequestLogMessageTypeRepository */
    protected $logMessageTypeRepository;

    private function getMessageLogTypeNameMap() {
        return [
            RequestLogMessageType::ID_URL_ENCODED => 'URL Encoded',
            RequestLogMessageType::ID_HTML        => 'HTML',
            RequestLogMessageType::ID_XML         => 'XML',
            RequestLogMessageType::ID_JSON        => 'JSON',
            RequestLogMessageType::ID_TEXT_PLAIN  => 'Plain Text',
        ];
    }

    private function getMessageLogTypeName(int $id) {
        $map = $this->getMessageLogTypeNameMap();

        return isset($map[$id]) ? $map[$id] : 'Unknown';
    }

    /**
     * @param int $id
     * @return RequestLogMessageType
     */
    public function getLogMessageType(int $id) {
        $type = $this->logMessageTypeRepository->findOneById($id);

        if (!$type) {
            $type = $this->createLogMessageType();
            $type->setId($id);
            $type->setName($this->getMessageLogTypeName($id));
            $this->logMessageTypeRepository->save($type);
        }

        return $type;
    }

    public function save(RequestLog $log) {
        $this->logRepository->save($log);
    }

    /**
     * Due to the invariant return types implementation in php
     * We can't really set the return type here
     * @return RequestLog
     */
    protected abstract function createLog();

    /**
     * @return RequestLogMessage
     */
    protected abstract function createLogMessage();

    /**
     * @return RequestLogException
     */
    protected abstract function createLogException();

    /**
     * @return RequestLogMessageType
     */
    protected abstract function createLogMessageType();

    /**
     * You should implement your own log method, if you require any additional parameters
     * If not, copying the below in your implementation is OK
     * Adapt anything else to your particular needs
     *
     * public function log(string $message) {
     *      $log = $this->_log($message);
     *
     *      $this->save($log);
     *
     *      return $log;
     * }
     */

    /**
     * @param string $message
     * @return RequestLog
     */
    protected function _log(string $message) {
        $log = $this->createLog();
        $log->setMessage($message);

        return $log;
    }

    /**
     * @param RequestLog $log
     * @param RequestInterface $request
     * @param int $messageType
     */
    public function logRequest(RequestLog $log, RequestInterface $request, int $messageType) {
        $this->_logRequest($log, $request, $messageType);

        $this->save($log);
    }

    protected function _logRequest(RequestLog $log, RequestInterface $request, int $messageType) {
        $body = $request->getBody();
        $body->rewind();
        $content = $body->getContents();
        $body->rewind();

        $messageType = $this->getLogMessageType($messageType);

        $message = $this->createLogMessage();
        $message->setContent($content);
        $message->setHeaders($request->getHeaders());
        $message->setType($messageType);
        $message->setRequestTo($log);

        $log->setRequest($message);

        if ($url = $request->getUri()) {
            $log->setUrl($url);
            $log->setUrlHash(md5($url));
        }

        $log->setMethod($request->getMethod());
    }

    /**
     * @param RequestLog $log
     * @param ResponseInterface $response
     * @param int $messageType
     */
    public function logResponse(RequestLog $log, ResponseInterface $response, int $messageType) {
        $this->_logResponse($log, $response, $messageType);

        $this->save($log);
    }

    public function _logResponse(RequestLog $log, ResponseInterface $response, int $messageType) {
        $body = $response->getBody();
        $body->rewind();
        $content = $body->getContents();
        $body->rewind();

        try {
            // Sometimes, as it appears, content is not in UTF-8, regardless of what server reports
            // Other times, headers say UTF-8, HTML content says iso-8859-1 via meta http-equiv="Content-Type"
            // So, always convert
            $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
        } catch (\Throwable $e) {
            $log->setDebug(sprintf('Could not convert content to UTF-8'));
            $content = sprintf('%s: %s', get_class($e), $e->getMessage());
        }

        $messageType = $this->getLogMessageType($messageType);
        $message = $this->createLogMessage();
        $message->setContent($content);
        $message->setHeaders($response->getHeaders());
        $message->setType($messageType);
        $message->setResponseTo($log);

        $log->setResponse($message);

        $log->setStatusCode($response->getStatusCode());
    }

    /**
     * @param RequestLog $log
     * @param RequestException $exception
     * @param int $messageType
     */
    public function logGuzzleException(RequestLog $log, RequestException $exception, int $messageType) {
        if ($response = $exception->getResponse()) {
            $this->logResponse($log, $response, $messageType);
        }

        $this->logException($log, $exception);
    }

    public function logException(RequestLog $log, \Throwable $exception, $extraData = null) {
        $this->_logException($log, $exception, $extraData);

        $this->save($log);
    }

    public function _logException(RequestLog $log, \Throwable $exception, $extraData = null) {
        $ex = $this->createLogException();
        $ex->setLog($log);
        $ex->setStackTraceString($exception->getTraceAsString());
        $ex->setJson($this->getExceptionJson($exception));
        $ex->setFile($exception->getFile());
        $ex->setLine($exception->getLine());

        if ($code = $exception->getCode()) {
            $ex->setCode($code);
        }

        if ($message = $exception->getMessage()) {
            $ex->setMessage($message);
        }

        if ($extraData) {
            $ex->setExtraData($extraData);
        }

        $log->setException($ex);
    }

    protected function getExceptionJson(\Throwable $e) {
        return json_encode($this->getExceptionData($e));
    }

    protected function getExceptionData(\Throwable $e) {
        return [
            'class'    => get_class($e),
            'message'  => $e->getMessage(),
            'code'     => $e->getCode(),
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'trace'    => $this->getNormalizedTrace($e->getTrace()),
            'previous' => $e->getPrevious() ? $this->getExceptionData($e->getPrevious()) : null
        ];
    }

    protected function getNormalizedTrace(array $trace) {
        $ret = [];

        foreach ($trace as $row) {
            $ret[] = [
                'file'     => isset($row['file']) ? $row['file'] : '',
                'line'     => isset($row['line']) ? $row['line'] : '',
                'class'    => isset($row['class']) ? $row['class'] : '',
                'function' => isset($row['function']) ? $row['function'] : '',
                'type'     => isset($row['type']) ? $row['type'] : ''
            ];
        }

        return $ret;
    }

    public function __construct() {
        //
    }

    /**
     * @return RequestLogRepository
     */
    public function getLogRepository() {
        return $this->logRepository;
    }

    /**
     * @param RequestLogRepository $logRepository
     */
    public function setLogRepository(RequestLogRepository $logRepository) {
        $this->logRepository = $logRepository;
    }

    /**
     * @return RequestLogExceptionRepository
     */
    public function getLogExceptionRepository() {
        return $this->logExceptionRepository;
    }

    /**
     * @param RequestLogExceptionRepository $logExceptionRepository
     */
    public function setLogExceptionRepository(RequestLogExceptionRepository $logExceptionRepository) {
        $this->logExceptionRepository = $logExceptionRepository;
    }

    /**
     * @return RequestLogMessageRepository
     */
    public function getLogMessageRepository() {
        return $this->logMessageRepository;
    }

    /**
     * @param RequestLogMessageRepository $logMessageRepository
     */
    public function setLogMessageRepository(RequestLogMessageRepository $logMessageRepository) {
        $this->logMessageRepository = $logMessageRepository;
    }

    /**
     * @return RequestLogMessageTypeRepository
     */
    public function getLogMessageTypeRepository() {
        return $this->logMessageTypeRepository;
    }

    /**
     * @param RequestLogMessageTypeRepository $logMessageTypeRepository
     */
    public function setLogMessageTypeRepository(RequestLogMessageTypeRepository $logMessageTypeRepository) {
        $this->logMessageTypeRepository = $logMessageTypeRepository;
    }

}