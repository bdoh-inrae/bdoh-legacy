<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Bridge\Twig\Attribute\Template;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohBundle\Manager\ObservatoireManagerInterface;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Irstea\BdohSecurityBundle\Security\Voter\JobVoter;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Entity\Repository\JobRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Class JobController.
 */
#[Route('/jobs')]
class JobController extends Controller
{
    /**
     * @var RegistryInterface
     *
     * @DI\Inject("doctrine")
     */
    public $doctrine;

    /**
     * @var ObservatoireManagerInterface
     *
     * @DI\Inject("irstea_bdoh.manager.observatoire")
     */
    public $observatoireManager;

    /**
     * @var AuthorizationCheckerInterface
     *
     * @DI\Inject("security.authorization_checker")
     */
    public $authChecker;

    /**
     * @var JobManagerInterface
     *
     * @DI\Inject("irstea_bdoh.job_manager")
     */
    public $jobManager;

    #[Route('/', name: 'bdoh_job_list', methods: ['GET'])]
    #[Template('@IrsteaBdohData/Job/all.html.twig')]
    public function indexAction()
    {
        $obs = $this->observatoireManager->getCurrent();

        if (!$this->authChecker->isGranted(JobVoter::LIST_PERM, $obs)) {
            throw new AccessDeniedHttpException();
        }

        /** @var JobRepository $repo */
        $repo = $this->doctrine->getRepository(Job::class);
        $jobs = $repo->findBy([], ['id' => 'DESC'], 250);

        return ['jobs' => array_map([$this, 'prepareJob'], $jobs)];
    }

    #[Route('/{id}', name: 'bdoh_job_show', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('JOB_SHOW', job)"))]
    #[Template('@IrsteaBdohData/Job/show.html.twig')]
    public function showAction(Job $job)
    {
        return ['job' => $this->prepareJob($job)];
    }

    /**
     * @param Request $request
     * @param Job     $job
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    #[Route('/{id}', name: 'bdoh_job_update', methods: ['POST'])]
    public function updateAction(Request $request, Job $job)
    {
        $action = $request->request->get('action');
        switch ($action) {
            case 'cancel':
                if (!$this->authChecker->isGranted(JobVoter::CANCEL_PERM, $job)) {
                    throw new AccessDeniedHttpException();
                }
                $this->jobManager->cancel($job);
                break;

            case 'retry':
                if (!$this->authChecker->isGranted(JobVoter::RETRY_PERM, $job)) {
                    throw new AccessDeniedHttpException();
                }
                $job = $this->jobManager->retry($job);
                break;

            default:
                throw new BadRequestHttpException(sprintf('Unknown action %s', $action));
        }

        return $this->redirectToRoute('bdoh_job_show', ['id' => $job->getId()]);
    }

    /**
     * @param Job $job
     *
     * @return array
     */
    public function prepareJob(Job $job)
    {
        $started = !$job->isNew() && !$job->isPending() && !$job->isCanceled();
        $ran = $started && $job->isInFinalState();

        if ($job->isPending() && $job->isStartable() && $job->getExecuteAfter() < new \DateTime()) {
            $state = 'ready';
        } else {
            $state = $job->getState();
        }

        return [
            'entity'         => $job,
            'owner'          => $job->findRelatedEntity(Utilisateur::class),
            'observatoire'   => $job->findRelatedEntity(Observatoire::class),
            'chroniques'     => $job->getRelatedEntities()->filter(
                function ($c) {
                    return $c instanceof Chronique;
                }
            ),
            'state'          => $state,
            'started'        => $started,
            'ran'            => $ran,
            'final'          => $job->isInFinalState(),
            'showable'       => $this->authChecker->isGranted(JobVoter::SHOW_PERM, $job),
            'cancelable'     => $this->jobManager->canCancel($job),
            'cancel_granted' => $this->authChecker->isGranted(JobVoter::CANCEL_PERM, $job),
            'retriable'      => $this->jobManager->canRetry($job),
            'retry_granted'  => $this->authChecker->isGranted(JobVoter::RETRY_PERM, $job),
        ];
    }
}
