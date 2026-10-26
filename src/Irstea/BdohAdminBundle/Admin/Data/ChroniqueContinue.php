<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use Doctrine\ORM\EntityRepository;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue as ChroniqueContinueEntity;
use Irstea\BdohDataBundle\Entity\OptionEchantillonnageSortie;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ChroniqueContinue.
 */
class ChroniqueContinue extends Chronique
{
    /**
     * @var array
     */
    private $samplings = null;

    /**
     * @var array
     */
    private $outputTimeSteps = null;

    /**
     * @var null
     */
    private $hadToSetExportOptions = null;

    /**
     * @return array
     */
    public function getSamplings()
    {
        if ($this->samplings === null) {
            $this->samplings = [];
            foreach ($this->getBdohRepo('Echantillonnage')->findAll() as $sampling) {
                /* @var \Irstea\BdohDataBundle\Entity\Echantillonnage $sampling */
                $this->samplings[$sampling->getCode()] = $sampling;
            }
        }

        return $this->samplings;
    }

    /**
     * @return array
     */
    public function getCumulativeSamplings()
    {
        return $this->filterMapSamplings(
            null,
            function (\Irstea\BdohDataBundle\Entity\Echantillonnage $s) {
                return $s->getCode() === 'cumulative';
            }
        );
    }

    /**
     * @return array
     */
    public function getOrientedSamplings()
    {
        return $this->filterMapSamplings(
            null,
            function (\Irstea\BdohDataBundle\Entity\Echantillonnage $s) {
                return $s->getHasDirection();
            }
        );
    }

    /**
     * @return array
     */
    public function getSamplingOrders()
    {
        return $this->filterMapSamplings(
            function (\Irstea\BdohDataBundle\Entity\Echantillonnage $s) {
                return $s->getOrdreSortie();
            }
        );
    }

    /**
     * @param callable|null $map
     * @param callable|null $filter
     *
     * @return array
     */
    private function filterMapSamplings(?callable $map = null, ?callable $filter = null)
    {
        $samplings = $this->getSamplings();
        $output = [];
        foreach ($samplings as $sampling) {
            /** @var \Irstea\BdohDataBundle\Entity\Echantillonnage $sampling */
            if ($filter === null || call_user_func($filter, $sampling)) {
                $output[$sampling->getId()] = $map ? call_user_func($map, $sampling) : true;
            }
        }

        return $output;
    }

    /**
     * @return array|null
     */
    public function getOutputTimeSteps()
    {
        if ($this->outputTimeSteps === null) {
            $ots = [];
            foreach ($this->getBdohRepo('OptionEchantillonnageSortie')->findAll() as $item) {
                /* @var OptionEchantillonnageSortie $item */
                $ots[$item->getId()] = $item->getEchantillonnageEntree()->getId();
            }
            $this->outputTimeSteps = $ots;
        }

        return $this->outputTimeSteps;
    }

    /**
     * @param bool $excludeNew
     *
     * @return bool
     */
    public function getHadToSetDefaultExportOptions($excludeNew = false)
    {
        $subject = $this->hasSubject() ? $this->getSubject() : null;
        if ($this->hadToSetExportOptions === null) {
            $this->hadToSetExportOptions = $subject && $subject->getMustEditExportOptions();
        }

        return $this->hadToSetExportOptions && (!$excludeNew || ($subject && $subject->getId() !== null));
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-chronique-continue.html.twig');
    }

    /**
     * {@inheritdoc}
     */
    protected function getUniteHelp(TranslatorInterface $translator): string
    {
        return $translator->trans('admin.help.chronique.update-unite-cumul');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        /* @var $chronique ChroniqueContinueEntity */
        $chronique = $this->getSubject();

        // If export options were not set for this time series, then set default values
        if ($this->getHadToSetDefaultExportOptions()) {
            // If no sampling was defined for this time series, set 'instantaneous'
            $defaultSamplingCode = 'instantaneous';

            $allSamplings = $this->getSamplings();

            // Time series' sampling & its code
            $sampling = $chronique->getEchantillonnage();
            if (!$sampling) {
                $sampling = $allSamplings[$defaultSamplingCode];
                $chronique->setEchantillonnage($sampling);
            }
            $samplingCode = $sampling->getCode();

            // Default export options
            $dae = $this->getContainer()->get('parameter_bag')->get('default_allowed_exports');
            $daeForSampling = $dae[$samplingCode];

            // Set default export samplings
            foreach ($daeForSampling['samplings'] as $code) {
                $chronique->addEchantillonnagesSortieLicite($allSamplings[$code]);
            }

            // Set default regular time steps
            foreach ($daeForSampling['steps'] as $valueAndUnit) {
                $qb = $this->getBdohRepo('OptionEchantillonnageSortie')->createQueryBuilder('oes')
                    ->leftJoin('oes.pasEchantillonnage', 'pe')
                    ->where('oes.echantillonnageEntree = :e')
                    ->andWhere('pe.valeur = :v')
                    ->andWhere('pe.unite = :u')
                    ->setParameter('e', $sampling)
                    ->setParameter('v', $valueAndUnit[0])
                    ->setParameter('u', $valueAndUnit[1]);
                $oes = $qb->getQuery()->getSingleResult();
                $chronique->addOptionsEchantillonnageSortie($oes);
            }
        }

        if (!$chronique->getUniteCumul() && $chronique->getUnite()) {
            $chronique->setUniteCumul($chronique->getUnite());
        }

        parent::configureFormFields($formMapper);

        $translator = $this->getContainer()->get('translator');
        $inEnglish = \strpos($translator->getLocale(), 'en') === 0;
        $i18nSuffix = $inEnglish ? 'en' : '';

        $formMapper
            ->add(
                'echantillonnage',
                null,
                [
                    'required'                  => true,
                    'choice_label'              => 'code',
                    'choice_translation_domain' => 'echantillonnage',
                ]
            )
            ->add(
                'directionMesure',
                ChoiceType::class,
                [ // Field for "measure direction"
                    'choices'  => [
                        $translator->trans('period.ahead')    => 'ahead',
                        $translator->trans('period.backward') => 'backward',
                    ],
                    'multiple' => false,
                    'expanded' => true,
                ]
            )
            ->add(
                'echantillonnagesSortieLicites',
                null,
                [ // Field for allowed regular-step sampling types
                    'multiple'     => true,
                    'expanded'     => true,
                    'choice_label' => 'nom' . $i18nSuffix,
                ]
            )
            ->add('uniteCumul')
            ->add(
                'optionsEchantillonnageSortie',
                null,
                [ // Field for allowed regular time steps
                    'multiple'      => true,
                    'expanded'      => true,
                    'attr'          => ['class' => 'inputs-list-inline'],
                    'choice_label'  => 'libelle' . $i18nSuffix,
                    'query_builder' => function (EntityRepository $repo) {
                        return $repo->createQueryBuilder('o')
                            ->innerJoin('o.pasEchantillonnage', 'p')
                            ->orderBy('p.approxSecondes', 'DESC');
                    },
                ]
            );
    }
}
