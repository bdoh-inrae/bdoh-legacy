<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

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
