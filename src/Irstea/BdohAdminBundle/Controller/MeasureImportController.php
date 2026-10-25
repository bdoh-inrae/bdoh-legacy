<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Controller;

use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohAdminBundle\Importer\Measure\ImporterInterface;
use Irstea\BdohAdminBundle\IrsteaBdohAdminBundle;
use Irstea\BdohAdminBundle\Manager\MeasureImportManager;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohDataBundle\Entity\Chronique;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class MeasureImportController.
 */
#[IsGranted(new Expression("is_granted('ROLE_ADMIN') and (user.isAManagerOfCurrentObservatory() or user.isASiteManagerOfCurrentObservatory() or user.isAContributorOfCurrentObservatory())"))]
class MeasureImportController extends Controller
{
    /**
     * @var ImporterInterface
     */
    protected $importer;

    /**
     * @var ImportErrors
     */
    protected $errors;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public static function getSubscribedServices(): array
    {
        return parent::getSubscribedServices() + ['irstea_bdoh_admin.manager.measure_import' => MeasureImportManager::class];
    }

    /**
     * From session, retrieves the importer and its errors manager.
     *
     * @return [Importer, ImporterErrors]
     */
    protected function retrieveImporter()
    {
        $this->importer = $this->getSession()->get('measure.importer');
        $this->errors = $this->importer->getErrors();
        $this->importer->wakeup($this->getEm(), $this->getTranslator());

        return [$this->importer, $this->errors];
    }

    /**
     * Stores the importer in session.
     */
    protected function storeImporter()
    {
        $this->getSession()->set('measure.importer', $this->importer);
    }

    /**
     * Shortcut to return the 'irstea_bdoh_admin.manager.measure_import' service.
     */
    protected function getManager()
    {
        return $this->container->get('irstea_bdoh_admin.manager.measure_import');
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
        return $this->redirect($this->generateUrl('bdoh_admin_measure_import'));
    }

    /**
     * Prepares the response data in case of errors.
     *
     * @return array
     */
    protected function getErrorsResponse()
    {
        if ($this->importer) {
            $this->importer->clean();
        }

        // Retrieves the identifier of current importer
        $importers = $this->getManager()->importers->all();

        $idImporter = null;
        foreach ($importers as $key => $class) {
            if (get_class($this->importer) === $class) {
                $idImporter = $key;
                break;
            }
        }

        // Sends the error info
        return [
            'action'     => 'errors',
            'idImporter' => $idImporter,
            'view'       => $this->renderView(
                '@IrsteaBdohAdmin/MeasureImport/errors.html.twig',
                [
                    'errors'     => $this->errors->translations($this->getTranslator()),
                    'idImporter' => $idImporter,
                ]
            ),
        ];
    }

    /**
     * Stops the measures import process and redirects to step 1 page.
     */
    public function stopAction()
    {
        list($importer) = $this->retrieveImporter();
        $importer->clean();
        $this->getSession()->getFlashBag()->add('error', $this->getTranslator()->trans('measure.import.info.wasCanceled'));

        return $this->redirectStep1();
    }

    /**
     * Displays step 1 : selection of file and file format.
     */
    public function step1Action()
    {
        return $this->render(
            '@IrsteaBdohAdmin/MeasureImport/step1.html.twig',
            [
                'fileFormats' => $this->getManager()->importers->keys(),
            ]
        );
    }

    /**
     * => Checks request parameters of step 1.
     * => Displays step 2 : selection/displaying of 'station', timezone and 'chroniques'.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function step2Action(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirectStep1();
        }

        $manager = $this->getManager();
        $file = $request->files->get('file');
        $format = $request->request->get('file-format');
        $importerClass = $manager->importers->get($format);
        $errors = $this->errors = new ImportErrors();

        $user = $this->getUser();

        /*
         * Checks request parameters of step 1 : file and format.
         */
        if (null === $importerClass) {
            // Bad format
            $errors->badFormat($format);
        } elseif (null === $file) {
            // No file
            $errors->noFile();
        } elseif (UPLOAD_ERR_OK !== $file->getError()) {
            // PHP upload error
            $errors->phpUploadError($file->getError());
        } else {
            $mimeType = $file->getMimeType();
            if (false === $manager->isValidMimeType($mimeType)) {
                // Bad mime type for file
                $errors->badMimeType($mimeType);
            }
        }

        // No error for step 1 parameters ?
        if ($errors->isClean()) {
            // Moves uploaded file in "measures import" directory
            $file = $file->move($manager->getDir());
            $filePath = $file->getPathname();

            // File is a zip archive ?
            if ($manager->isValidMimeType($mimeType, 'zip')) {
                // Extracts its first file
                $zipPath = $filePath;
                $filePath = IrsteaBdohAdminBundle::unzipFirst($zipPath, $manager->getDir());

                if (null === $filePath) {
                    $errors->badZip();
                } else {
                    // Checks mime type of this file (must be a text)
                    $file = new File($filePath, false);
                    $mimeType = $file->getMimeType();

                    if (false === $manager->isValidMimeType($mimeType, 'txt')) {
                        $errors->zipFirstBadMimeType($mimeType);
                    }
                }
                // Removes the zip archive
                unlink($zipPath);
            }

            // If no error, creates the importer and loads the file header
            if ($errors->isClean()) {
                $importer = $this->importer =
                    new $importerClass($this->getEm(), $this->getTranslator(), $filePath, $errors);
                $importer->loadHeader();

                $this->importer->format = $format;
            }
        }

        // Some errors ? Display it and quit !
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
        } // No station ? Forces the user to enter one, before further !
        elseif (null === $importer->getStation()) {
            $data = [
                'action' => 'step2.askStation',
                'view'   => $this->renderView(
                    '@IrsteaBdohAdmin/MeasureImport/step2-askStation.html.twig',
                    ['stations' => $this->getBdohRepo('Station')->securedFindAllForMesure($user)]
                ),
            ];
        } else {
            if (!$this->get('security.authorization_checker')->isGranted('UPLOAD_DATA', $importer->getStation())) {
                $errors->noRightOnStation($importer->getStation());
                $data = $this->getErrorsResponse();
            } else {
                $data = $this->step2Next();
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
     * => Retrieves the request parameter "station" from "step 2 - ask Station".
     * => Stores it in the importer.
     * => Checks the result.
     * => Displays the final step 2.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function step2NextAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirectStep1();
        }

        $stationCode = $request->request->get('station');
        list($importer, $errors) = $this->retrieveImporter();

        // Stores the request parameter "station" in the importer
        $importer->setStation($stationCode);

        // And checks the result
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
        } else {
            $data = $this->step2Next();
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
     * The main/final step 2.
     */
    protected function step2Next()
    {
        $importer = $this->importer;
        $errors = $this->errors;
        $timezone = $importer->getTimezone() ? 'UTC' . substr($importer->getTimezone(), 0, 3) : null;

        $user = $this->getUser();

        $data = [
            'action'      => 'step2',
            'view'        => $this->renderView(
                '@IrsteaBdohAdmin/MeasureImport/step2.html.twig',
                [
                    'station'          => $importer->getStation(),
                    'timezone'         => $timezone,
                    'chroniques'       => $importer->getChroniques(),
                    'chroniquesBoxTxt' => $this->getTranslator()->trans(
                        $importer->getChroniqueType() . 'Box(%station%)',
                        ['%station%' => $importer->getStation()]
                    ),
                ]
            ),
            'ignoredTimeSeries'  => $this->getTranslator()->trans('measure.import.ignoredTimeSeries'),
            'titleDelete'        => $this->getTranslator()->trans('measure.import.removeTimeSeries'),
            'titleRedo'          => $this->getTranslator()->trans('measure.import.restoreTimeSeries'),
            'ignoreColumn'       => $this->getTranslator()->trans('ignoreColumn'),
        ];

        // No 'chronique' read from file header ?
        if (false === $importer->hasChroniques()) {
            // Gets all 'chroniques' of right type, of the importer 'station'
            $tmpChron = $importer->getChroniqueRepo()->securedFindByStation($importer->getStation(), $user);

            // At least one 'chronique' available
            if ($tmpChron) {
                $chroniquesAvailable = [];
                foreach ($tmpChron as $chronique) {
                    if (!$chronique->isConvertie()) {
                        $chroniquesAvailable[$chronique->getCode()] = (string) $chronique;
                    }
                }
                $data['expectedChroniques'] = $importer->getExpectedChroniques();
                $data['chroniquesAvailable'] = $chroniquesAvailable;
            } // No 'chronique' available => error !
            else {
                $func = 'noChronique' . $importer->getChroniqueType() . 'Available';
                $errors->$func((string) $importer->getStation());
                $data = $this->getErrorsResponse();
            }
        }

        return $data;
    }

    /**
     * => Retrieves the request parameters "timezone" and "chroniques" from step 2.
     * => Stores them in the importer.
     * => Checks the result.
     * => Reads the measures in file.
     * => Displays the step 3 : validation of import.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function step3Action(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirectStep1();
        }

        $timezone = $request->request->get('timezone');
        $chroniques = $request->request->all('chroniques');

        list($importer, $errors) = $this->retrieveImporter();
        $alreadyDefined = $importer->hasChroniques();

        // Stores the request parameter "timezone" in the importer
        if ($timezone) {
            $importer->setTimezone('UTC' . $timezone);
        }

        // Stores each 'chronique' of the request parameter "chroniques", in the importer
        for ($i = 0; $i < $importer->getExpectedChroniques(); ++$i) {
            // 'Chronique' not found ? Don't import its new measures !
            if (false === isset($chroniques[$i])) {
                $importer->setChronique(null, $i);
            } // 'Chronique' already stored / defined ? No need to redo !
            elseif (false === $alreadyDefined) {
                $importer->setChronique($chroniques[$i], $i);
            }
        }

        // And checks the result
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
        } else {
            // Reads the measures in file
            $importer->loadData();

            // Some errors ? Display it and quit !
            if ($errors->isDirty()) {
                $data = $this->getErrorsResponse();
            } else {
                // Is the checkbox for children computation needed
                $hasChildrenGlobal = false;
                // How many children may be computed (for translation)
                $childrenCountGlobal = 0;
                // Prepares (for Twig template) the data of each 'chronique' selected by user
                $chroniques = [];
                foreach ($importer->getChroniques() as $chronique) {
                    if ($chronique) {
                        $chroniqueData = $this->prepareChroniqueData($chronique);
                        $hasChildrenGlobal = $hasChildrenGlobal || $chroniqueData['hasChildren'];
                        $childrenCountGlobal += $chroniqueData['childrenCount'];
                        $chroniques[] = $chroniqueData;
                    }
                }

                $data = [
                    'action'                  => 'step3',
                    'view'                    => $this->renderView(
                        '@IrsteaBdohAdmin/MeasureImport/step3.html.twig',
                        [
                            'chroniques'          => $chroniques,
                            'hasChildrenGlobal'   => $hasChildrenGlobal,
                            'childrenCountGlobal' => $childrenCountGlobal,
                        ]
                    ),
                    'numChroniquesToValidate' => count($chroniques),
                ];
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
     * => Retrieves the request parameter "importType" from step 3.
     * => Runs the effective import of measures, depending of it.
     * => Displays the step 4 : summary of import, or errors.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function step4Action(Request $request)
    {
        if (!$this->isXmlHttpRequest()) {
            return $this->redirectStep1();
        }

        $importTypes = $request->request->all('importTypes');
        list($importer, $errors) = $this->retrieveImporter();
        $propagate = $request->request->get('propagate', false) === 'propagate';

        // Stores the request parameter "propagate" in the importer
        $importer->setPropagate($propagate);

        // For each selected 'chronique', proceeds to the final import
        $stats = $importer->importData($importTypes, $this->getUser(), $this->dispatcher);

        // Some errors ? Display it and quit !
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
        } else {
            foreach ($stats as $stat) {
                if ($stat['insert'] > 0) {
                    $this->getLogger()->createHistoriqueDonneesImport(
                        $this->getUser(),
                        new \DateTime(),
                        $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent(),
                        $stat['entity'],
                        $stat['beginDate'],
                        $stat['endDate'],
                        $stat['insert'],
                        $this->importer->format,
                        $this->importer->getTimezone()
                    );
                }
                /* update of measures' dates on the 'chronique' */
                if ($stat['entity']) {
                    $this->getBdohRepo('Chronique')->updateDatesMesuresByChronique($stat['entity']->getId());
                }
            }
            $data = [
                'action'      => 'step4',
                'view'        => $this->renderView(
                    '@IrsteaBdohAdmin/MeasureImport/step4.html.twig',
                    [
                        'stats'         => $stats,
                        'chroniqueType' => $importer->getChroniqueType(),
                        'jobPageLink'   => $this->generateUrl('bdoh_job_list'),
                    ]
                ),
            ];
        }

        // Patch: remove UTF-8 invalid characters in the view returned in order to avoid JsonResponse to throw an error
        if ($data['view']) {
            ini_set('mbstring.substitute_character', 'U+003F');
            $data['view'] = mb_convert_encoding($data['view'], 'UTF-8', 'UTF-8');
        }

        $request->getSession()->remove('measure.importer');

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
        if (false === ($chronique instanceof Chronique)) {
            return null;
        }

        // Exact 'Chronique' repository
        $repChronique = $this->importer->getChroniqueRepo();

        // Dates of first and last existing measures
        list($existingFirstDate, $existingLastDate) = $repChronique->getFirstLastDates($chronique);

        // Dates of first and last measures to import
        $importFirstDate = $this->importer->firstDate;
        $importLastDate = $this->importer->lastDate;

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

        $children = $repChronique->findAllChildren($chronique);
        $childrenList = join('<br>', $children);
        $childrenCount = count($children);

        return [
            'chronique'         => $chronique,
            'existingFirstDate' => $existingFirstDate,
            'existingLastDate'  => $existingLastDate,
            'importerFirstDate' => $importFirstDate,
            'importerLastDate'  => $importLastDate,
            'overlap'           => $overlap,
            'valeurTooSmall'    => $this->importer->getValeurTooSmall($chronique->getId()),
            'valeurTooLarge'    => $this->importer->getValeurTooLarge($chronique->getId()),
            'hasChildren'       => $chronique->hasChildren(),
            'childrenList'      => $childrenList,
            'childrenCount'     => $childrenCount,
        ];
    }
}
