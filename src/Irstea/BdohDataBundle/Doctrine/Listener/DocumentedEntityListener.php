<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Doctrine\Listener;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use Irstea\BdohDataBundle\Entity\DocumentedEntityInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Met à jour/supprime des documents liés à une entité.
 */
class DocumentedEntityListener implements LoggerAwareInterface
{
    /**
     * @var string
     */
    public $webRoot;

    /**
     * @var string
     */
    public $documentPath = 'uploads';

    /**
     * @var PropertyAccessorInterface
     */
    public $propertyAccessor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string[]
     */
    private $toRename = [];

    /**
     * @var string[]
     */
    private $toUnlink = [];

    /**
     * DocumentedEntityListener constructor.
     */
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        string $webRoot = '',
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?LoggerInterface $logger = null
    ) {
        $this->webRoot = $webRoot;
        $this->propertyAccessor = $propertyAccessor ?? new PropertyAccessor();
        $this->logger = $logger ?? new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param DocumentedEntityInterface $entity
     *
     * @throws \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     * @throws \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @throws \Symfony\Component\PropertyAccess\Exception\AccessException
     */
    public function prePersist(DocumentedEntityInterface $entity)
    {
        foreach ($entity->getDocumentFields() as $field) {
            /* @var UploadedFile $value */
            $value = $this->propertyAccessor->getValue($entity, $field);

            if ($value instanceof UploadedFile) {
                $this->uploadFile($entity, $field, $value);
            }
        }
    }

    /**
     * @param DocumentedEntityInterface $entity
     * @param string                    $field
     * @param UploadedFile              $file
     */
    private function uploadFile(DocumentedEntityInterface $entity, $field, UploadedFile $newFile)
    {
        // L'extension vient du contenu, validé (image ou PDF), et non du nom que donne le navigateur
        $normalized = $this->normalizePath($entity, $field, $newFile->guessExtension() ?? 'bin');
        $this->renameFile($newFile->getRealPath(), $normalized);
        $this->propertyAccessor->setValue($entity, $field, $normalized);
    }

    /**
     * @param mixed  $field
     * @param string $extension
     *
     * @return string
     */
    private function normalizePath(DocumentedEntityInterface $entity, $field, $extension)
    {
        $filename = $field;
        if (substr($filename, 0, 4) === 'path') {
            $filename = lcfirst(substr($filename, 4));
        }

        return sprintf(
            '%s/%s-%s.%s',
            $this->documentPath,
            $entity->getDocumentFilePrefix(),
            $filename,
            $extension
        );
    }

    /**
     * @param string $absSource
     * @param string $relTarget
     */
    private function renameFile($absSource, $relTarget)
    {
        $absTarget = $this->webRoot . DIRECTORY_SEPARATOR . $relTarget;
        $this->logger->debug("Planning to rename $absSource to $absTarget");
        $this->toRename[$absSource] = $absTarget;
    }

    /**
     * @param DocumentedEntityInterface $entity
     * @param PreUpdateEventArgs        $event
     *
     * @throws \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     * @throws \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @throws \Symfony\Component\PropertyAccess\Exception\AccessException
     */
    public function preUpdate(DocumentedEntityInterface $entity, PreUpdateEventArgs $event)
    {
        $recompute = false;

        foreach ($entity->getDocumentFields() as $field) {
            if (!$event->hasChangedField($field)) {
                continue;
            }

            $old = $event->getOldValue($field);
            $new = $event->getNewValue($field);

            if ($new instanceof UploadedFile) {
                $this->logger->debug("New file for $field", compact('entity', 'field', 'old', 'new'));
                if (!empty($old)) {
                    $this->unlinkFile($old);
                }
                $this->uploadFile($entity, $field, $new);
                $recompute = true;
            } elseif (empty($new)) {
                if (!empty($old)) {
                    $this->logger->debug("$field removed", compact('entity', 'field', 'old', 'new'));
                    $this->unlinkFile($old);
                }
            }
        }

        if ($recompute) {
            $metadata = $event->getObjectManager()->getClassMetadata(get_class($entity));
            $event->getObjectManager()->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $entity);
        }
    }

    /**
     * @param string $relTarget
     */
    private function unlinkFile($relTarget)
    {
        $absTarget = $this->webRoot . DIRECTORY_SEPARATOR . $relTarget;
        $this->logger->debug("Planning to unlink $absTarget");
        $this->toUnlink[] = $absTarget;
    }

    /**
     * @param DocumentedEntityInterface $entity
     *
     * @throws \Symfony\Component\PropertyAccess\Exception\AccessException
     * @throws \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @throws \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function preRemove(DocumentedEntityInterface $entity)
    {
        foreach ($entity->getDocumentFields() as $field) {
            $file = $this->propertyAccessor->getValue($entity, $field);
            if ($file) {
                $this->unlinkFile($file);
            }
        }
    }

    public function postPersist()
    {
        $this->processPendingOperations();
    }

    public function postUpdate()
    {
        $this->processPendingOperations();
    }

    public function postRemove()
    {
        $this->processPendingOperations();
    }

    public function processPendingOperations()
    {
        $this->logger->debug(
            'processPendingOperations',
            ['toUnlink' => $this->toUnlink, 'toRename' => $this->toRename]
        );

        foreach ($this->toUnlink as $path) {
            $this->doUnlink($path);
        }
        $this->toUnlink = [];

        foreach ($this->toRename as $source => $target) {
            $this->doRename($source, $target);
        }
        $this->toRename = [];
    }

    /**
     * @param $path
     */
    private function doUnlink($path)
    {
        if (!file_exists($path)) {
            return;
        }
        $this->logger->debug("Trying to remove $path");
        if (unlink($path) && !file_exists($path)) {
            $this->logger->info("Removed $path");
        } else {
            $this->logger->warning("Could not remove $path");
        }
    }

    /**
     * @param $source
     * @param $target
     */
    private function doRename($source, $target)
    {
        $this->doUnlink($target);
        $this->logger->debug("Trying to rename $source to $target");
        $moved = is_uploaded_file($source) ? move_uploaded_file($source, $target) : rename($source, $target);
        if ($moved && file_exists($target)) {
            $this->logger->info("Renamed $source to $target");
        } else {
            $this->logger->warning("Could not rename $source to $target");
        }
    }
}
