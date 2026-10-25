<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Controller;

use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohAdminBundle\Importer\Shapefile\Manager;
use Irstea\BdohAdminBundle\IrsteaBdohAdminBundle;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohDataBundle\Entity\Repository\BassinRepository;
use Irstea\BdohDataBundle\Entity\Repository\CoursEauRepository;
use Irstea\BdohDataBundle\Entity\Repository\StationRepository;
use Irstea\BdohAdminBundle\Manager\ShapeImportManager;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ShapeImportController.
 */
#[IsGranted(new Expression("is_granted('ROLE_ADMIN') and user.isAManagerOfCurrentObservatory()"))]
class ShapeImportController extends Controller
{
    public static function getSubscribedServices(): array
    {
        return parent::getSubscribedServices() + ['irstea_bdoh_admin.manager.shape_import' => ShapeImportManager::class];
    }

    /**
     * @var Manager
     */
    protected $shapeManager;

    /**
     * @var ImportErrors
     */
    protected $errors;

    /*
     * Tables that have geometry column
     */
    /**
     * @var array
     */
    protected $geoTables = ['bassin', 'coursEau', 'station'];

    /**
     * From session, retrieves the importer and its errors manager.
     */
    protected function retrieveImporter()
    {
        $this->shapeManager = $this->getSession()->get('shape.importer');
        if (!$this->shapeManager) {
            return [null, null];
        }
        $this->errors = $this->shapeManager->getErrors();
        $this->shapeManager->wakeup($this->getEm(), $this->getTranslator());

        return [$this->shapeManager, $this->errors];
    }

    /**
     * Stores the importer in session.
     */
    protected function storeImporter()
    {
        $this->getSession()->set('shape.importer', $this->shapeManager);
    }

    /**
     * Shortcut to return the 'irstea_bdoh_admin.manager.shape_import' service.
     */
    protected function getManager()
    {
        return $this->container->get('irstea_bdoh_admin.manager.shape_import');
    }

    /**
     * Shortcut to return the Logger service.
     */
    protected function getLogger()
    {
        return $this->container->get('irstea_bdoh_logger.logger');
    }

    /**
     * Prepares the response data in case of errors.
     *
     * @return array
     */
    protected function getErrorsResponse()
    {
        if ($this->shapeManager) {
            $this->shapeManager->clean();
        }

        // Sends the error info
        $idImporter = null;
        list($importer) = $this->retrieveImporter();
        if ($importer && in_array($importer->getTargettedTable(), $this->geoTables)) {
            $idImporter = $importer->getTargettedTable();
        }

        return [
            'action'     => 'errors',
            'idImporter' => $idImporter,
            'view'       => $this->renderView(
                '@IrsteaBdohAdmin/ShapeImport/errors.html.twig',
                [
                    'errors'     => $this->errors->translations($this->getTranslator()),
                    'idImporter' => $idImporter,
                ]
            ),
        ];
    }

    /**
     * Redirects to step 1 page.
     */
    protected function redirectStep1()
    {
        return $this->redirect($this->generateUrl('bdoh_admin_shape_import'));
    }

    /**
     * Stops the measures import process and redirects to step 1 page.
     */
    public function stopAction()
    {
        list($importer) = $this->retrieveImporter();
        $importer->clean();
        $this->getSession()->getFlashBag()->add('error', $this->get('translator')->trans('shape.import.info.wasCanceled'));

        return $this->redirectStep1();
    }

    /**
     * Displays step 1 : selection of file and file format.
     */
    public function step1Action()
    {
        return $this->render(
            '@IrsteaBdohAdmin/ShapeImport/step1.html.twig',
            ['fileShapes' => $this->geoTables]
        );
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function step2Action(Request $request)
    {
        if (!$this->isXmlHttpRequest()) {
            return $this->redirectStep1();
        }

        $manager = $this->getManager();
        $file = $request->files->get('file');
        $table = $request->get('file-shape');
        $importerClass = $manager->importers->get('shape');
        $errors = $this->errors = new ImportErrors();

        /*
         * Checks request parameters of step 1 : file.
         */
        if (null === $importerClass) {
            // Bad format
            $errors->badFormat($table);
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
            // Moves uploaded file in import directory

            $fileName = explode('.', $file->getClientOriginalName());
            $file = $file->move($manager->getDir(), $fileName[0]);

            $filePath = $file->getPathname();
            if ($manager->isValidMimeType($mimeType, 'zip')) {
                // Extracts its first file
                $zipPath = $filePath;
                if (!IrsteaBdohAdminBundle::unzipAll($zipPath, $manager->getDir())) {
                    $errors->badZip();
                }
            }
            if ($errors->isClean()) {
                /** @var Manager $importer */
                $importer = $this->shapeManager = new $importerClass($this->getEm(), $this->getTranslator(), $filePath, $table, $errors);

                $importer->loadShapefile();

                if (filesize($filePath . '_Success.log') === 0) {
                    $errors->badShape(file_get_contents($filePath . '_Errors.log'));
                }
                if ($errors->isClean()) {
                    $shapeData = $this->prepareShapeData($importer);
                    if (isset($shapeData['error'])) {
                        $errors->incoherentShape($this->getTranslator()->trans($shapeData['error']));
                    }
                    if ($errors->isClean()) {
                        $data = [
                            'action' => 'step2',
                            'view'   => $this->renderView(
                                '@IrsteaBdohAdmin/ShapeImport/step2-' . \strtolower($shapeData['cible']) . '.html.twig',
                                ['shapeData' => $shapeData]
                            ),
                        ];
                    }
                }
            }
        }
        $this->storeImporter();
        // Some errors ? Display it and quit !
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
        }

        // Patch: remove UTF-8 invalid characters in the view returned in order to avoid JsonResponse to throw an error
        if ($data['view']) {
            ini_set('mbstring.substitute_character', 'U+003F');
            $data['view'] = mb_convert_encoding($data['view'], 'UTF-8', 'UTF-8');
        }

        return $this->renderJson($data);
    }

    /**
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function step3Action(Request $request)
    {
        if (!$this->isXmlHttpRequest()) {
            return $this->redirectStep1();
        }

        $importType = $request->request->get('shapeImportType');

        $token = $this->container->get('security.token_storage')->getToken();
        $user = $token ? $token->getUser() : null;
        list($importer, $errors) = $this->retrieveImporter();
        $stats = $importer->importData($importType, $user);
        // Some errors ? Display it and quit !
        if ($errors->isDirty()) {
            $data = $this->getErrorsResponse();
        } else {
            if ($stats[0] + $stats[1] > 0) {
                $this->getLogger()->createHistoriqueSig(
                    $this->container->get('security.token_storage')->getToken()->getUser(),
                    new \DateTime(),
                    $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent(),
                    $stats,
                    $importer->getTargettedTable()
                );
            }

            $data = [
                'action' => 'step3',
                'view'   => $this->renderView(
                    '@IrsteaBdohAdmin/ShapeImport/step3.html.twig',
                    [
                        'stats' => $stats,
                    ]
                ),
            ];
        }
        $importer->clean();

        // Patch: remove UTF-8 invalid characters in the view returned in order to avoid JsonResponse to throw an error
        if ($data['view']) {
            ini_set('mbstring.substitute_character', 'U+003F');
            $data['view'] = mb_convert_encoding($data['view'], 'UTF-8', 'UTF-8');
        }

        return $this->renderJson($data);
    }

    /**
     * @param Manager $importer
     *
     * @return mixed|null
     */
    public function prepareShapeData($importer)
    {
        /** @var BassinRepository|StationRepository|CoursEauRepository $repoShape */
        $repoShape = $this->getBdohRepo(ucfirst($importer->getTargettedTable()));
        $token = $this->container->get('security.token_storage')->getToken();
        $user = $token ? $token->getUser() : null;

        if (in_array($importer->getTargettedTable(), $this->geoTables)) {
            return $repoShape->getDataFromShapeTable($importer->getTmpTable(), $user);
        }

        return null;
    }
}
