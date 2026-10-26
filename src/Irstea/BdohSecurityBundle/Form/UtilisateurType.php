<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Form;

use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * {@inheritdoc}
 */
abstract class UtilisateurType extends AbstractType
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * UtilisateurType constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface $em
     */
    public function __construct(\Doctrine\ORM\EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $propertyCategorie = 'libelle';
        if (\strpos($options['locale'], 'en') === 0) {
            $propertyCategorie .= 'En';
        }
        $builder
            ->add('prenom', TextType::class, ['required' => true, 'label' => 'prenom'])
            ->add('nom', TextType::class, ['required' => true, 'label' => 'nom'])
            ->add('organisme', TextType::class, ['required' => true, 'attr' => ['class' => 'col-md-8'], 'label' => 'organisme'])
            ->add('email', EmailType::class, ['required' => true, 'attr' => ['class' => 'col-md-8'], 'label' => 'email'])
            //->add('password', PasswordType::class, ['required' => true, 'label' => 'password'])
            ->add('password', CheckedPasswordType::class, ['required' => true, 'label' => 'password'])
            ->add('telephone', TextType::class, ['required' => true, 'label' => 'telephone'])
            ->add('adresse', TextareaType::class, ['required' => true, 'attr' => ['class' => 'col-md-10', 'style' => 'height: 80px;'], 'label' => 'adresse'])
            ->add(
                'categorie',
                null,
                [
                    'label'         => 'categorie',
                    'required'      => true,
                    'query_builder' => $this->em->getRepository('Irstea\BdohSecurityBundle\Entity\Categorie')->createQueryBuilder('cat')->orderBy('cat.' . $propertyCategorie, 'ASC'),
                    'choice_label'  => $propertyCategorie,
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => Utilisateur::class,
                // La langue de la page, pour l'ordre et le libellé des listes
                'locale'     => 'fr',
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'irstea_bdohsecuritybundle_utilisateurtype';
    }
}
