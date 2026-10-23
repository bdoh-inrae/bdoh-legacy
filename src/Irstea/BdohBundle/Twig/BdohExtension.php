<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohBundle\Manager\CssManager;
use Irstea\BdohBundle\Manager\ObservatoireManagerInterface;
use Irstea\BdohBundle\Util\DurationFormater;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Twig extension for Bdoh.
 */
class BdohExtension extends AbstractExtension
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var ObservatoireManagerInterface
     */
    protected $manager;

    /**
     * @var CssManager
     */
    protected $cssManager;

    /**
     * @var DurationFormater
     */
    protected $durationFormater;

    /**
     * @param EntityManagerInterface       $em
     * @param ObservatoireManagerInterface $manager
     * @param CssManager                   $cssManager
     * @param DurationFormater             $durationFormater
     */
    public function __construct(
        EntityManagerInterface $em,
        ObservatoireManagerInterface $manager,
        CssManager $cssManager,
        DurationFormater $durationFormater
    ) {
        $this->em = $em;
        $this->manager = $manager;
        $this->cssManager = $cssManager;
        $this->durationFormater = $durationFormater;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'BdohExtension';
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions()
    {
        return [
            'round'               => new TwigFunction('round', 'round'),
            'themeCssFile'        => new TwigFunction('themeCssFile', [$this, 'getThemeCssFile']),
            'currentObservatoire' => new TwigFunction('currentObservatoire', [$this->manager, 'getCurrent']),
            'allObservatoires'    => new TwigFunction('allObservatoires', [$this, 'getAllObservatoires']),
        ];
    }

    /**
     * @return TwigTest[]
     */
    public function getTests()
    {
        return [
            new TwigTest('instanceof', [$this, 'isInstanceof'])
        ];
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters()
    {
        return [
            'durationFormat' => new TwigFilter('durationFormat', [$this->durationFormater, 'format']),
        ];
    }

    /**
     * @return Observatoire[]
     */
    public function getAllObservatoires()
    {
        try {
            return $this->em->getRepository(Observatoire::class)->findAll();
        } catch (\Exception $ex) {
            return [];
        }
    }

    /**
     * @return string
     */
    public function getThemeCssFile()
    {
        try {
            $observatoire = $this->manager->getCurrent();

            return $this->cssManager->getObservatoireCssFile($observatoire);
        } catch (\Exception $ex) {
            return '';
        }
    }

    /**
     * @param $var
     * @param $instance
     * @return bool
     */
    public function isInstanceof($var, $instance) {
        return  $var instanceof $instance;
    }
}
