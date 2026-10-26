<?php

namespace Irstea\BdohAdminBundle\Admin\Extension;

use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Datagrid\ListMapper;

/**
 * Les colonnes des listes se comportent comme dans Sonata 3 :
 *  - le lien d'une colonne d'identifiant, quand l'administration n'en donne pas la route, mène à la
 *    page d'édition si l'utilisateur peut éditer, sinon à la page de consultation
 *    (ListMapper::addIdentifier de Sonata 3) ; Sonata 4 prendrait la route de la configuration ;
 *  - une colonne d'entité liée ne se trie pas : Sonata 3 ne triait que les champs simples, Sonata 4
 *    trie aussi les entités liées.
 */
final class Sonata3ListExtension extends AbstractAdminExtension
{
    public function configureListFields(ListMapper $list): void
    {
        $admin = $list->getAdmin();
        foreach ($list->keys() as $key) {
            $field = $list->get($key);
            if ($field->getOption('identifier') && null === $field->getOption('route')) {
                $name = $admin->hasAccess('edit') && $admin->hasRoute('edit') ? 'edit' : 'show';
                $field->setOption('route', ['name' => $name, 'parameters' => []]);
            }
            if ($field->describesAssociation()) {
                $field->setOption('sortable', false);
            }
        }
    }
}
