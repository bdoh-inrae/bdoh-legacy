<?php

namespace Irstea\BdohAdminBundle\Form\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;

/**
 * Un nombre écrit avec au moins trois décimales, comme le faisait l'ancien formulaire (« 1000,000 »),
 * et jusqu'à quinze, sans arrondir ce qui est saisi : l'ancien arrondissait à trois décimales à chaque
 * enregistrement (dev/differences.md, « Seuils de validité arrondis »). La base en reçoit 14 chiffres
 * significatifs, ce que PHP écrit d'un nombre (réglage precision).
 */
final class PreciseNumberTransformer extends NumberToLocalizedStringTransformer
{
    protected function getNumberFormatter(): \NumberFormatter
    {
        $formatter = parent::getNumberFormatter();
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 3);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 15);

        return $formatter;
    }
}
