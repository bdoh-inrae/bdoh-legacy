<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Doctrine\Common\Collections\Collection;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\PasEchantillonnage;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
        AuthorizationCheckerInterface $authChecker
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
        }

        /** @var \DateTime $beginDate */
        $beginDate = $params['beginDate'];

        /** @var \DateTime $endDate */
        $endDate = $params['endDate'];

        $timezone = new \DateTimeZone(sprintf('%+03d00', $params['timezone']));

        switch ($params['exportType']) {
            case 'instantaneous':
                $timestep = $params['instantStep'];
                break;
            case 'cumulative':
            case 'mean':
                $timestep = $params['meanCumulStep']->getId();
                break;
            default:
                $timestep = null;
        }

        $job = $this->jobManager->enqueueExport(
            $this->getUser(),
            $params['exportType'],
            $params['exportFormat'],
            $beginDate,
            $endDate,
            $chroniques->toArray(),
            $timestep,
            $timezone,
            $this->isLocaleEn() ? 'en' : 'fr'
        );

        $this->reportJobToUser($request, $job);

        return $this->redirectToReferer();
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
                        'Bdoh'      => 'Bdoh',
                        'Qtvar'     => 'Qtvar',
                        'Vigilance' => 'Vigilance',
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
            ->add(
                'termsOfUses',
                CheckboxType::class,
                ['constraints' => [new NotBlank()]]
            )
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
