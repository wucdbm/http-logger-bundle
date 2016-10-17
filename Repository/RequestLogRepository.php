<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Repository;

use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLog;

class RequestLogRepository extends \Doctrine\ORM\EntityRepository {

    public function save(RequestLog $log) {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        $conn->beginTransaction();

        try {
            if ($request = $log->getRequest()) {
                $em->persist($request);
            }

            if ($response = $log->getResponse()) {
                $em->persist($response);
            }

            if ($exception = $log->getException()) {
                $em->persist($exception);
            }

            $em->persist($log);
            $em->flush();

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    public function remove(RequestLog $log) {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        $conn->beginTransaction();

        try {
            if ($request = $log->getRequest()) {
                $em->remove($request);
            }

            if ($response = $log->getResponse()) {
                $em->remove($response);
            }

            if ($exception = $log->getException()) {
                $em->remove($exception);
            }

            $em->remove($log);
            $em->flush();

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

}
