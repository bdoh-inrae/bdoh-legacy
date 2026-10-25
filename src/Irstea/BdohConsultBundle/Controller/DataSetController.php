<?php

namespace Irstea\BdohConsultBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Twig\Attribute\Template;
use Doctrine\ORM\Exception\ORMException;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Exception\NotFoundEntityException;
use Irstea\BdohDataBundle\Entity\DataSet;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Entity\Repository\DataSetRepository;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Class DataSetController.
 */
#[Route('/dataset/{dataset}')]
class DataSetController extends Controller
{
    /**
     * @param ParameterBag $parameters
     *
     * @return DataSet
     */
    public function fetchDataSet(ParameterBag $parameters)
    {
        // $dataset = new DataSet();
        /** @var DataSetRepository $datasetRepo */
        $datasetRepo = $this->getBdohRepo('DataSet');

        return $datasetRepo->findOneById($parameters->get('id'));
    }

    /**
     * @param $dataset
     *
     * @throws \Exception
     *
     * @return array
     */
    #[Route('', name: 'bdoh_consult_dataset', methods: ['GET'])]
    #[Template('@IrsteaBdohConsult/Dataset/show.html.twig')]
    public function getAction($dataset)
    {
        /** @var DataSetRepository $datasetRepo */
        $datasetRepo = $this->getBdohRepo('Dataset');
        /** @var ChroniqueRepository $chronRepo */
        $chronRepo = $this->getBdohRepo('Chronique');

        // Retrieves 'dataset' requested
        try {
            $datasetEntity = $datasetRepo->findOneByUuid($dataset);
        } catch (ORMException $e) {
            throw new NotFoundEntityException('Dataset', ['uuid' => " = $dataset"]);
        }

        // Les familles de paramètres utilisées sur ce dataset
        $familyTable = $this->getBdohRepo('FamilleParametres')->getFamillesAndTypesDataset($this->isLocaleEn(), $datasetEntity);

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

        // Retrieves 'chroniques' of $dataset
        $chroniques = $chronRepo->findByDataSet($datasetEntity);

        // For each, gets its first and last dates
        $dates = $datasetRepo->getFirstLastDatesByChronique($datasetEntity);

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

        // Computes the first and last dates, for dataset
        if ($dates) {
            $datasetFirstDate = '9999-99-99';
            $datasetLastDate = '0000-00-00';

            foreach ($dates as $date) {
                $datasetFirstDate = min($datasetFirstDate, $date['first']);
                $datasetLastDate = max($datasetLastDate, $date['last']);
            }
        } else {
            $datasetFirstDate = $datasetLastDate = null;
        }

        return [
            'dataset'                  => $datasetEntity,
            'chroniquesHavingMeasures' => $chroniquesHavingMeasures,
            'chroniquesByParam'        => $chroniquesByParam,
            'dates'                    => $dates,
            'datasetFirstDate'         => $datasetFirstDate,
            'datasetLastDate'          => $datasetLastDate,
            'typesEchant'              => $this->getBdohRepo('Echantillonnage')->getTypesEchant(),
            'familyTable'              => $familyTable,
            'rootFamilies'             => $rootFamilies,
            'typesWithoutFamily'       => $types,
        ];
    }
}
