<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin;

use Doctrine\ORM\EntityRepository as DoctrineEntityRepository;
use Irstea\BdohBundle\Manager\ObservatoireManagerInterface;
use Irstea\BdohDataBundle\Entity\Repository\EntityRepository;
use Irstea\BdohLoggerBundle\Logger\BdohLogger;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Psr\Container\ContainerInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Translator\NoopLabelTranslatorStrategy;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Defines some parameters & treatments for all BDOH admin classes.
 *
 * Les administrations demandent leurs services par $this->getContainer()->get('…'), comme dans
 * l'ancien BDOH : ceux de getSubscribedServices(), et eux seuls (abonnement aux services de Symfony).
 */
abstract class Admin extends AbstractAdmin implements ServiceSubscriberInterface
{
    /**
     * @var BdohLogger
     */
    protected $logger;

    /**
     * @var ContainerInterface
     */
    private $container;

    public static function getSubscribedServices(): array
    {
        return [
            'translator'                       => TranslatorInterface::class,
            'router'                           => RouterInterface::class,
            'twig'                             => Environment::class,
            'mailer'                           => MailerInterface::class,
            'parameter_bag'                    => ParameterBagInterface::class,
            'security.token_storage'           => TokenStorageInterface::class,
            'irstea_bdoh.manager.observatoire' => ObservatoireManagerInterface::class,
        ];
    }

    #[Required]
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @param BdohLogger $logger
     */
    #[Required]
    public function setLogger(BdohLogger $logger)
    {
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        /* Label name */
        $this->setLabel($this->getContainer()->get('translator')->trans($this->getClassnameLabel(), [], 'entitiesPlurals'));

        /* Labels translator strategy */
        $transStrategy = new NoopLabelTranslatorStrategy();
        $this->setLabelTranslatorStrategy($transStrategy);

        foreach (['edit', 'list', 'delete'] as $name) {
            $this->setTemplate($name, '@IrsteaBdohAdmin/CRUD/' . $name . '.html.twig');
        }
    }

    /* Route name */
    protected function generateBaseRouteName(bool $isChildAdmin = false): string
    {
        return 'bdoh_admin_' . $this->urlize($this->getClassnameLabel());
    }

    /* Route pattern */
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return $this->urlize($this->getClassnameLabel(), '-');
    }

    /**
     * Pagination de Sonata 3 : 32 lignes par page par défaut.
     */
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_per_page'] = 32;
    }

    /**
     * Choix du nombre de lignes par page de Sonata 3.
     */
    public function getPerPageOptions(): array
    {
        return [16, 32, 64, 128, 192];
    }

    /**
     * Direct access to the service container.
     *
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        return $this->container;
    }

    /**
     * Direct access to the current User.
     *
     * @return Utilisateur|null
     */
    protected function getUser()
    {
        $token = $this->getContainer()->get('security.token_storage')->getToken();

        return $token ? $token->getUser() : null;
    }

    /**
     * Shortcut to return a Doctrine repository.
     *
     * @param string $entity
     *
     * @return DoctrineEntityRepository
     */
    public function getRepository($entity)
    {
        return $this->getModelManager()->getEntityManager($entity)->getRepository($entity);
    }

    /**
     *  Shortcut to return a Doctrine repository of a BDOH entity.
     *
     * @param string $bdohEntity
     *
     * @return EntityRepository
     */
    public function getBdohRepo($bdohEntity)
    {
        return $this->getRepository('Irstea\\BdohDataBundle\\Entity\\' . $bdohEntity);
    }

    /**
     * No export formats.
     */
    public function getExportFormats(): array
    {
        return [];
    }

    /**
     * No batch actions.
     */
    protected function configureBatchActions(array $actions): array
    {
        return [];
    }
}
