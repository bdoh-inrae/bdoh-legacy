<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Security;

use Irstea\BdohAdminBundle\Admin\Admin;
use Irstea\BdohSecurityBundle\Entity\Categorie;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Mime\Email;

/**
 * Class Utilisateur.
 */
class Utilisateur extends Admin
{
    /**
     * Configure the ordered field.
     */
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        parent::configureDefaultSortValues($sortValues);
        $sortValues['_sort_by'] = 'nom';  // name of the ordered field
        // (default = the model's id field, if any)
    }

    /**
     * @param RouteCollection $collection
     */
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection
            ->remove('delete');
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('email', null, ['route' => ['name' => 'edit']])
            ->add('prenom')
            ->add('nom')
            ->add('organisme')
            ->add('finAcces', FieldDescriptionInterface::TYPE_DATETIME, ['format' => $this->getContainer()->get('translator')->trans('formatDateTime')]);
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $categoryProperty = 'libelle';
        $translator = $this->getContainer()->get('translator');
        if (\strpos($translator->getLocale(), 'en') === 0) {
            $categoryProperty .= 'En';
        }
        $categoryQueryBuilder = $this->getRepository('Irstea\\BdohSecurityBundle\\Entity\\Categorie')
            ->createQueryBuilder('c')->orderBy('c.' . $categoryProperty);

        $formMapper
            ->add('finAcces', DateType::class, ['required' => false, 'widget' => 'choice'])
            ->add('email', EmailType::class, ['attr' => ['class' => 'col-md-8']])
            ->add('prenom', TextType::class)
            ->add('nom', TextType::class)
            ->add('organisme', TextType::class, ['attr' => ['class' => 'col-md-8']])
            ->add('categorie', EntityType::class, [
                'class'         => Categorie::class,
                'required'      => true,
                'choice_label'  => $categoryProperty,
                'query_builder' => $categoryQueryBuilder,
            ])
            ->add('telephone', TextType::class)
            ->add('adresse', TextareaType::class, ['attr' => ['class' => 'col-md-10', 'style' => 'height: 80px;']]);
    }

    /**
     * @param $object
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationUtilisateur(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.UtilisateurCreate',
            $object
        );

        //Send a mail to inform the user of her new account
        $message = (new Email())
            ->subject($this->getContainer()->get('translator')->trans('utilisateur.newAccountMail.title'))
            ->from('bdoh@inrae.fr')
            ->to($object->getEmail())
            ->text(
                $this->getContainer()->get('twig')->render(
                    '@IrsteaBdohAdmin/Mail/newAccount.txt.twig',
                    ['user' => $object]
                )
            );

        $this->getContainer()->get('mailer')->send($message);
    }

    /**
     * @param $object
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationUtilisateur(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.UtilisateurUpdate',
            $object
        );
    }

    /**
     * @param $object
     */
    protected function prePersist(object $object): void
    {
        // Pas de mot de passe : connexion par un lien envoyé par courriel (dev/differences.md). Les
        // colonnes n'acceptent pas NULL.
        $object->setPassword('');
        $object->setSalt('');
    }
}
