<?php

namespace Irstea\BdohAdminBundle\Admin\Extension;

use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Le groupe de champs que Sonata crée de lui-même porte le nom de l'entité au singulier (« Unité »),
 * et non le libellé de l'administration, au pluriel (« Unités »).
 *
 * L'ancien BDOH le faisait dans Admin::setFormGroups, que Sonata 4 a rendu final.
 */
final class SingularFormGroupExtension extends AbstractAdminExtension
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function configureFormFields(FormMapper $form): void
    {
        $admin = $form->getAdmin();
        $groups = $admin->getFormGroups();
        foreach ($groups as &$group) {
            if ($group['label'] === $admin->getLabel()) {
                $group['label'] = $this->translator->trans($admin->getClassnameLabel(), [], 'entitiesSingulars');
            }
        }
        $admin->setFormGroups($groups);
    }
}
