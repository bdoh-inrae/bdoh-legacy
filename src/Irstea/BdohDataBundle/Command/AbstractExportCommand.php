<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Exporter\Measure\Archiver\ConsoleArchiver;
use Irstea\BdohDataBundle\Exporter\Measure\Archiver\ZipArchiver;
use Irstea\BdohDataBundle\Exporter\Measure\ArchiverInterface;
use Irstea\BdohDataBundle\Exporter\Measure\ExporterBuilder;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Class AbstractExportCommand.
 */
abstract class AbstractExportCommand extends Command
{
    const COMMAND_NAME_PREFIX = 'bdoh:export:';
    //const EXTRACTOR_TYPE = 'abstract';
    const DATETIME_FORMAT = "yyyy-MM-dd'T'HH:mm:ssX";

    /**
     * @var ExporterBuilder
     */
    public $exporterBuilder;

    /**
     * @var TranslatorInterface
     */
    public $translator;

    /**
     * @var Environment
     */
    public $templating;

    /**
     * @var MailerInterface
     */
    public $mailer;

    /**
     * @var ManagerRegistry
     */
    public $doctrine;

    public function __construct(
        ExporterBuilder $exporterBuilder,
        TranslatorInterface $translator,
        Environment $templating,
        MailerInterface $mailer,
        ManagerRegistry $doctrine
    ) {
        $this->exporterBuilder = $exporterBuilder;
        $this->translator = $translator;
        $this->templating = $templating;
        $this->mailer = $mailer;
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME_PREFIX . static::EXTRACTOR_TYPE)
            ->setDescription('Export de chroniques')
            ->addOption('jms-job-id', null, InputOption::VALUE_REQUIRED, 'Identifiant du job')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Localisation', 'fr')
            ->addOption('send-to', 'm', InputOption::VALUE_REQUIRED, "E-mail d'un utilisateur à qui envoyer l'archive")
            ->addOption('write-to', 'o', InputOption::VALUE_REQUIRED, 'Chemin du fichier de sortie')
            ->addOption('timezone', 't', InputOption::VALUE_REQUIRED, 'Fuseau horaire cible', 'UTC')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, "Format d'export", 'bdoh')
            ->addArgument('begin', InputArgument::REQUIRED, 'Date de début')
            ->addArgument('end', InputArgument::REQUIRED, 'Date de fin')
            ->addArgument(
                'chroniques',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Identifiants des chroniques à exporter'
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Form\Exception\AlreadySubmittedException
     * @throws \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @throws \Doctrine\ORM\EntityNotFoundException
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arguments = array_merge($input->getArguments(), $input->getOptions());
        $parameters = $this->validateParameters($arguments);

        $parameters['date'] = new \DateTime('now', new \DateTimeZone('UTC'));

        $this->setLocale($parameters['locale']);
        unset($parameters['locale']);

        $user = $parameters['user'] = $parameters['send-to'] ?? null;

        $archiver = $this->createArchiver($parameters, $output);
        unset($parameters['send-to'], $parameters['write-to']);

        $chroniques = $parameters['chroniques'];
        unset($parameters['chroniques']);

        $parameters['observatoire']->defineAsCurrent();

        $this->exporterBuilder
            ->getExporter($parameters, $archiver)
            ->export($chroniques);

        if ($user !== null) {
            return 1 - $this->sendArchive($archiver->getArchivePath(), $user, $chroniques);
        }

        return 0;
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    private function validateParameters(array $arguments)
    {
        $resolver = new OptionsResolver();
        $this->configureParameterResolver($resolver);

        $values = array_intersect_key($arguments, array_fill_keys($resolver->getDefinedOptions(), true));
        $parameters = $resolver->resolve($values);

        unset($parameters['command']);

        return $parameters;
    }

    /**
     * @param array           $parameters
     * @param OutputInterface $output
     *
     * @return ArchiverInterface
     */
    private function createArchiver(array $parameters, OutputInterface $output)
    {
        if (isset($parameters['send-to'])) {
            $archiver = new ZipArchiver();
            $filePath = $archiver->getArchivePath();
            register_shutdown_function(
                function () use ($filePath) {
                    @unlink($filePath);
                }
            );

            return $archiver;
        }

        if (isset($parameters['write-to'])) {
            return new ZipArchiver($parameters['write-to']);
        }

        return new ConsoleArchiver($output);
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @return mixed
     */
    protected function configureParameterResolver(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'send-to'      => null,
                'write-to'     => null,
                'extractor'    => static::EXTRACTOR_TYPE,
                'observatoire' => null,
                'locale'       => 'fr',
            ]
        );

        $resolver->setRequired(
            [
                'command',
                'format',
                'chroniques',
                'timezone',
                'begin',
                'end',
            ]
        );

        foreach (
            [
                'send-to'    => ['string', 'null'],
                'write-to'   => ['string', 'null'],
                'chroniques' => ['array'],
                'format'     => ['string'],
                'timezone'   => ['string'],
                'begin'      => ['string'],
                'end'        => ['string'],
            ] as $option => $types
        ) {
            $resolver->addAllowedTypes($option, $types);
        }

        foreach (
            [
                'send-to'      => function (Options $options, $value) {
                    if (!$value) {
                        return null;
                    }

                    return $this->doctrine
                        ->getRepository(Utilisateur::class)
                        ->findOneByEmail($value);
                },
                'chroniques'   => function (Options $options, $value) {
                    if (!$value) {
                        return [];
                    }

                    return array_map(
                        function ($id) {
                            return $this->doctrine
                                ->getRepository(Chronique::class)
                                ->findOneById($id);
                        },
                        $value
                    );
                },
                'timezone'     => function (Options $options, $value) {
                    return new \DateTimeZone(str_replace(':', '', trim($value, "'")));
                },
                'begin'        => function (Options $options, $value) {
                    return new \DateTime($value, $options['timezone']);
                },
                'end'          => function (Options $options, $value) {
                    return new \DateTime($value, $options['timezone']);
                },
                'observatoire' => function (Options $options, $value) {
                    $chroniques = $options['chroniques'];

                    return count($chroniques) > 0 ? $chroniques[0]->getObservatoire() : null;
                },
            ] as $option => $normalizer
        ) {
            $resolver->setNormalizer($option, $normalizer);
        }
    }

    /**
     * @param string $locale
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function setLocale($locale)
    {
        \Locale::setDefault($locale);
        $this->translator->setLocale($locale);
    }

    /**
     * @param string $archivePath
     *
     * @throws \Twig\Error\Error
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     *
     * @return int le nombre de destinataires, comme Swiftmailer : 1 ; un échec lève une exception
     */
    private function sendArchive($archivePath, Utilisateur $user, array $chroniques)
    {
        $message = (new Email())
            ->subject(
                $this->translator->trans(
                    'export.exportMail.title(%chronique%)',
                    ['%count%' => count($chroniques), '%chronique%' => implode(', ', $chroniques)]
                )
            )
            ->from('bdoh@inrae.fr')
            ->to(new Address($user->getEmail(), (string) $user))
            ->attachFromPath($archivePath, 'export.zip', 'application/zip')
            ->text(
                $this->templating->render(
                    '@IrsteaBdohConsult/Mail/export.txt.twig',
                    ['user' => (string) $user]
                )
            );

        $this->mailer->send($message);

        return 1;
    }
}
