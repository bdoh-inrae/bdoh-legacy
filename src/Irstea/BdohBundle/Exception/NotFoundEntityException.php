<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Thrown when the searching for an entity (from certain criteria)
 * returns nothing.
 */
class NotFoundEntityException extends NotFoundHttpException implements Exception
{
    private $criteria;

    private $entityClass;

    /**
     * @param string $entityClass
     * @param array  $criteria    Keys are criteria and values are values for these criteria
     */
    public function __construct($entityClass, $criteria = [])
    {
        $this->entityClass = $entityClass;
        $this->criteria = $criteria;

        $txt = "A '%s' entity was not found";
        if ($criteria) {
            $txt .= ' from the following criteria : %s';
        }

        parent::__construct(sprintf($txt, $entityClass, $this->criteriaToString()));
    }

    public function getCriteria()
    {
        return $this->criteria;
    }

    public function getEntityClass()
    {
        return $this->entityClass;
    }

    public function criteriaToString()
    {
        $txt = '';
        foreach ($this->criteria as $key => $value) {
            $txt .= $key . $value . ', ';
        }

        return substr($txt, 0, -2);
    }
}
