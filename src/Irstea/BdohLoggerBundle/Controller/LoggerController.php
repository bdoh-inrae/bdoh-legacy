<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Controller;

use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohDataBundle\Entity\ChroniqueCalculee;
use Irstea\BdohLoggerBundle\Entity\HistoriqueCalculsChroniques;
use Irstea\BdohLoggerBundle\Entity\Repository\HistoriqueRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Class LoggerController.
 */
#[IsGranted('ROLE_ADMIN')]
class LoggerController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function advancedSearchAction(Request $request)
    {
        return $this->renderAdvancedSearch($request);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function advancedSearchRecentAction(Request $request)
    {
        return $this->renderAdvancedSearch($request, true);
    }

    /**
     * @param Request $request
     * @param bool    $recent
     *
     * @throws \Doctrine\ORM\ORMException
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function renderAdvancedSearch(Request $request, $recent = false)
    {
        // Map: key => "IrsteaBdohLoggerBundle:Historique".value repository
        $entitySuffixes = [
            'all'                          => '',
            'admin'                        => 'Administration',
            'adminObservatoire'            => 'AdministrationObservatoire',
            'adminSiteExperimental'        => 'AdministrationSiteExperimental',
            'adminStation'                 => 'AdministrationStation',
            'adminChronique'               => 'AdministrationChronique',
            'adminBareme'                  => 'AdministrationBareme', // there is no such thing in real life so no menu for it
            'adminBassin'                  => 'AdministrationBassin',
            'adminCoursEau'                => 'AdministrationCoursEau',
            'adminCommune'                 => 'AdministrationCommune',
            'adminDoi'                     => 'AdministrationDoi',
            'adminDataset'                 => 'AdministrationDataSet', // casse de la classe (Dataset dans l'ancien)
            'adminPartenaire'              => 'AdministrationPartenaire',
            'adminFamilleParametres'       => 'AdministrationFamilleParametres',
            'adminTypeParametre'           => 'AdministrationTypeParametre',
            'adminTypeFunding'             => 'AdministrationTypeFunding',
            'adminTopicCategory'           => 'AdministrationTopicCategory',
            'adminTheiaCategories'         => 'AdministrationTheiaCategories',
            'adminInspireTheme'            => 'AdministrationInspireTheme',
            'adminDataConstraint'          => 'AdministrationDataConstraint',
            'adminCriteriaGeology'         => 'AdministrationCriteriaGeology',
            'adminCriteriaClimate'         => 'AdministrationCriteriaClimate',
            'adminMilieu'                  => 'AdministrationMilieu',
            'adminPersonneTheia'           => 'AdministrationPersonneTheia',
            'adminUnite'                   => 'AdministrationUnite',
            'adminUtilisateur'             => 'AdministrationUtilisateur',
            'donnees'                      => 'Donnees',
            'donneesExport'                => 'DonneesExport',
            'donneesImport'                => 'DonneesImport',
            'donneesSuppression'           => 'DonneesSuppression',
            'donneesPointControle'         => 'DonneesPointControle',
            'transformations'              => 'Transformations',
            'baremesImport'                => 'BaremesImport',
            'baremesExport'                => 'BaremesExport',
            'calculsChroniques'            => 'CalculsChroniques',
            'conversionsChroniques'        => 'ConversionsChroniques',
            'sig'                          => 'Sig',
        ];

        $observatoire = $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent();

        // $historiques will be a map: "historiques".key => history from repository (see $entitySuffixes)
        // This map will be used to render the TWIG template
        $historiques = [];
        $years = null;
        $year = 0;

        if ($recent) {
            $date = new \DateTime('now', new \DateTimeZone('UTC'));
            // P for period, 2M for 2 months (Y for year, M for Month, D for day)
            $date->sub(new \DateInterval('P2M'));
            foreach ($entitySuffixes as $key => $suffix) {
                $historiques[$key] = $this->getHistoriqueRepo($suffix)
                    ->findByObservatoire($observatoire, 0, $date);
            }
        } else {
            $years = $this->getHistoriqueRepo('')->getYears($observatoire);
            // max([]) rendait false sous PHP 7, avec un avertissement ; sous PHP 8, il lève une erreur
            $year = $years ? max($years) : false;
            if ($request->query->has('year')) {
                $yearQuery = (int) $request->query->get('year');
                if (\in_array($yearQuery, $years, true)) {
                    $year = $yearQuery;
                }
            }
            foreach ($entitySuffixes as $key => $suffix) {
                $historiques[$key] = $this->getHistoriqueRepo($suffix)
                    ->findByObservatoire($observatoire, $year);
            }
        }

        // Specific case: the description for chronicle computations must be converted to HTML data
        foreach ($historiques['all'] as $historique) {
            if ($historique instanceof HistoriqueCalculsChroniques) {
                $this->convertCalculDescription($historique);
            }
        }

        foreach ($historiques['transformations'] as $historique) {
            if ($historique instanceof HistoriqueCalculsChroniques) {
                $this->convertCalculDescription($historique);
            }
        }
        foreach ($historiques['calculsChroniques'] as $historique) {
            $this->convertCalculDescription($historique);
        }

        // Merge measure imports into transformation log for computed time series
        // Also, fill a sub-array of $historique with these imports
        $historiques['donneesImportManuel'] = [];
        foreach ($historiques['donneesImport'] as $historique) {
            if ($historique->getChronique() instanceof ChroniqueCalculee) {
                // Create a 'clone' log rather than using the original one
                $historiqueClone = new \Irstea\BdohLoggerBundle\Entity\HistoriqueTransformations();
                $historiqueClone->setAction('historique.actions.ImportManuelMesure');
                $historiqueClone->setAuteur($historique->getAuteur());
                $historiqueClone->setDate($historique->getDate());
                $historiqueClone->setDescription(
                    $this->getTranslator()->trans('historique.action') .
                    $this->getTranslator()->trans('deux_points') . ' ' .
                    $this->getTranslator()->trans('historique.actions.ImportManuelMesure') . "\n" .
                    preg_replace("/^.*\n/", '', $historique->getDescription())
                );
                $historiqueClone->setLien($historique->getLien());
                $historiqueClone->setObservatoire($historique->getObservatoire());

                $historiques['transformations'][] = $historiqueClone;
                $historiques['donneesImportManuel'][] = $historiqueClone;
            }
        }

        $historiques['derniereConnection'] =
            $this->getSecurityRepo('Utilisateur')->findDerniereConnection($observatoire);

        // Eventually, return the HTML page
        return $this->render(
            '@IrsteaBdohLogger/index.html.twig',
            [
                'historiques' => $historiques,
                'filters'     => json_encode($request->query->all()),
                'recent'      => $recent,
                'years'       => $years,
                'year'        => $year,
            ]
        );
    }

    /**
     * @param $suffix
     *
     * @return \Doctrine\ORM\EntityRepository|HistoriqueRepository
     */
    private function getHistoriqueRepo($suffix)
    {
        return $this->getRepository('Irstea\\BdohLoggerBundle\\Entity\\Historique' . $suffix);
    }

    /**
     * @param $historique
     */
    private function convertCalculDescription($historique)
    {
        // If the description does not start with the header prefix string, then it has already been processed.
        if (\strpos($historique->getDescription(), HistoriqueCalculsChroniques::TRANSFO_HEADER) === 0) {
            $description = $historique->unserializeDescription();
            $historique->setDescription(
                str_replace(
                    "\n",
                    '',
                    $this->renderView(
                        '@IrsteaBdohLogger/descriptionHistoriqueCalculChronique.html.twig',
                        ['description' => $description, 'random' => \mt_rand()]
                    )
                )
            );
        }
    }
}
