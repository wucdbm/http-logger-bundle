<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Twig;

use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog;

class LogExtension extends \Twig_Extension {

    public function getFilters() {
        return [
            new \Twig_SimpleFilter('getCurlCommand', [$this, 'getCurlCommand'])
        ];
    }

    public function getCurlCommand(RequestLog $log) {
        $request = $log->getRequest();

        if (!$request) {
            return '';
        }

        $method = $log->getMethod();

        $headers = [];
        foreach ($request->getHeaders() as $header => $values) {
            foreach ($values as $value) {
                $headers[] = sprintf('-H "%s: %s"', $header, $value);
            }
        }

        $pieces = [
            sprintf('-X %s', $method),
            implode(' ', $headers)
        ];

        if ($request->getContent()) {
            $pieces[] = sprintf('-d "%s"', str_replace('"', '\"', $request->getContent()));
        }

        $pieces[] = $log->getUrl();

        $command = sprintf('curl %s', implode(' ', $pieces));

        return $command;
    }

}