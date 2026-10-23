<?php

namespace Irstea\BdohBundle\Job;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityRepository;

/**
 * Les requêtes de JMS JobQueue dont BDOH se sert, sur les mêmes tables.
 */
class JobRepository extends EntityRepository
{
    /**
     * La tâche ouverte (en attente ou en cours) de cette commande pour cette entité, s'il y en a une.
     *
     * @throws \Doctrine\ORM\NonUniqueResultException s'il y en a plusieurs, comme JMS
     */
    public function findOpenJobForRelatedEntity($command, $relatedEntity)
    {
        return $this->findJobForRelatedEntity($command, $relatedEntity, [Job::STATE_RUNNING, Job::STATE_PENDING, Job::STATE_NEW]);
    }

    public function findJobForRelatedEntity($command, $relatedEntity, array $states = [])
    {
        $em = $this->getEntityManager();
        $metadata = $em->getClassMetadata(get_class($relatedEntity));
        $id = $metadata->getIdentifierValues($relatedEntity);
        asort($id);

        $sql = 'SELECT j.id FROM jms_jobs j INNER JOIN jms_job_related_entities r ON r.job_id = j.id
                 WHERE r.related_class = :class AND r.related_id = :id AND j.command = :command';
        $params = ['class' => $metadata->getName(), 'id' => json_encode($id), 'command' => $command];
        $types = [];
        if ($states) {
            $sql .= ' AND j.state IN (:states)';
            $params['states'] = $states;
            $types['states'] = ArrayParameterType::STRING;
        }
        $ids = $em->getConnection()->fetchFirstColumn($sql, $params, $types);
        if (\count($ids) > 1) {
            throw new \Doctrine\ORM\NonUniqueResultException();
        }

        return $ids ? $this->find($ids[0]) : null;
    }

    /**
     * La prochaine tâche à lancer : en attente, sans exécuteur, à son heure, hors des files
     * occupées ; par priorité, puis dans l'ordre d'arrivée.
     */
    public function findStartableJob(array $excludedQueues)
    {
        $builder = $this->createQueryBuilder('j')
            ->where('j.state = :state')
            ->andWhere('j.workerName IS NULL')
            ->andWhere('j.executeAfter < :now')
            ->orderBy('j.priority', 'ASC')
            ->addOrderBy('j.id', 'ASC')
            ->setMaxResults(1)
            ->setParameter('state', Job::STATE_PENDING)
            ->setParameter('now', new \DateTime(), 'datetime');
        if ($excludedQueues) {
            $builder->andWhere('j.queue NOT IN (:queues)')->setParameter('queues', $excludedQueues);
        }

        return $builder->getQuery()->getOneOrNullResult();
    }
}
