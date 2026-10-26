<?php

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohDataBundle\Entity\Chronique;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ChroniqueController extends CRUDController
{
    public function cloneAction(Request $request): Response
    {
        $object = $this->admin->getSubject();
        /** @var Chronique $object */
        $newObject = clone $object;

        $newObject->setCode($object->getCode().' (Clone)');

        $templateKey = 'edit';

        $this->admin->checkAccess('create');

        $class = new \ReflectionClass($this->admin->hasActiveSubClass() ? $this->admin->getActiveSubClass() : $this->admin->getClass());

        if ($class->isAbstract()) {
            return $this->renderWithExtraParams(
                '@SonataAdmin/CRUD/select_subclass.html.twig',
                [
                    'action' => 'create',
                ]
            );
        }

        $preResponse = $this->preCreate($request, $newObject);
        if ($preResponse !== null) {
            return $preResponse;
        }

        $this->admin->setSubject($newObject);

        /** @var $form \Symfony\Component\Form\Form */
        $form = $this->admin->getForm();
        $form->setData($newObject);

        $form->handleRequest($request);

        $formView = $form->createView();

        return $this->renderWithExtraParams($this->admin->getTemplateRegistry()->getTemplate($templateKey), [
            'action' => 'clone',
            'form' => $formView,
            'object' => $newObject,
            'objectId' => null,
        ]);
    }
}
