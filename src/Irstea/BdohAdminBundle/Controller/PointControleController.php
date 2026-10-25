<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Controller;

use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\DataTransformer\ToUtcDiffTransformer;
use Irstea\BdohBundle\Util\HttpFileResponse;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohAdminBundle\Manager\ControleImportManager;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Description of PointControleController.
 */
#[IsGranted(new Expression("is_granted('ROLE_ADMIN') and (user.isAManagerOfCurrentObservatory() or user.isASiteManagerOfCurrentObservatory() or user.isAContributorOfCurrentObservatory())"))]
class PointControleController extends Controller
{
    public static function getSubscribedServices(): array
    {
        return parent::getSubscribedServices() + ['irstea_bdoh_admin.manager.controle_import' => ControleImportManager::class];
    }

    /**
     * @var Irstea\BdohAdminBundle\Importer\Controle\Manager
     */
    protected $controlePointManager;

    /**
     * @var Irstea\BdohAdminBundle\Errors\ImportErrors
     */
    protected $errors;

    /**
     * From session, retrieves the importer and its errors manager.
     */
    protected function retrieveImporter()
    {
        $this->controlePointManager = $this->getSession()->get('controle.importer');
        $this->errors = $this->controlePointManager->getErrors();
        $this->controlePointManager->wakeup($this->getEm(), $this->getTranslator());

        return [$this->controlePointManager, $this->errors];
    }

    /**
     * Stores the importer in session.
     */
    protected function storeImporter()
    {
        $this->getRequest()->getSession()->set('controle.importer', $this->controlePointManager);
    }

    /**
     * Shortcut to return the 'irstea_bdoh_admin.manager.controle_import' service.
     * !!! : different from controlePointManager.
     */
    protected function getManager()
    {
        return $this->container->get('irstea_bdoh_admin.manager.controle_import');
    }

    /**
     * Shortcut to return the Logger service.
     */
    protected function getLogger()
    {
        return $this->container->get('irstea_bdoh_logger.logger');
    }

    /**
     * Redirects to step 1 page.
     */
    protected function redirectStep1()
    {
        return $this->redirect($this->generateUrl('bdoh_admin_controle_import'));
    }

    /**
     * Prepares the response data in case of errors.
     *
     * @return array
     */
    protected function getErrorsResponse()
    {
        if ($this->controlePointManager) {
            $this->controlePointManager->clean();
        }

        $view = $this->renderView(
            '@IrsteaBdohAdmin/ControleManagement/errors.html.twig',
            [
                'errors'     => $this->errors->translations($this->getTranslator()),
                'idImporter' => 'controle',
            ]
        );

        // Sends the error info
        return [
            'action'     => 'errors',
            'idImporter' => 'controle',
            'view'       => $view,
        ];
    }

    /**
     * Stops the measures import process and redirects to step 1 page.
     */
    public function stopAction()
    {
        list($importer) = $this->retrieveImporter();
        $importer->clean();
        $this->getSession()->getFlashBag()->add('error', $this->get('translator')->trans('controle.import.info.wasCanceled'));

        return $this->redirectStep1();
    }

    /**
     * Displays step 1 : selection of file and file format.
     */
    public function step1Action()
    {
        $user = $this->getUser();

        $chroniques = $this->getBdohRepo('ChroniqueContinue')->securedFindAllHavingControlePoints($user);

        return $this->render(
            '@IrsteaBdohAdmin/ControleManagement/step1.html.twig',
            ['chroniques' => $chroniques]
        );
    }

    public function step2Action()
    {
        if (!$this->isXmlHttpRequest()) {
            return $this->redirectStep1();
        }

        $request = $this->getRequest();
        $manager = $this->getManager();
        $file = $request->files->get('file');
        $importerClass = $manager->importers->get('controle');
        $errors = $this->errors = new ImportErrors();

        /*
         * Checks request parameters of step 1 : file.
         */
        if (null === $file) {
            $errors->noFile();  // No file
        } else {
            $mimeType = $file->getMimeType();

            // Bad mime type for file
            if (false === $manager->isValidMimeType($mimeType)) {
                $errors->badMimeType($mimeType);
            }
        }

        // No error for step 1 parameters ?
        if ($errors->isClean()) {
            // Moves uploaded file in import directory
            $file = $file->move($manager->getDir());
            $filePath = $file->getPathname();

            // If no error, creates the importer and loads the file header
            if ($errors->isClean()) {
                $importer = $this->controlePointManager = new $importerClass($this->getEm(), $this->getTranslator(), $filePath, $errors);
                $importer->loadHeader();
                // Some errors ? Display it and quit !
                if ($errors->isDirty()) {
                    $data = $this->getErrorsResponse();
                } else {
                    if (!$this->get('security.authorization_checker')->isGranted('UPLOAD_DATA', $importer->getStation())) {
                        $errors->noRightOnStation($importer->getStation());
                    }
                    // Some errors ? Display it and quit !
                    if ($errors->isDirty()) {
                        $data = $this->getErrorsResponse();
                    } else {
                        // Reads the controles in file
                        $importer->loadData();
                        // Some errors ? Display it and quit !
                        if ($errors->isDirty()) {
                            $data = $this->getErrorsResponse();
                        } else {
                            // Prepares (for Twig template) the data of each 'chronique' selected by user
                            $chroniqueData = $this->prepareChroniqueData($importer->getChronique());
                            list($nbNewPointControle, $nbPointControle) =
                                $this->getBdohRepo('PointControle')->importStats($importer->tmpSqlTable, $importer->getChronique()->getId());
                            $data = [
                                'action' => 'step3',
                                'view'   => $this->renderView(
                                    '@IrsteaBdohAdmin/ControleManagement/step2.html.twig',
                                    [
                                        'chroniqueData'      => $chroniqueData,
                                        'nbNewPointControle' => $nbNewPointControle,
                                        'nbPointControle'    => $nbPointControle,
                                        'station'            => $importer->getStation(),
                                        'timezone'           => $importer->getTimezone(),
                                        'chronique'          => $importer->getChronique(),
                                    ]
                                ),
                            ];
                        }
                    }
                }
            }
        }

        // Some errors ? Display it and quit !
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
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
     * => Retrieves the request parameter "importType" from step 3.
     * => Runs the effective import of measures, depending of it.
     * => Displays the step 3 : summary of import, or errors.
     */
    public function step3Action()
    {
        if (!$this->isXmlHttpRequest()) {
            return $this->redirectStep1();
        }

        $request = $this->getRequest();
        $importType = $request->request->get('importType');
        list($importer, $errors) = $this->retrieveImporter();

        // For each selected 'chronique', proceeds to the final import
        $stats = $importer->importData($importType);

        // Some errors ? Display it and quit !
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
        } else {
            foreach ($stats as $stat) {
                $this->getLogger()->createHistoriqueDonneesPointControle(
                    $this->container->get('security.token_storage')->getToken()->getUser(),
                    new \DateTime(),
                    $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent(),
                    $stat['entity'],
                    $stat['beginDate'],
                    $stat['endDate'],
                    $stat['insert'],
                    'historique.actions.PointControleImport',
                    $this->controlePointManager->getTimezone()
                );
            }
            $data = [
                'action' => 'step4',
                'view'   => $this->renderView(
                    '@IrsteaBdohAdmin/ControleManagement/step3.html.twig',
                    [
                        'stats' => $stats,
                    ]
                ),
            ];
        }

        // Patch: remove UTF-8 invalid characters in the view returned in order to avoid JsonResponse to throw an error
        if ($data['view']) {
            ini_set('mbstring.substitute_character', 'U+003F');
            $data['view'] = mb_convert_encoding($data['view'], 'UTF-8', 'UTF-8');
        }

        $this->getRequest()->getSession()->remove('controle.importer');

        return $this->renderJson($data);
    }

    /**
     * From a 'chronique' entity, returns an array with :
     *  => this entity ;
     *  => its first measure date ;
     *  => its last measure date ;
     *  => the importer first measure date ;
     *  => the importer last measure date ;
     *  => the category of dates overlap ;
     *  => the "too small" new measures if there are ;
     *  => the "too large" new measures if there are .
     *
     * @param Irstea\BdohDataBundle\Entity\Chronique|null $chronique
     * @param int
     *
     * @return array|null returns null if $chronique isn't a valid 'Chronique' object
     */
    protected function prepareChroniqueData($chronique)
    {
        // Dates of first and last existing controles
        $existingDates = $this->controlePointManager->getControleRepo()->getFirstLastDates($chronique);

        $existingFirstDate = $existingDates['first'];
        $existingLastDate = $existingDates['last'];

        // Dates of first and last controles to import
        $importFirstDate = $this->controlePointManager->firstDate;
        $importLastDate = $this->controlePointManager->lastDate;

        /*
         * Computes the category of dates overlap.
         */

        // There is no existing measure
        if ((null === $existingFirstDate) && (null === $existingLastDate)) {
            $overlap = 'new';
        } // New measures are completely BEFORE existing measures
        elseif ($importLastDate < $existingFirstDate) {
            $overlap = 'before';
        } // New measures are completely AFTER existing measures
        elseif ($importFirstDate > $existingLastDate) {
            $overlap = 'after';
        } // Bounds of new and old measures are exactly equal
        elseif (($importFirstDate === $existingFirstDate) && ($importLastDate === $existingLastDate)) {
            $overlap = 'boundsEqual';
        } // New measures are completely IN existing measures
        elseif (($importFirstDate >= $existingFirstDate) && ($importLastDate <= $existingLastDate)) {
            $overlap = 'newInOld';
        } // Existing measures are completely IN new measures
        elseif (($importFirstDate < $existingFirstDate) && ($importLastDate > $existingLastDate)) {
            $overlap = 'oldInNew';
        } // Only some first new measures are IN existing measures
        elseif (($importFirstDate >= $existingFirstDate) && ($importLastDate > $existingLastDate)) {
            $overlap = 'firstIn';
        } // Only some last new measures are IN existing measures
        elseif (($importFirstDate < $existingFirstDate) && ($importLastDate <= $existingLastDate)) {
            $overlap = 'lastIn';
        }

        return [
            'existingFirstDate' => $existingFirstDate,
            'existingLastDate'  => $existingLastDate,
            'importerFirstDate' => $importFirstDate,
            'importerLastDate'  => $importLastDate,
            'overlap'           => $overlap,
        ];
    }

    public function exportAction()
    {
        $request = $this->getRequest();
        $manager = $this->getManager();
        $chroniqueId = $request->request->get('chronique');
        $timezone = $request->request->get('timezone');
        $importerClass = $manager->importers->get('controle');
        $errors = $this->errors = new ImportErrors();

        /*
         * Checks request parameters of step 1 : chronique.
         */

        if (false === $timezone = ToUtcDiffTransformer::bdohTimezone('UTC' . $timezone)) {
            $this->getSession()->getFlashBag()->add('error', $this->get('translator')->trans('measure.export.error.badTimezone'));

            return $this->redirectToReferer();
        }

        if (null === $chroniqueId || $chroniqueId === '') {
            $this->getSession()->getFlashBag()->add('error', $this->get('translator')->trans('controle.export.error.noChronique'));

            return $this->redirectToReferer();
        }

        $exporter = $this->controlePointManager = new $importerClass(
            $this->getEm(),
            $this->getTranslator(),
            '',
            $errors,
            true,
            $timezone
        );

        try {
            $chronique = $this->getBdohRepo('Chronique')->findOneById($chroniqueId);

            if (!$this->get('security.authorization_checker')->isGranted('UPLOAD_DATA', $chronique)) {
                $this->getSession()->getFlashBag()->add('error', $this->get('translator')->trans('controle.export.error.noRight'));

                return $this->redirectToReferer();
            }

            $defaultQualityCodes = $this->container->getParameter('absent_quality_codes');
            $jeuName = Observatoire::getCurrent()->getJeu()->getNom();
            $defaultQualityCode = \array_key_exists($jeuName, $defaultQualityCodes) ? $defaultQualityCodes[$jeuName] : '';

            $exporter->run($chroniqueId, $defaultQualityCode);

            $existingDates = $exporter->getControleRepo()->getFirstLastDates($chronique);

            $existingFirstDate = $existingDates['first'];
            $existingLastDate = $existingDates['last'];
            $existingControlePointsNumber = $existingDates['number'];

            //log the export
            $this->getLogger()->createHistoriqueDonneesPointControle(
                $this->container->get('security.token_storage')->getToken()->getUser(),
                new \DateTime(),
                $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent(),
                $chronique,
                $existingFirstDate,
                $existingLastDate,
                $existingControlePointsNumber,
                'historique.actions.PointControleExport',
                $this->controlePointManager->getTimezone()
            );

            return new HttpFileResponse($exporter->getFilePath(), 'text/csv');
        } catch (\Exception $e) {
            $this->getSession()->getFlashBag()->add('error', $this->get('translator')->trans('controle.export.error.cantFinish'));

            return $this->redirectToReferer();
        }
    }
}
