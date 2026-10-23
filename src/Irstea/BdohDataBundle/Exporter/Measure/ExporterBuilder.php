<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure;

use Irstea\BdohDataBundle\Entity\JeuQualite;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Exporter\Measure\Extractor\Factory as ExtractorFactory;
use Irstea\BdohDataBundle\Exporter\Measure\Formatter\Factory as FormatterFactory;
use Irstea\BdohDataBundle\Exporter\Measure\Reporter\LoggerDecorator;
use Irstea\BdohDataBundle\Exporter\Measure\Reporter\Twig;
use Irstea\BdohDataBundle\Util\JeuToJeu;
use Irstea\BdohLoggerBundle\Logger\BdohLogger;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Class ExporterBuilder.
 */
class ExporterBuilder
{
    /**
     * @var FormatterFactory
     */
    public $formatterFactory;

    /**
     * @var ExtractorFactory
     */
    public $extractorFactory;

    /**
     * @var Environment
     */
    public $twigEngine;

    /**
     * @var BdohLogger
     */
    public $bdohLogger;

    /**
     * @var ManagerRegistry
     */
    public $doctrine;

    /**
     * @var string
     */
    public $documentBasePath;

    /**
     * @var string
     */
    public $formatDocPath;

    /**
     * @var string
     */
    public $defaultTosPath;

    public function __construct(
        FormatterFactory $formatterFactory,
        ExtractorFactory $extractorFactory,
        Environment $twigEngine,
        BdohLogger $bdohLogger,
        ManagerRegistry $doctrine,
        #[Autowire('%irstea_bdoh_data.measure_export.document_base_path%')] string $documentBasePath,
        #[Autowire('%irstea_bdoh_data.measure_export.formats_export_file%')] string $formatDocPath,
        #[Autowire('%irstea_bdoh_data.measure_export.default_termofuses_file%')] string $defaultTosPath
    ) {
        $this->formatterFactory = $formatterFactory;
        $this->extractorFactory = $extractorFactory;
        $this->twigEngine = $twigEngine;
        $this->bdohLogger = $bdohLogger;
        $this->doctrine = $doctrine;
        $this->documentBasePath = $documentBasePath;
        $this->formatDocPath = $formatDocPath;
        $this->defaultTosPath = $defaultTosPath;
    }

    /**
     * @param array $parameters
     *
     * @return ExporterInterface
     */
    public function getExporter(array $parameters, ArchiverInterface $archiver)
    {
        $formatter = $this->createFormatter($parameters['format'], $parameters['observatoire']);
        $extractor = $this->createExtractor($parameters['extractor'], $formatter, $parameters);
        $reporter = $this->createReporter($parameters);

        return $this->createExporter($extractor, $reporter, $archiver);
    }

    /**
     * @param string       $format
     * @param Observatoire $observatoire
     *
     * @return FormatterInterface
     */
    private function createFormatter($format, Observatoire $observatoire)
    {
        $formatter = $this->formatterFactory->create($format);
        $formatter->setJeuToJeu($this->createJeuToJeu($observatoire, $formatter));

        return $formatter;
    }

    /**
     * @param FormatterInterface $formatter
     *
     * @return JeuToJeu
     */
    private function createJeuToJeu(Observatoire $observatoire, FormatterInterface $formatter)
    {
        $manager = $this->doctrine->getManagerForClass(JeuQualite::class);

        $jeuObservatoire = $observatoire->getJeu()->getNom();
        $jeuFormatter = $formatter->resolveJeuQualite($jeuObservatoire);

        return new JeuToJeu($manager, $jeuObservatoire, $jeuFormatter);
    }

    /**
     * @param string             $type
     * @param FormatterInterface $formatter
     * @param array              $parameters
     *
     * @return ExtractorInterface
     */
    private function createExtractor($type, FormatterInterface $formatter, array $parameters)
    {
        return $this->extractorFactory->create($type, $formatter, $parameters);
    }

    /**
     * @param array $parameters
     *
     * @return ReporterInterface
     */
    private function createReporter(array $parameters)
    {
        $reporter = new Twig($this->twigEngine);
        if (isset($parameters['user'])) {
            $reporter = new LoggerDecorator($this->bdohLogger, $reporter);
        }
        $reporter->addParameters($parameters);

        return $reporter;
    }

    /**
     * @param ExtractorInterface $extractor
     * @param ReporterInterface  $reporter
     * @param ArchiverInterface  $archiver
     *
     * @return ExporterInterface
     */
    private function createExporter(
        ExtractorInterface $extractor,
        ReporterInterface $reporter,
        ArchiverInterface $archiver
    ) {
        $exporter = new Exporter();

        return $exporter
            ->setExtractor($extractor)
            ->setReporter($reporter)
            ->setArchiver($archiver)
            ->setFormatDocPath($this->formatDocPath)
            ->setDocumentBasePath($this->documentBasePath)
            ->setDefaultTosPath($this->defaultTosPath);
    }
}
