<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Repository\ObservatoireRepository;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This class allows to :
 *   => load the 'observatoire' context ;
 *   => manage the current 'observatoire' ;
 *   => manage the css file of an 'observatoire' ;.
 */
class ObservatoireManager implements ObservatoireManagerInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * Constructor.
     *
     * @param EntityManagerInterface $em
     * @param RouterInterface        $router
     * @param TranslatorInterface    $translator
     */
    public function __construct(EntityManagerInterface $em, RouterInterface $router, TranslatorInterface $translator)
    {
        $this->em = $em;
        $this->router = $router;
        $this->translator = $translator;
    }

    /**
     * Gets the current 'observatoire'.
     *
     * @return Observatoire
     */
    public function getCurrent()
    {
        return Observatoire::getCurrent();
    }

    /**
     * Loads the 'observatoire' context only if request contains the '_observatoire' attribute.
     * This attribute must be an 'observatoire' slug.
     */
    public function loadContext(KernelEvent $event)
    {
        $request = $event->getRequest();

        // If no '_observatoire' attribute => nothing to do !
        if (false === $request->attributes->has('_observatoire')) {
            return;
        }

        $observatoireSlug = strtoupper($request->attributes->get('_observatoire'));

        // Stores the 'observatoire' entity from its slug
        $this->setCurrentFromSlug($observatoireSlug);

        // Stores this slug in the request context of router service
        $this->router->getContext()->setParameter('_observatoire', $observatoireSlug);
    }

    /**
     * Gets 'observatoire' entity in DB from its slug.
     * Then, stores it as the current 'observatoire'.
     *
     * @param string $observatoireSlug
     *
     * @throws NotFoundHttpException if 'observatoire' doesn't exist in DB
     */
    protected function setCurrentFromSlug($observatoireSlug)
    {
        /** @var ObservatoireRepository $repObs */
        $repObs = $this->em->getRepository(Observatoire::class);

        // If does NOT EXIST in DB => EXCEPTION !

        /* @var $observatoire \Irstea\BdohDataBundle\Entity\Observatoire */
        $observatoire = $repObs->findOneBySlug($observatoireSlug);

        if (!$observatoire) {
            $errorMessage = $this->translator->trans(
                'Observatoire.notExist(%observatoireSlug%)',
                ['%observatoireSlug%' => $observatoireSlug]
            );
            throw new NotFoundHttpException($errorMessage);
        }

        $observatoire->defineAsCurrent();
    }
}
