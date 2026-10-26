<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BesoinType extends AbstractType
{
    protected $em;

    public function __construct(\Doctrine\ORM\EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $propertyObjectif = 'libelle';
        $propertyType = 'libelle';
        $propertyParametre = 'nom';
        $isLocaleEn = \strpos($options['locale'], 'en') === 0;
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
            ->add('duree', TextType::class, ['label' => 'besoin.duree', 'required' => true])
            ->add(
                'objectif',
                null,
                [
                    'label'         => 'besoin.objectif',
                    'required'      => true,
                    'query_builder' => $this->em->getRepository('Irstea\BdohSecurityBundle\Entity\ObjectifRecherche')->createQueryBuilder('ore')->orderBy('ore.' . $propertyObjectif, 'ASC'),
                    'choice_label'  => $propertyObjectif,
                ]
            )
            ->add(
                'type',
                null,
                [
                    'label'         => 'besoin.type',
                    'required'      => true,
                    'query_builder' => $this->em->getRepository('Irstea\BdohSecurityBundle\Entity\TypeTravaux')->createQueryBuilder('tt')->orderBy('tt.' . $propertyType, 'ASC'),
                    'choice_label'  => $propertyType,
                ]
            )
            ->add(
                'parametres',
                null,
                [
                    'label'         => 'besoin.parametres',
                    'required'      => true,
                    'query_builder' => $this->em->getRepository('Irstea\BdohDataBundle\Entity\Observatoire')->createParametresQueryBuilder($isLocaleEn),
                    'choice_label'  => $propertyParametre,
                ]
            )
            ->add(
                'details',
                TextareaType::class,
                [
                    'label'    => 'besoin.details',
                    'required' => true,
                    'attr'     => ['class' => 'col-md-17', 'style' => 'height: 150px;'],
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => 'Irstea\BdohSecurityBundle\Entity\Besoin',
                // La langue de la page, pour l'ordre et le libellé des listes
                'locale'     => 'fr',
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return 'irstea_bdohsecuritybundle_besointype';
    }
}
