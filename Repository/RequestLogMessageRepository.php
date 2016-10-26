<?php

namespace Wucdbm\Bundle\WucdbmHttpLoggerBundle\Repository;

use Wucdbm\Bundle\WucdbmHttpLoggerBundle\Entity\RequestLogMessage;

class RequestLogMessageRepository extends \Doctrine\ORM\EntityRepository {

    /**
     * @param $id
     * @return RequestLogMessage|null
     */
    public function findOneById($id) {
        $builder = $this->createQueryBuilder('m')
            ->addSelect('t')
            ->leftJoin('m.type', 't')
            ->andWhere('m.id = :id')
            ->setParameter('id', $id);

        $query = $builder->getQuery();

        return $query->getOneOrNullResult();
    }

}
