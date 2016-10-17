# Purpose

The purpose of this Bundle is to log HTTP Request/Responses in logs.
In addition to that, you can host exceptions (\Throwable). 
This is especially useful when working with terribly written APIs you have no control of, that tend to easily break libxml (and thus Symfony's Crawler).

# Presentation

At this point, the bundle has no presentation of the data it collects.
You should implement that on your own.

# Installation & Setup

## config.yml

```
wucdbm_http_logger:
    configs:
        bookings:
            log_class: Some\Name\Space\RequestLog
            log_message_class: Some\Name\Space\RequestLogMessage
            log_message_type_class: Some\Name\Space\RequestLogMessageType
            log_exception_class: Some\Name\Space\RequestLogException
```
            
## AppKernel

```
new \Wucdbm\Bundle\WucdbmHttpLoggerBundle\WucdbmHttpLoggerBundle(),
```

You need to extend each of the entities and create your own. 
You can freely add any additional fields and map them via your preferred method. 
The base mapping is done via a Subscriber in the bundle.

```
<?php

namespace Some\Name\Space;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="SomeRepositoryClass")
 * @ORM\Table(name="some_table")
 */
class YourRequestLog extends \Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog {

    /**
     * @ORM\ManyToOne(targetEntity="Some\Name\Space\SomeOtherEntity", inversedBy="inverseSideField")
     * @ORM\JoinColumn(name="relation_id", referencedColumnName="id", nullable=alse)
     */
    protected $someOtherEntity;
    
}    
```

Finally, before you can use the logger, you must create a Logger that extends `\Wucdbm\Bundle\WucdbmHttpLoggerBundle\Logger\AbstractLogger`
You must implement the factory methods for creating each of your entities. 
This may be automated in future versions, so I would advise against creating constructors on these, unless I get enough time and get a proper implementation using an interface and a base factory that just works out of the box.


```
<?php

namespace App\Logger;

// any other use, dopped for brevity
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Logger\AbstractLogger;

class BookingLogger extends AbstractLogger {

    /**
     * @return YourRequestLog
     */
    protected function createLog() {
        return new YourRequestLog();
    }

    /**
     * @return RequestLogMessage
     */
    protected function createLogMessage() {
        return new RequestLogMessage();
    }

    /**
     * @return RequestLogException
     */
    protected function createLogException() {
        return new RequestLogException();
    }

    /**
     * @return RequestLogMessageType
     */
    protected function createLogMessageType() {
        return new RequestLogMessageType();
    }

    /**
     * @return YourRequestLog
     */
    public function log(string $msg, SomeOtherEntity $entity) {
        /** @var YourRequestLog $log */
        $log = parent::log($msg);
        
        $log->setSomeOtherEntity($entity);

        $this->save($log);

        return $log;
    }

}
```