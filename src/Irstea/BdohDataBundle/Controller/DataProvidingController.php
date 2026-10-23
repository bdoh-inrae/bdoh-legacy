<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Controller;

use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Form\Type\UTCDateTimeType;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Entity\Repository\QualiteRepository;
use Irstea\BdohSecurityBundle\Exception\ForbiddenException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * This controller has actions returning data sets,
 * for chart visualization, displaying of measures table...
 *
 * Usually, these actions return JSON data, for Javascript processing.
 */
class DataProvidingController extends Controller
{
    /**
     * Returns all measures :
     *      -> in json format ;
     *      -> for a given 'chronique'
     *      -> and beetwen 2 dates.
     *
     * @param Request $request
     * @param $id
     *
     * @return Response
     * @throws \Exception
     *
     */
    public function getChroniqueMeasuresAction(Request $request, $id)
    {
        $request->setRequestFormat('json');
        $params = $this->parseQuery($request);
        $data = $this->fetchViewerData(
            (int)$id,
            $params['beginDate'],
            $params['endDate']
        );

        return $this->renderJson($data);
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    private function parseQuery(Request $request)
    {
        $form = $this->createQueryForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new BadRequestHttpException($this->createErrorMessage($form));
        }

        return $form->getData();
    }

    /**
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createQueryForm()
    {
        return $this->container->get('form.factory')
            ->createNamedBuilder('', FormType::class, null, ['csrf_protection' => false])
            ->setMethod('GET')
            ->add('beginDate', UTCDateTimeType::class, ['constraints' => [new NotBlank()]])
            ->add('endDate', UTCDateTimeType::class, ['constraints' => [new NotBlank()]])
            ->getForm();
    }

    /**
     * @param FormInterface $form
     *
     * @return Response
     */
    private function createErrorMessage($form)
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $property = $error->getOrigin()->getName();
            $value = json_encode($error->getOrigin()->getViewData());
            $message = $error->getMessage();
            $errors[] = "$property: $message ($value)";
        }

        return implode("\n", $errors);
    }

    /**
     * @param $ids
     * @param \DateTime $beginDate
     * @param \DateTime $endDate
     *
     * @return mixed
     * @throws \Exception
     *
     */
    private function fetchViewerData($ids, \DateTime $beginDate, \DateTime $endDate)
    {
        $authChecker = $this->get('security.authorization_checker');

        /** @var ChroniqueRepository $repository */
        $repository = $this->getBdohRepo('Chronique');

        $chroniques = $repository->findById(\explode('_', $ids));
        if (empty($chroniques)) {
            throw new NotFoundHttpException();
        }

        $chroniquesAndRights = [];
        foreach ($chroniques as $chronique) {
            if (!$this->isGranted('CONSULT_TIMESERIES', $chronique)) {
                throw new ForbiddenException();
            }
            $chroniquesAndRights[] = [$chronique, $authChecker->isGranted('CONSULT_CHECKPOINTS', $chronique)];
        }

        $maxPoints = $this->getParameter('irstea_bdoh_data.viewers.max_valid_display_points');

        $data = $repository->getMultipleChroniquesViewerData(
            $chroniquesAndRights,
            $beginDate,
            $endDate,
            $maxPoints
        );

        // construction des données des qualités (style, label, showLabel)
        /** @var QualiteRepository $qualiteRepo */
        $qualiteRepo = $this->getBdohRepo('Qualite');
        $isEnglish = \strpos($this->getTranslator()->getLocale(), 'en') === 0;
        $qualites = [];
        foreach ($data['chronicles'] as &$chroniquesData) {
            foreach ($chroniquesData['series'] as &$serie) {
                if (isset($serie['label'])) {
                    $serie['label'] = $this->getTranslator()->trans($serie['label']);
                } elseif (isset($serie['qualiteId'])) {
                    $qid = $serie['qualiteId'];
                    if (!isset($qualites[$qid])) {
                        $qualites[$qid] = $qualiteRepo->findOneById($qid);
                    }
                    $serie['style'] = $qualites[$qid]->getStyle();
                    if (isset($chroniquesData['discontinuousStyle'])) {
                        $serie['style'] = \array_merge($serie['style'], $chroniquesData['discontinuousStyle']);
                    }
                    $serie['label'] = $qualites[$qid]->getLibelle($isEnglish ? 'en' : 'fr');
                    $serie['showLabel'] = true;
                }
            }
        }
        $data = $this->setLowerValueToMissing($data);
        return $data;
    }

    // Set values of non valide series to lowest of others to avoid scaling problems in the graph.
    protected function setLowerValueToMissing(array $data): array
    {
        $lowerValue = $this->getLowerValue($data);
//  Ici on s'attend à ce que les séries invalid/gap aient déjà une valeur de 0,
//  mais ce n'est pas toujours le cas. Quickfix en attendant de trouver l'origine du probléme
//  (import des données ?).
//        if (0.0 === $lowerValue) {
//
//            return $data;
//        }
        foreach ($data['chronicles'] as &$chronicle) {
            foreach ($chronicle['series'] as &$serie) {
                if (in_array($serie['seriesFlag'], ['invalid', 'gap'])) {
                    foreach ($serie['values'] as &$value) {
                        $value[1] = $lowerValue;
                    }
                }
            }
        }

        return $data;
    }

    // Find lowest value in valid series.
    protected function getLowerValue(array $data): ?float
    {
        $lowerValue = null;
        foreach ($data['chronicles'] as $chronicle) {
            foreach ($chronicle['series'] as $serie) {
                if (!in_array($serie['seriesFlag'], ['invalid', 'gap'])) {
                    foreach ($serie['values'] as $value) {
                        if (is_numeric($value[1])) {
                            if (null === $lowerValue) {
                                $lowerValue = $value[1];
                            } else if ($value[1] < $lowerValue) {
                                $lowerValue = $value[1];
                            }
                            // S'il y a des valeurs négatives, ce n'est pas dérangeant que le gap soit fixé à 0.
                            if ($lowerValue < 0) {
                                return 0.0;
                            }
                        }

                    }
                }
            }
        }
        if (null === $lowerValue) {

            return null;
        }

        return $this->graphFloor($lowerValue);
    }

    protected function graphFloor(float $value): float
    {
        $digitToFloor = strlen((string)(int)$value) - 1;
        if ($digitToFloor <= 0) {
            $digitToFloor = 1;
        }

        return (floor($value / (10 ** $digitToFloor)) * 10 ** $digitToFloor);
    }
}
