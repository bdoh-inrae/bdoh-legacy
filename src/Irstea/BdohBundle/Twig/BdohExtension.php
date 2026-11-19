<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohBundle\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohBundle\Manager\CssManager;
use Irstea\BdohBundle\Manager\ObservatoireManagerInterface;
use Irstea\BdohBundle\Util\DurationFormater;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Security\AccountRoutes;
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
            // La route d'une page du compte, d'un observatoire ou du portail (Security/AccountRoutes.php)
            'accountRoute'        => new TwigFunction('accountRoute', fn (string $page) => AccountRoutes::name($page, $this->manager->getCurrent())),
            'currentObservatoire' => new TwigFunction('currentObservatoire', [$this->manager, 'getCurrent']),
            'allObservatoires'    => new TwigFunction('allObservatoires', [$this, 'getAllObservatoires']),
            'hasReservedChroniques' => new TwigFunction('hasReservedChroniques', [$this, 'hasReservedChroniques']),
        ];
    }

    /**
     * @var array<int, bool> par identifiant d'observatoire, le temps d'une requête
     */
    private array $reserved = [];

    /**
     * Si un observatoire a des chroniques réservées à ses membres (estvisible = false) : la demande d'accès, au menu du
     * compte et dans le courriel de bienvenue, ne sert qu'à elles (Lou, 2026-11-19 ; dev/plans/2026-11-16_reopening.md,
     * point 1). Une requête plutôt que les collections, que le menu chargerait sinon sur chaque page.
     */
    public function hasReservedChroniques(?Observatoire $observatoire): bool
    {
        if ($observatoire === null) {
            return false;
        }

        return $this->reserved[$observatoire->getId()] ??= $this->em->createQuery(
            'SELECT c.id FROM Irstea\BdohDataBundle\Entity\Chronique c JOIN c.station st JOIN st.sites si
             WHERE si.observatoire = :observatoire AND c.estVisible = false'
        )->setParameter('observatoire', $observatoire)->setMaxResults(1)->getOneOrNullResult() !== null;
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
            'excerpt'        => new TwigFilter('excerpt', [$this, 'excerpt']),
        ];
    }

    /**
     * Le début d'un texte mis en forme (la description d'un observatoire, écrite dans l'éditeur de l'administration),
     * en texte simple : sans balises ni entités, les blancs réduits, coupé avant $length caractères, à la fin d'un mot.
     * Pour les fiches de l'accueil du portail ; le texte entier reste sur la page de l'observatoire.
     */
    public function excerpt(?string $html, int $length = 240): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br', '</p>', '</li>'], [' <br', ' </p>', ' </li>'], $html ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > $length / 2 ? mb_substr($cut, 0, $space) : $cut, ' ,;:.') . '…';
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
