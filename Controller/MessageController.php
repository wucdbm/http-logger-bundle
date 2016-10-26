<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Controller;

use Camspiers\JsonPretty\JsonPretty;
use PrettyXml\Formatter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLogMessageType;
use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Repository\RequestLogMessageRepository;

class MessageController extends Controller {

    public function viewAction($id, $class) {
        $doctrine = $this->getDoctrine();
        $em = $doctrine->getManager();
        /** @var RequestLogMessageRepository $repository */
        $repository = $em->getRepository($class);
        $message = $repository->findOneById($id);
        if ($message->getType()->getId() == RequestLogMessageType::ID_XML) {
            $formatter = new Formatter();
            $content = $formatter->format($message->getContent());
            $response = new Response($content);
            $response->headers->set('Content-type', 'text/plain');

            return $response;
        }

        if ($message->getType()->getId() == RequestLogMessageType::ID_JSON) {
            $jsonPretty = new JsonPretty();
            $content = $jsonPretty->prettify($message->getContent());
            $response = new Response($content);
            $response->headers->set('Content-type', 'text/plain');

            return $response;
        }

        if ($message->getType()->getId() == RequestLogMessageType::ID_TEXT_PLAIN) {
            $response = new Response($message->getContent());
            $response->headers->set('Content-type', 'text/plain');

            return $response;
        }

        if ($message->getType()->getId() == RequestLogMessageType::ID_HTML) {
            $response = new Response($message->getContent());
            $response->headers->set('Content-type', 'text/html');

            return $response;
        }

        if ($message->getType()->getId() == RequestLogMessageType::ID_URL_ENCODED) {
            $contents = $message->getContent();

            $array = explode('&', $contents);

            $rows = [];
            foreach ($array as $v) {
                list($key, $value) = explode('=', $v);
                $rows[] = implode(' => ', [$key, $value]);
            }

            $response = new Response(implode("\n", $rows));
            $response->headers->set('Content-type', 'text/plain');

            return $response;
        }

        return new Response('UNSUPPORTED TYPE');
    }

    public function viewRawAction($id, $class) {
        $doctrine = $this->getDoctrine();
        $em = $doctrine->getManager();
        /** @var RequestLogMessageRepository $repository */
        $repository = $em->getRepository($class);
        $message = $repository->findOneById($id);
        return new Response($message->getContent());
    }

}