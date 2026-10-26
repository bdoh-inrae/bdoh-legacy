<?php

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Irstea\BdohAdminBundle\Form\Type\TheiaThesaurusType;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Validator\ErrorElement;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class TheiaCategories extends Admin
{
    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('nom', null, ['route' => ['name' => 'edit']])
            ->add('familleParametre')
            ->add('milieu');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('milieu')
            ->add('familleParametre');
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $translator = $this->getContainer()->get('translator');
        $formMapper
            ->add('milieu', null, ['required' => true, 'placeholder' => '---',])
            ->add('nom', HiddenType::class)
            ->add('familleParametre', null, ['required' => true, 'placeholder' => '---'])
            ->add(
                'uriOzcarTheia',
                TheiaThesaurusType::class,
                [
                    'multiple'  => true,
                    'required'  => true,
                    'help'      => $translator->trans('admin.help.theia.uriOzcarTheia'),
                    'help_html' => true,
                ]
            );
    }

    public function validate(ErrorElement $errorElement, $object)
    {
        $errorElement
            ->with('uriOzcarTheia')
            ->assertCount(
                [
                    'min'        => 1,
                    'minMessage' => 'Veuillez sélectionner au moins une catégorie',
                ]
            )
            ->end();
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationTheiaCategories(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.TheiaCategoriesCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationTheiaCategories(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.TheiaCategoriesUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationTheiaCategories(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.TheiaCategoriesRemove',
            $object
        );
    }
}
