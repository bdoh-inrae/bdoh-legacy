<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Irstea\BdohBundle\Model\LabelledEntityInterface;
use Irstea\BdohBundle\Model\LabelledEntityTrait;

/**
 * Class Qualite.
 */
class Qualite implements LabelledEntityInterface
{
    use LabelledEntityTrait;

    const GAP_CODE = 'gap';

    const GAP_ORDRE = 100;

    const INVALID_ORDRE = 200;

    /**
     * @var array
     */
    public static $ordresInvalides = [self::GAP_ORDRE, self::INVALID_ORDRE];

    const LD_ORDRE = 600;

    const LQ_ORDRE = 700;

    /**
     * @var array
     */
    public static $ordresLimites = [self::LD_ORDRE, self::LQ_ORDRE];

    const DEFAULT_COLOR = [0, 0, 255]; // bleu #0000FF

    const DEFAULT_THICKNESS = 1;

    const DEFAULT_STYLE = [
        'color'     => self::DEFAULT_COLOR,
        'thickness' => self::DEFAULT_THICKNESS,
    ];

    const DEFAULT_GAP_COLOR = [255, 0, 0]; // rouge #FF0000

    const DEFAULT_GAP_THICKNESS = 5;

    const DEFAULT_GAP_STYLE = [
        'color'     => self::DEFAULT_GAP_COLOR,
        'thickness' => self::DEFAULT_GAP_THICKNESS,
    ];

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $libelle;

    /**
     * @var string
     */
    protected $libelleEn;

    /**
     * @var int
     */
    protected $ordre;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var Collection
     */
    protected $traductions;

    /**
     * @var JeuQualite
     */
    protected $jeu;

    /**
     * @var array|null
     */
    protected $style;

    /**
     * Qualite constructor.
     */
    public function __construct()
    {
        $this->traductions = new ArrayCollection();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set code.
     *
     * @param string $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set ordre.
     *
     * @param int $ordre
     */
    public function setOrdre($ordre)
    {
        $this->ordre = $ordre;
    }

    /**
     * Get ordre.
     *
     * @return int
     */
    public function getOrdre()
    {
        return $this->ordre;
    }

    /**
     * Set type.
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Add traductions.
     *
     * @param QualiteJeuQualite $traduction
     */
    public function addTraduction(QualiteJeuQualite $traduction)
    {
        $this->traductions[] = $traduction;
    }

    /**
     * Remove traductions.
     *
     * @param QualiteJeuQualite $traductions
     */
    public function removeTraduction(QualiteJeuQualite $traduction)
    {
        $this->traductions->removeElement($traduction);
    }

    /**
     * Get traductions.
     *
     * @return Collection
     */
    public function getTraductions()
    {
        return $this->traductions;
    }

    /**
     * Set jeu.
     *
     * @param JeuQualite $jeu
     */
    public function setJeu(JeuQualite $jeu)
    {
        $this->jeu = $jeu;
    }

    /**
     * Get jeu.
     *
     * @return JeuQualite
     */
    public function getJeu()
    {
        return $this->jeu;
    }

    /**
     * Set style.
     *
     * @param array|null $style
     */
    public function setStyle($style)
    {
        $this->style = $style;
    }

    /**
     * Get style.
     *
     * @return array
     */
    public function getStyle()
    {
        $isGap = $this->isInvalide();
        $style = $this->style ?: ($isGap ? self::DEFAULT_GAP_STYLE : self::DEFAULT_STYLE);
        // always return a fully defined style in case it has been defined partialy in case the style components evolved
        if (!isset($style['color'])) {
            $style['color'] = $isGap ? self::DEFAULT_GAP_COLOR : self::DEFAULT_COLOR;
        }
        if (!isset($style['thickness'])) {
            $style['thickness'] = $isGap ? self::DEFAULT_GAP_THICKNESS : self::DEFAULT_THICKNESS;
        }

        return $style;
    }

    /**
     * Is invalide.
     *
     * @return bool
     */
    public function isInvalide()
    {
        return \in_array($this->getOrdre(), self::$ordresInvalides, true);
    }

    /**
     * Is limite.
     *
     * @return bool
     */
    public function isLimite()
    {
        return \in_array($this->getOrdre(), self::$ordresLimites, true);
    }

    /**
     * Is default gap.
     *
     * @return bool
     */
    public function isDefaultGap()
    {
        // we don't want the 'gap' code since we don't use it
        return $this->ordre === self::GAP_ORDRE && $this->code !== self::GAP_CODE;
    }

    /**
     * To string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getCode() . ' (' . $this->getLibelle() . ')';
    }
}
