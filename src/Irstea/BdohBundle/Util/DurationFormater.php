<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohBundle\Util;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class DurationFormater.
 */
class DurationFormater
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * DurationFormater constructor.
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param int $duration
     *
     * @return string
     */
    public function format(int $duration): string
    {
        $times = [
            'jour'    => 86400,
            'heure'   => 3600,
            'minute'  => 60,
            'seconde' => 1,
        ];

        $text = '';

        foreach ($times as $label => $time) {
            if ($text !== '') {
                $text .= ' ';
            }
            if ($duration >= $time) {
                $nb = intdiv($duration, $time);
                $duration %= $time;
                $text .= $nb . ' ' . $this->translator->trans($label, ['%count%' => $nb]);
            }
        }

        return trim($text);
    }
}
