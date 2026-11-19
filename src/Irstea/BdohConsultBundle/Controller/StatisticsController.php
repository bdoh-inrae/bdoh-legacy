<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohConsultBundle\Controller;

use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohConsultBundle\Statistics\Usage;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La page « Statistiques » d'un observatoire, pour qui voit son historique (Lou, 2026-11-12 et 16 ;
 * dev/plans/2026-11-16_reopening.md, point 6) : sa vie par année, et par chronique, en tableaux téléchargeables en CSV,
 * pour les montrer à ceux qui financent et construire des projets autour de BDOH. Un gestionnaire de tous les
 * observatoires, ou un administrateur technique, y voit aussi tout BDOH (?all=1).
 */
class StatisticsController extends Controller
{
    public function __construct(private Usage $usage)
    {
    }

    #[Route('/statistics', name: 'bdoh_consult_statistics', methods: ['GET'])]
    public function indexAction(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur || !(
            $user->isGestionnaireDesObservatoires() || $user->isAManagerOfCurrentObservatory()
            || $user->isASiteManagerOfCurrentObservatory() || $user->isAContributorOfCurrentObservatory()
            || $this->isGranted('ROLE_SUPER_ADMIN'))) {
            throw new AccessDeniedHttpException();
        }
        $canSeeAll = $user->isGestionnaireDesObservatoires() || $this->isGranted('ROLE_SUPER_ADMIN');
        $all = $canSeeAll && $request->query->getBoolean('all');
        $observatoire = $all ? null : $this->getCurrentObservatoire();

        $table = $request->query->get('csv');
        if ($table === 'years' || $table === 'series') {
            $rows = $table === 'years' ? $this->usage->byYear($observatoire) : $this->usage->bySeriesAndYear($observatoire);

            return $this->csv($rows, sprintf('BDOH-statistics_%s_%s_on-%s.csv', $observatoire ? 'obs-' . $observatoire->getSlug() : 'all',
                $table === 'years' ? 'by-year' : 'by-series-and-year', date('Ymd')));
        }

        // Par chronique, le total de toutes les années : le détail par année est dans le CSV
        $series = [];
        foreach ($this->usage->bySeriesAndYear($observatoire) as $row) {
            $key = $row['observatory'] . '|' . $row['station'] . '|' . $row['series'];
            $series[$key] ??= ['observatory' => $row['observatory'], 'station' => $row['station'], 'series' => $row['series'],
                'imported_values' => 0, 'downloads' => 0, 'views' => 0];
            foreach (['imported_values', 'downloads', 'views'] as $column) {
                $series[$key][$column] += (int) $row[$column];
            }
        }

        return $this->render('@IrsteaBdohConsult/Statistics/index.html.twig', [
            'years'     => $this->usage->byYear($observatoire),
            'series'    => array_values($series),
            'all'       => $all,
            'canSeeAll' => $canSeeAll,
            'columns'   => array_keys(Usage::YEAR_COLUMNS),
        ]);
    }

    /** Un tableau en CSV, comme les exports : UTF-8, virgule, une ligne d'en-tête aux noms anglais */
    private function csv(array $rows, string $filename): Response
    {
        $output = fopen('php://temp', 'r+');
        if ($rows) {
            fputcsv($output, array_keys($rows[0]), ',', '"', '', "\n");
        }
        foreach ($rows as $row) {
            fputcsv($output, array_values($row), ',', '"', '', "\n");
        }
        rewind($output);

        return new Response(stream_get_contents($output), 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
