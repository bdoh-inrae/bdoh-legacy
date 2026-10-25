<?php

namespace Irstea\BdohBundle\Job;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;

/**
 * Les entités liées d'une tâche (chronique, observatoire, utilisateur), rangées comme le faisait JMS
 * JobQueue dans jms_job_related_entities : la classe réelle de l'entité, et son identifiant en JSON
 * ({"id":17}). Chargées à la lecture d'une tâche, enregistrées par JobManager.
 */
class JobRelatedEntities
{
    public function postLoad(Job $job, PostLoadEventArgs $event): void
    {
        $em = $event->getObjectManager();
        $rows = $em->getConnection()->fetchAllNumeric(
            'SELECT related_class, related_id FROM jms_job_related_entities WHERE job_id = :id',
            ['id' => $job->getId()]
        );
        // Regroupées par classe, dans l'ordre où chaque classe apparaît, comme JMS les chargeait
        $byClass = [];
        foreach ($rows as [$class, $id]) {
            $byClass[$class][] = json_decode($id, true);
        }
        foreach ($byClass as $class => $ids) {
            foreach ($ids as $id) {
                if (class_exists($class) && ($entity = $em->find($class, $id))) {
                    $job->addRelatedEntity($entity);
                }
            }
        }
    }

    /**
     * Enregistre les entités liées d'une tâche qui vient d'être écrite.
     */
    public static function save(EntityManagerInterface $em, Job $job): void
    {
        foreach ($job->getRelatedEntities() as $entity) {
            $metadata = $em->getClassMetadata(get_class($entity));
            $id = $metadata->getIdentifierValues($entity);
            asort($id);
            $em->getConnection()->executeStatement(
                'INSERT INTO jms_job_related_entities (job_id, related_class, related_id) VALUES (:job, :class, :id)',
                ['job' => $job->getId(), 'class' => $metadata->getName(), 'id' => json_encode($id)]
            );
        }
    }
}
