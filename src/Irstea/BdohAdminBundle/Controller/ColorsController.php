<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Controller;

use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohDataBundle\Entity\JeuQualite;
use Irstea\BdohDataBundle\Entity\Qualite;
use Irstea\BdohDataBundle\Entity\Repository\JeuQualiteRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ColorsController.
 */
#[IsGranted(new Expression("is_granted('ROLE_ADMIN') and user.isGestionnaireDesObservatoires()"))]
class ColorsController extends Controller
{
    const CSRF_TOKEN_TIME_VALIDITY = 7200; // 2 heures (en secondes)

    const MIN_THICKNESS = 1;
    const MAX_THICKNESS = 9;
    const SHAPES = [
        'diamond'       => '◊',
        'circle'        => '○',
        'square'        => '□',
        'x'             => '×',
        'plus'          => '+',
        'dash'          => '−',
        'filledDiamond' => '⧫',
        'filledCircle'  => '●',
        'filledSquare'  => '■',
    ];
    const MIN_STROKE = 1;
    const MAX_STROKE = 9;
    const MIN_SIZE = 1;
    const MAX_SIZE = 50;

    const HEX_COLOR_RE = '/^#[0-9A-F]{6}$/';

    /**
     * @param array $rgbArray
     *
     * @return string;
     */
    private function rgbArrayToHex(array $rgbArray)
    {
        if (!\is_array($rgbArray) || \count($rgbArray) !== 3) {
            return '#000000';
        }

        $hex = '#';
        foreach ($rgbArray as $color) {
            $colorInt = \min(255, \max(0, (int) $color));
            $hex .= $colorInt < 16 ? '0' : '';
            $hex .= strtoupper(\dechex($colorInt));
        }

        return $hex;
    }

    /**
     * @param string $hex
     *
     * @return array;
     */
    private function hexToRgbArray(string $hex)
    {
        if (!\is_string($hex) || \strlen($hex) !== 7 || $hex[0] !== '#') {
            return [0, 0, 0];
        }

        $rgbArray = [];
        $rgbArray[] = \min(255, \max(0, (int) \hexdec(substr($hex, 1, 2))));
        $rgbArray[] = \min(255, \max(0, (int) \hexdec(substr($hex, 3, 2))));
        $rgbArray[] = \min(255, \max(0, (int) \hexdec(substr($hex, 5, 2))));

        return $rgbArray;
    }

    /**
     * colorsAction.
     *
     * @param Request $request
     * @param string  $jeuQualiteId
     *
     * @throws \Exception
     *
     * @return array
     */
    #[Route('/colors/{jeuQualiteId}', name: 'bdoh_admin_colors', requirements: ['jeuQualiteId' => '\d+'],
        defaults: ['jeuQualiteId' => ''], methods: ['GET'])]
    #[Template('@IrsteaBdohAdmin/Colors/colors.html.twig')]
    public function colorsAction(Request $request, $jeuQualiteId)
    {
        $response = [];

        /** @var Session $session */
        $session = $request->getSession();

        // token pour la protection CSRF
        if (!$session->has('colors.CSRFTokenTime') ||
            ((int) $session->get('colors.CSRFTokenTime', 0) +
                self::CSRF_TOKEN_TIME_VALIDITY) <= (int) \date('U')) {
            $CSRFTokenName = 'CSRFToken' . \bin2hex(\random_bytes(8));
            $CSRFTokenValue = \bin2hex(\random_bytes(64));
            $session->set('colors.CSRFTokenName', $CSRFTokenName);
            $session->set('colors.CSRFTokenValue', $CSRFTokenValue);
            $session->set('colors.CSRFTokenTime', \date('U'));
        } else {
            $CSRFTokenName = $session->get('colors.CSRFTokenName');
            $CSRFTokenValue = $session->get('colors.CSRFTokenValue');
        }
        $response['CSRFTokenName'] = $CSRFTokenName;
        $response['CSRFTokenValue'] = $CSRFTokenValue;

        // construction de la liste des jeux de qualités affectables
        /** @var JeuQualiteRepository $jeuQualiteRepo */
        $jeuQualiteRepo = $this->getBdohRepo('JeuQualite');
        $jeux = $jeuQualiteRepo->findAll();
        $response['jeux'] = $jeux;

        // récupération du jeu de qualité si fourni via le formulaire (en parametre de l'url)
        if (ctype_digit($jeuQualiteId) && (int) $jeuQualiteId >= 1) {
            /** @var JeuQualite $jeuQualite */
            $jeuQualite = $jeuQualiteRepo->find($jeuQualiteId);

            // vérification de l'existance du jeu de qualité et qu'il est affectable
            if (!$jeuQualite || !$jeuQualite->getEstAffectable()) {
                throw new AccessDeniedHttpException();
            }

            // construction du tableau des données de style pour les qualités du jeu
            $qualitesData = [];
            $isEnglish = \strpos($this->getTranslator()->getLocale(), 'en') === 0;
            /** @var Qualite[] $qualites */
            $qualites = $jeuQualite->getQualites();
            foreach ($qualites as $qualite) {
                if ($qualite->getCode() !== Qualite::GAP_CODE) {
                    $id = $qualite->getId();
                    $isGap = $qualite->isInvalide();
                    $style = $qualite->getStyle();
                    $color = $this->rgbArrayToHex($isGap ? Qualite::DEFAULT_GAP_COLOR : Qualite::DEFAULT_COLOR);
                    if (\is_array($style) && isset($style['color']) && \is_array($style['color']) && \count($style['color']) === 3) {
                        $color = $this->rgbArrayToHex($style['color']);
                    }
                    $thickness = $isGap ? Qualite::DEFAULT_GAP_THICKNESS : Qualite::DEFAULT_THICKNESS;
                    if (\is_array($style) && isset($style['thickness'])) {
                        $thickness = \min(self::MAX_THICKNESS, \max(self::MIN_THICKNESS, (int) $style['thickness']));
                    }
                    $qualitesData[$id] = [
                        'id'        => $id,
                        'label'     => $qualite->getLibelle($isEnglish ? 'en' : 'fr'),
                        'code'      => $qualite->getCode(),
                        'color'     => $color,
                        'thickness' => $thickness,
                        'gap'       => $isGap,
                    ];
                }
            }
            \ksort($qualitesData);

            // construction des données de style pour les marqueurs des bornes des plages des discontinues
            $discontinuousData = [
                'shape'  => JeuQualite::DEFAULT_DISCONTINUOUS_SHAPE,
                'stroke' => JeuQualite::DEFAULT_DISCONTINUOUS_STROKE,
                'size'   => JeuQualite::DEFAULT_DISCONTINUOUS_SIZE,
            ];
            $discontinuousStyle = $jeuQualite->getDiscontinuousStyle();
            if (\is_array($discontinuousStyle)) {
                if (isset($discontinuousStyle['shape']) && \in_array($discontinuousStyle['shape'], \array_keys($this::SHAPES))) {
                    $discontinuousData['shape'] = $discontinuousStyle['shape'];
                }
                if (isset($discontinuousStyle['stroke'])) {
                    $discontinuousData['stroke'] = \min(self::MAX_STROKE, \max(self::MIN_STROKE, (int) $discontinuousStyle['stroke']));
                }
                if (isset($discontinuousStyle['size'])) {
                    $discontinuousData['size'] = \min(self::MAX_SIZE, \max(self::MIN_SIZE, (int) $discontinuousStyle['size']));
                }
            }

            // construction des données de style pour les points de contrôle
            $checkpointData = [
                'shape'  => JeuQualite::DEFAULT_CHECKPOINT_SHAPE,
                'stroke' => JeuQualite::DEFAULT_CHECKPOINT_STROKE,
                'color'  => $this->rgbArrayToHex(JeuQualite::DEFAULT_CHECKPOINT_COLOR),
                'size'   => JeuQualite::DEFAULT_CHECKPOINT_SIZE,
            ];
            $checkpointStyle = $jeuQualite->getCheckpointStyle();
            if (\is_array($checkpointStyle)) {
                if (isset($checkpointStyle['shape']) && \in_array($checkpointStyle['shape'], \array_keys($this::SHAPES))) {
                    $checkpointData['shape'] = $checkpointStyle['shape'];
                }
                if (isset($checkpointStyle['stroke'])) {
                    $checkpointData['stroke'] = \min(self::MAX_STROKE, \max(self::MIN_STROKE, (int) $checkpointStyle['stroke']));
                }
                if (isset($checkpointStyle['color']) && \is_array($checkpointStyle['color']) && \count($checkpointStyle['color']) === 3) {
                    $checkpointData['color'] = $this->rgbArrayToHex($checkpointStyle['color']);
                }
                if (isset($checkpointStyle['size'])) {
                    $checkpointData['size'] = \min(self::MAX_SIZE, \max(self::MIN_SIZE, (int) $checkpointStyle['size']));
                }
            }

            $response['jeuQualite'] = $jeuQualite;
            $response['qualitesData'] = $qualitesData;
            $response['discontinuousData'] = $discontinuousData;
            $response['checkpointData'] = $checkpointData;
            $response['thicknesses'] = \range(self::MIN_THICKNESS, self::MAX_THICKNESS);
            $response['shapes'] = self::SHAPES;
            $response['strokes'] = \range(self::MIN_STROKE, self::MAX_STROKE);
            $response['sizes'] = \range(self::MIN_SIZE, self::MAX_SIZE);
        } // pas de jeu de qualité fourni

        return $response;
    }

    /**
     * colorsFormAction.
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    #[Route('/colors', name: 'bdoh_admin_colors_form', methods: ['POST'])]
    public function colorsFormAction(Request $request)
    {
        $jeuQualiteIdParam = '';

        /** @var Session $session */
        $session = $request->getSession();

        if ($request->request->has('jeuQualiteId')
            && ctype_digit($request->request->get('jeuQualiteId'))) {
            $jeuQualiteId = $request->request->get('jeuQualiteId');

            /** @var JeuQualiteRepository $jeuQualiteRepo */
            $jeuQualiteRepo = $this->getBdohRepo('JeuQualite');
            /** @var JeuQualite $jeuQualite */
            $jeuQualite = $jeuQualiteRepo->find($jeuQualiteId);

            // vérification de l'existance du jeu de qualité et qu'il est affectable
            if (!$jeuQualite || !$jeuQualite->getEstAffectable()) {
                throw new AccessDeniedHttpException();
            }

            // enregistrement du jeu de qualité pour l'affichage du formulaire de selection des couleurs
            $jeuQualiteIdParam = $jeuQualite->getId();

            // si il ne s'agit pas d'un simple changement du jeu de qualité on analyse les paramètres du formulaire
            if (!$request->request->has('jeuQualiteChanged') ||
                $request->request->get('jeuQualiteChanged') !== 'jeuQualiteChanged') {
                // verification du token pour la protection CSRF
                $CSRFTokenNameInSession = $session->get('colors.CSRFTokenName', false);
                $CSRFTokenValueInSession = $session->get('colors.CSRFTokenValue', false);
                $CSRFTokenTimeInSession = $session->get('colors.CSRFTokenTime', 0);
                $CSRFTokenNameInRequest = $CSRFTokenNameInSession && $request->request->has($CSRFTokenNameInSession);
                $CSRFTokenValueInRequest = $request->request->get($CSRFTokenNameInSession, false);
                if ($CSRFTokenNameInRequest && $CSRFTokenValueInSession && $CSRFTokenTimeInSession &&
                    ((int) $CSRFTokenTimeInSession + self::CSRF_TOKEN_TIME_VALIDITY) >= (int) \date('U') &&
                    $CSRFTokenValueInSession === $CSRFTokenValueInRequest) {
                    // traitement du style des qualités
                    /** @var Qualite[] $qualites */
                    $qualites = $jeuQualite->getQualites();
                    foreach ($qualites as $qualite) {
                        if ($qualite->getCode() !== Qualite::GAP_CODE) {
                            $id = $qualite->getId();
                            $isGap = $qualite->isInvalide();
                            $style = $isGap ? Qualite::DEFAULT_GAP_STYLE : Qualite::DEFAULT_STYLE;
                            if ($request->request->has('colorId_' . $id)) {
                                $color = \strtoupper(\trim($request->request->get('colorId_' . $id)));
                                if (\preg_match(self::HEX_COLOR_RE, $color) === 1) {
                                    $style['color'] = $this->hexToRgbArray($color);
                                }
                            }
                            if ($request->request->has('thicknessId_' . $id)) {
                                $thickness = \trim($request->request->get('thicknessId_' . $id));
                                if (\ctype_digit($thickness)) {
                                    $thickness = (int) $thickness;
                                    if ($thickness >= self::MIN_THICKNESS && $thickness <= self::MAX_THICKNESS) {
                                        $style['thickness'] = $thickness;
                                    }
                                }
                            }
                            $qualite->setStyle($style);
                            $this->getEm()->persist($qualite);
                        }
                    }
                    // traitement du style des marqueurs des bornes des plages des discontinues
                    $discontinuousStyle = JeuQualite::DEFAULT_DISCONTINUOUS_STYLE;
                    $discontinuousId = $jeuQualite->getId();
                    if ($request->request->has('discontinuousShapeId_' . $discontinuousId)) {
                        $discontinuousShape = \trim($request->request->get('discontinuousShapeId_' . $discontinuousId));
                        if (\array_key_exists($discontinuousShape, self::SHAPES)) {
                            $discontinuousStyle['shape'] = $discontinuousShape;
                        }
                    }
                    if ($request->request->has('discontinuousStrokeId_' . $discontinuousId)) {
                        $discontinuousStroke = \trim($request->request->get('discontinuousStrokeId_' . $discontinuousId));
                        if (\ctype_digit($discontinuousStroke)) {
                            $discontinuousStroke = (int) $discontinuousStroke;
                            if ($discontinuousStroke >= self::MIN_STROKE && $discontinuousStroke <= self::MAX_STROKE) {
                                $discontinuousStyle['stroke'] = $discontinuousStroke;
                            }
                        }
                    }
                    if ($request->request->has('discontinuousSizeId_' . $discontinuousId)) {
                        $discontinuousSize = \trim($request->request->get('discontinuousSizeId_' . $discontinuousId));
                        if (\ctype_digit($discontinuousSize)) {
                            $discontinuousSize = (int) $discontinuousSize;
                            if ($discontinuousSize >= self::MIN_SIZE && $discontinuousSize <= self::MAX_SIZE) {
                                $discontinuousStyle['size'] = $discontinuousSize;
                            }
                        }
                    }
                    $jeuQualite->setDiscontinuousStyle($discontinuousStyle);
                    // traitement du style des points de contrôle
                    $checkpointStyle = JeuQualite::DEFAULT_CHECKPOINT_STYLE;
                    $checkpointId = $jeuQualite->getId();
                    if ($request->request->has('checkpointShapeId_' . $checkpointId)) {
                        $checkpointShape = \trim($request->request->get('checkpointShapeId_' . $checkpointId));
                        if (\array_key_exists($checkpointShape, self::SHAPES)) {
                            $checkpointStyle['shape'] = $checkpointShape;
                        }
                    }
                    if ($request->request->has('checkpointStrokeId_' . $checkpointId)) {
                        $checkpointStroke = \trim($request->request->get('checkpointStrokeId_' . $checkpointId));
                        if (\ctype_digit($checkpointStroke)) {
                            $checkpointStroke = (int) $checkpointStroke;
                            if ($checkpointStroke >= self::MIN_STROKE && $checkpointStroke <= self::MAX_STROKE) {
                                $checkpointStyle['stroke'] = $checkpointStroke;
                            }
                        }
                    }
                    if ($request->request->has('checkpointColorId_' . $checkpointId)) {
                        $checkpointColor = \strtoupper(\trim($request->request->get('checkpointColorId_' . $checkpointId)));
                        if (\preg_match(self::HEX_COLOR_RE, $checkpointColor) === 1) {
                            $checkpointStyle['color'] = $this->hexToRgbArray($checkpointColor);
                        }
                    }
                    if ($request->request->has('checkpointSizeId_' . $checkpointId)) {
                        $checkpointSize = \trim($request->request->get('checkpointSizeId_' . $checkpointId));
                        if (\ctype_digit($checkpointSize)) {
                            $checkpointSize = (int) $checkpointSize;
                            if ($checkpointSize >= self::MIN_SIZE && $checkpointSize <= self::MAX_SIZE) {
                                $checkpointStyle['size'] = $checkpointSize;
                            }
                        }
                    }
                    $jeuQualite->setCheckpointStyle($checkpointStyle);
                    $this->getEm()->persist($jeuQualite);
                    $this->getEm()->flush();
                    // le message s'affiche en HTML (toastr) : le nom, saisi dans l'administration, s'échappe
                    $session->getFlashBag()->add('success', $this->getTranslator()->trans('colors.success(%jeu%)', ['%jeu%' => htmlspecialchars($jeuQualite->getNom())]));
                } else {
                    // protection CSRF absente
                    $session->getFlashBag()->add('error', $this->getTranslator()->trans('colors.CSRFerror'));
                }
            } // pas une traitement juste un changement de jeu de qualité
        } // pas de jeu de qualité fourni

        return $this->redirectToRoute(
            'bdoh_admin_colors',
            ['jeuQualiteId' => $jeuQualiteIdParam]
        );
    }
}
