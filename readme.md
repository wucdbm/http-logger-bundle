# TODO
- This doesn't play very nice with GuzzleHttp form_params (passing an array, instead of body as http_build_query) and cookies (using a cookie jar)

# Purpose

The purpose of this Bundle is to log HTTP Request/Responses in logs.
In addition to that, you can host exceptions (\Throwable). 
This is especially useful when working with terribly written APIs you have no control of, that tend to easily break libxml (and thus Symfony's Crawler).

# Presentation

At this point, the bundle has no presentation of the data it collects.
You should implement that on your own.

# Basic Usage

```
$manager = $this->container->get('some.manager');
$log = $manager->log('This is some message with any information that would eventually help you once you need to debug something');

try {
    $client = new \GuzzleHttp\Client();
    $request = new Request('GET', 'http://some-website.com/');

    $manager->logRequest($log, $request, RequestLogMessageType::ID_TEXT_PLAIN);

    $response = $client->send($request);

    $manager->logResponse($log, $response, RequestLogMessageType::ID_HTML);

    $ex = new \Exception('First Exception');

    throw new \Exception('Second Exception', 0, $ex);
} catch (\Throwable $e) {
    $manager->logException($log, $e);
}
```

# Advanced Usage

```
$log = $this->log('SomeClass::someMethod()');

try {
    $request = new Request('POST', 'https://someUri.com/API', [
        RequestOptions::BODY => 'SomeBody'
    ]);

    $this->logRequest($log, $request, RequestLogMessageType::ID_XML);

    $this->pool->sendAsync($request, function (ResponseInterface $response) use ($log) {
        try {
            $rawResponse = $response->getBody()->getContents();

            $this->logResponse($log, $response, RequestLogMessageType::ID_XML);

            $crawler = new Crawler($rawResponse);

            try {
                // do some Crawler work
            } catch (\InvalidArgumentException $ex) {
                $this->exception($log, $ex, $crawler->html());
            }
        } catch (\Throwable $ex) {
            $this->exception($log, $ex);
        }
    }, function (RequestException $ex) use ($log) {
        $this->requestException($log, $ex, RequestLogMessageType::ID_XML);
    });
} catch (\Throwable $ex) {
    $this->exception($log, $ex);
}
```

There is also a method called "logGuzzleException". It is a shorthand for logging the response, if any, upon HTTP 500 and such.
Keep in mind that this is a very basic example. The real power of this bundle comes when you have to execute tons of requests asynchronously, without human overview, via curl, and where it is painfully hard to find which one exactly went broke, without proper logging.

# Installation & Setup

## config.yml

```
wucdbm_http_logger:
    configs:
        bookings:
            table_prefix: some_logs__
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

class YourRequestLogLogger extends AbstractLogger {

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
        /** @var RequestLog $log */
        $log = parent::_log($msg);
        
        $log->setSomeOtherEntity($entity);

        $this->save($log);

        return $log;
    }

}
```