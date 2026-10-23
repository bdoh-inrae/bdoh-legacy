<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;

/**
 * JeuBareme.
 */
class JeuBareme implements ChroniqueRelatedInterface
{
    use ChroniqueRelatedTrait;

    const LQ_LD_TRUE_VALUE = 'chronique.lq_ld.options.true_value';

    const LQ_LD_HALF_VALUE = 'chronique.lq_ld.options.half_value';

    const LQ_LD_PLACEHOLDER = 'chronique.lq_ld.options.placeholder';

    const LQ_LD_GAP = 'chronique.lq_ld.options.gap';

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Collection
     */
    protected $baremeJeuBaremes;

    /**
     * @var Transformation
     */
    protected $transformation;

    /**
     * @var \DateTime
     */
    private $dateCreation;

    /**
     * @var float
     */
    protected $delaiPropagation = 0;

    /**
     * @var string
     */
    protected $valueLimitTransformationType;

    /**
     * @var float
     */
    protected $valueLimitPlaceholder = 0.0;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->baremeJeuBaremes = new ArrayCollection();
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
     * Add baremeJeuBaremes.
     *
     * @param BaremeJeuBareme $baremeJeuBaremes
     *
     * @return JeuBareme
     */
    public function addBaremeJeuBareme(BaremeJeuBareme $baremeJeuBaremes)
    {
        $this->baremeJeuBaremes[] = $baremeJeuBaremes;

        return $this;
    }

    /**
     * Remove baremeJeuBaremes.
     *
     * @param BaremeJeuBareme $baremeJeuBaremes
     */
    public function removeBaremeJeuBareme(BaremeJeuBareme $baremeJeuBaremes)
    {
        $this->baremeJeuBaremes->removeElement($baremeJeuBaremes);
    }

    /**
     * Get baremeJeuBaremes.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBaremeJeuBaremes()
    {
        return $this->baremeJeuBaremes;
    }

    /**
     * Set transformation.
     *
     * @param Transformation $transformation
     *
     * @return JeuBareme
     */
    public function setTransformation(?Transformation $transformation = null)
    {
        $this->transformation = $transformation;

        return $this;
    }

    /**
     * Get transformation.
     *
     * @return Transformation
     */
    public function getTransformation()
    {
        return $this->transformation;
    }

    /**
     * Set dateCreation.
     *
     * @param \DateTime $dateCreation
     *
     * @return JeuBareme
     */
    public function setDateCreation($dateCreation)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation.
     *
     * @return \DateTime
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * @param JeuBareme $jeuBareme
     *
     * @return bool
     */
    public function equals($jeuBareme)
    {
        if (!$jeuBareme
            || $this->getDelaiPropagation() !== $jeuBareme->getDelaiPropagation()
            || $this->getValueLimitTransformationType() !== $jeuBareme->getValueLimitTransformationType()
            || ($this->getValueLimitTransformationType() && $this->getValueLimitPlaceholder() !== $jeuBareme->getValueLimitPlaceholder())
        ) {
            return false;
        }

        if (!$this->getBaremeJeuBaremes() && !$jeuBareme->getBaremeJeuBaremes()) {
            return true;
        }

        $bjbs1 = $this->getBaremeJeuBaremes();
        $bjbs2 = $jeuBareme->getBaremeJeuBaremes();
        $equals = ($bjbs1 && $bjbs2 && $bjbs1->count() === $bjbs2->count());

        while ($equals && ($bjb1 = $bjbs1->current())) {
            $equals = $bjb1->equals($bjbs2->current());
            $bjbs1->next();
            $bjbs2->next();
        }

        return $equals;
    }

    /**
     * Set propagation delay.
     *
     * @param float $delaiPropagation
     *
     * @return self
     */
    public function setDelaiPropagation($delaiPropagation)
    {
        $this->delaiPropagation = $delaiPropagation;

        return $this;
    }

    /**
     * Get propagation delay.
     *
     * @return float
     */
    public function getDelaiPropagation()
    {
        return $this->delaiPropagation;
    }

    /**
     * @return array
     */
    public static function getValueLimitTransformationTypeList()
    {
        return [
            self::LQ_LD_TRUE_VALUE,
            self::LQ_LD_HALF_VALUE,
            self::LQ_LD_PLACEHOLDER,
            self::LQ_LD_GAP,
        ];
    }

    /**
     * Set how value limits should be processed when transforming this chronique.
     *
     * @param string $valueLimitTransformationType
     *
     * @return self
     */
    public function setValueLimitTransformationType($valueLimitTransformationType = null)
    {
        if (!$valueLimitTransformationType) {
            $valueLimitTransformationType = null;
        }
        if ($valueLimitTransformationType && !\in_array($valueLimitTransformationType, self::getValueLimitTransformationTypeList())) {
            throw new InvalidArgumentException("Invalid value limit transformation type: $valueLimitTransformationType");
        }
        $this->valueLimitTransformationType = $valueLimitTransformationType;

        return $this;
    }

    /**
     * Get how value limits should be processed when transforming this chronique.
     *
     * @return string
     */
    public function getValueLimitTransformationType()
    {
        return $this->valueLimitTransformationType;
    }

    /**
     * Set the placeholder for value limits when transforming this chronique.
     *
     * @param float $valueLimitPlaceholder
     *
     * @return self
     */
    public function setValueLimitPlaceholder($valueLimitPlaceholder)
    {
        $this->valueLimitPlaceholder = $valueLimitPlaceholder;

        return $this;
    }

    /**
     * Get the placeholder for value limits when transforming this chronique.
     *
     * @return float
     */
    public function getValueLimitPlaceholder()
    {
        return $this->valueLimitPlaceholder;
    }

    /**
     * {@inheritdoc}
     */
    public function getChronique()
    {
        return $this->transformation->getChronique();
    }
}
