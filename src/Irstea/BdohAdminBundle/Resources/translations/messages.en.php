<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

define('IrsteaBdohDataBundle_transDir_en', __DIR__ . '/../../../BdohDataBundle/Resources/translations/');
define('IrsteaBdohSecurityBundle_transDir_en', __DIR__ . '/../../../BdohSecurityBundle/Resources/translations/');

$entitiesSingulars_Data = require IrsteaBdohDataBundle_transDir_en . 'entitiesSingulars.en.php';
$entitiesSingularsArticles_Data = require IrsteaBdohDataBundle_transDir_en . 'entitiesSingularsArticles.en.php';
$entitiesSingularsDefinedArticles_Data = require IrsteaBdohDataBundle_transDir_en . 'entitiesSingularsDefinedArticles.en.php';
$entitiesPlurals_Data = require IrsteaBdohDataBundle_transDir_en . 'entitiesPlurals.en.php';

$entitiesSingulars_Security = require IrsteaBdohSecurityBundle_transDir_en . 'entitiesSingulars.en.php';
$entitiesSingularsArticles_Security = require IrsteaBdohSecurityBundle_transDir_en . 'entitiesSingularsArticles.en.php';
$entitiesSingularsDefinedArticles_Security = require IrsteaBdohSecurityBundle_transDir_en . 'entitiesSingularsDefinedArticles.en.php';
$entitiesPlurals_Security = require IrsteaBdohSecurityBundle_transDir_en . 'entitiesPlurals.en.php';

/*************************************************
 * ENTITIES ACTIONS (for SonataAdminBundle)
 ************************************************/

$entitiesActions = [];

// For BdohDataBundle
$entitiesKeys = array_keys($entitiesSingulars_Data);

foreach ($entitiesKeys as $key) {
    $entitiesActions[$key . '_list'] =
        'List of the ' .
        ($entitiesPlurals_Data[$key] === 'DOI' ? $entitiesPlurals_Data[$key] : lcfirst($entitiesPlurals_Data[$key]));
    $entitiesActions[$key . '_create'] =
        'Create ' . $entitiesSingularsArticles_Data[$key] . ' ' .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_create_undefined'] =
        'Creation of ' . $entitiesSingularsArticles_Data[$key] . ' ' .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_edit'] =
        'Edit ' . $entitiesSingularsArticles_Data[$key] . ' ' .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_edit_defined'] =
        'Editing ' . $entitiesSingularsDefinedArticles_Data[$key] .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_delete'] =
        'Delete ' . $entitiesSingularsArticles_Data[$key] . ' ' .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_delete_defined'] =
        'Deletion ' . $entitiesSingularsDefinedArticles_Data[$key] .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
}

// For BdohSecurityBundle
$entitiesKeys = array_keys($entitiesSingulars_Security);

foreach ($entitiesKeys as $key) {
    $entitiesActions[$key . '_list'] =
        'List of the ' . lcfirst($entitiesPlurals_Security[$key]);
    $entitiesActions[$key . '_create'] =
        'Create ' . $entitiesSingularsArticles_Security[$key] . ' ' . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_create_undefined'] =
        'Creation of ' . $entitiesSingularsArticles_Security[$key] . ' ' . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_edit'] =
        'Edit ' . $entitiesSingularsArticles_Security[$key] . ' ' . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_edit_defined'] =
        'Editing ' . $entitiesSingularsDefinedArticles_Security[$key] . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_delete'] =
        'Delete ' . $entitiesSingularsArticles_Security[$key] . ' ' . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_delete_defined'] =
        'Deletion ' . $entitiesSingularsDefinedArticles_Security[$key] . lcfirst($entitiesSingulars_Security[$key]);
}

/*************************************************
 * FIELDSETS
 ************************************************/

$fieldSets = [
    'fieldset.text'    => 'Descriptions and others',
    'fieldset.graphic' => 'Display options and pictures',
];

/*************************************************
 * FILE FORMATS
 ************************************************/

$fileFormats = [
    'import' => [
        'Qjo'                   => 'Hydro2 - QJO',
        'Qtvar'                 => 'Hydro2 - QTVAR',
        'AixPluie'              => 'Aix - pluie',
        'AixDebitHauteur'       => 'Aix - débit/hauteur',
        'AntonyChimie'          => 'Antony - chimie',
        'AntonyHuml2x'          => 'Antony - HUML2X',
        'AntonyHutdr'           => 'Antony - HUTDR',
        'AntonyMeteoJournalier' => 'Antony - météo J',
        'AntonyMeteoHoraire'    => 'Antony - météo H',
        'AntonyPiezo'           => 'Antony - piezo',
        'AntonyPluie'           => 'Antony - pluie',
        'GrenobleDom'           => 'Grenoble - DOM',
        'LyonBiche'             => 'Lyon - Biche',
        'Bdoh'                  => 'BDOH - continuous',
        'BdohPlage'             => 'BDOH - discontinuous',
        'controle'              => 'Checkpoints',
    ],
];

/*************************************************
 * IMPORT OF MEASURES
 ************************************************/

$measureImport = [
    'measure.import' => [
        'title'                                  => 'Data upload',
        'step'                                   => [
            '1' => 'Step 1: data file and format',
            '2' => 'Step 2: station, time zone and time series',
            '3' => 'Step 3: file and format validation',
            '4' => 'Step 4: end of the upload',
        ],
        'question'                               => [
            'importType'      => 'How do you wish to upload these data?',
            'computeChildren' => 'Update the child time series',
        ],
        'action'                                 => [
            'doImport'          => 'Upload data.',
            'doNotImport'       => 'Do not upload data.',
            'keepExisting'      => 'Preserve previous data in case of overlap.',
            'overwriteExisting' => 'Overwrite with new data in case of overlap.',
            'newImport'         => 'New upload',
        ],
        'help'                                   => [
            'quitWarning'         => 'While uploading <b>please use the dedicated button to cancel</b>.',
            'file.onlyOneStation' => 'Only one station per file.',
            'file.bigInZip'       => '<u>Large data files</u> must be compressed <i>(one <b>zipped</b> file per station)</i>.',
            'file.sizeLimit'      => 'The upload file size must not exceed 2 MB.',
            'chroniquesBox'       => '<b>Instructions:</b> Make each column match with a time series by sliding it to the left.',
            'timezoneToUTC'       => 'after conversion to UTC',
            'overlap'             => [
                'new'         => "Time series' first upload.",
                'before'      => 'All the data to be uploaded are prior to the existing data.',
                'after'       => 'All the data to be uploaded are posterior to the existing data.',
                'boundsEqual' => 'Bounds of the existing data match those of the data to bo uploaded.',
                'newInOld'    => 'All the data to be uploaded lie within the existing data bounds.',
                'oldInNew'    => 'All the existing data lie within the bounds of the data to be uploaded.',
                'firstIn'     => 'The beginning of the data to be uploaded overlaps the end of the existing data.',
                'lastIn'      => 'The end of the data to be uploaded overlaps the beginning of the existing data.',
            ],
            'children'            => 'Child time series',
        ],
        'info'                                   => [
            'wasCanceled'             => 'The data upload has been canceled.',
            'noMeasuresImported'      => 'No data were uploaded.',
            'measuresInsert'          => 'Data point(s) added',
            'measuresDelete'          => 'Data point(s) deleted',
            'computingOfFillingRates' => '<b>Warning:</b> The data completeness is being updated, ' .
                'this operation may take up to a few minutes during which its display is outdated.',
            'jobPage(%jobPageLink%)'  => 'You may check the end of this operation from <a href="%jobPageLink%">the job page</a>.',
            'children(%count%)'       => 'This time series is used for one child time series|This time series is used for %count% child time series',
        ],
        'fileColumns'                            => "File's column(s)",
        'thereAreErrors.cantContinue'            => 'The upload process cannot continue because of errors.',
        'valeurTooLarge(%first%,%last%,%count%)' => '{0}|' .
            '{1}There is one data point greater than the maximum, at <i>%first%</i>|' .
            ']1,Inf]From <i>%first%</i> to <i>%last%</i> inclusive: there are <b>%count%</b> data points greater than maximum',
        'valeurTooSmall(%first%,%last%,%count%)' => '{0}|' .
            '{1}There is one data point less than the minimum, at <i>%first%</i>|' .
            ']1,Inf]From <i>%first%</i> to <i>%last%</i> inclusive: there are <b>%count%</b> data points less than minimum',
        'analysisInProgress'                     => 'Analysis in progress',
        'processingInProgress'                   => 'Processing in progress',
        'canTakeAWhile'                          => 'This operation may take some time',
        'pleaseWait'                             => 'Please wait...',
        'timeSeries'                             => 'Time series',
        'ignoredTimeSeries'                      => 'Ignored time series',
        'removeTimeSeries'                       => 'Do not include this time series',
        'restoreTimeSeries'                      => 'Restore this time series',
        'formats'                                => [
            'Bdoh'=> <<<FORMAT
  <li>
      <b>Header (3 lines):</b>
      <ul>
          <li>"Station ; Fuseau ; Followed by, for N time series: Chronique ; Unite ; Chronique ; Unite ... (N times)"</li>
          <li>
              "station code ; timezone value <i>(<u>format</u>: UTC +/-HH)</i> ; time series code 1 ; time series unit 1 ;
              time series code 2 ; time series unit 2 ... (N times)"
          </li>
          <li>
              "DateHeure ; Valeur ; Qualite ; Min (optional) ; Max (optional) ; Valeur ; Qualite ; Min (optional) ;
              Max (optional) ... (N times)"
          </li>
      </ul>
  </li>
  <li>
      <b>Data:</b> "DD/MM/YYYY HH:MM:SS ; value ; quality code ; value (optional) ; value (optional) ;
      value ; quality code ; value (optional) ; value (optional) ... (N times)"
  </li>
  <li>
      <b>Special values and missing values:</b>
      <ul>
          <li>-9999 for gap values;</li>
          <li>The quality code must match the quality set used by the observatory.</li>
      </ul>
  </li>
  <li>
      <b>Comments:</b>
      <ul>
          <li>
              The character '#' indicates a comment line and can also be found inside the line,
              in which case the rest of the line is ignored;
          </li>
          <li>
              Comments are not allowed in the first three lines of the file,
              which must always be the header as described here.
          </li>
      </ul>
  </li>
  <li>
      <b>Min and max columns go compulsorily together but they may be
      present or not depending of each time series.</b>
  </li>
FORMAT
            ,
            'BdohPlage'=> <<<FORMAT
  <li>
      <b>Header (3 lines):</b>
      <ul>
          <li>"Station ; Fuseau ; Followed by, for N time series: Chronique ; Unite ; Chronique ; Unite ... (N times)"</li>
          <li>
              "station code ; timezone value <i>(<u>format</u>: UTC +/-HH)</i> ; time series code 1 ; time series unit 1 ;
              time series code 2 ; time series unit 2 ... (N times)"
          </li>
          <li>
              "Debut ; Fin ; Valeur ; Qualite ; Min (optional) ; Max (optional) ; Valeur ; Qualite ; Min (optional) ;
              Max (optional) ... (N times)"
          </li>
      </ul>
  </li>
  <li>
      <b>Data:</b> "DD/MM/YYYY HH:MM:SS ; DD/MM/YYYY HH:MM:SS ; value ; quality code ; value (optional) ; value (optional) ;
      value ; quality code ; value (optional) ; value (optional) ... (N times)"
  </li>
  <li>
      <b>Special values and missing values:</b>
      <ul>
          <li>-9999 for gap values;</li>
          <li>The quality code must match the quality set used by the observatory.</li>
      </ul>
  </li>
  <li>
      <b>Comments:</b>
      <ul>
          <li>
              The character '#' indicates a comment line and can also be found inside the line,
              in which case the rest of the line is ignored;
          </li>
          <li>
              Comments are not allowed in the first three lines of the file,
              which must always be the header as described here.
          </li>
      </ul>
  </li>
  <li>
      <b>Min and max columns go compulsorily together but they may be
      present or not depending of each time series.</b>
  </li>
FORMAT
            ,
            'Qjo'=> <<<FORMAT
  <li><b>Header (3 lines):</b> Not used</li>
  <li><b>Data:</b> "QJ0 ; HYDRO2 staion code (not used) ; YYYYMMDD ; value (in l/s) ; C or S ; validity code ;"</li>
  <li><b>Warning:</b> Do not forget the last line which should look like "FIN;EXP-HYDRO;8007;"</li>
FORMAT
            ,
            'Qtvar'=> <<<FORMAT
  <li><b>Header (4 lines):</b> Not used</li>
  <li>
      <b>Data:</b> "920 ; index (not used) ; HYDRO2 staion code (not used) ; YYYYMMDD ; HH:MM ; value (in m3/s) ;
      validity code ; continuity code ;"
  </li>
  <li><b>Warning:</b> Do not forget the last line which should look like "FIN;EXP-HYDRO;8007;"</li>
FORMAT
            ,
            'GrenobleDom'=> <<<FORMAT
  <li>
      <b>Header (1 line):</b>
      <ul>
          <li>At least four fields separated by “\u{a0};\u{a0}”;</li>
          <li>The station code is expected to be in the fourth field, the rest of the line is not used.</li>
      </ul>
  <li>
      <b>Data:</b>
      <ul>
          <li>"DD/MM/YYYY HH:MM:SS ; value ; quality ; comment";</li>
          <li>The line mays also have extra “\u{a0};\u{a0}” after the comment.</li>
      </ul>
  </li>
  <li>
      <b>Special values and missing values:</b>
      <ul>
          <li>-999 for gap values;</li>
          <li>The comment mays be empty or be “\u{a0}ras\u{a0}”, it is not used;</li>
          <li>The quality code must match the quality set Draix which must also be the one used by the observatory.</li>
      </ul>
  </li>
FORMAT
            ,
        ],
    ],
];

/*************************************************
 * IMPORT OF CONTROLES
 ************************************************/

$controleImport = [
    'controle.import'     => [
        'title'                       => 'Checkpoints upload',
        'step'                        => [
            '1' => 'Step 1: checkpoints file',
            '2' => 'Step 2: file validation',
            '3' => 'Step 3: end of the upload',
        ],
        'question'                    => [
            'importType' => 'Do you wish to upload these checkpoints?',
        ],
        'action'                      => [
            'newImport'         => 'New upload',
            'doNotImport'       => 'Do not upload',
            'overwriteExisting' => 'Upload the checkpoints: this will delete all the previous checkpoints.',
        ],
        'help'                        => [
            'quitWarning'            => 'While uploading <b>please use the dedicated button to cancel</b>.',
            'file.onlyOneTimeSeries' => 'Only one time series per file.',
            'timezoneToUTC'          => 'after conversion to UTC',
            'overlap'                => [
                'new'         => 'First upload of checkpoints for this time series.',
                'before'      => 'All the checkpoints to be uploaded are prior to the existing checkpoints.',
                'after'       => 'All the checkpoints to be uploaded are posterior to the existing checkpoints.',
                'boundsEqual' => 'Bounds of the existing checkpoints match those of the checkpoints to be uploaded.',
                'newInOld'    => 'All the checkpoints to be uploaded lie within the existing checkpoints bounds.',
                'oldInNew'    => 'All the existing checkpoints lie within the bounds of the checkpoints to be uploaded.',
                'firstIn'     => 'The beginning of the checkpoints to be uploaded overlaps the end of the existing checkpoints.',
                'lastIn'      => 'The end of the checkpoints to be uploaded overlaps the beginning of the existing checkpoints.',
            ],
        ],
        'info'                        => [
            'wasCanceled'        => 'The checkpoint upload has been canceled',
            'noControleImported' => 'No checkpoints have been uploaded.',
            'timeSeries'         => 'Time series',
            'controlesInsert'    => 'Checkpoint(s) added',
            'controlesDelete'    => 'Checkpoint(s) deleted',
            'nbControles'        => 'Checkpoint(s)',
        ],
        'fileColumns'                 => 'Time series',
        'thereAreErrors.cantContinue' => 'The upload process cannot continue because of errors.',
        'formats.bdoh'                => <<<FORMAT
    <li>
        <b>Header (3 lines):</b>
        <ul>
            <li>"Station ; Fuseau ; Chronique ; Unite"</li>
            <li>"station code ; timezone value <i>(<u>format</u>: UTC +/-HH)</i> ; time series code ; unit"</li>
            <li>"DateHeure ; Valeur ; Qualite ; Min (optional) ; Max (optional)"</li>
        </ul>
    </li>
    <li>
        <b>Data:</b> "DD/MM/YYYY HH:MM:SS ; value ; quality code ; value (optional) ; value (optional)"
    </li>
    <li>
        <b>Comments:</b>
        <ul>
            <li>
                The character '#' indicates a comment line and can also be found inside the line,
                in which case the rest of the line is ignored;
            </li>
            <li>
                Comments are not allowed in the first three lines of the file,
                which must always be the header as described here.
            </li>
        </ul>
    </li>
FORMAT
        ,
    ],
    'controle.management' => [
        'title' => 'Time series checkpoints management',
    ],
    'controle.export'     => [
        'title' => 'Checkpoints download',
        'error' => [
            'noChronique' => 'No time seires selected.',
            'cantFinish'  => 'An error prevented the download to succeed.',
            'noRight'     => "You don't have enough right to download those checkpoints.",
        ],
    ],
];

/*************************************************
 * TRANSFORMATION
 ************************************************/

$transformation = [
    'transformation' => [
        'management' => 'Time series calculation',
        'help'       => [
            'selectionner-chronique-mere' => 'Choose a parent time series',
            'meaning-coefficient'         => 'Multiplying coefficient used to create calculated values in the physical ' .
                'unit of the one of the calculated time series. This unit will be the product of this coefficient with ' .
                'the output units of both of the scales of the parent time series.',
        ],
        'bareme'     => [
            'new'             => 'Add a new scale',
            'import'          => [
                'title'                => 'Scale upload',
                'step'                 => [
                    '1' => 'Step 1: scale file and format',
                    '2' => 'Step 2: file and format validation',
                    '3' => 'Step 3: end of the upload',
                ],
                'help'                 => [
                    'quitWarning' => 'While uploading <b>please use the dedicated button to cancel</b>.',
                ],
                'entete'               => 'Header (3 lines):',
                'detail'               => '<li>"Nom ; Unite entree ; Unite sortie ; Commentaire"</li>
                                           <li>"scale name ; unit label ; unit label ; text (optional)"</li>
                                           <li>"X ; Y ; Qualite ; Min (optional) ; Max (optional)"</li>',
                'donnees'              => '<li><b>Data:</b> "value ; value ; quality code ; value (optional) ; value (optional)"</li>',
                'valid'                => '<li><b>The quality codes must be in the VALIDE format for this observatory.</b></li>',
                'draix'                => '<li><b>The quality codes must be in the Draix format for this observatory.</b></li>',
                'commentaires'         => "<li>
                                               <b>Comments:</b>
                                               <ul>
                                                   <li>The character '#' indicates a comment line and can also be found inside the line in which case the rest of the line is ignored;</li>
                                                   <li>Comments are not allowed in the first three lines of the file which must always be the header as described here.</li>
                                               </ul>
                                           </li>",
                'format'               => 'Scale',
                'stats(%nbDataLines%)' => 'The file contains %nbDataLines% rows of data.',
                'success'              => 'Scale uploaded successfully.',
                'nom'                  => 'Scale name: ',
                'uniteEntree'          => 'Input unit: ',
                'uniteSortie'          => 'Output unit: ',
                'commentaire'          => 'Comment: ',
                'newImport'            => 'New upload',
            ],
            'baremeTechnique' => [
                'identite' => 'Identity',
                'lacune'   => 'Gap',
                'manuel'   => 'Manual',
            ],
            'export'          => [
                'doExport'    => 'Download scales',
                'title'       => 'Scale download',
                'help'        => 'Please select the scale(s) to download from the following list.',
                'noSelection' => 'Please select at least one scale',
            ],
            'dateBareme' => 'd-m-Y H:i:s',
        ],

        'delaiPropagation'       => 'Travel time (minutes)',
        'helpDelaiPropagation'   => 'A positive travel time delays the time series (downstream shifting) while a negative one moves it forward (upstream shifting).',
        'jeu-baremes'            => 'Scale set',
        'jeu-baremes-du(%date%)' => 'Scale set as of %date%',
        'saveAndCompute'         => 'Save and calculate',
        'erreur'                 => [
            'coefficientNonNumerique'        => "Multiplying coefficient “\u{a0}%coeff%\u{a0}” not numeric",
            'delaiNonNumerique'              => "Travel time “\u{a0}%delai%\u{a0}” not numeric",
            'limitPlaceholderNonNumerique'   => "Limit placeholder “\u{a0}%placeholder%\u{a0}” not numeric",
            'chroniqueFilleIntrouvable'      => 'Unknown calculated time series',
            'chroniqueMere.principale'       => 'First parent time series:',
            'chroniqueMere.secondaire'       => 'Second parent time series:',
            'chroniqueMereIntrouvable'       => 'Parent time series not specified or unknown',
            'aucunBareme'                    => 'no defined scale',
            'baremeIntrouvable'              => 'Line %numLigne%: scale not specified or unknown',
            'dateDebutIndefinie'             => 'Line %numLigne%: beginning date of scale application not specified',
            'dateFinIndefinie'               => 'Line %numLigne%: end date of scale application not specified',
            'finAvantDebut'                  => 'Line %numLigne%: end date of scale application (%dateFin%) earlier or equal to the beginning date (%dateDebut%)',
            'conflitDebutFinPrecedent'       => 'Line %numLigne%: beginning date of scale application (%dateDebut%) different from the end date of the previous scale (%dateFin%)',
            'uniteEntreeBaremeIncorrecte'    => "Line %numLigne%: input unit of scale (“\u{a0}%uniteEntreeBareme%\u{a0}”) different from unit of parent time series (“\u{a0}%uniteChroniqueMere%\u{a0}”)",
            'uniteSortieBaremeIncorrecte'    => "Line %numLigne%: output unit of scale (“\u{a0}%uniteSortieBareme\u{a0}”) different from unit of calculated time series (“\u{a0}%uniteChroniqueFille%\u{a0}”)",
            'unitesSortieBaremesDifferentes' => 'Scales have different output units, listed below in the order:',
            'jeuBaremeIntrouvable'           => 'Scale set not found',
        ],
        'submitted'              => [
            'submitted'  => "The calculation of the time series “\u{a0}<a href='_linkToChronique_'>_chroniqueName_</a>\u{a0}” has been submitted and should take a few moments.",
            'seeJobPage' => "You may check the end of the calculation from <a href='_linkToJobPage_'>the job page</a> (job #_jobId_).",
        ],
        'confirmComputation'     => 'If you submit the calculation you will no longer be able to change the parent time series or travel times. Do you wish to continue?',
        'cancelModifications'    => 'Cancel modifications',
    ],
];

/*************************************************
 * IMPORT OF SHAPE
 ************************************************/

$shapeImport = [
    'shape.import'     => [
        'title'                       => 'Geographic data upload',
        'step'                        => [
            '1' => 'Step 1: geographic data file',
            '2' => 'Step 2: file validation',
            '3' => 'Step 3: end of the upload',
        ],
        'question'                    => [
            'importType' => 'What do you wish to upload?',
        ],
        'action'                      => [
            'newImport'         => 'New upload',
            'doNotImport'       => 'Do not upload anything',
            'doImport'          => 'Upload the new data',
            'replace'           => 'Replace the existing data',
            'keepExisting'      => 'Upload only the new data',
            'overwriteExisting' => 'Upload the new data and replace the existing data',
        ],
        'help'                        => [
            'quitWarning'        => 'While uploading <b>please use the dedicated button to cancel</b>.',
            'file.shape'         => 'Provide a full shapefile containing the geographic data (at least <strong>shx</strong>, <strong>shp</strong> and <strong>dbf</strong> files).',
            'requiredProjection' => '<b>Data projection should be Lambert93 - EPSG:2154.</b>',
            'sizeLimit'          => 'The upload file size must not exceed 2 MB.',
            'station'            => [
                'existingWithData'    => 'Existing station with geographic data|Existing stations with geographic data',
                'existingWithoutData' => 'Existing station without geographic data|Existing stations without geographic data',
                'nonExisting'         => 'Unknown station|Unknown stations',
            ],
            'courseau'           => [
                'new'       => 'New river|New rivers',
                'existing'  => 'Existing river|Existing rivers',
                'nom'       => '(according to the name of the river)',
            ],
            'bassin'             => [
                'new'         => 'New basin|New basins',
                'existing'    => 'Existing basin|Existing basins',
                'nopeStation' => 'Basin with unknown outlet station|Basins with unknown outlet station',
            ],
        ],
        'info'                   => [
            'title'               => 'Upload geographic data of type',
            'wasCanceled'         => 'The geographic data upload has been canceled.',
            'noShapeImported'     => 'No geographic data have been added.',
            'noPossibleImport'    => 'No geographic data can be added.',
            'shapeInsert'         => 'Geographic data added',
            'shapeUpdate'         => 'Geographic data updated',
            'noData'              => 'No geographic data',
            'nom'                 => 'Name',
            'wontupload'          => '(<u>these data cannot be uploaded</u>)',
            'codeStationExutoire' => 'Outlet station code',
            'codeStation'         => 'Code',
        ],
        'fileColumns'                 => '????',
        'thereAreErrors.cantContinue' => 'The upload process cannot continue because of errors.',
        'format'                      => [
            'bassin'   => <<<FORMAT
        <li><b>One zipped file with the same name as all the files inside, without folders.</b></li>
        <li><b>Expected attribute columns, without specific order:</b></li>
        <ul>
            <li><b>nom</b>, for the name of the basin;</li>
            <li><b>code_exu</b>, for the code of the outlet station of the basin, can be empty, the code must be a string.</b></li>
        </ul>
FORMAT
            ,
            'coursEau' => <<<FORMAT
        <li><b>One zipped file with the same name as all the files inside, without folders.</b></li>
        <li><b>Expected attribute columns, without specific order (based on the Carthage format):</b></li>
        <ul>
            <li><b>toponyme</b>, for the name of the river;</li>
            <li><b>code_hydro</b>, for the hydro code of the river, can be empty, the code must be a string;</li>
            <li><b>classifica</b>, for the Strahler number of the river, can be empty, must be a number.</b></li>
        </ul>

FORMAT
            ,
            'station'  => <<<FORMAT
        <li><b>One zipped file with the same name as all the files inside, without folders.</b></li>
        <li><b>Expected attribute column:</b></li>
        <ul>
            <li>code</b>, for the code of the station, the code must be a string.</li>
        </ul>
FORMAT
            ,
        ],
    ],
];

/*************************************************
 * CONVERSION
 ************************************************/

$conversion = [
    'conversion' => [
        'chroniqueMere'                  => 'Parent time series',
        'paramConversion'                => 'Gap threshold',
        'paramConversionHelp'            => 'Time interval <b><u>in minutes</u></b> between two successive data ranges that, if exceeded, results in the insertion of gaps.<br>' .
            'Gaps are inserted at + and − 1 seconde from the surrounding data ranges.',
        'paramConversionTitle'           => 'Number of minutes, minimum 1 minute.',
        'paramConversionError'           => 'minimum 1 minute.',
        'saveAndConvert'                 => 'Save and convert',
        'cancelModifications'            => 'Cancel modifications',
        'confirmConversion'              => 'If you submit the conversion you will no longer be able to change the parent time series. Do you wish to continue?',
        'noChroniqueMere'                => 'No discontinuous time series with this unit on this station.',
        'success(%link%, %chronique%)'   => "The conversion of the time series “\u{a0}<a href=\"%link%\">%chronique%</a>\u{a0}” has been submitted and should take a few moments.",
        'seeJobPage(%jobPage%, %jobId%)' => 'You may check the end of the conversion from <a href="%jobPage%">the job page</a> (job #%jobId%).',
        'CSRFerror'                      => 'The form validity expired and has been reset, please send the form again.',
    ],
];

/*************************************************
 * COLORS
 ************************************************/

$colors = [
    'colors' => [
        'titles'=> [
            'qualite'       => 'Qualities styles',
            'discontinuous' => 'Discontinuous time series markers',
            'checkpoint'    => 'Checkpoints',
        ],
        'default'=> [
            'qualite'       => 'For the simple-viewer, the initial values are: ' .
                'color #FF0000 (red) and thickness 5 for the qualities processed as a gap and ' .
                'color #0000FF (blue) and thickness 1 for the other qualities.<br>' .
                'For information: For the multi-viewer, one color is generated automatically and used ' .
                'for the whole time series (including gap qualities that are displayed with a transparency effect). ' .
                'Line thickness is 5 for the gap qualities and 1 for the others. ' .
                'These styles cannot be changed.',
            'discontinuous' => 'For the simple-viewer, the initial values are: shape ●, thickness 2 and size 5.<br>' .
                'For information: For the multi-viewer the following values are used: ' .
                'shape ●, thickness 2 and size 5. ' .
                'This style cannot be changed.',
            'checkpoint'    => 'For the simple-viewer, the initial values are: ' .
                'shape ○, thickness 2, color #008000 (green) and size 5.<br>' .
                'For information: Checkpoints are not displayed on the multi-viewer.',
        ],
        'title'          => 'Viewer styles',
        'longTitle'      => 'Styles for the simple viewer',
        'success(%jeu%)' => "The qualities colors for the set “\u{a0}%jeu%\u{a0}” have been saved.",
        'color'          => 'Color',
        'thickness'      => 'Line thickness',
        'gap'            => 'This quality is processed as a gap.',
        'shape'          => 'Shape',
        'stroke'         => 'Stroke thickness',
        'strokeHelp'     => 'Stroke thickness of the non-filled shapes only.',
        'size'           => 'Size',
        'save'           => 'Save',
        'cancel'         => 'Cancel modifications',
        'CSRFerror'      => 'The form validity expired and has been reset, please send the form again.',
    ],
];

/*************************************************
 * VARIOUS MESSAGES
 ************************************************/

$various = [
    'data'        => [
        'management' => 'Data management',
    ],
    'button' => [
        'add' => 'Create',
    ],

    'admin'       => [
        'measuresImport' => 'Data upload',
        'controleImport' => 'Checkpoint upload and download',
        'sendJson'       => [
            'save'              => 'Save',
            'saveMessage'       => 'Check this to enable automatic updates',
            'send'              => 'Send your JSON',
            'result'            => 'Result',
            'resultMessage'     => 'Your JSON valided',
            'resultMessageFail' => 'Your JSON failed!',
            'schema'            => 'Validation schema',
        ],
        'shapeImport'    => 'Geographic data upload',
        'help'           => [
            'astuce'                              => '<strong style="color:#0e90d2; font-size: 10px">Tip: Click on the line to display the existing drop-down list. Write the first letters of the search word to filter.</strong>',
            'from_the_observatory'                => 'from the observatory',
            'inspireTheme'                        => [
                'nom' => "Offcial list of INSPIRE theme<strong> in english</strong> at this link : <a href='https://inspire.ec.europa.eu/theme' target='_blank>Here</a> ",
            ],
            'topicCategory' => [
                'nom' => "Official list of INSPIRE topicCategory<strong> in english</strong> at this link : <a href='https://inspire.ec.europa.eu/metadata-codelist/TopicCategory' target='_blank>Here</a> ",
            ],
            'theia'                => [
                'theia-message'        => '<strong style="color:red; font-size: 10px">[Theia/Ozcar fields]</strong><br> ',
                'warning-update-theia' => '<strong>Attention :</strong> Veuillez compléter les nouveaux champs pour Theia/Ozcar',
                'uriOzcarTheia'        => "This field is the corresponding variable category (or variables categories) on the Theia / Ozcar Thesaurus (you must write the letters of the research word) of the fields <strong> Environment </strong> and of the <strong> Parameter Categories </strong> BDOH. Link to Skomos. You can find your variable category : <a href='https://in-situ.theia-land.fr/skosmos/theia_ozcar_thesaurus/en/page/c_341ce66a' target='_blank'>Here</a>",
            ],
            'observatoire'         => [
                'theiaCode-message'                        => "<strong style=\"color:red; font-size: 10px\">[Theia/Ozcar fields]</strong><br>Corresponds to the first 4 capital letters of the Observatory. <br> The identifiers refer to the Theia / Ozcar pivot data model (Annex 1 page 15) : <a href='https://theia-ozcar.gricad-pages.univ-grenoble-alpes.fr/doc-producer/producer-documentation.html#modele-de-donnees-pivot-description-et-implementation-en-json' target='_blank'>Here</a> ",
                'theiaPassword-message'                    => "The password is not displayed in this field for privacy reasons. Leave it as is for safekeeping.",
                'jeu'                                      => 'The change of quality code set should be restricted to empty observatories with no uploaded data.',
                'warning-observatory-has-sites'            => 'This observatory cannot be deleted because it contains the following experimental site:|This observatory cannot be deleted because it contains the following experimental sites:',
                'warning-observatory-has-scales'           => 'This observatory has the following scale:|This observatory has the following scales:',
                'warning-observatory-has-dois'             => 'This observatory has the following DOI:|This observatory has the following DOIs:',
                'warning-observatory-has-basins'           => 'This observatory has the following basin:|This observatory has the following basins:',
                'warning-observatory-has-rivers'           => 'This observatory has the following river:|This observatory has the folowing rivers:',
                'confirm-delete-observatory(%name%)'       => "Are you sure you want to delete the observatory “\u{a0}%name%\u{a0}”?",
                'email-info'                               => 'Email of generic conatct of this producer.',
                'confirm-delete-observatory-info'          => '<strong style="color:red">The listed scales, basins, rivers and/or DOIs will be deleted as well.</strong>',
                'warning-delete-observatory-info-redirect' => 'This is the current observatory, if you delete it you will be redirected to the home page of all the observatories.',
            ],
            'dataset' => [
                'contact'                        => "<strong style=\"color:red; font-size: 10px\">[Champ pour Theia/Ozcar]</strong><br>Theia/Ozcar demande la présence obligatoire d'au moins un Project Leader",
                'description'                    => 'The absract must describe the resource.',
                'genealogy'                      => 'Describes the genealogy of a dataset, i.e. the history of the dataset and, if known, its life cycle, from the acquisition and entry of information to its compilation with other games and variants of its current form.  ',
            ],
            'site'                 => [
                'warning-site-has-stations' => 'To delete this experimental site you must first dissociate the following station from it:|To delete this experimental site you must first dissociate the following stations from it:',
            ],
            'station'              => [
                'code'                             => 'Characters with accents and special of this code will be automatically replaced.',
                'codeAlternatif'                   => "Station codes separated by “\u{a0};\u{a0}” in the form of “\u{a0}Format=CODE\u{a0}”, used to download time series in a non-BDOH format. Example: Hydro2 = MY_HYDRO2_CODE",
                'latlong'                          => 'Lambert93 - EPSG:2154 (meters)',
                'warning-station-has-time-series'  => 'This station cannot be deleted because it contains the following time series:|This station cannot be deleted because it contains the following time series:',
                'warning-station-has-basins'       => 'This station is used by the following basin as its outlet station:|This station is used by the following basins as their outlet station:',
                'town-name-is-case-sensitive'      => '<u>Warning: the search filter is case-sensitive, all town names have capital letters and compound names use dashes and not spaces ' .
                    '(ex.: Saint-Germain-sur-l\'Arbresle, Sainte-Foy-lès-Lyon, etc.), in doubt use a part of the name that can\'t have capital letters nor dashes or use the zip code.</u>',
                'confirm-delete-station(%name%)'   => "Are you sure you want to delete the station “\u{a0}%name%\u{a0}”?",
                'confirm-delete-station-info'      => "The listed basins won't be deleted, they will just lose their outlet station.",
            ],
            'chronique'            => [
                'code'                                   => 'Characters with accents and special characters will be automatically replaced.',
                'libelle'                                => 'A short text describing the time series in a unique, specific way...',
                'estVisible'                             => 'Is the time series visible to the public, yes or no?',
                'warning-has-calculated-time-series'     => 'This time series cannot be deleted because it is used to calculate the following time series:|This time series cannot be deleted because it is used to calculate the following time series:',
                'warning-has-converted-time-series'      => 'This time series cannot be deleted because it is used for the conversion of the following time series:|This time series cannot be deleted because it is used for the conversion of the following time series:',
                'warning-no-export-options-were-defined' => '<strong>Warning:</strong> options for regular time step downloads were not defined for this time series yet. Default values have been automatically set. Please check/edit the values and save the modifications.',
                'contains-n-mesures(%nb%)'               => 'This time series has one data point.|This time series has %nb% data points.',
                'contains-n-plages(%nb%)'                => 'This time series has one data range.|This time series has %nb% data ranges.',
                'contains-n-controles(%nb%)'             => 'This time series has one checkpoint.|This time series has %nb% checkpoints.',
                'confirm-delete-time-series(%name%)'     => "Are you sure you want to delete the time series “\u{a0}%name%\u{a0}”?",
                'confirm-delete-time-series-info'        => '<u>The data points, data ranges and/or checkpoints of this time series will be deleted as well.</u>',
                'update-unite-cumul'                     => 'If you allow this time series to be downloaded as accumulations please check or update the unit for accumulations below.',
                'warning-no-milieu-defined'              => '<strong>Warning:</strong> This time series does not have an environment (the first one is selected by default). You must select an environment for this type and save the modification.',
            ],
            'commune'              => [
                'warning-commune-has-stations'   => 'This town is used by the following station:|This town is used by the following stations:',
                'confirm-delete-commune(%name%)' => "Are you sure you want to delete the town “\u{a0}%name%\u{a0}”?",
                'confirm-delete-commune-info'    => "The listed stations won't be deleted, they will just lose their town.",
            ],
            'doi'                  => [
                'identifiant' => 'Identifier example: 10.17180/MY.OBSERVATORY',
                'description' => 'Details about the data such as: authors, year, title, institution (most often INRAE), the DOI is automatically added to the description.',
            ],
            'partenaire'           => [
                'warning-partenaire-has-time-series'   => 'This partner cannot be deleted because it is used by the following time series as a producer:|This partner cannot be deleted because it is used by the following time series as a producer:',
                'warning-partenaire-has-observatories' => 'This partner cannot be deleted because it is used by the following observatory:|This partner cannot be deleted because it is used by the following observatories:',
                'partenaire-has-time-series'           => 'This partner is used by the following time series as a producer:|This partner is used by the following time series as a producer:',
                'partenaire-has-observatories'         => 'This partner is used by the following observatory:|This partner is used by the following observatories:',
                'confirm-delete-partenaire(%name%)'    => "Are you sure you want to delete the partner “\u{a0}%name%\u{a0}”?",
                'confirm-delete-partenaire-info'       => "The listed time series and/or observatories won't be deleted, they will just lose their partner or producer.",
                'help-scanR'                           => 'Scan Id on the website <a href="https://scanr.enseignementsup-recherche.gouv.fr/" target="_blank">https://scanr.enseignementsup-recherche.gouv.fr/</a> Example : Unité PIAF, ID: 200017466P</strong>',
            ],
            'familleParametres'    => [
                'warning-familleParametres-has-children' => 'This parameter category cannot be deleted because it is used by the following parameter category (as its parent category):|This parameter category cannot be deleted because it is used by the following parameter categories (as their parent category):',
                'warning-familleParametres-has-types'    => 'This parameter category cannot be deleted because it is used by the following parameter type:|This parameter category cannot be deleted because it is used by the following parameter types:',
                'help-prefix'                            => 'The prefix is used for ordering the categories of the same hierarchic level (for exemple: 001, 002, 003, ...), it is not displayed.',
                'help-familleParente'                    => 'Child and descendant categories have been removed from this selection list. You may also remove the parent category with the little cross on the right side.',
            ],
            'typeParametre'        => [
                'warning-typeParametre-has-time-series' => 'This parameter type cannot be deleted because it is used by the following time series:|This parameter type cannot be deleted because it is used by the following time series:',
                'warning-no-family-defined'             => '<strong>Warning:</strong> This parameter type does not have a parameter category (the first one is selected by default). You must select a parameter category for this type and save the modification.',
            ],
            'unite'                => [
                'warning-unit-has-time-series'     => 'This unit cannot be deleted because it is used by the following time series:|This unit cannot be deleted because it is used by the following time series:',
                'warning-unit-has-scales'          => 'This unit cannot be deleted because it is used by the following scale:|This unit cannot be deleted because it is used by the following scales:',
                'warning-unit-has-parameter-types' => 'This unit is used by the following parameter type:|This unit is used by the following parameter types:',
                'confirm-delete-unit(%name%)'      => "Are you sure you want to delete the unit “\u{a0}%name%\u{a0}”?",
                'confirm-delete-unit-info'         => "The listed parameter types won't be deleted, they will just lose this unit.",
            ],
        ],
    ],
    'utilisateur' => [
        'newAccountMail' => [
            'body'   => 'Hello,

An account has been created for you on BDOH.

Your login is the e-mail address at which you received this message and the password is “\u{a0}password\u{a0}”, we invite you to change it as soon as you sign in.

You can ask for specific data access form the following link: ',
            'footer' => 'This e-mail is automatically generated, thank you for not replying.',
            'title'  => 'Account opened on BDOH',
        ],
    ],
    'export'      => [
        'exportMail' => [
            'body'               => 'Please find your downloaded time series as an attachement.',
            'footer'             => 'This e-mail is automatically generated, thank you for not replying.',
            'title(%chronique%)' => "BDOH: Downloaded time series “\u{a0}%chronique%\u{a0}”",
        ],
    ],
    'backToBdoh'             => 'Get back to BDOH',
    'forAllTheObservatories' => 'For all the observatories',
    'title_edit_defined'     => "%entity% “\u{a0}%name%\u{a0}”",
    'title_create_undefined' => '%entity%',
    'title_delete_defined'   => "%entity% “\u{a0}%name%\u{a0}”",
    'new'                    => 'New',
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($entitiesActions, $fieldSets, $fileFormats, $measureImport, $various, $controleImport, $transformation, $shapeImport, $conversion, $colors);
