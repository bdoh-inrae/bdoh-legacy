<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Irstea\BdohBundle\Manager\ObservatoireManagerInterface;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohLoggerBundle\Logger\BdohLogger;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Base des contrôleurs de BDOH. Sous Symfony 2, $this->get() donnait accès à tout le conteneur ;
 * les services que les contrôleurs demandent ainsi sont désormais déclarés ici
 * (getSubscribedServices), sous leurs anciens identifiants, pour que ces appels restent tels quels.
 */
class Controller extends AbstractController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'doctrine'                          => ManagerRegistry::class,
            'translator'                        => TranslatorInterface::class,
            'event_dispatcher'                  => EventDispatcherInterface::class,
            'irstea_bdoh.manager.observatoire'  => ObservatoireManagerInterface::class,
            'irstea_bdoh_logger.logger'         => '?' . BdohLogger::class,
        ]);
    }

    /**
     * Un service par son ancien identifiant ; la session n'est plus un service, mais celle de la
     * requête en cours.
     */
    protected function get(string $id): object
    {
        if ($id === 'session') {
            return $this->container->get('request_stack')->getSession();
        }

        return $this->container->get($id);
    }

    protected function getDoctrine(): ManagerRegistry
    {
        return $this->container->get('doctrine');
    }

    /**
     * Shortcut to return the session manager.
     *
     * @return SessionInterface
     */
    public function getSession(): SessionInterface
    {
        return $this->get('session');
    }

    /**
     *  Shortcut to return the translator.
     *
     * @return TranslatorInterface
     */
    public function getTranslator(): TranslatorInterface
    {
        return $this->get('translator');
    }

    /**
     * Shortcut to return the default Doctrine Entity Manager.
     *
     * @return EntityManagerInterface
     */
    public function getEm(): EntityManagerInterface
    {
        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getDoctrine()->getManager();
    }

    /**
     * Shortcut to return a Doctrine repository.
     *
     * @param string $entity
     *
     * @return EntityRepository
     */
    public function getRepository(string $entity): EntityRepository
    {
        return $this->getDoctrine()->getRepository($entity);
    }

    /**
     * Shortcut to return the Doctrine repository suitable for an entity class.
     *
     * @param object $entity
     *
     * @return EntityRepository
     */
    public function getClassRepo($entity): EntityRepository
    {
        return $this->getRepository(get_class($entity));
    }

    /**
     *  Shortcut to return a Doctrine repository of a BDOH entity.
     *
     * @param string $bdohEntity
     *
     * @return EntityRepository
     */
    public function getBdohRepo(string $bdohEntity): EntityRepository
    {
        return $this->getRepository('Irstea\\BdohDataBundle\\Entity\\' . $bdohEntity);
    }

    /**
     * Shortcut to return a Doctrine repository of a "BDOH Security" entity.
     *
     * @param string $securityEntity
     *
     * @return EntityRepository
     */
    public function getSecurityRepo(string $securityEntity): EntityRepository
    {
        return $this->getRepository('Irstea\\BdohSecurityBundle\\Entity\\' . $securityEntity);
    }

    /**
     * @param mixed $data
     * @param int   $status
     * @param array $headers
     *
     * @return Response with json encoded data
     */
    public function renderJson($data, int $status = 200, array $headers = [])
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * @return bool true if the request is done by an ajax query
     */
    public function isXmlHttpRequest(): bool
    {
        $request = $this->get('request_stack')->getMainRequest();

        return $request ? $request->isXmlHttpRequest() : false;
    }

    /**
     * @return RedirectResponse to HTTP_REFERER
     */
    public function redirectToReferer(): RedirectResponse
    {
        $referer = $this->get('request_stack')->getMainRequest()->headers->get('referer');

        return new RedirectResponse($referer);
    }

    /**
     * @return bool
     */
    protected function isLocaleEn(): bool
    {
        $locale = $this->get('session')->get('_locale');

        return \strtolower(\substr((string) $locale, 0, 2)) === 'en';
    }

    /**
     * @return Observatoire|null
     */
    protected function getCurrentObservatoire()
    {
        return $this->get('irstea_bdoh.manager.observatoire')->getCurrent();
    }

    /**
     * @return Utilisateur|null
     */
    protected function getUtilisateur()
    {
        $token = $this->get('security.token_storage')->getToken();
        $user = $token ? $token->getUser() : null;

        return $user instanceof Utilisateur ? $user : null;
    }
}
