<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Class JeuQualite.
 */
class JeuQualite
{
    const DEFAULT_CHECKPOINT_SHAPE = 'circle';

    const DEFAULT_CHECKPOINT_STROKE = 2;

    const DEFAULT_CHECKPOINT_COLOR = [0, 128, 0]; // vert #008000

    const DEFAULT_CHECKPOINT_SIZE = 5;

    const DEFAULT_CHECKPOINT_STYLE = [
        'shape'  => self::DEFAULT_CHECKPOINT_SHAPE,
        'stroke' => self::DEFAULT_CHECKPOINT_STROKE,
        'color'  => self::DEFAULT_CHECKPOINT_COLOR,
        'size'   => self::DEFAULT_CHECKPOINT_SIZE,
    ];

    const DEFAULT_DISCONTINUOUS_SHAPE = 'filledCircle';

    const DEFAULT_DISCONTINUOUS_STROKE = 2;

    const DEFAULT_DISCONTINUOUS_SIZE = 5;

    const DEFAULT_DISCONTINUOUS_STYLE = [
        'shape'  => self::DEFAULT_DISCONTINUOUS_SHAPE,
        'stroke' => self::DEFAULT_DISCONTINUOUS_STROKE,
        'size'   => self::DEFAULT_DISCONTINUOUS_SIZE,
    ];

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $nom;

    /**
     * @var array|null
     */
    protected $checkpointStyle;

    /**
     * @var array|null
     */
    protected $discontinuousStyle;

    /**
     * @var bool
     */
    protected $estAffectable = false;

    /**
     * @var Collection|Qualite[]
     */
    protected $qualites;

    /**
     * JeuQualite constructor.
     */
    public function __construct()
    {
        $this->qualites = new ArrayCollection();
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
     * Set nom.
     *
     * @param string $nom
     */
    public function setNom($nom)
    {
        $this->nom = $nom;
    }

    /**
     * Get nom.
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set estAffectable.
     *
     * @param bool $estAffectable
     */
    public function setEstAffectable($estAffectable)
    {
        $this->estAffectable = $estAffectable;
    }

    /**
     * Get estAffectable.
     *
     * @return bool
     */
    public function getEstAffectable()
    {
        return $this->estAffectable;
    }

    /**
     * Add qualites.
     *
     * @param Qualite $qualite
     */
    public function addQualite(Qualite $qualite)
    {
        $this->qualites[$qualite->getCode()] = $qualite;
    }

    /**
     * Remove qualites.
     *
     * @param Qualite $qualites
     */
    public function removeQualite(Qualite $qualites)
    {
        $this->qualites->removeElement($qualites);
    }

    /**
     * Get qualites.
     *
     * @return Qualite[]|Collection
     */
    public function getQualites()
    {
        return $this->qualites;
    }

    /**
     * @param string $code
     *
     * @return Qualite
     */
    public function getQualite($code)
    {
        if (!$this->qualites->containsKey($code)) {
            throw new \OutOfBoundsException("Unknown qualite in $this: " . json_encode($code));
        }

        return $this->qualites->get($code);
    }

    /**
     * Renvoie la qualité "lacune" par défaut.
     *
     * @return Qualite|null
     */
    public function getDefaultGapQualite()
    {
        foreach ($this->qualites as $q) {
            if ($q->isDefaultGap()) {
                return $q;
            }
        }

        return null;
    }

    /**
     * Est-ce que ce jeu contient des qualités lq/ld.
     *
     * @return bool
     */
    public function hasValueLimits()
    {
        foreach ($this->qualites as $q) {
            if (in_array($q->getOrdre(), Qualite::$ordresLimites, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set checkpoint style.
     *
     * @param array|null $style
     */
    public function setCheckpointStyle($style)
    {
        $this->checkpointStyle = $style;
    }

    /**
     * Get checkpoint style.
     *
     * @return array
     */
    public function getCheckpointStyle()
    {
        $checkpointStyle = $this->checkpointStyle ?: self::DEFAULT_CHECKPOINT_STYLE;
        // always return a fully defined style in case it has been defined partialy in case the style components evolved
        if (!isset($checkpointStyle['shape'])) {
            $checkpointStyle['shape'] = self::DEFAULT_CHECKPOINT_SHAPE;
        }
        if (!isset($checkpointStyle['stroke'])) {
            $checkpointStyle['stroke'] = self::DEFAULT_CHECKPOINT_STROKE;
        }
        if (!isset($checkpointStyle['color'])) {
            $checkpointStyle['color'] = self::DEFAULT_CHECKPOINT_COLOR;
        }
        if (!isset($checkpointStyle['size'])) {
            $checkpointStyle['size'] = self::DEFAULT_CHECKPOINT_SIZE;
        }

        return $checkpointStyle;
    }

    /**
     * Set discontinuous style.
     *
     * @param array|null $style
     */
    public function setDiscontinuousStyle($style)
    {
        $this->discontinuousStyle = $style;
    }

    /**
     * Get discontinuous style.
     *
     * @return array
     */
    public function getDiscontinuousStyle()
    {
        $discontinuousStyle = $this->discontinuousStyle ?: self::DEFAULT_DISCONTINUOUS_STYLE;
        // always return a fully defined style in case it has been defined partialy in case the style components evolved
        if (!isset($discontinuousStyle['shape'])) {
            $discontinuousStyle['shape'] = self::DEFAULT_DISCONTINUOUS_SHAPE;
        }
        if (!isset($discontinuousStyle['stroke'])) {
            $discontinuousStyle['stroke'] = self::DEFAULT_DISCONTINUOUS_STROKE;
        }
        if (!isset($discontinuousStyle['size'])) {
            $discontinuousStyle['size'] = self::DEFAULT_DISCONTINUOUS_SIZE;
        }

        return $discontinuousStyle;
    }

    /**
     * To string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getNom();
    }
}
