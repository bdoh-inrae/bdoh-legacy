<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohConsultBundle\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Bridge\Twig\Attribute\Template;
use Doctrine\ORM\Query;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Form\Type\UTCDateTimeType;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohBundle\Util\HttpFileResponse;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueDiscontinue;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Entity\Repository\ObservatoireRepository;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Irstea\BdohTheiaOzcarBundle\Service\SchemaJSON;
use Irstea\BdohTheiaOzcarBundle\Service\SerializerJSON;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class ObservatoireController.
 */
class ObservatoireController extends Controller
{
    /** @var SerializerJSON */
    private $serializerJson;

    /**
     * @var SchemaJSON
     */
    private $schemaJSON;

    /**
     * @var JobManagerInterface
     */
    private $jobManager;

    public function __construct(
        #[Autowire(service: 'irstea_bdoh.job_manager')] JobManagerInterface $jobManager,
        #[Autowire(service: 'irstea_bdoh_theia_ozcar.serializerjson')] SerializerJSON $serializerJson,
        #[Autowire(service: 'irstea_bdoh_theia_ozcar.schemajson')] SchemaJSON $schemaJSON
    ) {
        $this->jobManager = $jobManager;
        $this->serializerJson = $serializerJson;
        $this->schemaJSON = $schemaJSON;
    }

    /**
     * @return array
     */
    #[Route('', name: 'bdoh_consult_observatoire', methods: ['GET'])]
    #[Template('@IrsteaBdohConsult/Observatoire/show.html.twig')]
    public function getAction()
    {
        // Les familles de paramètres utilisées sur cet observatoire
        $familyTable = $this->getBdohRepo('FamilleParametres')->getFamillesAndTypes($this->isLocaleEn());

        // Les familles de niveau 0
        $rootFamilies = [];
        foreach ($familyTable as $family) {
            if ($family['pid'] === null) {
                $rootFamilies[] = $family['id'];
            }
        }

        return [
            'familyTable'           => $familyTable,
            'rootFamilies'          => $rootFamilies,
            'parametres'            => $this->getBdohRepo('Observatoire')->getParametres($this->isLocaleEn()),
            'sites'                 => $this->getBdohRepo('SiteExperimental')->findAll(),
            'countChroniquesBySite' => $this->getBdohRepo('SiteExperimental')->countChroniquesBySite(),
        ];
    }

    /**
     * @param Request $request
     */
    #[Route('/observatoire/{id}/save', name: 'bdoh_admin_observatoire_save_miseajour', methods: ['POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') and user.isAManagerOfCurrentObservatory()"))]
    public function saveMiseAJourAutomatiqueFormAction(Request $request)
    {
        $observatoire = $this->fetchObservatoire($request->attributes);
        // récupération des autre paramètres
        $miseAJourAutomatique = $request->request->get('miseajourAutomatique');
        $observatoireId = $observatoire->getId();

        // mise à jour de l'observatoire et enregistrement des entities
        if ($miseAJourAutomatique === null || $miseAJourAutomatique === false) {
            $observatoire->setMiseajourAutomatique(false);
        } else {
            $observatoire->setMiseajourAutomatique($miseAJourAutomatique);
        }

        $this->getEm()->persist($observatoire);
        $this->getEm()->flush();

        return $this->redirectToRoute(
            'bdoh_admin_observatoire_edit_jsonValidator',
            ['id'                      => $observatoireId,
                'miseajourAutomatique' => $observatoire->isMiseajourAutomatique(),
            ]
        );
    }

    /**
     * @param Request $request
     */
    #[Route('/observatoire/{id}/envoieJson', name: 'bdoh_admin_observatoire_edit_envoieJson', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') and user.isAManagerOfCurrentObservatory()"))]
    public function getSendJsonToTheia(Request $request)
    {
        $observatoire = $this->fetchObservatoire($request->attributes);
        // récupération des autre paramètres

        $this->jobManager->enqueueJsonTheia($observatoire);

        return $this->redirectToRoute('bdoh_job_list');
    }

    /**
     * @param Request $request
     */
    #[Route('/observatoire/{id}/downloadJson', name: 'bdoh_admin_observatoire_download_envoieJson', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') and user.isAManagerOfCurrentObservatory()"))]
    public function downLoadJsonToTheia(Request $request):Response
    {
        $observatoire = $this->fetchObservatoire($request->attributes);
        $json = $this->serializerJson->serialize($observatoire);

        $response = new Response($json);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('Theia_%s.json',$observatoire->getSlug())

        );
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }

    /**
     * @param Request $request
     */
    #[Route('/observatoire/{id}/jsonValidator', name: 'bdoh_admin_observatoire_edit_jsonValidator', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') and user.isAManagerOfCurrentObservatory()"))]
    public function validateTheiaJsonAction(Request $request)
    {
        $observatoire = $this->fetchObservatoire($request->attributes);

        $json = $this->serializerJson->serialize($observatoire);
        $data = json_decode($json);

        $schema = $this->schemaJSON->loadSchemaTheia();
        $result = $this->schemaJSON->validateSchemaTheia($data);

        return $this->render(
            '@IrsteaBdohConsult/JsonValidator/report.html.twig',
            [
                'json'                 => $json,
                'schemas'              => $schema->id(),
                'result'               => $result,
                'miseajourAutomatique' => $observatoire->isMiseajourAutomatique(),
            ]
        );
    }

    /**
     * @param ParameterBag $parameters
     *
     * @return Observatoire
     */
    public function fetchObservatoire(ParameterBag $parameters)
    {
        /** @var ObservatoireRepository $obseRepo */
        $obseRepo = $this->getBdohRepo('Observatoire');

        return $obseRepo->findOneById($parameters->get('id'));
    }

    /**
     * @return HttpFileResponse
     */
    #[Route('/terms_of_use', name: 'bdoh_cgu_observatoires', methods: ['GET'])]
    public function cguAction()
    {
        $observatoire = Observatoire::getCurrent();
        $termsOfUses = $observatoire->getConditionsUtilisation();
        $cguPath = $termsOfUses ?: $this->getParameter('irstea_bdoh_data.measure_export.default_termofuses_file');

        return new HttpFileResponse($cguPath, 'application/octet-stream');
    }

    /**
     * @return array
     */
    #[Route('/contact', name: 'bdoh_consult_contact', methods: ['GET'])]
    #[Template('@IrsteaBdohConsult/Observatoire/contact.html.twig')]
    public function contactAction()
    {
        $obs = $this->get('irstea_bdoh.manager.observatoire')->getCurrent();
        $gestionnaires = $this->getRepository('Irstea\BdohSecurityBundle\Entity\Utilisateur')->findGestionnairesByObservatoire($obs);

        $dataToShow = [];
        /** @var Utilisateur $gest */
        foreach ($gestionnaires as $gest) {
            $dataToShow[] = ['firstName' => $gest->getPrenom(), 'lastName' => $gest->getNom(), 'email' => $gest->getEmail()];
        }

        return ['dataToShow' => $dataToShow];
    }

    /**
     * @return array
     */
    #[Route('/nos-donnees', name: 'bdoh_consult_advancedSearch', methods: ['GET'])]
    #[Template('@IrsteaBdohConsult/Observatoire/advancedSearch.html.twig')]
    public function advancedSearchAction(Request $request)
    {
        /** @var ChroniqueRepository $chronRepo */
        $chronRepo = $this->getBdohRepo('Chronique');
        $chroniques = $chronRepo->bigFindAll();
        $chroniquesNumber = [];
        /** @var Observatoire $obs */
        $obs = $this->get('irstea_bdoh.manager.observatoire')->getCurrent();

        // les bassins, cours d'eau et familles parentes pour chaques chroniques
        $bassins = $chronRepo->findContainingBassins($obs->getId());
        $coursEaux = $chronRepo->findNeighbouringCoursEaux($obs->getId());
        $familles = $chronRepo->findParentFamilies($obs->getId(), $this->isLocaleEn());

        // Computes the 'chroniques' number, by 'station'
        foreach ($chroniques as &$chronique) {
            $stationId = $chronique->getStation()->getId();

            if (!isset($chroniquesNumber[$stationId])) {
                $chroniquesNumber[$stationId] = 1;
            } else {
                ++$chroniquesNumber[$stationId];
            }

            $chronique->bassins = isset($bassins[$chronique->getId()]) ? $bassins[$chronique->getId()] : null;
            $chronique->coursEaux = isset($coursEaux[$chronique->getId()]) ? $coursEaux[$chronique->getId()] : null;
            $chronique->familles = isset($familles[$chronique->getId()]) ? $familles[$chronique->getId()] : null;
        }

        return [
            'chroniques'       => $chroniques,
            'chroniquesNumber' => $chroniquesNumber,
        ];
    }

    /**
     * @return array
     */
    #[Route('/viewer', name: 'bdoh_consult_complexViewer', methods: ['GET'])]
    #[Template('@IrsteaBdohConsult/Observatoire/complexViewer.html.twig')]
    public function complexViewerAction()
    {
        $token = $this->get('security.token_storage')->getToken();
        $userOrNull = $token !== null ? $token->getUser() : null;
        $user = $userOrNull instanceof Utilisateur ? $userOrNull : null;

        /** @var ChroniqueRepository $repo */
        $repo = $this->getRepository(Chronique::class);

        $dates = $repo->createSecuredQueryBuilderForView($user)
            ->select('MIN(ch.dateDebutMesures)', 'MAX(ch.dateFinMesures)')
            ->getQuery()
            ->getSingleResult(Query::HYDRATE_ARRAY);

        return [
            'minDate' => $dates[1],
            'maxDate' => $dates[2],
        ];
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/viewer/load', name: 'bdoh_consult_loadComplexViewerData', methods: ['GET'])]
    public function loadComplexViewerDataAction(Request $request)
    {
        $token = $this->get('security.token_storage')->getToken();
        $userOrNull = $token !== null ? $token->getUser() : null;
        $user = $userOrNull instanceof Utilisateur ? $userOrNull : null;

        $request->setRequestFormat('json');

        $form = $this->createMetadataForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new BadRequestHttpException();
        }

        $startDate = $form->get('start')->getData();
        $endDate = $form->get('end')->getData();

        $obs = $this->get('irstea_bdoh.manager.observatoire')->getCurrent();

        $data = [
            'observatory' => ['name' => $obs->getNom()],
            'stations'    => $this->fetchSecuredStationData($user, $obs, $startDate, $endDate),
        ];

        return $this->renderJson($data);
    }

    /**
     * @return Form
     */
    private function createMetadataForm()
    {
        return $this->container->get('form.factory')
            ->createNamedBuilder('', FormType::class, null, ['csrf_protection' => false])
            ->setMethod('GET')
            ->add('start', UTCDateTimeType::class, ['constraints' => [new NotBlank()]])
            ->add('end', UTCDateTimeType::class, ['constraints' => [new NotBlank()]])
            ->getForm();
    }

    /**
     * @param Utilisateur|null $user
     * @param Observatoire     $obs
     * @param \DateTime        $startDate
     * @param \DateTime        $endDate
     *
     * @return array
     */
    private function fetchSecuredStationData($user, Observatoire $obs, \DateTime $startDate, \DateTime $endDate)
    {
        $stations = $this->fetchAllStations($obs);

        return array_map(
            function (Station $station) use ($user, $startDate, $endDate) {
                return $this->buildSecuredStationData($user, $station, $startDate, $endDate);
            },
            $stations
        );
    }

    /**
     * @param Observatoire $obs
     *
     * @return Station[]
     */
    private function fetchAllStations(Observatoire $obs)
    {
        $stations = [];
        foreach ($obs->getSites() as $site) {
            /** @var Station $station */
            foreach ($site->getStations() as $station) {
                $stations[$station->getId()] = $station;
            }
        }

        return $stations;
    }

    /**
     * @param Utilisateur|null $user
     * @param Station          $station
     * @param \DateTime        $startDate
     * @param \DateTime        $endDate
     *
     * @return array
     */
    private function buildSecuredStationData($user, Station $station, \DateTime $startDate, \DateTime $endDate)
    {
        $hasMeasures = array_fill_keys(
            $this->getBdohRepo('Chronique')->getSecuredChroniqueIDsWithMeasures($user, $station->getId(), $startDate, $endDate),
            true
        );

        $chronicles = array_map(
            function (Chronique $chronique) use ($hasMeasures) {
                $data = $this->buildChronicleData($chronique);
                $data['hasMeasures'] = !empty($hasMeasures[$chronique->getId()]);

                return $data;
            },
            $station->getChroniques()->toArray()
        );

        usort($chronicles, function ($a, $b) { return strnatcasecmp($a['code'], $b['code']); });

        return [
            'id'          => $station->getId(),
            'name'        => $station->getNom(),
            'code'        => $station->getCode(),
            'hasMeasures' => !empty($hasMeasures),
            'chronicles'  => $chronicles,
        ];
    }

    /**
     * @param Chronique $chronique
     *
     * @return array
     */
    private function buildChronicleData(Chronique $chronique)
    {
        if ($chronique instanceof ChroniqueDiscontinue) {
            $samplingType = 'discontinuous';
        } else {
            $sampling = $chronique->getEchantillonnage();
            $samplingType = $sampling ? $sampling->getCode() : 'instantaneous';
        }

        $paramType = $chronique->getParametre();
        $label = $chronique->getLibelle($this->isLocaleEn() ? 'en' : 'fr');
        if ($this->isLocaleEn()) {
            $paramName = $paramType->getNomEn() ?: $paramType->getNom();
        } else {
            $paramName = $paramType->getNom();
        }

        $unit = $chronique->getUnite();

        return [
            'id'           => $chronique->getId(),
            'label'        => $label,
            'code'         => $chronique->getCode(),
            'paramName'    => $paramName,
            'unitId'       => ($unit) ? $unit->getId() : null,
            'unitLabel'    => ($unit) ? $unit->getLibelle() : '',
            'samplingType' => $samplingType,
            'reverseAxis'  => $chronique->getReverseAxis(),
        ];
    }
}
