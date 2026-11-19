<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Doctrine\Common\Collections\Collection;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\PasEchantillonnage;
use Irstea\BdohDataBundle\Exporter\Measure\ExportFiles;
use Irstea\BdohBundle\Job\Job;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Realizes the following exports :
 *      => Export of measures for one or several 'chroniques'.
 */
class ExportController extends Controller
{
    /**
     * Sans compte, ce qui peut attendre dans la file des exports : un seul visiteur ne doit pas la saturer pour tous.
     */
    private const MAX_WAITING_WITHOUT_ACCOUNT = 30;

    /**
     * @var JobManagerInterface
     */
    public $jobManager;

    /**
     * @var FormFactoryInterface
     */
    public $formFactory;

    /**
     * @var AuthorizationCheckerInterface
     */
    public $authChecker;

    public function __construct(
        JobManagerInterface $jobManager,
        FormFactoryInterface $formFactory,
        AuthorizationCheckerInterface $authChecker,
        private ExportFiles $exportFiles,
        private RateLimiterFactoryInterface $exportIpLimiter,
        /*
         * Le volume d'une demande, en valeurs écrites, quels que soient son type et le nombre de ses chroniques, et ses
         * deux seuils (Lou, 2026-11-16 et 19 ; dev/plans/2026-11-16_reopening.md, point 2b) : au-delà du premier, un
         * compte est demandé ; au-delà du second, la demande est refusée, pour qu'elle ne retienne pas la file ni ne
         * remplisse le dossier temporaire, où les fichiers s'écrivent avant l'archive. Les valeurs lues (export à
         * l'identique, valeurs interpolées) s'écrivent à 90 000 par seconde sur le poste, et pèsent 56 octets en CSV ;
         * les valeurs calculées (moyenne, cumul) à 640 par seconde, d'où un second jeu de seuils. Ils se règlent dans
         * l'environnement de la machine (.env.example), par défaut ceux de services.yml ; le second des valeurs
         * calculées reste au-dessus de ce que l'ancien acceptait, 1 157 112 (dev/decisions.md, "Ouverture des données").
         */
        #[Autowire('%env(int:EXPORT_READ_WITHOUT_ACCOUNT)%')] private int $readWithoutAccount,
        #[Autowire('%env(int:EXPORT_READ_MAX)%')] private int $readMax,
        #[Autowire('%env(int:EXPORT_COMPUTED_WITHOUT_ACCOUNT)%')] private int $computedWithoutAccount,
        #[Autowire('%env(int:EXPORT_COMPUTED_MAX)%')] private int $computedMax
    ) {
        $this->jobManager = $jobManager;
        $this->formFactory = $formFactory;
        $this->authChecker = $authChecker;
    }

    /**
     * @param Request $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @throws \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @throws \Symfony\Component\Validator\Exception\InvalidOptionsException
     * @throws \Symfony\Component\Validator\Exception\MissingOptionsException
     * @throws \LogicException
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     *
     * @return Response
     */
    #[Route('/mesure/export', name: 'bdoh_data_measure_export', methods: ['POST'])]
    public function chroniquesMeasuresAction(Request $request)
    {
        $form = $this->createExportForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->reportFormErrors($form);
        }

        $params = $form->getData();

        /** @var Collection $chroniques */
        $chroniques = $params['chroniques'];

        foreach ($chroniques as $chronique) {
            if (!$this->authChecker->isGranted('DOWNLOAD', $chronique)) {
                throw new AccessDeniedHttpException();
            }
            // Une chronique cachée garde ses conditions d'utilisation ; le reste est sous Licence Ouverte
            if (!$chronique->getEstVisible() && !$params['termsOfUses']) {
                $form->get('termsOfUses')->addError(new FormError(
                    $this->getTranslator()->trans('This value should not be blank.', [], 'validators')
                ));

                return $this->reportFormErrors($form);
            }
        }

        /** @var \DateTime $beginDate */
        $beginDate = $params['beginDate'];

        /** @var \DateTime $endDate */
        $endDate = $params['endDate'];

        switch ($params['exportType']) {
            case 'instantaneous':
                $timestep = $params['instantStep'];
                break;
            case 'cumulative':
            case 'mean':
                if ($params['meanCumulStep'] === null) {
                    $form->get('meanCumulStep')->addError(new FormError(
                        $this->getTranslator()->trans('This value should not be blank.', [], 'validators')
                    ));

                    return $this->reportFormErrors($form);
                }
                $timestep = $params['meanCumulStep']->getId();
                break;
            default:
                $timestep = null;
        }

        $user = $this->getUser();
        if ($user === null && !$this->exportIpLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->refuse($request, 'measure.export.tooMany');
        }
        $kind = \in_array($params['exportType'], ['mean', 'cumulative'], true) ? 'computed' : 'read';
        [$withoutAccount, $atMost] = $kind === 'computed'
            ? [$this->computedWithoutAccount, $this->computedMax]
            : [$this->readWithoutAccount, $this->readMax];
        $limit = $user === null ? $withoutAccount : $atMost;
        $volume = $this->volume($params, $chroniques->toArray(), $beginDate, $endDate, $limit);
        if ($volume > $limit) {
            // Au-delà du second seuil, le refus vaut avec ou sans compte : c'est lui que dit le message
            return $this->refuse($request, $volume > $atMost ? 'measure.export.tooLarge' : 'measure.export.tooHeavy', [
                '%volume%' => number_format($volume, 0, ',', ' '),
                '%max%'    => number_format($volume > $atMost ? $atMost : $limit, 0, ',', ' '),
                '%what%'   => $this->getTranslator()->trans('measure.export.values.' . $kind),
            ]);
        }
        if ($user === null
            && $this->getEm()->getRepository(Job::class)->countWaiting('job_queue_export') >= self::MAX_WAITING_WITHOUT_ACCOUNT) {
            return $this->refuse($request, 'measure.export.busy');
        }

        $timezone = new \DateTimeZone(sprintf('%+03d00', $params['timezone']));

        $token = $this->exportFiles->newToken();
        $job = $this->jobManager->enqueueExport(
            $user,
            $params['exportType'],
            $params['exportFormat'],
            $beginDate,
            $endDate,
            $chroniques->toArray(),
            $this->exportFiles->path($token),
            $timestep,
            $timezone,
            $this->isLocaleEn() ? 'en' : 'fr'
        );

        // Un visiteur sans compte n'a ni courriel ni page des tâches : il suit son export par ce lien
        if ($user === null) {
            return $this->redirectToRoute('bdoh_data_measure_export_status', ['id' => $job->getId(), 'token' => $token]);
        }

        $this->reportJobToUser($request, $job);

        return $this->back($request);
    }

    /**
     * L'état d'un export, par son lien.
     */
    #[Route('/mesure/export/{id}/{token}', name: 'bdoh_data_measure_export_status', requirements: ['id' => '\\d{1,18}', 'token' => '[0-9a-f]{32}'], methods: ['GET'])]
    public function statusAction(int $id, string $token): Response
    {
        $job = $this->findExportJob($id, $token);

        if ($job->isFinished()) {
            $state = $this->exportFiles->isAvailable($token) ? 'finished' : 'expired';
        } elseif ($job->isRunning()) {
            $state = 'running';
        } elseif ($job->isPending() || $job->isNew()) {
            $state = 'waiting';
        } else {
            $state = 'failed';
        }

        $response = $this->render('@IrsteaBdohConsult/Export/status.html.twig', [
            'id'       => $id,
            'token'    => $token,
            'state'    => $state,
            'keepDays' => ExportFiles::KEEP_DAYS,
        ]);
        if ($state === 'running' || $state === 'waiting') {
            $response->headers->set('Refresh', '10');
        }

        return $response;
    }

    /**
     * L'archive d'un export. Le lien peut circuler : une chronique cachée redemande son droit à qui l'ouvre.
     */
    #[Route('/mesure/export/{id}/{token}/file', name: 'bdoh_data_measure_export_file', requirements: ['id' => '\\d{1,18}', 'token' => '[0-9a-f]{32}'], methods: ['GET'])]
    public function fileAction(int $id, string $token): Response
    {
        $job = $this->findExportJob($id, $token);

        // D'après les arguments de la tâche : une chronique supprimée depuis ne se retrouve plus parmi ses entités liées
        $chroniques = [];
        foreach ($this->chroniqueIdsOf($job) as $chroniqueId) {
            $chronique = $this->getEm()->find(Chronique::class, $chroniqueId);
            if ($chronique === null || !$this->authChecker->isGranted('DOWNLOAD', $chronique)) {
                throw $this->createAccessDeniedException();
            }
            $chroniques[] = $chronique;
        }

        if (!$job->isFinished() || !$this->exportFiles->isAvailable($token)) {
            throw new NotFoundHttpException();
        }

        return (new BinaryFileResponse($this->exportFiles->path($token)))
            ->setContentDisposition('attachment', $this->archiveName($job, $chroniques));
    }

    /**
     * Le nom de l'archive dit ce qu'elle contient (dev/plans/2026-11-13_fair-exports.md ; forme revue avec Lou le
     * 2026-11-16, dev/plans/2026-11-16_before-kit.md, « Le nom de l'archive ») : des champs « clé-valeur » séparés par
     * « _ », la clé étant ce qui précède le premier « - » ; dans une valeur, rien que des lettres, des chiffres et « - »
     * (ni point, qui se lit comme une extension, ni « + », qui devient une espace dans une adresse) ; des dates
     * compactes (ISO 8601 de base). La station et la chronique en deux champs ; « n-12 » pour plusieurs chroniques.
     * Le nom n'a qu'à être lisible et sûr : les codes exacts sont dans le README. Par exemple
     * BDOH-export_obs-DRAIX_sta-LAVAL_chr-Q_type-identical_format-csv_period-20200101-20241231_on-20261116.zip.
     *
     * @param Chronique[] $chroniques
     */
    private function archiveName(Job $job, array $chroniques): string
    {
        $safe = fn (string $text) => trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-') ?: 'x';
        $type = substr($job->getCommand(), strlen('bdoh:export:'));
        $positional = [];
        $options = [];
        $args = array_map('strval', $job->getArgs());
        foreach ($args as $k => $argument) {
            if (str_starts_with($argument, '--')) {
                $options[substr($argument, 2)] = $args[$k + 1] ?? '';
            } elseif (!$options) {
                $positional[] = $argument;
            }
        }
        $step = null;
        if ($type !== 'identical' && $positional) {
            $value = array_shift($positional);
            if ($type === 'instantaneous') {
                $step = (int) $value . 'min';
            } elseif (($pas = $this->getEm()->find(PasEchantillonnage::class, (int) $value)) !== null) {
                $step = $pas->getUnite() === 'event' ? 'event' : $pas->getValeur() . $pas->getUnite();
            }
        }
        $date = fn (?string $value) => $value ? (new \DateTime($value))->format('Ymd') : 'x';
        $parts = ['BDOH-export', 'obs-' . $safe($chroniques ? $chroniques[0]->getObservatoire()->getSlug() : 'x')];
        if (count($chroniques) === 1) {
            $parts[] = 'sta-' . $safe($chroniques[0]->getStation()->getCode());
            $parts[] = 'chr-' . $safe($chroniques[0]->getCode());
        } else {
            $parts[] = 'n-' . count($chroniques);
        }
        $parts[] = 'type-' . $safe($type);
        if ($step !== null) {
            $parts[] = 'step-' . $safe($step);
        }
        $parts[] = 'format-' . $safe($options['format'] ?? 'bdoh');
        $parts[] = 'period-' . $date($positional[0] ?? null) . '-' . $date($positional[1] ?? null);
        $parts[] = 'on-' . ($job->getClosedAt() ?? $job->getCreatedAt() ?? new \DateTime())->format('Ymd');

        return implode('_', $parts) . '.zip';
    }

    /**
     * Les chroniques d'une tâche d'export : ses arguments valent [pas de temps,] début, fin, chroniques…, options.
     *
     * @return int[]
     */
    private function chroniqueIdsOf(Job $job): array
    {
        $ids = [];
        $dates = 0;
        foreach ($job->getArgs() as $argument) {
            $argument = (string) $argument;
            if (str_starts_with($argument, '--')) {
                break;
            }
            if ($dates === 2) {
                $ids[] = (int) $argument;
            } elseif (!ctype_digit($argument)) {
                ++$dates;
            }
        }

        return $ids;
    }

    /**
     * La tâche d'un export, si le jeton est bien le sien.
     */
    private function findExportJob(int $id, string $token): Job
    {
        $job = $this->getEm()->find(Job::class, $id);
        if ($job === null || !\in_array($this->exportFiles->path($token), $job->getArgs(), true)) {
            throw new NotFoundHttpException();
        }

        return $job;
    }

    /**
     * Le volume d'une demande : les valeurs qu'elle écrirait, toutes chroniques réunies. À l'identique, les mesures de
     * la période ; en valeurs interpolées, les pas de la période demandée, que l'export écrit tous, lacunes comprises ;
     * en moyenne ou en cumul, les pas de la période où la chronique a des mesures, une seule valeur pour le pas
     * « événement ».
     *
     * @param Chronique[] $chroniques
     * @param int         $limit      le seuil à comparer : à l'identique, les mesures ne se comptent en base que si
     *                                toutes celles des chroniques le dépassent
     */
    private function volume(array $params, array $chroniques, \DateTime $beginDate, \DateTime $endDate, int $limit): int
    {
        $steps = fn (int $seconds, int $step) => $seconds < 0 ? 0 : intdiv($seconds, $step) + 1;
        switch ($params['exportType']) {
            case 'instantaneous':
                return \count($chroniques)
                    * $steps($endDate->getTimestamp() - $beginDate->getTimestamp(), 60 * max(1, (int) $params['instantStep']));
            case 'mean':
            case 'cumulative':
                /** @var PasEchantillonnage $step */
                $step = $params['meanCumulStep'];
                $utc = new \DateTimeZone('UTC');
                $total = 0;
                foreach ($chroniques as $chronique) {
                    if (!$chronique->getDateDebutMesures() || !$chronique->getDateFinMesures()) {
                        continue;
                    }
                    $first = max($beginDate->getTimestamp(), (new \DateTime($chronique->getDateDebutMesures(), $utc))->getTimestamp());
                    $last = min($endDate->getTimestamp(), (new \DateTime($chronique->getDateFinMesures(), $utc))->getTimestamp());
                    if ($last >= $first) {
                        $total += $step->getUnite() === 'event' ? 1 : $steps($last - $first, max(1, $step->getApproxSecondes()));
                    }
                }

                return $total;
            default:
                $total = 0;
                foreach ($chroniques as $chronique) {
                    $total += (int) $chronique->getNbMesures();
                }

                return $total > $limit ? $this->getBdohRepo('Chronique')->countMeasuresBetween($chroniques, $beginDate, $endDate) : $total;
        }
    }

    private function refuse(Request $request, string $message, array $parameters = []): RedirectResponse
    {
        /** @var Session $session */
        $session = $request->getSession();
        $session->getFlashBag()->add('error', $this->getTranslator()->trans($message, $parameters));

        return $this->back($request);
    }

    private function back(Request $request): RedirectResponse
    {
        return new RedirectResponse($request->headers->get('referer') ?: $this->generateUrl('bdoh_home'));
    }

    /**
     * @throws \Symfony\Component\Validator\Exception\MissingOptionsException
     * @throws \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @throws \Symfony\Component\Validator\Exception\InvalidOptionsException
     * @throws \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     *
     * @return FormInterface
     */
    private function createExportForm()
    {
        $dateOptions = [
            'widget'         => 'single_text',
            'format'         => DateTimeType::HTML5_FORMAT,
            'model_timezone' => 'UTC',
            'view_timezone'  => 'UTC',
            'constraints'    => [new NotBlank()],
        ];

        return $this->formFactory
            ->createNamedBuilder('', FormType::class, null, ['csrf_protection' => false])
            ->add('beginDate', DateTimeType::class, $dateOptions)
            ->add('endDate', DateTimeType::class, $dateOptions)
            ->add(
                'exportFormat',
                ChoiceType::class,
                [
                    'choices'     => [
                        'Bdoh'  => 'Bdoh',
                        'Qtvar' => 'Qtvar',
                        'Csv'   => 'Csv',
                    ],
                    'constraints' => [new NotBlank()],
                ]
            )
            ->add(
                'exportType',
                ChoiceType::class,
                [
                    'choices'     => [
                        'identical'     => 'identical',
                        'instantaneous' => 'instantaneous',
                        'mean'          => 'mean',
                        'cumulative'    => 'cumulative',
                    ],
                    'constraints' => [new NotBlank()],
                ]
            )
            ->add(
                'instantStep',
                IntegerType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThan(0),
                        new LessThanOrEqual(1440),
                    ],
                ]
            )
            ->add(
                'meanCumulStep',
                EntityType::class,
                ['class' => PasEchantillonnage::class]
            )
            ->add(
                'timezone',
                IntegerType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual(-12),
                        new LessThanOrEqual(12),
                    ],
                ]
            )
            ->add(
                'chroniques',
                EntityType::class,
                [
                    'class'       => Chronique::class,
                    'multiple'    => true,
                    'constraints' => [new Count(['min' => 1])],
                ]
            )
            ->add('termsOfUses', CheckboxType::class, ['required' => false])
            ->getForm();
    }

    /**
     * @param FormInterface $form
     *
     * @return JsonResponse
     */
    private function reportFormErrors(FormInterface $form)
    {
        $msg = [
            'status'  => Response::HTTP_BAD_REQUEST,
            'message' => 'Bad request',
            'details' => [],
        ];

        foreach ($form->getErrors(true) as $error) {
            /** @var FormError $error */
            $path = $error->getOrigin()->getPropertyPath();
            $msg['details'][(string) $path] = [
                'message' => $error->getMessage(),
                'value'   => $error->getOrigin()->getViewData(),
            ];
        }

        return new JsonResponse($msg, $msg['status']);
    }

    /**
     * @param Request $request
     * @param Job     $job
     */
    private function reportJobToUser(Request $request, Job $job)
    {
        /** @var Session $session */
        $session = $request->getSession();
        $session->getFlashBag()->add(
            'info',
            $this->getTranslator()->trans('measure.export.job_queued(%job_id%)', ['%job_id%' => $job->getId()])
        );
    }
}
