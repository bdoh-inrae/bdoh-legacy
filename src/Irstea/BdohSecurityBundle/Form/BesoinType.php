<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class BesoinType extends AbstractType
{
    protected $em;

    protected $locale;

    public function __construct(\Doctrine\ORM\EntityManagerInterface $em, $locale = 'fr')
    {
        $this->em = $em;
        $this->locale = $locale;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $propertyObjectif = 'libelle';
        $propertyType = 'libelle';
        $propertyParametre = 'nom';
        $isLocaleEn = \strpos($this->locale, 'en') === 0;
        if ($isLocaleEn) {
            $propertyObjectif .= 'En';
            $propertyType .= 'En';
            $propertyParametre .= 'En';
        }

        $builder
            ->add(
                'stations',
                null,
                [
                    'label'         => 'besoin.stations',
                    'required'      => true,
                    'query_builder' => $this->em->getRepository('Irstea\BdohDataBundle\Entity\Station')->createQueryBuilder('st')->orderBy('st.nom', 'ASC'),
                ]
            )
            ->add('duree', 'text', ['label' => 'besoin.duree', 'required' => true])
            ->add(
                'objectif',
                null,
                [
                    'label'         => 'besoin.objectif',
                    'required'      => true,
                    'query_builder' => $this->em->getRepository('Irstea\BdohSecurityBundle\Entity\ObjectifRecherche')->createQueryBuilder('ore')->orderBy('ore.' . $propertyObjectif, 'ASC'),
                    'property'      => $propertyObjectif,
                ]
            )
            ->add(
                'type',
                null,
                [
                    'label'         => 'besoin.type',
                    'required'      => true,
                    'query_builder' => $this->em->getRepository('Irstea\BdohSecurityBundle\Entity\TypeTravaux')->createQueryBuilder('tt')->orderBy('tt.' . $propertyType, 'ASC'),
                    'property'      => $propertyType,
                ]
            )
            ->add(
                'parametres',
                null,
                [
                    'label'         => 'besoin.parametres',
                    'required'      => true,
                    'query_builder' => $this->em->getRepository('Irstea\BdohDataBundle\Entity\Observatoire')->createParametresQueryBuilder($isLocaleEn),
                    'property'      => $propertyParametre,
                ]
            )
            ->add(
                'details',
                'textarea',
                [
                    'label'    => 'besoin.details',
                    'required' => true,
                    'attr'     => ['class' => 'col-md-17', 'style' => 'height: 150px;'],
                ]
            );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'Irstea\BdohSecurityBundle\Entity\Besoin',
            ]
        );
    }

    public function getName()
    {
        return 'irstea_bdohsecuritybundle_besointype';
    }
}
