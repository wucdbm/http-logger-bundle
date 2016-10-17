<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

class RequestLogMessageType {

    const ID_URL_ENCODED = 1,
        ID_HTML = 2,
        ID_XML = 3,
        ID_JSON = 4,
        ID_TEXT_PLAIN = 5;

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var RequestLogMessage[] */
    protected $messages;

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
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name) {
        $this->name = $name;
    }

    /**
     * @return RequestLogMessage[]
     */
    public function getMessages() {
        return $this->messages;
    }

    public function __construct() {
        //
    }

}
