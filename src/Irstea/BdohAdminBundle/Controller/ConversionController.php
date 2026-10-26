<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Controller;

use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Entity\ChroniqueConvertie;
use Irstea\BdohDataBundle\Entity\ChroniqueDiscontinue;
use Irstea\BdohDataBundle\Entity\Conversion;
use Irstea\BdohDataBundle\Entity\JeuConversion;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueConvertieRepository;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueDiscontinueRepository;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ConversionController.
 */
#[IsGranted(new Expression("is_granted('ROLE_ADMIN') and (user.isAManagerOfCurrentObservatory() or user.isASiteManagerOfCurrentObservatory() or user.isAContributorOfCurrentObservatory())"))]
class ConversionController extends Controller
{
    const CSRF_TOKEN_TIME_VALIDITY = 7200; // 2 heures (en secondes)

    /**
     * @var JobManagerInterface
     */
    private $jobManager;

    public function __construct(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    /**
     * conversionAction.
     *
     * @param Request $request
     * @param string  $chroniqueConvertieId
     *
     * @throws \Exception
     *
     * @return array
     */
    #[Route('/conversion/{chroniqueConvertieId}', name: 'bdoh_admin_conversion',
        requirements: ['chroniqueConvertieId' => '\d+'], defaults: ['chroniqueConvertieId' => ''], methods: ['GET'])]
    #[Template('@IrsteaBdohAdmin/Conversion/conversion.html.twig')]
    public function conversionAction(Request $request, $chroniqueConvertieId)
    {
        $response = [];

        /** @var Session $session */
        $session = $request->getSession();

        // token pour la protection CSRF
        if (!$session->has('conversion.CSRFTokenTime') ||
            ((int) $session->get('conversion.CSRFTokenTime', 0) +
                self::CSRF_TOKEN_TIME_VALIDITY) <= (int) \date('U')) {
            $CSRFTokenName = 'CSRFToken' . \bin2hex(\random_bytes(8));
            $CSRFTokenValue = \bin2hex(\random_bytes(64));
            $session->set('conversion.CSRFTokenName', $CSRFTokenName);
            $session->set('conversion.CSRFTokenValue', $CSRFTokenValue);
            $session->set('conversion.CSRFTokenTime', \date('U'));
        } else {
            $CSRFTokenName = $session->get('conversion.CSRFTokenName');
            $CSRFTokenValue = $session->get('conversion.CSRFTokenValue');
        }
        $response['CSRFTokenName'] = $CSRFTokenName;
        $response['CSRFTokenValue'] = $CSRFTokenValue;

        // construction de la liste des chroniques converties
        $stations = [];
        $chroniquesConverties = [];
        /** @var ChroniqueConvertieRepository $chroniqueConvertieRepo */
        $chroniqueConvertieRepo = $this->getBdohRepo('ChroniqueConvertie');
        $allSecuredConverties = $chroniqueConvertieRepo->securedBigFindAll($this->getUser());
        foreach ($allSecuredConverties as $chroniqueConvertie) {
            $station = $chroniqueConvertie->getStation();
            $stationId = $station->getId();
            if (!isset($stations[$stationId])) {
                $stations[$stationId] = $station;
            }
            $chroniquesConverties[$stationId][] = $chroniqueConvertie;
        }
        $response['stations'] = $stations;
        $response['chroniquesConverties'] = $chroniquesConverties;

        // récupération de la chronique convertie si fournie via le formulaire (en parametre de l'url)
        if (ctype_digit($chroniqueConvertieId) && (int) $chroniqueConvertieId >= 1) {
            /** @var ChroniqueConvertie $chroniqueConvertie */
            $chroniqueConvertie = $chroniqueConvertieRepo->find($chroniqueConvertieId);

            // vérification des droits sur la chronique (et que la chronique existe et qu'elle est convertie)
            if (!$chroniqueConvertie
                || !$chroniqueConvertie->isConvertie()
                || !$this->get('security.authorization_checker')->isGranted('UPLOAD_DATA', $chroniqueConvertie)) {
                throw new AccessDeniedHttpException();
            }

            // récupération des données de la chronique convertie
            /** @var ChroniqueRepository $chroniqueRepo */
            $chroniqueRepo = $this->getBdohRepo('Chronique');
            $children = $chroniqueRepo->findAllChildren($chroniqueConvertie);
            $childrenList = join('<br>', $children);
            $childrenCount = count($children);
            $maj = $chroniqueConvertie->getMiseAJour();
            $maj = $maj ? $maj->format($this->getTranslator()->trans('transformation.bareme.dateBareme'))
                    . ' ' . $this->getTranslator()->trans('UTC') : '';
            $response['chroniqueConvertie'] = $chroniqueConvertie;
            $response['unite'] = $chroniqueConvertie->getUnite()->getLibelle();
            $response['maj'] = $maj;
            $response['genealogie'] = $chroniqueConvertie->getGenealogie();
            $response['genealogieEn'] = $chroniqueConvertie->getGenealogieEn();
            $response['hasChildren'] = $chroniqueConvertie->hasChildren();
            $response['childrenList'] = $childrenList;
            $response['childrenCount'] = $childrenCount;
            $response['paramConversion'] = '';

            $conversion = $chroniqueConvertie->getConversion();
            if ($conversion) {
                // récupération des données de la conversion
                $response['chroniqueMere'] = $conversion->getEntree();
                $response['paramConversion'] = $conversion->getJeuConversionActuel()->getParamConversion();
            } else {
                // ou construction de la liste des chroniques discontinues de la station avec la même unité
                /** @var ChroniqueDiscontinueRepository $chroniqueDiscontinueRepo */
                $chroniqueDiscontinueRepo = $this->getBdohRepo('ChroniqueDiscontinue');
                $allDiscontinuesStation = $chroniqueDiscontinueRepo
                    ->findByStationAndUnite($chroniqueConvertie->getStation(), $chroniqueConvertie->getUnite());
                $response['chroniquesDiscontinues'] = $allDiscontinuesStation;
            }
        }

        // recupération et envoie des erreurs ou informations si elles existent
        if ($session->has('conversion.errors')) {
            $response['errorsConversion'] = $session->get('conversion.errors');
            $session->remove('conversion.errors');
        }
        if ($session->has('conversion.success')) {
            $response['successConversion'] = $session->get('conversion.success');
            $session->remove('conversion.success');
        }

        return $response;
    }

    /**
     * conversionFormAction.
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    #[Route('/conversion', name: 'bdoh_admin_conversion_form', methods: ['POST'])]
    public function conversionFormAction(Request $request)
    {
        $chroniqueConvertieIdParam = '';

        $errors = new ImportErrors();

        /** @var Session $session */
        $session = $request->getSession();

        if ($request->request->has('chroniqueConvertieId')
            && ctype_digit($request->request->get('chroniqueConvertieId'))) {
            $chroniqueConvertieId = $request->request->get('chroniqueConvertieId');

            /** @var ChroniqueConvertieRepository $chroniqueConvertieRepo */
            $chroniqueConvertieRepo = $this->getBdohRepo('ChroniqueConvertie');
            /** @var ChroniqueConvertie $chroniqueConvertie */
            $chroniqueConvertie = $chroniqueConvertieRepo->find($chroniqueConvertieId);

            // vérification des droits sur la chronique (et que la chronique existe et qu'elle est convertie)
            if (!$chroniqueConvertie
                || !$chroniqueConvertie->isConvertie()
                || !$this->get('security.authorization_checker')->isGranted('UPLOAD_DATA', $chroniqueConvertie)) {
                throw new AccessDeniedHttpException();
            }

            // enregistrement la chronique convertie pour le paramètre de l'action d'affichage de la conversion
            $chroniqueConvertieIdParam = $chroniqueConvertie->getId();

            // si il ne s'agit pas d'un simple changement de chronique convertie on analyse les paramètres du formulaire
            if (!$request->request->has('chroniqueChanged') ||
                $request->request->get('chroniqueChanged') !== 'chroniqueChanged') {
                // verification du token pour la protection CSRF
                $CSRFTokenNameInSession = $session->get('conversion.CSRFTokenName', false);
                $CSRFTokenValueInSession = $session->get('conversion.CSRFTokenValue', false);
                $CSRFTokenTimeInSession = $session->get('conversion.CSRFTokenTime', 0);
                $CSRFTokenNameInRequest = $CSRFTokenNameInSession && $request->request->has($CSRFTokenNameInSession);
                $CSRFTokenValueInRequest = $request->request->get($CSRFTokenNameInSession, false);
                if ($CSRFTokenNameInRequest && $CSRFTokenValueInSession && $CSRFTokenTimeInSession &&
                    ((int) $CSRFTokenTimeInSession + self::CSRF_TOKEN_TIME_VALIDITY) >= (int) \date('U') &&
                    $CSRFTokenValueInSession === $CSRFTokenValueInRequest) {
                    /** @var Conversion $conversion */
                    $conversion = $chroniqueConvertie->getConversion();

                    // si la chronique n'est pas encore convertie on essaye de récupèrer la chronique mère fournie
                    $chroniqueMere = null;
                    if (!$conversion) {
                        // vérification de la chronique mère fournie (la chronique discontinue)
                        if (!$request->request->has('chroniqueMereId')) {
                            $errors->badParentTimeSeries();
                        } else {
                            $chroniqueMereId = $request->request->get('chroniqueMereId');
                            if (!ctype_digit($chroniqueMereId)
                                || (int) $chroniqueMereId < 1) {
                                $errors->badParentTimeSeries();
                            } else {
                                /** @var ChroniqueDiscontinueRepository $chroniqueDiscontinueRepo */
                                $chroniqueDiscontinueRepo = $this->getBdohRepo('ChroniqueDiscontinue');
                                /** @var ChroniqueDiscontinue $chroniqueMere */
                                $chroniqueMere = $chroniqueDiscontinueRepo->find($chroniqueMereId);
                                if (!$chroniqueMere
                                    || !$chroniqueMere->isDiscontinue()
                                    || !($chroniqueMere->getUnite() === $chroniqueConvertie->getUnite())
                                    || !$this->get('security.authorization_checker')->isGranted('UPLOAD_DATA', $chroniqueMere)) {
                                    $errors->badParentTimeSeries();
                                }
                            }
                        }
                    } else {
                        $chroniqueMere = $conversion->getEntree();
                    }

                    // vérification du paramètre de conversion fourni (intervalle pour les lacunes)
                    if (!$request->request->has('paramConversion')) {
                        $errors->undefinedParamConversion();
                        $paramConversion = null;
                    } else {
                        $paramConversion = trim($request->request->get('paramConversion'));
                        if (!ctype_digit($paramConversion) || (int) $paramConversion < 1) {
                            if ($paramConversion == '') {
                                $errors->undefinedParamConversion();
                            } else {
                                $errors->badParamConversion($paramConversion);
                            }
                        } else {
                            // conversion en secondes et limitation à 9999999 min pour rendre explicite la limitation
                            // dans l'interface et rester en dessous de la valeur max des entiers en base
                            $paramConversion = min(((int) $paramConversion) * 60, 599999940);
                        }
                    }

                    // si on a des erreurs ici on s'arrète et on les affiche
                    if ($errors->isDirty()) {
                        $session->set('conversion.errors', $errors->translations($this->getTranslator()));

                        return $this->redirectToRoute(
                            'bdoh_admin_conversion',
                            ['chroniqueConvertieId' => $chroniqueConvertieIdParam]
                        );
                    }

                    // creation de la conversion si elle n'existe pas
                    if (!$conversion) {
                        $conversion = new Conversion();
                        $conversion->setEntree($chroniqueMere);
                        $chroniqueConvertie->setConversion($conversion); // fait aussi le setSortie sur la conversion
                    }

                    // date de création du jeu de conversion
                    $date = new \DateTime('now', new \DateTimeZone('UTC'));

                    // creation du nouveau jeu de conversion
                    $newJeuConversion = new JeuConversion();
                    $newJeuConversion->setParamConversion($paramConversion);
                    // ajout du nouveau jeu si il est différent de l'actuel
                    if (!$newJeuConversion->equals($conversion->getJeuConversionActuel())) {
                        $newJeuConversion->setDateCreation($date);
                        $conversion->setJeuConversionActuel($newJeuConversion);
                        $conversion->addJeuConversionsHistoriques($newJeuConversion);
                        $newJeuConversion->setConversion($conversion);
                    }

                    // récupération des autre paramètres
                    $genealogie = $request->request->get('genealogie');
                    $genealogieEn = $request->request->get('genealogieEn');
                    $propagate = $request->request->get('propagate', false) === 'propagate';

                    // mise à jour de la chronique et enregistrement des entities
                    $chroniqueConvertie->setGenealogie($genealogie);
                    $chroniqueConvertie->setGenealogieEn($genealogieEn);
                    $this->getEm()->persist($chroniqueConvertie);
                    $this->getEm()->flush();

                    // lance le job de conversion
                    $job = $this->jobManager->enqueueChronicleConversion($chroniqueConvertie, $this->getUser(), $propagate);

                    // enregistrement des informations pour le message de succès
                    $session->set('conversion.success', [
                        'link' => $this->generateUrl(
                            'bdoh_consult_chronique',
                            [
                                'station'   => $chroniqueConvertie->getStation()->getCode(),
                                'chronique' => $chroniqueConvertie->getCode(),
                            ]
                        ),
                        'chronique' => $chroniqueConvertie->__toString(),
                        'jobPage'   => $this->generateUrl('bdoh_job_list'),
                        'jobId'     => $job->getId(),
                    ]);
                } else {
                    // protection CSRF absente
                    $session->getFlashBag()->add('error', $this->getTranslator()->trans('conversion.CSRFerror'));
                }
            } // pas une conversion juste un changement de chronique
        } // pas de chronique convertie fournie

        return $this->redirectToRoute(
            'bdoh_admin_conversion',
            ['chroniqueConvertieId' => $chroniqueConvertieIdParam]
        );
    }
}
