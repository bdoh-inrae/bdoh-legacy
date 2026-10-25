<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Formatter;

use Irstea\BdohDataBundle\Exporter\Exception\UnknownFormatException;
use Irstea\BdohDataBundle\Exporter\Measure\FormatterInterface;

/**
 * Class Factory.
 */
class Factory
{
    /**
     * @var array
     */
    private static $classes = [
        'bdoh'      => Bdoh::class,
        'qtvar'     => Qtvar::class,
    ];

    /**
     * @param $format
     *
     * @return FormatterInterface
     */
    public function create($format)
    {
        $class = self::$classes[$format] ?? null;
        if (!$class) {
            throw new UnknownFormatException($format);
        }

        return new $class();
    }
}
