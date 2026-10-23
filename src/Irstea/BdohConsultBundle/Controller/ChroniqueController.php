<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohConsultBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Twig\Attribute\Template;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Echantillonnage;
use Irstea\BdohDataBundle\Entity\PasEchantillonnage;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueContinueRepository;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Entity\Repository\MesureRepository;
use Irstea\BdohDataBundle\Entity\Repository\PlageRepository;
use Irstea\BdohDataBundle\Entity\Repository\PointControleRepository;
use Irstea\BdohDataBundle\Entity\Repository\StationRepository;
use Irstea\BdohDataBundle\EventListener\MeasuresUpdateEvent;
use Irstea\BdohDataBundle\Events;
use Irstea\BdohLoggerBundle\Logger\BdohLogger;
use Irstea\BdohSecurityBundle\Exception\ForbiddenException;
use Irstea\BdohBundle\Exception\NotFoundEntityException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class ChroniqueController.
 */
#[Route('/{station}/{chronique}', requirements: [
    'station'   => '([A-Z0-9_-]+|_[A-Za-z]+_)',
    'chronique' => '([A-Z0-9_-]+|_[A-Za-z]+_)',
])]
class ChroniqueController extends Controller
{
    const DATE_FORMAT = 'Y-m-d\TH:i:sT';

    /**
     * @var JobManagerInterface
     */
    private $jobManager;

    public function __construct(#[Autowire(service: 'irstea_bdoh.job_manager')] JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    /**
     * @return array
     */
    #[Route('', name: 'bdoh_consult_chronique', methods: ['GET'])]
    #[Template('@IrsteaBdohConsult/Chronique/show.html.twig')]
    public function getAction(Request $request)
    {
        $chronique = $this->fetchChronique($request->attributes);

        $authChecker = $this->get('security.authorization_checker');

        if (!$authChecker->isGranted('CONSULT_TIMESERIES', $chronique)) {
            throw new ForbiddenException();
        }

        /** @var ChroniqueRepository $chronRepo */
        $chronRepo = $this->getClassRepo($chronique);

        $dateFirst = $chronique->getDateDebutMesures();
        $dateLast = $chronique->getDateFinMesures();
        $nbMeasures = $chronique->getNbMesures();

        // If the chronicle is a computed one, then send the corresponding "JeuBaremes" to the TWIG template
        if (!($chronique->isCalculee())) {
            $jeuxBaremes = null;
        } else {
            $jeuxBaremes = [];
            foreach ([0, 1] as $i) {
                $transformation = ($i === 0) ? $chronique->getPremiereEntree() : $chronique->getSecondeEntree();
                $jeuxBaremes[] = ($transformation) ? $transformation->getJeuBaremesHistoriques() : null;
            }
        }

        // Si c'est une chronique convertie on récupère l'historique des conversions
        if (!($chronique->isConvertie())) {
            $conversions = null;
        } else {
            $conversion = $chronique->getConversion();
            $conversions = ($conversion) ? $conversion->getJeuConversionsHistoriques() : null;
        }

        // If rights are sufficient, display checkpoint info
        /** @var PointControleRepository $pctlRepo */
        $pctlRepo = $this->getBdohRepo('PointControle');
        $checkpointStats = ($authChecker->isGranted('CONSULT_CHECKPOINTS', $chronique)) ? $pctlRepo->getFirstLastDates($chronique) : null;

        // Allowed sampling types at regular time step
        $allowedSamplings = [];
        $allowedTimeSteps = [];
        if ($chronique->isContinue()) {
            $dae = $this->getParameter('default_allowed_exports');
            /** @var ChroniqueContinueRepository $chronRepo */
            $allowedParameters = $chronRepo->getAllowedExportParameters($chronique, $dae);

            $samplingGetter = 'getNom';
            $timeStepGetter = 'getLibelle';
            if ($this->isLocaleEn()) {
                $samplingGetter .= 'En';
                $timeStepGetter .= 'En';
            }

            /** @var Echantillonnage $sampling */
            foreach ($allowedParameters['samplings'] as $sampling) {
                $samplingCode = $sampling->getCode();
                $samplingConstant = \strtolower($samplingCode);

                $allowedSamplings[$samplingConstant] = [
                    'code'  => $samplingCode,
                    'label' => $sampling->$samplingGetter(),
                ];
            }

            /** @var PasEchantillonnage $timeStep */
            foreach ($allowedParameters['timeSteps'] as $timeStep) {
                $allowedTimeSteps[$timeStep->getId()] = [
                    'label'           => $timeStep->$timeStepGetter(),
                    'approxSeconds'   => $timeStep->getApproxSecondes(),
                    'askCumulRefHour' => $timeStep->getApproxSecondes() >= 86400,
                ];
            }
        }

        if ($chronique->isContinue()
            && ($inputSampling = $chronique->getEchantillonnage())
            && \in_array(($inputSamplingCode = $inputSampling->getCode()), ['mean', 'cumulative'])
        ) {
            $preferExistingMeasure = (
                $inputSamplingCode === 'mean'
                || ($inputSamplingCode === 'cumulative' && $chronique->getDirectionMesure() === 'ahead')
            );
            $displayStartField = [
                'check'                     => $preferExistingMeasure ? 'existing-measure' : 'round-time',
                'warnBetterExistingMeasure' => $preferExistingMeasure,
            ];
        } else {
            $displayStartField = false;
        }

        $children = $chronRepo->findAllChildren($chronique);
        $childrenList = join('<br>', $children);
        $childrenCount = count($children);

        $hasActiveJobs = $this->jobManager->hasActiveJobs($chronique);

        return [
            'chronique'               => $chronique,
            'dateFirst'               => $dateFirst,
            'dateLast'                => $dateLast,
            'measuresNumber'          => $nbMeasures,
            'fillingRates'            => $chronRepo->getFillingRatesByYearAndMonth($chronique),
            'jeuxBaremes'             => $jeuxBaremes,
            'checkpointStats'         => $checkpointStats,
            'exportSamplings'         => $allowedSamplings,
            'exportTimeSteps'         => $allowedTimeSteps,
            'displayStartField'       => $displayStartField,
            'hasChildren'             => $chronique->hasChildren(),
            'childrenList'            => $childrenList,
            'childrenCount'           => $childrenCount,
            'chroniqueContinue'       => $chronique->isContinue(),
            'hasActiveJobs'           => $hasActiveJobs,
            'jobPage'                 => $this->generateUrl('bdoh_job_list'),
            'conversions'             => $conversions,
        ];
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    #[Route('/empty', name: 'bdoh_empty_chronique', methods: ['POST'])]
    public function emptyChroniqueAction(Request $request)
    {
        $chronique = $this->fetchChronique($request->attributes);

        if (!$this->get('security.authorization_checker')->isGranted('REMOVE_DATA', $chronique)) {
            throw new AccessDeniedHttpException();
        }

        $beginDate = $this->parseUTCDate($request, 'beginDate');
        $endDate = $this->parseUTCDate($request, 'endDate');
        $propagate = $request->request->get('propagate', false) === 'propagate';

        /** @var ChroniqueRepository $chroniqueRepo */
        $chroniqueRepo = $this->getClassRepo($chronique);

        /** @var MesureRepository|PlageRepository $measureRepo */
        $measureRepo = $chroniqueRepo->getMeasureRepoByChronique($chronique);

        $this->getDoctrine()->getManager()->transactional(
            function () use ($measureRepo, $chronique, $beginDate, $endDate, $propagate, &$result) {
                $result = $measureRepo->emptyChroniquePeriode($chronique, $beginDate, $endDate);

                if ($result['count'] === 0) {
                    return;
                }

                /** @var BdohLogger $logger */
                $logger = $this->container->get('irstea_bdoh_logger.logger');
                $logger->createHistoriqueDonneesSuppression(
                    $this->getUser(),
                    new \DateTime(),
                    $chronique->getObservatoire(),
                    $chronique,
                    $beginDate->format(DATE_ATOM),
                    $endDate->format(DATE_ATOM),
                    $result['count'],
                    $result['changed']
                );

                $event = new MeasuresUpdateEvent($chronique, $this->getUser(), $beginDate, $endDate, $propagate);
                $this->container->get('event_dispatcher')->dispatch($event, Events::MEASURES_UPDATE);
            }
        );

        $session = $request->getSession();
        if ($session instanceof Session) {
            $trans = $this->getTranslator();
            $session->getFlashBag()->add(
                'success',
                $result['changed'] ?
                    $trans->trans('changedMeasures(%changed%)', ['%count%' => $result['count'], '%changed%' => $result['count']]) :
                    $trans->trans('removedMeasures(%removed%)', ['%count%' => $result['count'], '%removed%' => $result['count']])
            );
        }

        $referer = $request->headers->get('referer');

        return $this->redirect($referer);
    }

    /**
     * @param Request $request
     * @param string  $key
     *
     * @return \DateTime
     */
    private function parseUTCDate(Request $request, $key)
    {
        if (!$request->request->has($key)) {
            throw new BadRequestHttpException("Missing parameter $key");
        }
        $value = $request->request->get($key);
        $date = \DateTime::createFromFormat(self::DATE_FORMAT, $value, new \DateTimeZone('UTC'));
        if ($date === false) {
            throw new BadRequestHttpException(
                sprintf('Invalid date value for %s: "%s", expected format: "%s"', $key, $value, self::DATE_FORMAT)
            );
        }

        return $date;
    }

    /**
     * @param ParameterBag $parameters
     *
     * @return Chronique
     */
    private function fetchChronique(ParameterBag $parameters)
    {
        /** @var StationRepository $stationRepo */
        $stationRepo = $this->getBdohRepo('Station');
        /** @var ChroniqueRepository $chroniqueRepo */
        $chroniqueRepo = $this->getBdohRepo('Chronique');

        $station = $stationRepo->findOneByCode($parameters->get('station'));
        $chronique = $station ? $chroniqueRepo->findOneByStationAndCode($station, $parameters->get('chronique')) : null;

        // L'ancien poursuivait sans chronique et finissait sur une erreur interne (dev/differences.md).
        if (!$chronique) {
            throw new NotFoundEntityException('Chronique', ['station' => $parameters->get('station'), 'code' => $parameters->get('chronique')]);
        }

        return $chronique;
    }
}
