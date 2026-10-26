<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Controller;

use Doctrine\ORM\NoResultException;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueContinueRepository;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Class FillingRateController.
 *
 * TODO à virer ? normalement ce controller n'est plus utilisé
 * (les calculs de taux de remplissage sont tout automatique)
 */
#[IsGranted(new Expression("is_granted('ROLE_ADMIN') and (user.isAManagerOfCurrentObservatory() or user.isASiteManagerOfCurrentObservatory() or user.isAContributorOfCurrentObservatory())"))]
class FillingRateController extends Controller
{
    /**
     * @var JobManagerInterface
     */
    private $jobManager;

    public function __construct(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    /**
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    #[Route('/fillingrate', name: 'bdoh_admin_recalculate', methods: ['GET'])]
    public function fillingRateRecalculateDoAction(Request $request)
    {
        $chroniqueId = (int) $request->query->get('id');
        if (!$chroniqueId) {
            throw new BadRequestHttpException();
        }

        /** @var ChroniqueContinueRepository $repo */
        $repo = $this->getDoctrine()->getRepository(ChroniqueContinue::class);

        // findOneById() ne cherche que dans l'observatoire de la page, et lève une exception sur une
        // chronique inconnue, avant le test qui suit : 404, et non l'erreur interne de l'ancien
        // (dev/differences.md)
        try {
            /** @var ChroniqueContinue $chronique */
            $chronique = $repo->findOneById($chroniqueId);
        } catch (NoResultException) {
            $chronique = null;
        }
        if (!$chronique) {
            throw new NotFoundHttpException();
        }

        if (!$this->get('security.authorization_checker')->isGranted('UPLOAD_DATA', $chronique)) {
            throw new AccessDeniedHttpException();
        }

        $this->jobManager->enqueueComputeFillingRates($chronique);

        $url = $this->generateUrl(
            'bdoh_consult_chronique',
            [
                'station'   => $chronique->getStation()->getCode(),
                'chronique' => $chronique->getCode(),
            ]
        );

        return $this->redirect($url);
    }
}
