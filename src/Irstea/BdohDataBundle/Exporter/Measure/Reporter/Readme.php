<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure\Reporter;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\PasEchantillonnage;
use Irstea\BdohDataBundle\Exporter\Measure\Csv\Tables;
use Irstea\BdohDataBundle\Exporter\Measure\ReporterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Le README d'un export, qui remplace l'ancien Report.txt (dev/plans/2026-11-13_fair-exports.md). Sa forme suit
 * celle que Lou fait passer dans ses curations et dans MEANDRE (texte brut de 80 colonnes, sections en capitales
 * soulignées, colonnes décrites une à une) : informations générales, licence et citation, paramètres de l'export,
 * méthode (ce que disait le rapport, chronique par chronique), fichiers, puis colonnes. En français ou en anglais,
 * selon la langue de l'export ; écrit en PHP plutôt qu'en Twig, pour tenir la mise en page au caractère près.
 */
class Readme implements ReporterInterface
{
    private const WIDTH = 80;

    /** @var array */
    private $parameters = ['chroniques' => []];

    /** @var string[] */
    private $lines = [];

    public function __construct(private Environment $twig, private UrlGeneratorInterface $router)
    {
    }

    public function addChronique(Chronique $chronique, array $report)
    {
        $this->parameters['chroniques'][] = ['chronique' => $chronique, 'report' => $report];
    }

    public function addParameters(array $parameters)
    {
        $this->parameters = array_replace($this->parameters, $parameters);

        return $this;
    }

    public function writeTo($resource)
    {
        $this->lines = [];
        $this->banner();
        $this->general();
        $this->exportParameters();
        $this->methods();
        $this->filesOverview();
        $this->dataSpecific();
        fwrite($resource, implode("\n", array_map('rtrim', $this->lines)) . "\n");
    }

    // --- Les sections ----------------------------------------------------------------------------------------------

    private function banner()
    {
        $date = $this->parameters['date'] ?? new \DateTime('now', new \DateTimeZone('UTC'));
        $this->lines = [
            ' ___  ___    ___   _  _',
            '| _ )|   \\  / _ \\ | || |',
            '| _ \\| |) || (_) || __ |   ' . $this->t('Export de BDOH', 'BDOH export'),
            '|___/|___/  \\___/ |_||_|   ' . $this->t('Base de Données des Observatoires en Hydrologie', 'Database of the Observatories in Hydrology'),
            '',
            $this->t('Ce fichier a été écrit par BDOH le ', 'This file was written by BDOH on ') . $date->format('Y-m-d H:i:s') . ' UTC.',
            $this->url('bdoh_all_home', []),
            '',
        ];
        $this->paragraph($this->t(
            "Il décrit l'archive où il se trouve : à lire avant d'employer les données.",
            'It describes the archive it belongs to: to be read before using the data.'
        ));
    }

    private function general()
    {
        $observatoire = $this->parameters['observatoire'];
        $slug = ['_observatoire' => $observatoire->getSlug()];
        $this->title($this->t('INFORMATIONS GÉNÉRALES', 'GENERAL INFORMATION'));
        $this->field($this->t('Observatoire', 'Observatory'), $observatoire->getNom());
        $this->field($this->t("Page de l'observatoire", 'Page of the observatory'), $this->url('bdoh_home', $slug));
        $this->field('Contact', $this->url('bdoh_consult_contact', $slug));

        $dois = iterator_to_array($observatoire->getDois());
        if ($dois) {
            $this->lines[] = '';
            $this->lines[] = $this->t("DOI de l'observatoire :", 'DOI of the observatory:');
            foreach ($dois as $doi) {
                $description = $this->t($doi->getDescription(), $doi->getDescriptionEn() ?: $doi->getDescription());
                $this->item($this->doiUrl($doi->getIdentifiant()) . ($description ? ' (' . trim($description) . ')' : ''));
            }
        }

        $this->lines[] = '';
        $this->lines[] = $this->t('Licence :', 'Licence:');
        $visible = $this->parameters['visibleCount'] ?? 0;
        $hidden = $this->parameters['hiddenCount'] ?? 0;
        if ($visible) {
            $this->paragraph($this->t(
                ($hidden ? sprintf('Les %d chroniques ouvertes sont', $visible) : ($visible > 1 ? 'Les chroniques sont' : 'La chronique est'))
                . ' sous Licence Ouverte 2.0 (etalab-2.0), dont le texte est joint en français et en anglais ('
                . implode(', ', $this->parameters['openFiles'] ?? []) . '). La réutilisation est libre, commerciale '
                . 'comprise, en mentionnant la source et la date de dernière mise à jour, à défaut celle du '
                . 'téléchargement.',
                ($hidden ? sprintf('The %d open time series are', $visible) : ($visible > 1 ? 'The time series are' : 'The time series is'))
                . ' under the Open Licence 2.0 (etalab-2.0), whose text is attached in French and in English ('
                . implode(', ', $this->parameters['openFiles'] ?? []) . '). Reuse is free, commercial use included, '
                . 'mentioning the source and the date of last update, or else that of the download.'
            ), 2);
        }
        if ($hidden) {
            $this->paragraph($this->t(
                ($visible ? sprintf('Les %d chroniques en accès restreint sont', $hidden) : ($hidden > 1 ? 'Les chroniques, en accès restreint, sont' : 'La chronique, en accès restreint, est'))
                . ' sous les conditions générales d\'utilisation de BDOH (' . $this->parameters['termsFile'] . '), qui en '
                . 'interdisent notamment la transmission à des tiers.',
                ($visible ? sprintf('The %d time series with restricted access are', $hidden) : ($hidden > 1 ? 'The time series, with restricted access, are' : 'The time series, with restricted access, is'))
                . ' under the general terms of use of BDOH (' . $this->parameters['termsFile'] . '), which forbid in '
                . 'particular passing them on to third parties.'
            ), 2);
        }
        if ($visible && $hidden) {
            $this->paragraph($this->t(
                'Licences.txt dit laquelle relève de quoi ; meta_series.csv aussi, pour un export CSV.',
                'Licences.txt tells which is which; so does meta_series.csv, for a CSV export.'
            ), 2);
        }
        $this->field($this->t("Conditions d'utilisation", 'Terms of use'), $this->url('bdoh_cgu_observatoires', $slug), 2);

        $this->lines[] = '';
        $this->lines[] = $this->t('Comment citer :', 'How to cite:');
        $date = ($this->parameters['date'] ?? new \DateTime())->format('Y-m-d');
        $this->paragraph($this->t(
            "Source : INRAE, BDOH, observatoire {$observatoire->getNom()}, données téléchargées le $date, "
            . $this->url('bdoh_home', $slug) . '.',
            "Source: INRAE, BDOH, observatory {$observatoire->getNom()}, data downloaded on $date, "
            . $this->url('bdoh_home', $slug) . '.'
        ), 2);
        $this->paragraph($this->t(
            "Si l'observatoire ou le jeu de données d'une chronique a un DOI (plus haut, ou dans meta_series.csv pour un "
            . 'export CSV), le citer de préférence.',
            'If the observatory or the dataset of a time series has a DOI (above, or in meta_series.csv for a CSV '
            . 'export), cite it preferably.'
        ), 2);
    }

    private function exportParameters()
    {
        $this->title($this->t("PARAMÈTRES DE L'EXPORT", 'EXPORT PARAMETERS'));
        $format = strtolower($this->parameters['format'] ?? 'bdoh');
        $this->field('Format', ['csv' => 'CSV', 'bdoh' => 'BDOH', 'qtvar' => 'Hydro2 - QTVAR'][$format] ?? $format);
        $this->field($this->t('Période demandée', 'Requested period'), $this->t('du ', 'from ')
            . $this->utc($this->parameters['begin']) . $this->t(' au ', ' to ') . $this->utc($this->parameters['end']));
        // Le décalage que prend le SQL des extractions (ChroniqueRepository::getTzCorrectionForSql)
        $this->field($this->t('Fuseau horaire des dates', 'Time zone of the dates'),
            'UTC' . (new \DateTime('2001-01-01T00:00:00Z'))->setTimezone($this->parameters['timezone'])->format('P'));
        $extractor = $this->parameters['extractor'] ?? 'identical';
        if ($extractor === 'identical') {
            $this->field($this->t('Valeurs', 'Values'), $this->t("à l'identique, telles qu'enregistrées dans BDOH", 'as stored in BDOH'));
        } else {
            $this->field($this->t('Valeurs', 'Values'), $this->t('rééchantillonnées à pas de temps fixe', 'resampled at a fixed time step'));
            $this->field($this->t('Rééchantillonnage', 'Resampling'), [
                'instantaneous' => $this->t('interpolation linéaire', 'linear interpolation'),
                'mean'          => $this->t('moyenne', 'mean'),
                'cumulative'    => $this->t('cumul', 'cumulative'),
            ][$extractor] ?? $extractor);
            $this->field($this->t('Pas de temps', 'Time step'), $this->step());
        }
        $this->field($this->t('Chroniques', 'Time series'), (string) count($this->parameters['chroniques']));
    }

    private function methods()
    {
        $this->title($this->t('INFORMATIONS MÉTHODOLOGIQUES', 'METHODOLOGICAL INFORMATION'));
        $this->paragraph($this->t(
            "Chaque chronique a sa page dans BDOH, qui la décrit avec sa station, son jeu de données et ses "
            . 'producteurs. Ci-dessous, ce que dit BDOH de leur origine et de ce que l\'export en a tiré.',
            'Each time series has its page in BDOH, which describes it with its station, its dataset and its '
            . 'producers. Below, what BDOH says of their origin and of what the export took from them.'
        ));
        foreach ($this->parameters['chroniques'] as ['chronique' => $chronique, 'report' => $report]) {
            $station = $chronique->getStation();
            // Le code seul dans le titre, qui tient ainsi sur une ligne ; le nom en dessous
            $this->title($station->getCode() . ' / ' . $chronique->getCode(), 2);
            $this->field($this->t('Nom', 'Name'), $this->t($chronique->getLibelle(), $chronique->getLibelleEn() ?: $chronique->getLibelle()));
            $this->field('Page', $this->url('bdoh_consult_chronique', [
                '_observatoire' => $this->parameters['observatoire']->getSlug(),
                'station'       => $station->getCode(),
                'chronique'     => $chronique->getCode(),
            ]));
            if (isset($report['error'])) {
                $this->lines[] = $this->t('Erreur :', 'Error:');
                $this->paragraph(trim(strip_tags($report['error'])), 2);
                continue;
            }
            // Comme l'ancien rapport : les mesures présentes sur la période, puis ce que l'export en a tiré
            $number = (int) ($report['number'] ?? 0);
            $this->field($this->t('Mesures présentes', 'Stored values'), match (true) {
                $number === 0 => '0',
                $number === 1 => '1, ' . $this->t('le ', 'on ') . $this->local($report['first'] ?? null),
                default       => $number . $this->t(', du ', ', from ') . $this->local($report['first'] ?? null)
                    . $this->t(' au ', ' to ') . $this->local($report['last'] ?? null),
            });
            if ($number > 0) {
                $gaps = (int) ($report['count']['gaps'] ?? 0);
                $this->field($this->t('Valeurs exportées', 'Exported values'), ((int) ($report['count']['measures'] ?? 0) + $gaps)
                    . $this->t(', dont ', ', of which ') . $gaps . $this->t(' lacunes', ' gaps'));
                $this->field($this->t('Fichier', 'File'), $report['filename'] ?? '');
            }
            $genealogie = trim((string) $this->t($chronique->getGenealogie(), $chronique->getGenealogieEn() ?: $chronique->getGenealogie()));
            if ($genealogie !== '') {
                $this->lines[] = $this->t('Généalogie :', 'Lineage:');
                foreach (preg_split('/\R/', $genealogie) as $line) {
                    $this->paragraph($line, 2);
                }
            }
            if ($chronique->isCalculee()) {
                $this->lines[] = $this->t('Calcul :', 'Computation:');
                // Les entrées et les barèmes, tels que les écrivait l'ancien rapport (transformations.text.twig), avec
                // leur retrait : il dit quel barème va avec quelle chronique mère
                $text = $this->twig->createTemplate(
                    "{% import '@IrsteaBdohData/Exporter/transformations.text.twig' as m %}{% autoescape false %}{{ m.transformations(chronique) }}{% endautoescape %}"
                )->render(['chronique' => $chronique]);
                $lines = array_filter(preg_split('/\R/', rtrim($text)), fn ($line) => trim($line) !== '');
                $margin = $lines ? min(array_map(fn ($line) => strlen($line) - strlen(ltrim($line)), $lines)) : 0;
                foreach ($lines as $line) {
                    $this->paragraph(trim($line), 2 + min(8, strlen($line) - strlen(ltrim($line)) - $margin));
                }
            }
        }
    }

    private function filesOverview()
    {
        $files = $this->parameters['files'] ?? [];
        $this->title($this->t('APERÇU DES FICHIERS', 'DATA & FILE OVERVIEW'));
        $this->title($this->t('Arborescence', 'File hierarchy'), 2);
        $this->lines[] = '.';
        foreach ($files as $k => $file) {
            $this->lines[] = ($k === count($files) - 1 ? '└── ' : '├── ') . $file;
        }
        $this->lines[] = '';
        $this->lines[] = sprintf($this->t('%d fichiers', '%d files'), count($files));

        $csv = strtolower($this->parameters['format'] ?? '') === 'csv';
        $extension = $csv ? 'csv' : 'txt';
        $this->title($this->t('Convention de nommage', 'File naming convention'), 2);
        $this->paragraph($this->t(
            "{station}_{chronique}.$extension : les valeurs d'une chronique, {station} étant le code de sa station et {chronique} le sien.",
            "{station}_{series}.$extension: the values of one time series, {station} being the code of its station and {series} its own."
        ));

        $this->title($this->t('Les fichiers', 'The files'), 2);
        $this->item($this->t('README.txt : ce fichier.', 'README.txt: this file.'));
        $this->item($this->t(
            "{station}_{chronique}.$extension : les valeurs d'une chronique" . ($csv ? ', une ligne par valeur.' : ', au format ' . (strtolower($this->parameters['format']) === 'qtvar' ? 'Hydro2 - QTVAR.' : 'BDOH.')),
            "{station}_{series}.$extension: the values of one time series" . ($csv ? ', one row per value.' : ', in the ' . (strtolower($this->parameters['format']) === 'qtvar' ? 'Hydro2 - QTVAR' : 'BDOH') . ' format.')
        ));
        if ($csv) {
            foreach ([Tables::SERIES_FILE, Tables::STATION_FILE, Tables::QUALITY_FILE] as $table) {
                $title = $this->tables()[$table]['title'][$this->lang()];
                $this->item($table . $this->t(' : ', ': ') . mb_strtolower(mb_substr($title, 0, 1)) . mb_substr($title, 1));
            }
            $this->item($this->t(
                Tables::CSVW_FILE . ' : la description de ces tables au format CSVW, recommandé par le W3C '
                . '(https://www.w3.org/TR/tabular-metadata/), pour les outils qui la lisent.',
                Tables::CSVW_FILE . ': the description of these tables in the CSVW format, recommended by the W3C '
                . '(https://www.w3.org/TR/tabular-metadata/), for the tools that read it.'
            ));
        }
        if ($this->parameters['openFiles'] ?? []) {
            $this->item(implode(', ', $this->parameters['openFiles']) . $this->t(
                ' : la Licence Ouverte 2.0, en français et en anglais.',
                ': the Open Licence 2.0, in French and in English.'
            ));
        }
        if ($this->parameters['termsFile'] ?? null) {
            $this->item($this->parameters['termsFile'] . $this->t(
                " : les conditions générales d'utilisation de BDOH, pour les chroniques en accès restreint.",
                ': the general terms of use of BDOH, for the time series with restricted access.'
            ));
        }
        if (in_array('Licences.txt', $files, true)) {
            $this->item($this->t('Licences.txt : quelle chronique relève de quelle licence.', 'Licences.txt: which time series falls under which licence.'));
        }
        if ($this->parameters['formatDocFile'] ?? null) {
            $this->item($this->parameters['formatDocFile'] . $this->t(
                ' : la description des formats BDOH et Hydro2 - QTVAR.',
                ': the description of the BDOH and Hydro2 - QTVAR formats.'
            ));
        }
    }

    private function dataSpecific()
    {
        $this->title($this->t('INFORMATIONS PROPRES AUX DONNÉES', 'DATA-SPECIFIC INFORMATION'));
        if (strtolower($this->parameters['format'] ?? '') !== 'csv') {
            $this->paragraph($this->t(
                'Le format des fichiers de valeurs est décrit dans ' . ($this->parameters['formatDocFile'] ?? 'FormatsExport.pdf') . '.',
                'The format of the value files is described in ' . ($this->parameters['formatDocFile'] ?? 'FormatsExport.pdf') . '.'
            ));
            if (strtolower($this->parameters['format'] ?? '') === 'bdoh') {
                $this->paragraph($this->t(
                    'En bref : point-virgule entre les colonnes, point décimal ; une ligne qui nomme la station, le '
                    . 'fuseau horaire, la chronique et l\'unité, puis leurs valeurs, puis le nom des colonnes ; dates '
                    . 'jj/mm/aaaa hh:mm:ss au fuseau indiqué ; -9999 pour une lacune, avec son code de qualité.',
                    'In short: semicolon between columns, decimal point; a line naming the station, the time zone, the '
                    . 'time series and the unit, then their values, then the names of the columns; dates '
                    . 'dd/mm/yyyy hh:mm:ss in the given time zone; -9999 for a gap, with its quality code.'
                ));
            }

            return;
        }

        $this->paragraph($this->t(
            "Tous les CSV : encodage UTF-8, virgule entre les colonnes, point décimal, une ligne d'en-tête, texte "
            . 'entre guillemets quand il contient une virgule, un guillemet ou un retour à la ligne.',
            'All CSV files: UTF-8 encoding, comma between columns, decimal point, one header line, text between '
            . 'double quotes when it holds a comma, a double quote or a line break.'
        ));
        $this->field($this->t('Code des valeurs manquantes', 'Missing data code'), Tables::MISSING);
        $this->paragraph($this->t(
            'Dans un tableur, ouvrir un CSV par son import (LibreOffice : Fichier > Ouvrir ; Excel : Données > '
            . 'À partir d\'un fichier texte/CSV), en choisissant l\'encodage UTF-8 et la virgule comme séparateur : '
            . 'un double-clic, dans un tableur réglé en français, mettrait tout dans une seule colonne.',
            'In a spreadsheet, open a CSV file through its import (LibreOffice: File > Open; Excel: Data > From '
            . 'Text/CSV), choosing the UTF-8 encoding and the comma as separator.'
        ));
        $this->paragraph($this->t(
            'Les fichiers de valeurs ont tous les mêmes colonnes : mis bout à bout, ils forment une seule table, au '
            . 'format long. En R :',
            'The value files all have the same columns: put end to end, they make a single table, in long format. '
            . 'In R:'
        ));
        $this->lines[] = '  files <- setdiff(Sys.glob("*.csv"), Sys.glob("meta_*.csv"))';
        $this->lines[] = '  values <- do.call(rbind, lapply(files, read.csv))';
        $this->lines[] = $this->t('En Python :', 'In Python:');
        $this->lines[] = '  import glob, pandas';
        $this->lines[] = '  files = [f for f in glob.glob("*.csv") if not f.startswith("meta_")]';
        $this->lines[] = '  values = pandas.concat(pandas.read_csv(f) for f in files)';

        foreach ($this->tables() as $file => $table) {
            $this->title($file === '{station}_{series}.csv' ? $this->t('{station}_{chronique}.csv', $file) : $file, 2);
            $this->paragraph($table['title'][$this->lang()]);
            $this->lines[] = $this->t('Colonnes :', 'Columns:');
            foreach ($table['columns'] as [$name, $datatype, $unit, $fr, $en]) {
                $this->lines[] = '';
                $this->lines[] = '- displayed_name: ' . $name;
                $description = $this->t($fr, $en);
                $wrapped = explode("\n", wordwrap($description, self::WIDTH - 4, "\n", false));
                if (count($wrapped) === 1 && mb_strlen('  description: ' . $description) <= self::WIDTH) {
                    $this->lines[] = '  description: ' . $description;
                } else {
                    $this->lines[] = '  description: >';
                    foreach ($wrapped as $line) {
                        $this->lines[] = '    ' . $line;
                    }
                }
                $this->lines[] = '  type: ' . $datatype;
                if ($unit !== null) {
                    $this->lines[] = '  unit: ' . $unit;
                }
            }
        }
    }

    // --- Les outils ------------------------------------------------------------------------------------------------

    private function lang(): string
    {
        return ($this->parameters['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
    }

    private function t(?string $fr, ?string $en): ?string
    {
        return $this->lang() === 'en' ? $en : $fr;
    }

    private function tables(): array
    {
        return $this->parameters['tables'] ?: Tables::all();
    }

    private function url(string $route, array $parameters): string
    {
        return $this->router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function doiUrl(string $doi): string
    {
        return 'https://doi.org/' . preg_replace('#^(?:https?://(?:dx\.)?doi\.org/|doi:)#i', '', trim($doi));
    }

    /** Le pas d'un rééchantillonnage : des minutes pour une interpolation, un pas de la liste sinon. */
    private function step(): string
    {
        $timestep = $this->parameters['timestep'] ?? null;

        return $timestep instanceof PasEchantillonnage
            ? $this->t($timestep->getLibelle(), $timestep->getLibelleEn() ?: $timestep->getLibelle())
            : sprintf('%d minute%s', (int) $timestep, (int) $timestep > 1 ? 's' : '');
    }

    private function utc($date): string
    {
        $date = $date instanceof \DateTimeInterface ? \DateTime::createFromInterface($date) : new \DateTime((string) $date, new \DateTimeZone('UTC'));

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') . ' UTC';
    }

    /** Une date du rapport (UTC), au fuseau de l'export. */
    private function local($date): string
    {
        if ($date === null || $date === '') {
            return '?';
        }
        $date = $date instanceof \DateTimeInterface ? \DateTime::createFromInterface($date) : new \DateTime((string) $date, new \DateTimeZone('UTC'));

        return $date->setTimezone($this->parameters['timezone'])->format('Y-m-d H:i:s');
    }

    /** Un titre, souligné jusqu'à la 80e colonne : « # TITRE ____ » ou « ## Titre ____ ». */
    private function title(string $text, int $level = 1)
    {
        $head = str_repeat('#', $level) . ' ' . $text . ' ';
        $this->lines[] = '';
        if ($level === 1) {
            $this->lines[] = '';
        }
        $this->lines[] = $head . str_repeat('_', max(3, self::WIDTH - mb_strlen($head)));
    }

    /** « Nom : valeur », la valeur repliée sous elle-même si elle dépasse. */
    private function field(string $label, ?string $value, int $indent = 0)
    {
        $head = str_repeat(' ', $indent) . $label . $this->t(' : ', ': ');
        $value = (string) $value;
        if (mb_strlen($head . $value) <= self::WIDTH) {
            $this->lines[] = $head . $value;

            return;
        }
        $this->lines[] = rtrim($head);
        $this->paragraph($value, $indent + 2);
    }

    private function item(string $text)
    {
        $lines = explode("\n", wordwrap($text, self::WIDTH - 4, "\n", false));
        foreach ($lines as $k => $line) {
            $this->lines[] = ($k === 0 ? '  - ' : '    ') . $line;
        }
    }

    private function paragraph(?string $text, int $indent = 0)
    {
        foreach (explode("\n", wordwrap((string) $text, self::WIDTH - $indent, "\n", false)) as $line) {
            $this->lines[] = str_repeat(' ', $indent) . $line;
        }
    }
}
