<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohBundle\Twig;

use Irstea\BdohBundle\Doi\Citations;
use Irstea\BdohDataBundle\Entity\DataSet;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Les citations dans les gabarits, dans la langue de la page (Irstea\BdohBundle\Doi\Citations) : celle d'un
 * observatoire, d'un jeu de données, et les autres DOI d'un observatoire.
 */
class CitationExtension extends AbstractExtension
{
    public function __construct(private Citations $citations, private RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('observatoryCitation', fn (Observatoire $o) => $this->citations->observatory($o, $this->locale())),
            new TwigFunction('datasetCitation', fn (DataSet $d) => $this->citations->dataset($d, $this->locale())),
            new TwigFunction('otherDois', fn (Observatoire $o) => $this->citations->others($o, $this->locale())),
        ];
    }

    private function locale(): string
    {
        return str_starts_with((string) $this->requests->getCurrentRequest()?->getLocale(), 'en') ? 'en' : 'fr';
    }
}
