<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Extractor;

use Irstea\BdohDataBundle\Exporter\Exception\UnknownExtractorException;
use Irstea\BdohDataBundle\Exporter\Measure\ExtractorInterface;
use Irstea\BdohDataBundle\Exporter\Measure\FormatterInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class Factory.
 */
class Factory
{
    /**
     * @var ManagerRegistry
     */
    public $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @var string[]
     */
    private static $classes = [
        'identical'     => Identical::class,
        'instantaneous' => Instantaneous::class,
        'mean'          => Mean::class,
        'cumulative'    => Cumulative::class,
    ];

    /**
     * @param $extraction
     * @param FormatterInterface $formatter
     * @param array              $parameters
     *
     * @throws UnknownExtractorException
     *
     * @return ExtractorInterface
     */
    public function create($extraction, FormatterInterface $formatter, array $parameters)
    {
        $class = self::$classes[$extraction];
        if (!$class) {
            throw new UnknownExtractorException($extraction);
        }

        return new $class($this->doctrine, $formatter, $parameters);
    }
}
