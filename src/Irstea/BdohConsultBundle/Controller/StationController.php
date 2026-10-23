<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohConsultBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Twig\Attribute\Template;
use Doctrine\ORM\ORMException;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Exception\NotFoundEntityException;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Entity\Repository\StationRepository;

/**
 * Class StationController.
 */
#[Route('/station/{station}', requirements: ['station' => '[A-Z0-9_-]+'])]
class StationController extends Controller
{
    /**
     * @param $station
     *
     * @throws \Exception
     *
     * @return array
     */
    #[Route('', name: 'bdoh_consult_station', methods: ['GET'])]
    #[Template('@IrsteaBdohConsult/Station/show.html.twig')]
    public function getAction($station)
    {
        /** @var StationRepository $stationRepo */
        $stationRepo = $this->getBdohRepo('Station');
        /** @var ChroniqueRepository $chronRepo */
        $chronRepo = $this->getBdohRepo('Chronique');

        // Retrieves 'station' requested
        try {
            $stationEntity = $stationRepo->findOneByCode($station);
        } catch (ORMException $e) {
            throw new NotFoundEntityException('Station', ['code' => " = $station"]);
        }

        // Les familles de paramètres utilisées sur cette station
        $familyTable = $this->getBdohRepo('FamilleParametres')->getFamillesAndTypes($this->isLocaleEn(), $stationEntity);

        // Les familles de niveau 0
        $rootFamilies = [];
        foreach ($familyTable as $family) {
            if ($family['pid'] === null) {
                $rootFamilies[] = $family['id'];
            }
        }

        // les types de paramètres sans famille
        $typesWithoutFamily = $this->getBdohRepo('Observatoire')->getParametres($this->isLocaleEn());
        $types = [];
        foreach ($typesWithoutFamily as $type) {
            $types[] = $this->isLocaleEn() ? $type['nomEn'] : $type['nom'];
        }

        // Retrieves 'chroniques' of $station
        $chroniques = $chronRepo->findByStation($stationEntity);

        // For each, gets its first and last dates
        $dates = $stationRepo->getFirstLastDatesByChronique($stationEntity);

        $chroniquesByParam = [];
        // Groups these 'chroniques' by 'TypeParametre'
        foreach ($chroniques as $chronique) {
            $param = $chronique->getParametre();
            if ($this->isLocaleEn()) {
                $paramName = $param->getNomEn();
                if (!$paramName) {
                    $paramName = $param->getNom();
                }
            } else {
                $paramName = $param->getNom();
            }
            $chroniquesByParam[$paramName][] = $chronique;
        }

        $chroniquesHavingMeasures = [];

        // Selects only 'chroniques' having measures
        foreach ($chroniques as $chronique) {
            if (($chronDates = @$dates[$chronique->getId()])
                && $chronDates['first'] && $chronDates['last']
            ) {
                $chroniquesHavingMeasures[] = $chronique;
            }
        }

        // Computes the first and last dates, for station
        if ($dates) {
            $stationFirstDate = '9999-99-99';
            $stationLastDate = '0000-00-00';

            foreach ($dates as $date) {
                $stationFirstDate = min($stationFirstDate, $date['first']);
                $stationLastDate = max($stationLastDate, $date['last']);
            }
        } else {
            $stationFirstDate = $stationLastDate = null;
        }

        return [
            'station'                  => $stationEntity,
            'chroniquesHavingMeasures' => $chroniquesHavingMeasures,
            'chroniquesByParam'        => $chroniquesByParam,
            'dates'                    => $dates,
            'stationFirstDate'         => $stationFirstDate,
            'stationLastDate'          => $stationLastDate,
            'typesEchant'              => $this->getBdohRepo('Echantillonnage')->getTypesEchant(),
            'familyTable'              => $familyTable,
            'rootFamilies'             => $rootFamilies,
            'typesWithoutFamily'       => $types,
        ];
    }
}
