<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Controller;

use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohAdminBundle\Exporter\Bareme\BaremeExporter;
use Irstea\BdohAdminBundle\Importer\Bareme\Manager;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohBundle\Util\HttpZipResponse;
use Irstea\BdohDataBundle\Entity\BaremeJeuBareme;
use Irstea\BdohDataBundle\Entity\ChroniqueCalculee;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\JeuBareme;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueCalculeeRepository;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueContinueRepository;
use Irstea\BdohDataBundle\Entity\Transformation;
use Irstea\BdohLoggerBundle\Logger\BdohLogger;
use Irstea\BdohAdminBundle\Manager\BaremeImportManager;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of TransformationController.
 */
#[IsGranted(new Expression("is_granted('ROLE_ADMIN') and (user.isAManagerOfCurrentObservatory() or user.isASiteManagerOfCurrentObservatory() or user.isAContributorOfCurrentObservatory())"))]
class TransformationController extends Controller
{
    public static function getSubscribedServices(): array
    {
        return parent::getSubscribedServices() + ['irstea_bdoh_admin.manager.bareme_import' => BaremeImportManager::class];
    }

    const HISTO_TRANSFO_HEADER = '## ';

    const HISTO_TRANSFO_SEP = ' || ';

    /**
     * @return BdohLogger
     */
    protected function getLogger()
    {
        return $this->container->get('irstea_bdoh_logger.logger');
    }

    /**
     * @var ImportErrors
     */
    protected $errors;

    /**
     * @var Manager
     */
    protected $baremeManager;

    /**
     * @var JobManagerInterface
     */
    private $jobManager;

    public function __construct(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function displayAction()
    {
        $stations = [];
        $chroniquesMeres = [];
        $chroniquesFilles = [];

        /** @var ChroniqueContinueRepository $repo */
        $repo = $this->getBdohRepo('ChroniqueContinue');

        /** @var ChroniqueContinue[] $all */
        $all = $repo->bigFindAll();

        foreach ($all as $chronique) {
            $station = $chronique->getStation();
            $stationId = $station->getId();

            if (!isset($stations[$stationId])) {
                $stations[$stationId] = $station;
            }

            $chroniquesMeres[$stationId][] = $chronique;
        }

        /** @var ChroniqueCalculeeRepository $repo */
        $repo = $this->getBdohRepo('ChroniqueCalculee');

        /** @var ChroniqueCalculee[] $all */
        $all = $repo->securedBigFindAll($this->getUser());

        foreach ($all as $chronique) {
            $station = $chronique->getStation();
            $stationId = $station->getId();

            if (!isset($stations[$stationId])) {
                $stations[$stationId] = $station;
            }

            $chroniquesFilles[$stationId][] = $chronique;
        }

        $baremes = $this->getBdohRepo('Bareme')->securedFindAll();
        $jeuQualite = $this->getBdohRepo('Bareme')->getCurrentObservatoire()->getJeu();

        return $this->render(
            '@IrsteaBdohAdmin/Transformation/main.html.twig',
            [
                'chroniquesFilles'   => $chroniquesFilles,
                'stations'           => $stations,
                'chroniques'         => $chroniquesMeres,
                'baremes'            => $baremes,
                'jeuQualite'         => $jeuQualite,
                'valueLimitPolicies' => JeuBareme::getValueLimitTransformationTypeList(),
                'hasChildren'        => true,
            ]
        );
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getChroniqueCalculeeAction(Request $request)
    {
        $chroniqueId = $request->get('id');

        if ($chroniqueId !== -1) {
            /** @var ChroniqueContinueRepository $chronRepo */
            $chronRepo = $this->getBdohRepo('ChroniqueCalculee');

            /** @var ChroniqueCalculee $chronique */
            $chronique = $chronRepo->find($chroniqueId);

            $children = $chronRepo->findAllChildren($chronique);
            // la liste s'affiche en HTML (popover) : les noms s'échappent
            $childrenList = join('<br>', array_map(fn ($child) => htmlspecialchars((string) $child), $children));
            $childrenCount = count($children);

            $maj = $chronique->getMiseAJour();
            $response = [
                'unite'         => $chronique->getUnite()->getLibelle(),
                'maj'           => ($maj) ? $maj->format($this->getTranslator()->trans('transformation.bareme.dateBareme'))
                    . ' ' . $this->getTranslator()->trans('UTC') : '',
                'coefficient'   => $chronique->getFacteurMultiplicatif(),
                'genealogie'    => $chronique->getGenealogie(),
                'genealogieEn'  => $chronique->getGenealogieEn(),
                'hasChildren'   => $chronique->hasChildren(),
                'childrenList'  => $childrenList,
                'childrenCount' => $childrenCount,
            ];

            foreach (['Principale', 'Secondaire'] as $block) {
                $parent = ($block === 'Principale') ? $chronique->getPremiereEntree() : $chronique->getSecondeEntree();
                /* @var $parent Transformation */
                if ($parent) {
                    $response['chroniqueMere' . $block] = $parent->getEntree()->getId();
                    $response['delaiPropagation' . $block] = $parent->getJeuBaremeActuel()->getDelaiPropagation();
                    $response['allowValueLimits' . $block] = $parent->getEntree()->getAllowValueLimits();
                    $response['valueLimitPolicy' . $block] = $parent->getJeuBaremeActuel()->getValueLimitTransformationType();
                    $response['valueLimitPlaceholder' . $block] = $parent->getJeuBaremeActuel()->getValueLimitPlaceholder();
                    $baremeData = [];
                    foreach ($parent->getJeuBaremeActuel()->getBaremeJeuBaremes() as $bjb) {
                        $bareme = $bjb->getBareme();
                        $dateEnd = $bjb->getFinValidite();
                        $baremeData[] = [
                            'id'    => $bareme->getId(),
                            'begin' => $bjb->getDebutValidite()->format('Y-m-d H:i:s'),
                            'end'   => ($dateEnd) ? $dateEnd->format('Y-m-d H:i:s') : null,
                        ];
                    }
                    $response['baremes' . $block] = $baremeData;
                }

                if ($block === 'Principale') {
                    $response['isFirstEdition'] = (!$parent);
                }
            }

            return new JsonResponse($response);
        }
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function saveTransformationAction(Request $request)
    {
        if (!$this->isXmlHttpRequest()) {
            $this->redirectTransformation();
        }

        $idChroniqueCalculee = $request->get('chroniqueCalculee');
        $idChroniqueMerePrincipale = $request->get('selectChroniqueMerePrincipale');
        $idChroniqueMereSecondaire = $request->get('selectChroniqueMereSecondaire');
        $coefficient = $request->get('coefficient');
        $genealogie = $request->get('genealogie');
        $genealogieEn = $request->get('genealogieEn');
        $propagate = $request->get('propagate', false) === 'propagate';
        $dataJeuBaremePrincipale = $request->get('jeuBaremePrincipale');
        $dataJeuBaremeSecondaire = $request->get('jeuBaremeSecondaire');
        $delaiPrincipale = (float) $request->get('delaiPropagationPrincipale');
        $delaiSecondaire = (float) $request->get('delaiPropagationSecondaire');
        $limitPolicyPrincipale = $request->get('valueLimitsPrincipale');
        $limitPolicySecondaire = $request->get('valueLimitsSecondaire');
        $limitPlaceholderPrincipale = $request->get('limitPlaceholderPrincipale');
        $limitPlaceholderSecondaire = $request->get('limitPlaceholderSecondaire');

        $hasChroniqueMereSecondaire = ($idChroniqueMereSecondaire && is_numeric($idChroniqueMereSecondaire) &&
            ($idChroniqueMereSecondaire = ((int) $idChroniqueMereSecondaire)) >= 0);

        if (!$hasChroniqueMereSecondaire) {
            $delaiPrincipale = $delaiSecondaire = null;
        }

        $chroniqueCalculee = (is_numeric($idChroniqueCalculee)) ?
            $this->getBdohRepo('ChroniqueCalculee')->findOneById($idChroniqueCalculee) : null;

        $chroniqueMerePrincipale = (is_numeric($idChroniqueMerePrincipale)) ?
            $this->getBdohRepo('ChroniqueContinue')->findOneById($idChroniqueMerePrincipale) : null;

        $chroniqueMereSecondaire = ($hasChroniqueMereSecondaire && is_numeric($idChroniqueMereSecondaire)) ?
            $this->getBdohRepo('ChroniqueContinue')->findOneById($idChroniqueMereSecondaire) : null;

        $uniteChroniqueFille = (!$hasChroniqueMereSecondaire && $chroniqueCalculee) ? $chroniqueCalculee->getUnite() : null;

        $errors = [
            'general'    => [],
            'principale' => [],
            'secondaire' => [],
        ];

        // Bad coefficient?
        if ($coefficient && !is_numeric($coefficient)) {
            $errors['general'][] = $this->translateTransfoErrors('coefficientNonNumerique', ['%coeff%' => $coefficient]);
        }

        // No child chronicle?
        if (!$chroniqueCalculee) {
            $errors['general'][] = $this->translateTransfoErrors('chroniqueFilleIntrouvable');
        }

        // First parent chronicle not found?
        if (!$chroniqueMerePrincipale) {
            $errors['principale'][] = $this->translateTransfoErrors('chroniqueMereIntrouvable');
        }

        // No grading scale for the first parent chronicle?
        if (!$dataJeuBaremePrincipale) {
            $errors['principale'][] = $this->translateTransfoErrors('aucunBareme');
        }

        // Check grading scales for the first parent chronicle
        $dataAndErrorsPrincipale = $this->extractJeuBareme(
            $dataJeuBaremePrincipale,
            ($chroniqueMerePrincipale) ? $chroniqueMerePrincipale->getUnite() : null,
            !$hasChroniqueMereSecondaire ? $uniteChroniqueFille : null
        );
        $errors['principale'] = array_merge($errors['principale'], $dataAndErrorsPrincipale['errors']);

        // If there is a second parent chronicle...
        if ($hasChroniqueMereSecondaire) {
            // Second parent chronicle not found?
            if (!$chroniqueMereSecondaire) {
                $errors['secondaire'][] = $this->translateTransfoErrors('chroniqueMereIntrouvable');
            } else {
                // No grading scale for the second parent chronicle?
                if (!$dataJeuBaremeSecondaire) {
                    $dataAndErrorsSecondaire = null;
                    $errors['secondaire'][] = $this->translateTransfoErrors('aucunBareme');
                } else {
                    // Check grading scales for the second parent chronicle
                    $dataAndErrorsSecondaire = $this->extractJeuBareme(
                        $dataJeuBaremeSecondaire,
                        ($chroniqueMereSecondaire) ? $chroniqueMereSecondaire->getUnite() : null
                    );
                    $errors['secondaire'] = array_merge($errors['secondaire'], $dataAndErrorsSecondaire['errors']);
                }
            }

            // For each of the first and second parent chronicles, all grading scales must hold the same output unit
            foreach ([0, 1] as $i) {
                $errorCase = ($i === 0) ? 'principale' : 'secondaire';
                $outputUnits = $this->extractBaremeOutputUnits(($i === 0) ? $dataJeuBaremePrincipale : $dataJeuBaremeSecondaire);
                if (($nUnits = count($outputUnits)) > 1) {
                    $unitsOk = true;
                    $firstUnitId = $outputUnits[0]->getId();
                    for ($j = 1; $j < $nUnits && $unitsOk; ++$j) {
                        $unitsOk = ($outputUnits[$j]->getId() === $firstUnitId);
                    }

                    if (!$unitsOk) {
                        //$errors[$errorCase][] = $this->translateTransfoErrors("unitesSortieBaremesDifferentes");
                        $outputUnitLabels = [];
                        foreach ($outputUnits as $ou) {
                            $outputUnitLabels[] = $ou->getLibelle();
                        }
                        $errors[$errorCase][] = $this->translateTransfoErrors('unitesSortieBaremesDifferentes') .
                            $this->renderView(
                                '@IrsteaBdohAdmin/Transformation/bareme-output-unit-list.html.twig',
                                ['labels' => $outputUnitLabels]
                            );
                    }
                }

                $delai = ($i === 0) ? $delaiPrincipale : $delaiSecondaire;
                if (!\is_numeric($delai)) {
                    $errors[$errorCase][] = $this->translateTransfoErrors('delaiNonNumerique', ['%delai%' => $delai]);
                }

                $limitPlaceholder = ($i === 0) ? $limitPlaceholderPrincipale : $limitPlaceholderSecondaire;
                if ($limitPlaceholder !== null && $limitPlaceholder !== '' && !\is_numeric($limitPlaceholder)) {
                    $errors[$errorCase][] =
                        $this->translateTransfoErrors('limitPlaceholderNonNumerique', ['%placeholder%' => $limitPlaceholder]);
                }
            }
        } else {
            $dataAndErrorsSecondaire = null;
        }

        $numErrors = 0;
        foreach ($errors as $categoryErrors) {
            $numErrors += count($categoryErrors);
        }
        if ($numErrors > 0) {
            return new JsonResponse(
                [
                    'errors' => $this->renderView(
                        '@IrsteaBdohAdmin/Transformation/errors-transformation.html.twig',
                        ['errors' => $errors]
                    ),
                ]
            );
        }
        // Input data are valid: persist them

        $chroniqueCalculee->setFacteurMultiplicatif($coefficient);
        $chroniqueCalculee->setGenealogie($genealogie);
        $chroniqueCalculee->setGenealogieEn($genealogieEn);

        // Backup the 1st Transformation's "JeuBareme" for comparison with the one that will be recorded
        $this->setChronicleTransformation(
            $chroniqueCalculee,
            $chroniqueMerePrincipale,
            $dataAndErrorsPrincipale['data'],
            $delaiPrincipale,
            $limitPolicyPrincipale,
            $limitPlaceholderPrincipale
        );
        if ($chroniqueMereSecondaire) {
            $this->setChronicleTransformation(
                $chroniqueCalculee,
                $chroniqueMereSecondaire,
                $dataAndErrorsSecondaire['data'],
                $delaiSecondaire,
                $limitPolicySecondaire,
                $limitPlaceholderSecondaire,
                false
            );
        } else {
            $chroniqueCalculee->setSecondeEntree(null);
        }

        $this->getEm()->persist($chroniqueCalculee);
        $this->getEm()->flush();

        // Call action to computed chronicle update
        $job = $this->jobManager->enqueueChronicleComputation($chroniqueCalculee, $this->getUser(), $propagate);

        return new JsonResponse(
            [
                'submitted' => [
                    'chroniqueName' => $chroniqueCalculee->__toString(),
                    'chroniqueCode' => $chroniqueCalculee->getCode(),
                    'stationCode'   => $chroniqueCalculee->getStation()->getCode(),
                    'jobId'         => $job->getId(),
                    'jobPage'       => $this->generateUrl('bdoh_job_list'),
                ],
            ]
        );
    }

    /**
     * @param $dataJeuBareme
     * @param $inputUnit
     * @param null $outputUnit
     *
     * @return array
     */
    private function extractJeuBareme($dataJeuBareme, $inputUnit, $outputUnit = null)
    {
        $dataAndErrors = [
            'data'   => [],
            'errors' => [],
        ];

        $baremeRepo = $this->getBdohRepo('Bareme');

        $dataLines = explode('|', $dataJeuBareme);
        $numDataLines = count($dataLines);
        $i = 1;
        $dateEndPrevious = null;
        foreach ($dataLines as $line) {
            $lineElts = explode('_', $line);

            $bareme = $baremeRepo->findOneById($lineElts[0]);
            if (!$bareme) {
                $dataAndErrors['errors'][] = $this->translateTransfoErrors('baremeIntrouvable', ['%numLigne%' => $i]);
            } else {
                if (($unit = $bareme->getUniteEntree()) && $unit != $inputUnit) {
                    $dataAndErrors['errors'][] = $this->translateTransfoErrors(
                        'uniteEntreeBaremeIncorrecte',
                        [
                            '%numLigne%'           => $i,
                            '%uniteEntreeBareme%'  => $unit->getLibelle(),
                            '%uniteChroniqueMere%' => $inputUnit->getLibelle(),
                        ]
                    );
                }
                if ($outputUnit && ($unit = $bareme->getUniteSortie()) && $unit != $outputUnit) {
                    $dataAndErrors['errors'][] = $this->translateTransfoErrors(
                        'uniteSortieBaremeIncorrecte',
                        [
                            '%numLigne%'            => $i,
                            '%uniteSortieBareme%'   => $unit->getLibelle(),
                            '%uniteChroniqueFille%' => $outputUnit,
                        ]
                    );
                }
            }

            $UTC = new \DateTimeZone('UTC');

            if (!empty($lineElts[1])) {
                $dateBegin = new \DateTime($lineElts[1], $UTC);
            } else {
                $dataAndErrors['errors'][] = $this->translateTransfoErrors('dateDebutIndefinie', ['%numLigne%' => $i]);
                $dateBegin = null;
            }

            if (!empty($lineElts[2])) {
                $dateEnd = new \DateTime($lineElts[2], $UTC);
            } else {
                $dateEnd = null;
                if ($i < $numDataLines) {
                    $dataAndErrors['errors'][] = $this->translateTransfoErrors('dateFinIndefinie', ['%numLigne%' => $i]);
                }
            }

            if ($dateBegin && $dateEnd && $dateEnd <= $dateBegin) {
                $dataAndErrors['errors'][] = $this->translateTransfoErrors(
                    'finAvantDebut',
                    [
                        '%numLigne%'  => $i,
                        '%dateDebut%' => $dateBegin->format($this->getTranslator()->trans('transformation.bareme.dateBareme')),
                        '%dateFin%'   => $dateEnd->format($this->getTranslator()->trans('transformation.bareme.dateBareme')),
                    ]
                );
            }

            if ($dateBegin && $dateEndPrevious && $dateEndPrevious != $dateBegin) {
                $dataAndErrors['errors'][] = $this->translateTransfoErrors(
                    'conflitDebutFinPrecedent',
                    [
                        '%numLigne%'  => $i,
                        '%dateDebut%' => $dateBegin->format($this->getTranslator()->trans('transformation.bareme.dateBareme')),
                        '%dateFin%'   => $dateEndPrevious->format($this->getTranslator()->trans('transformation.bareme.dateBareme')),
                    ]
                );
            }

            $dataAndErrors['data'][] = [
                'bareme' => $bareme,
                'begin'  => $dateBegin,
                'end'    => $dateEnd,
            ];

            $dateEndPrevious = $dateEnd;
            ++$i;
        }

        return $dataAndErrors;
    }

    /**
     * @param $dataJeuBareme
     *
     * @return array
     */
    private function extractBaremeOutputUnits($dataJeuBareme)
    {
        $outputUnits = [];
        $dataLines = explode('|', $dataJeuBareme);
        $baremeRepo = $this->getBdohRepo('Bareme');
        foreach ($dataLines as $line) {
            $lineElts = explode('_', $line);
            $bareme = $baremeRepo->findOneById($lineElts[0]);
            if ($bareme && ($unit = $bareme->getUniteSortie())) {
                $outputUnits[] = $unit;
            }
        }

        return $outputUnits;
    }

    /**
     * @param $key
     * @param array $parms
     *
     * @return string
     */
    private function translateTransfoErrors($key, $parms = [])
    {
        return $this->getTranslator()->trans('transformation.erreur.' . $key, $parms);
    }

    /**
     * @param $chroniqueFille
     * @param $chroniqueMere
     * @param $baremeData
     * @param $delaiPropagation
     * @param $limitPolicy
     * @param $limitPlaceholder
     * @param bool $isFirstParent
     */
    private function setChronicleTransformation(
        $chroniqueFille,
        $chroniqueMere,
        $baremeData,
        $delaiPropagation,
        $limitPolicy,
        $limitPlaceholder,
        $isFirstParent = true
    ) {
        $jeuBareme = new JeuBareme();
        $jeuBareme->setDelaiPropagation($delaiPropagation);
        foreach ($baremeData as $dataOneBareme) {
            $baremeJeuBareme = new BaremeJeuBareme();
            $baremeJeuBareme->setJeuBareme($jeuBareme);
            $baremeJeuBareme->setBareme($dataOneBareme['bareme']);
            $baremeJeuBareme->setDebutValidite($dataOneBareme['begin']);
            $baremeJeuBareme->setFinValidite($dataOneBareme['end']);
            $jeuBareme->addBaremeJeuBareme($baremeJeuBareme);
        }

        if ($isFirstParent) {
            $transformation = $chroniqueFille->getPremiereEntree();
            if (!$transformation) {
                $transformation = new Transformation();
                $chroniqueFille->setPremiereEntree($transformation);
            }
        } else {
            $transformation = $chroniqueFille->getSecondeEntree();
            if (!$transformation) {
                $transformation = new Transformation();
                $chroniqueFille->setSecondeEntree($transformation);
            }
        }

        $transformation->setEntree($chroniqueMere);

        if (!$chroniqueMere->getAllowValueLimits()) {
            $limitPolicy = null;
            $limitPlaceholder = null;
        } elseif ($limitPolicy !== JeuBareme::LQ_LD_PLACEHOLDER) {
            $limitPlaceholder = null;
        }
        $jeuBareme->setValueLimitTransformationType($limitPolicy);
        $jeuBareme->setValueLimitPlaceholder($limitPlaceholder !== null ? (float) $limitPlaceholder : null);

        if (!$jeuBareme->equals($transformation->getJeuBaremeActuel())) {
            $jeuBareme->setDateCreation(new \DateTime('now', new \DateTimeZone('UTC')));
            $transformation->setJeuBaremeActuel($jeuBareme);
            $transformation->addJeuBaremesHistorique($jeuBareme);
            $jeuBareme->setTransformation($transformation);
        }
    }

    /**
     * Redirects to step 1 page.
     */
    protected function redirectTransformation()
    {
        return $this->redirect($this->generateUrl('bdoh_admin_transformation'));
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function importBaremeStep2Action()
    {
        if (!$this->isXmlHttpRequest()) {
            return $this->redirectTransformation();
        }

        $request = $this->getRequest();
        $manager = $this->getManager();
        $file = $request->files->get('file');
        $importerClass = $manager->importers->get('bareme');
        $errors = $this->errors = new ImportErrors();
        $numDataLines = 0;

        /*
         * Checks request parameters of step 1 : file.
         */
        if (null === $file) {
            $errors->noFile();  // No file
        } else {
            $mimeType = $file->getMimeType();
            if (!$manager->isValidMimeType($mimeType)) {
                $errors->badMimeType($mimeType);
            }
        }

        if ($errors->isClean()) {
            // Moves uploaded file in import directory
            $file = $file->move($manager->getDir());
            $filePath = $file->getPathname();
        }

        if ($errors->isClean()) {
            $importer = $this->baremeManager = new $importerClass(
                $this->getEm(),
                $this->getTranslator(),
                $filePath,
                $errors
            );
            $importer->loadHeader();
        }

        if ($errors->isClean()) {
            $numDataLines = $importer->read();
        }

        if ($errors->isClean()) {
            $data = [
                'action' => 'step3',
                'view'   => $this->renderView(
                    '@IrsteaBdohAdmin/Transformation/bareme-modal-step2.html.twig',
                    ['nbLines' => $numDataLines]
                ),
            ];
        }
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
            // Sans fichier, ou d'un type refusé, il n'y a pas d'importeur : l'ancien s'arrêtait là
            // sur une erreur 500 (dev/differences.md).
            if (isset($importer)) {
                $importer->clean();
            }
        }

        // Patch: remove UTF-8 invalid characters in the view returned in order to avoid JsonResponse to throw an error
        if ($data['view']) {
            ini_set('mbstring.substitute_character', 'U+003F');
            $data['view'] = mb_convert_encoding($data['view'], 'UTF-8', 'UTF-8');
        }

        $this->storeImporter();

        return $this->renderJson($data);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function importBaremeStep3Action()
    {
        if (!$this->isXmlHttpRequest()) {
            return $this->redirectTransformation();
        }

        list($importer, $errors) = $this->retrieveImporter();
        $summaryData = $importer->loadData();
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
            $importer->clean();
        } else {
            $this->getLogger()->createHistoriqueBaremesImport(
                $this->getUser(),
                new \DateTime('now', new \DateTimeZone('UTC')),
                $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent(),
                $summaryData['nom'],
                $summaryData['uniteEntree'],
                $summaryData['uniteSortie'],
                $summaryData['xMin'],
                $summaryData['xMax'],
                $summaryData['dateCreation'],
                $summaryData['nValues']
            );

            if ($summaryData['dateCreation'] instanceof \DateTime) {
                $summaryData['dateCreation'] = $summaryData['dateCreation']->format($this->getTranslator()->trans('transformation.bareme.dateBareme'));
            }
            $data = [
                'action'        => 'step4',
                'view'          => $this->renderView(
                    '@IrsteaBdohAdmin/Transformation/bareme-modal-step3.html.twig',
                    [
                        'summary' => $summaryData,
                    ]
                ),
                'newBaremeData' => $summaryData,
            ];
        }

        // Patch: remove UTF-8 invalid characters in the view returned in order to avoid JsonResponse to throw an error
        if ($data['view']) {
            ini_set('mbstring.substitute_character', 'U+003F');
            $data['view'] = mb_convert_encoding($data['view'], 'UTF-8', 'UTF-8');
        }

        return $this->renderJson($data);
    }

    /**
     * Stops the scale import process.
     */
    public function importBaremeStopAction()
    {
        if (!$this->isXmlHttpRequest()) {
            return $this->redirectTransformation();
        }

        list($importer) = $this->retrieveImporter();
        $importer->clean();

        return $this->renderJson('');
    }

    /**
     * Prepares the response data in case of errors.
     *
     * @return array
     */
    protected function getErrorsResponse()
    {
        /*if($this->controlePointManager){
            $this->controlePointManager->clean();
        }*/

        $view = $this->renderView(
            '@IrsteaBdohAdmin/Transformation/errors.html.twig',
            [
                'errors'     => $this->errors->translations($this->getTranslator()),
                'jeuQualite' => $this->getBdohRepo('Bareme')->getCurrentObservatoire()->getJeu()->__toString(),
            ]
        );

        // Sends the error info
        return [
            'action' => 'errors',
            'view'   => $view,
        ];
    }

    /**
     * @return object
     */
    protected function getManager()
    {
        return $this->container->get('irstea_bdoh_admin.manager.bareme_import');
    }

    protected function storeImporter()
    {
        $this->getRequest()->getSession()->set('bareme.importer', $this->baremeManager);
    }

    /**
     * @return array
     */
    protected function retrieveImporter()
    {
        $this->baremeManager = $this->getSession()->get('bareme.importer');
        $this->errors = $this->baremeManager->getErrors();
        $this->baremeManager->wakeup($this->getEm(), $this->getTranslator());

        return [$this->baremeManager, $this->errors];
    }

    /**
     * @return HttpZipResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function exportJeuBaremeAction()
    {
        $childChronicleName = $this->getRequest()->query->get('child-chronicle-name');
        $parentChronicleId = $this->getRequest()->query->get('parent-chronicle-id');

        $jeuBaremeId = $this->getRequest()->query->get('jeu-bareme-id');
        if (!($jeuBareme = $this->getBdohRepo('JeuBareme')->findOneById($jeuBaremeId))) {
            $this->getSession()->getFlashBag()->add('error', $this->getTranslator()->trans('transformation.erreur.jeuBaremeIntrouvable'));

            return $this->redirectToReferer();
        }

        $baremesData = [];
        foreach ($jeuBareme->getBaremeJeuBaremes() as $bjb) {
            $baremesData[] = [$bjb->getBareme()->getId(), $parentChronicleId, $bjb->getDebutValidite(), $bjb->getFinValidite()];
        }

        return $this->exportBaremes($childChronicleName, $baremesData);
    }

    /**
     * @param string $childChronicleName
     * @param array  $baremesData
     *
     * @return mixed
     */
    private function exportBaremes($childChronicleName, $baremesData)
    {
        $exporter = new BaremeExporter($this->getEm(), $this->getTranslator(), $childChronicleName);
        foreach ($baremesData as $baremeLine) {
            $exporter->run($baremeLine);

            $info = $exporter->getInfoSelection();
            $this->getLogger()->createHistoriqueBaremesExport(
                $this->getUser(),
                new \DateTime('now', new \DateTimeZone('UTC')),
                $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent(),
                $info['name'],
                $info['inputUnit'],
                $info['outputUnit'],
                $info['min'],
                $info['max'],
                $info['dateCreation'],
                $info['number']
            );
        }

        $filesToZip = $exporter->getBaremeFiles();
        $filesToZip[] = $exporter->getReportPath();

        // zip archive + main folder name : 'Observatoire' name + datetime
        $zipName = $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent()->getSlug()
            . '_' . date('Y-m-d_H-i-s');

        try {
            return new HttpZipResponse(sys_get_temp_dir(), $zipName, $filesToZip);
        } catch (\Exception $e) {
            $this->getSession()->getFlashBag()->add('error', $this->getTranslator()->trans('Error.zip.cantCreate'));

            return $this->redirectToReferer();
        }
    }

    /**
     * @return HttpZipResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function exportBaremeAction()
    {
        $childChronicleName = $this->getRequest()->request->get('child-chronicle-name');
        $exportData = explode(
            '|',
            $this->getRequest()->request->get('baremes-export-info')
        );

        $baremesData = [];
        foreach ($exportData as $exportLine) {
            $baremesData[] = explode('_', $exportLine);
        }

        return $this->exportBaremes($childChronicleName, $baremesData);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function getBaremesForExportAction()
    {
        if (!$this->isXmlHttpRequest()) {
            return $this->redirectTransformation();
        }

        $baremes = $this->getBdohRepo('Bareme')->securedFindAll(false);

        return $this->render('@IrsteaBdohAdmin/Transformation/bareme-export-table.html.twig', ['baremes' => $baremes]);
    }
}
