<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

define('IrsteaBdohDataBundle_transDir', __DIR__ . '/../../../BdohDataBundle/Resources/translations/');
define('IrsteaBdohSecurityBundle_transDir', __DIR__ . '/../../../BdohSecurityBundle/Resources/translations/');

$entitiesSingulars_Data = require IrsteaBdohDataBundle_transDir . 'entitiesSingulars.fr.php';
$entitiesSingularsArticles_Data = require IrsteaBdohDataBundle_transDir . 'entitiesSingularsArticles.fr.php';
$entitiesSingularsDefinedArticles_Data = require IrsteaBdohDataBundle_transDir . 'entitiesSingularsDefinedArticles.fr.php';
$entitiesPlurals_Data = require IrsteaBdohDataBundle_transDir . 'entitiesPlurals.fr.php';

$entitiesSingulars_Security = require IrsteaBdohSecurityBundle_transDir . 'entitiesSingulars.fr.php';
$entitiesSingularsArticles_Security = require IrsteaBdohSecurityBundle_transDir . 'entitiesSingularsArticles.fr.php';
$entitiesSingularsDefinedArticles_Security = require IrsteaBdohSecurityBundle_transDir . 'entitiesSingularsDefinedArticles.fr.php';
$entitiesPlurals_Security = require IrsteaBdohSecurityBundle_transDir . 'entitiesPlurals.fr.php';

/*************************************************
 * ENTITIES ACTIONS (for SonataAdminBundle)
 ************************************************/

$entitiesActions = [];

// For BdohDataBundle
$entitiesKeys = array_keys($entitiesSingulars_Data);

foreach ($entitiesKeys as $key) {
    $entitiesActions[$key . '_list'] =
        'Liste des ' .
        ($entitiesPlurals_Data[$key] === 'DOI' ? $entitiesPlurals_Data[$key] : lcfirst($entitiesPlurals_Data[$key]));
    $entitiesActions[$key . '_create'] =
        'Créer ' . $entitiesSingularsArticles_Data[$key] . ' ' .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_create_undefined'] =
        "Création d'" . $entitiesSingularsArticles_Data[$key] . ' ' .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_edit'] =
        'Éditer ' . $entitiesSingularsArticles_Data[$key] . ' ' .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_edit_defined'] =
        'Modification ' . $entitiesSingularsDefinedArticles_Data[$key] .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_delete'] =
        'Supprimer ' . $entitiesSingularsArticles_Data[$key] . ' ' .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
    $entitiesActions[$key . '_delete_defined'] =
        'Suppression ' . $entitiesSingularsDefinedArticles_Data[$key] .
        ($entitiesSingulars_Data[$key] === 'DOI' ? $entitiesSingulars_Data[$key] : lcfirst($entitiesSingulars_Data[$key]));
}

// For BdohSecurityBundle
$entitiesKeys = array_keys($entitiesSingulars_Security);

foreach ($entitiesKeys as $key) {
    $entitiesActions[$key . '_list'] =
        'Liste des ' . lcfirst($entitiesPlurals_Security[$key]);
    $entitiesActions[$key . '_create'] =
        'Créer ' . $entitiesSingularsArticles_Security[$key] . ' ' . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_create_undefined'] =
        "Création d'" . $entitiesSingularsArticles_Security[$key] . ' ' . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_edit'] =
        'Éditer ' . $entitiesSingularsArticles_Security[$key] . ' ' . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_edit_defined'] =
        'Modification ' . $entitiesSingularsDefinedArticles_Security[$key] . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_delete'] =
        'Supprimer ' . $entitiesSingularsArticles_Security[$key] . ' ' . lcfirst($entitiesSingulars_Security[$key]);
    $entitiesActions[$key . '_delete_defined'] =
        'Suppression ' . $entitiesSingularsDefinedArticles_Security[$key] . lcfirst($entitiesSingulars_Security[$key]);
}

/*************************************************
 * FIELDSETS
 ************************************************/

$fieldSets = [
    'fieldset.text'    => 'Descriptions et autres',
    'fieldset.graphic' => "Options d'affichage et photos",
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
        'Bdoh'                  => 'BDOH - continu',
        'BdohPlage'             => 'BDOH - discontinu',
        'controle'              => 'Points de contrôle',
    ],
];

/*************************************************
 * IMPORT OF MEASURES
 ************************************************/

$measureImport = [
    'measure.import' => [
        'title'                                  => 'Import de mesures',
        'step'                                   => [
            '1' => 'Étape 1 : fichier de mesures et format',
            '2' => 'Étape 2 : station, fuseau horaire et chroniques',
            '3' => "Étape 3 : validation de l'import",
            '4' => "Étape 4 : fin de l'import",
        ],
        'question'                               => [
            'importType'      => 'Comment souhaitez-vous importer ces mesures ?',
            'computeChildren' => 'Mettre à jour la chronique fille|Mettre à jour les chroniques filles',
        ],
        'action'                                 => [
            'doImport'          => 'Les importer.',
            'doNotImport'       => 'Ne pas les importer.',
            'keepExisting'      => "Conserver l'existant en cas de chevauchement.",
            'overwriteExisting' => "Écraser l'existant au profit des nouvelles mesures en cas de chevauchement.",
            'newImport'         => 'Nouvel import',
        ],
        'help'                                   => [
            'quitWarning'         => "En cours d'import <b>merci de n'annuler que par le bouton prévu à cet effet</b>.",
            'file.onlyOneStation' => "Un fichier contient des données d'une seule station.",
            'file.bigInZip'       => 'Vous pouvez envoyer vos <u>gros fichiers de mesures</u> sous format <b>zip</b> <i>(un fichier par zip)</i>.',
            'file.sizeLimit'      => 'Le fichier envoyé ne doit pas dépasser 2 Mo.',
            'chroniquesBox'       => "<b>Mode d'emploi :</b> Faites correspondre chaque colonne à importer à une chronique en faisant glisser cette dernière vers la gauche.",
            'timezoneToUTC'       => 'après passage en UTC',
            'overlap'             => [
                'new'         => 'Premier import pour cette chronique.',
                'before'      => 'Toutes les mesures à importer sont antérieures aux mesures existantes.',
                'after'       => 'Toutes les mesures à importer sont postérieures aux mesures existantes.',
                'boundsEqual' => 'Les bornes des mesures existantes et des mesures à importer sont égales.',
                'newInOld'    => 'Les mesures à importer sont entièrement incluses dans les bornes des mesures existantes.',
                'oldInNew'    => 'Les mesures existantes sont entièrement incluses dans les bornes des mesures à importer.',
                'firstIn'     => 'Le début des mesures à importer chevauche la fin des mesures existantes.',
                'lastIn'      => 'La fin des mesures à importer chevauche le début des mesures existantes.',
            ],
            'children'            => 'Chronique fille|Chroniques filles',
        ],
        'info'                                   => [
            'wasCanceled'             => "L'import de mesures a été annulé.",
            'noMeasuresImported'      => "Aucune mesure n'a été importée.",
            'measuresInsert'          => 'Mesure(s) insérée(s)',
            'measuresDelete'          => 'Mesure(s) supprimée(s)',
            'computingOfFillingRates' => '<b>Attention :</b> le calcul des taux de remplissage est en cours, ' .
                'cette opération peut prendre quelques minutes pendant lesquelles leur affichage est obsolète.',
            'jobPage(%jobPageLink%)'  => 'Vous pouvez vérifier la fin de ce calcul depuis <a href="%jobPageLink%">la page des jobs</a>.',
            'children(%count%)'       => 'Cette chronique a une chronique fille|Cette chronique a %count% chroniques filles',
        ],
        'fileColumns'                            => 'Colonne(s) du fichier',
        'thereAreErrors.cantContinue'            => "L'import ne peut se poursuivre car il y a des erreurs.",
        'valeurTooLarge(%first%,%last%,%count%)' => '{0}|' .
            '{1}Une mesure est supérieure au maximum, le <i>%first%</i>|' .
            ']1,Inf]Du <i>%first%</i> au <i>%last%</i> inclus : ily a <b>%count%</b> mesures supérieures au maximum',
        'valeurTooSmall(%first%,%last%,%count%)' => '{0}|' .
            '{1}Une mesure est inférieure au minimum, le <i>%first%</i>|' .
            ']1,Inf]Du <i>%first%</i> au <i>%last%</i> inclus : il y a <b>%count%</b> mesures inférieures au minimum',
        'analysisInProgress'                     => 'Analyse en cours',
        'processingInProgress'                   => 'Traitement en cours',
        'canTakeAWhile'                          => 'Cette opération peut prendre du temps',
        'pleaseWait'                             => 'Veuillez patienter...',
        'timeSeries'                             => 'Chronique(s)',
        'ignoredTimeSeries'                      => 'Chronique ignorée',
        'removeTimeSeries'                       => 'Ne pas inclure cette chronique',
        'restoreTimeSeries'                      => 'Restaurer cette chronique',
        'formats'                                => [
            'Bdoh'=> <<<FORMAT
  <li>
      <b>Entête (3 lignes) :</b>
      <ul>
          <li>"Station ; Fuseau ; Puis, pour N chroniques : Chronique ; Unite ; Chronique ; Unite ... (N fois)"</li>
          <li>
              "code station ; valeur fuseau <i>(<u>format</u> : UTC +/-HH)</i> ; code chronique 1 ; unité chronique 1 ;
              code chronique 2 ; unité chronique 2 ... (N fois)"
          </li>
          <li>
              "DateHeure ; Valeur ; Qualite ; Min (optionnel) ; Max (optionnel) ; Valeur ; Qualite ; Min (optionnel) ;
              Max (optionnel) ... (N fois)"
          </li>
      </ul>
  </li>
  <li>
      <b>Données :</b> "JJ/MM/AAAA HH:MM:SS ; valeur ; code qualité ; valeur (optionnel) ; valeur (optionnel) ;
      valeur ; code qualité ; valeur (optionnel) ; valeur (optionnel) ... (N fois)"
  </li>
  <li>
      <b>Valeurs manquantes ou spéciales :</b>
      <ul>
          <li>-9999 pour les lacunes ;</li>
          <li>La qualité doit utiliser le jeu de codes défini pour l'observatoire.</li>
      </ul>
  </li>
  <li>
      <b>Commentaires :</b>
      <ul>
          <li>
              Le caractère '#' marque une ligne de commentaire et peut également se trouver en cours de ligne,
              auquel cas toute la suite de la ligne est commentée ;
          </li>
          <li>
              Les commentaires ne sont pas autorisés sur les trois premières lignes du fichier,
              qui doivent toujours être l'entête tel que décrit ici.
          </li>
      </ul>
  </li>
  <li>
      <b>Les colonnes min et max vont obligatoirement par paires par contre elles peuvent
      être présentes ou absentes en fonction de chaque chronique.</b>
  </li>
FORMAT
            ,
            'BdohPlage'=> <<<FORMAT
  <li>
      <b>Entête (3 lignes) :</b>
      <ul>
          <li>"Station ; Fuseau ; Puis, pour N chroniques : Chronique ; Unite ; Chronique ; Unite ... (N fois)"</li>
          <li>
              "code station ; valeur fuseau <i>(<u>format</u> : UTC +/-HH)</i> ; code chronique 1 ; unité chronique 1 ;
              code chronique 2 ; unité chronique 2 ... (N fois)"
          </li>
          <li>
              "Debut ; Fin ; Valeur ; Qualite ; Min (optionnel) ; Max (optionnel) ; Valeur ; Qualite ; Min (optionnel) ;
              Max (optionnel) ... (N fois)"
          </li>
      </ul>
  </li>
  <li>
      <b>Données :</b> "JJ/MM/AAAA HH:MM:SS ; JJ/MM/AAAA HH:MM:SS ;  valeur ; code qualité ; valeur (optionnel) ;
      valeur (optionnel) ; valeur ; code qualité ; valeur (optionnel) ; valeur (optionnel) ... (N fois)"
  </li>
  <li>
      <b>Valeurs manquantes ou spéciales :</b>
      <ul>
          <li>-9999 pour les lacunes ;</li>
          <li>La qualité doit utiliser le jeu de codes défini pour l'observatoire.</li>
      </ul>
  </li>
  <li>
      <b>Commentaires :</b>
      <ul>
          <li>
              Le caractère '#' marque une ligne de commentaire et peut également se trouver en cours de ligne,
              auquel cas toute la suite de la ligne est commentée ;
          </li>
          <li>
              Les commentaires ne sont pas autorisés sur les trois premières lignes du fichier,
              qui doivent toujours être l'entête tel que décrit ici.
          </li>
      </ul>
  </li>
  <li>
      <b>Les colonnes min et max vont obligatoirement par paires par contre elles peuvent être
      présentes ou absentes en fonction de chaque chronique.</b>
  </li>
FORMAT
            ,
            'Qjo'=> <<<FORMAT
  <li><b>Entête (3 lignes) :</b> Inexploité</li>
  <li><b>Données :</b> "QJ0 ; code station HYDRO2 (inexploité) ; AAAAMMJJ ; valeur (en l/s) ; C ou S ; code validité ;"</li>
  <li><b>Attention :</b> Ne pas oublier la dernière ligne de la forme "FIN;EXP-HYDRO;8007;"</li>
FORMAT
            ,
            'Qtvar'=> <<<FORMAT
  <li><b>Entête (4 lignes) :</b> Inexploité</li>
  <li>
      <b>Données :</b> "920 ; index (inexploité) ; code station HYDRO2 (inexploité) ; AAAAMMJJ ; HH:MM ; valeur (en m3/s) ;
      code validité ; code de continuité ;"
  </li>
  <li><b>Attention :</b> Ne pas oublier la dernière ligne de la forme "FIN;EXP-HYDRO;8007;"</li>
FORMAT
            ,
            'GrenobleDom'=> <<<FORMAT
  <li>
      <b>Entête (1 ligne) :</b>
      <ul>
          <li>Au moins quatre champs séparés par des «\u{a0};\u{a0}» ;</li>
          <li>Le code de la station est attendu en quatrième position, le reste est inexploité.</li>
      </ul>
  <li>
      <b>Données :</b>
      <ul>
          <li>"JJ/MM/AAAA HH:MM:SS ; valeur ; qualité ; commentaire" ;</li>
          <li>La ligne contient éventuellement des «\u{a0};\u{a0}» surnuméraires au-delà du commentaire.</li>
      </ul>
  </li>
  <li>
      <b>Valeurs manquantes ou spéciales :</b>
      <ul>
          <li>-999 pour les lacunes ;</li>
          <li>Le commentaire peut être vide ou contenir «\u{a0}ras\u{a0}», il est ignoré ici ;</li>
          <li>La qualité doit utiliser le jeu de codes Draix qui doit aussi être celui défini pour l'observatoire.</li>
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
        'title'                       => 'Import de points de contrôle',
        'step'                        => [
            '1' => 'Étape 1 : fichier de points de contrôle',
            '2' => "Étape 2 : validation de l'import",
            '3' => "Étape 3 : fin de l'import",
        ],
        'question'                    => [
            'importType' => 'Souhaitez-vous importer ces points de contrôle ?',
        ],
        'action'                      => [
            'newImport'         => 'Nouvel import',
            'doNotImport'       => 'Ne pas les importer',
            'overwriteExisting' => "Importer les nouvelles donnnées : cela supprimera l'intégralité des points existants.",
        ],
        'help'                        => [
            'quitWarning'            => "En cours d'import <b>merci de n'annuler que par le bouton prévu à cet effet</b>.",
            'file.onlyOneTimeSeries' => "Un fichier contient des données d'une seule chronique.",
            'timezoneToUTC'          => 'après passage en UTC',
            'overlap'                => [
                'new'         => 'Premier import de points de contrôle pour cette chronique.',
                'before'      => 'Touts les points de contrôle à importer sont antérieures aux points de contrôle existants.',
                'after'       => 'Touts les points de contrôle à importer sont postérieures aux points de contrôle existants.',
                'boundsEqual' => 'Les bornes des points de contrôle existants et des points de contrôle à importer sont égales.',
                'newInOld'    => 'Les points de contrôle à importer sont entièrement incluses dans les bornes des points de contrôle existants.',
                'oldInNew'    => 'Les points de contrôle existants sont entièrement incluses dans les bornes des points de contrôle à importer.',
                'firstIn'     => 'Le début des points de contrôle à importer chevauchent la fin des points de contrôle existants.',
                'lastIn'      => 'La fin des points de contrôle à importer chevauchent le début des points de contrôle existants.',
            ],
        ],
        'info'                        => [
            'wasCanceled'        => "L'import de points de contrôles a été annulé.",
            'noControleImported' => "Aucun point de contrôle n'a été importé.",
            'timeSeries'         => 'Chronique',
            'controlesInsert'    => 'Point(s) de contrôle inséré(s)',
            'controlesDelete'    => 'Point(s) de contrôle supprimé(s)',
            'nbControles'        => 'Point(s) de contrôle',
        ],
        'fileColumns'                 => 'Chronique concernée',
        'thereAreErrors.cantContinue' => "L'import ne peut se poursuivre car il y a des erreurs.",
        'formats.bdoh'                => <<<FORMAT
    <li>
        <b>Entête (3 lignes) :</b>
        <ul>
            <li>"Station ; Fuseau ; Chronique ; Unite"</li>
            <li>"code station ; valeur fuseau <i>(<u>format</u> : UTC +/-HH)</i> ; code chronique ; unité"</li>
            <li>"DateHeure ; Valeur ; Qualite ; Min (optionnel) ; Max (optionnel)"</li>
        </ul>
    </li>
    <li>
        <b>Données :</b> "JJ/MM/AAAA HH:MM:SS ; valeur ; code qualité ; valeur (optionnel) ; valeur (optionnel)"
    </li>
    <li>
        <b>Commentaires :</b>
        <ul>
            <li>
                Le caractère '#' marque une ligne de commentaire et peut également se trouver en cours de ligne,
                auquel cas toute la suite de la ligne est commentée ;
            </li>
            <li>
                Les commentaires ne sont pas autorisés sur les trois premières lignes du fichier,
                qui doivent toujours être l'entête tel que décrit ici.
            </li>
        </ul>
    </li>
FORMAT
        ,
    ],
    'controle.management' => [
        'title' => "Gestion des points de contrôle d'une chronique",
    ],
    'controle.export'     => [
        'title' => 'Export de points de contrôle',
        'error' => [
            'noChronique' => 'Pas de chronique sélectionné.',
            'cantFinish'  => "Une erreur a bloqué l'export des points de contrôle.",
            'noRight'     => "Vous n'avez pas les droits suffisants pour exporter ces points de contrôle.",
        ],
    ],
];

/*************************************************
 * TRANSFORMATION
 ************************************************/

$transformation = [
    'transformation' => [
        'management' => "Calcul d'une chronique fille",
        'help'       => [
            'selectionner-chronique-mere' => 'Sélectionnez une chronique mère',
            'meaning-coefficient'         => 'Coefficient multiplicateur à renseigner pour que les valeurs calculées ' .
                "soient dans l'unité physique de la chronique fille. Cette unité sera le produit de ce coefficient avec " .
                'les unités de sortie des barèmes des deux chroniques mères.',
        ],
        'bareme'     => [
            'new'             => 'Ajouter un nouveau barème',
            'import'          => [
                'title'                => "Import d'un barème",
                'step'                 => [
                    '1' => 'Étape 1 : fichier du barème et format',
                    '2' => "Étape 2 : validation de l'import",
                    '3' => "Étape 3 : fin de l'import",
                ],
                'help'                 => [
                    'quitWarning' => "En cours d'import <b>merci de n'annuler que par le bouton prévu à cet effet</b>.",
                ],
                'entete'               => 'En-tête (3 lignes) :',
                'detail'               => '<li>"Nom ; Unite entree ; Unite sortie ; Commentaire"</li>
                                           <li>"nom du barème ; libellé unité ; libellé unité ; texte (optionnel)"</li>
                                           <li>"X ; Y ; Qualite ; Min (optionnel) ; Max (optionnel)"</li>',
                'donnees'              => '<li><b>Données :</b> "valeur ; valeur ; code qualité ; valeur (optionnel) ; valeur (optionnel)"</li>',
                'valid'                => '<li><b>Les codes de qualité doivent être au format VALIDE pour cet observatoire.</b></li>',
                'draix'                => '<li><b>Les codes de qualité doivent être au format Draix pour cet observatoire.</b></li>',
                'commentaires'         => "<li>
                                               <b>Commentaires :</b>
                                               <ul>
                                                   <li>Le caractère '#' marque une ligne de commentaire et peut également se trouver en cours de ligne auquel cas toute la suite de la ligne est commentée ;</li>
                                                   <li>Les commentaires ne sont pas autorisés sur les trois premières lignes du fichier qui doivent toujours être l'entête tel que décrit ici.</li>
                                               </ul>
                                           </li>",
                'format'               => 'Barème',
                'stats(%nbDataLines%)' => 'Le fichier contient %nbDataLines% lignes de données.',
                'success'              => "L'import du barème s'est effectué avec succès.",
                'nom'                  => 'Nom du barème : ',
                'uniteEntree'          => "Unité d'entrée : ",
                'uniteSortie'          => 'Unité de sortie : ',
                'commentaire'          => 'Commentaire : ',
                'newImport'            => 'Nouvel import',
            ],
            'baremeTechnique' => [
                'identite' => 'Identité',
                'lacune'   => 'Lacune',
                'manuel'   => 'Manuel',
            ],
            'export'          => [
                'doExport'    => 'Exporter des barèmes',
                'title'       => 'Export de barèmes',
                'help'        => 'Veuillez sélectionner le(s) barème(s) à exporter dans la liste ci-dessous.',
                'noSelection' => 'Veuillez sélectionner au moins un barème',
            ],
            'dateBareme' => 'd/m/Y H:i:s',
        ],

        'delaiPropagation'       => 'Temps de propagation (minutes)',
        'helpDelaiPropagation'   => "Un temps de propagation positif retarde la chronique (décalage vers l'aval) tandis qu'un temps négatif l'avance (décalage vers l'amont).",
        'jeu-baremes'            => 'Jeu de barèmes',
        'jeu-baremes-du(%date%)' => 'Jeu de barèmes du %date%',
        'saveAndCompute'         => 'Sauvegarder et calculer',
        'erreur'                 => [
            'coefficientNonNumerique'        => "Coefficient multiplicateur «\u{a0}%coeff%\u{a0}» non numérique",
            'delaiNonNumerique'              => "Temps de propagation «\u{a0}%delai%\u{a0}» non numérique",
            'limitPlaceholderNonNumerique'   => "Valeur de remplacement de limite «\u{a0}%placeholder%\u{a0}» non numérique",
            'chroniqueFilleIntrouvable'      => 'Chronique calculée non reconnue',
            'chroniqueMere.principale'       => 'Première chronique mère :',
            'chroniqueMere.secondaire'       => 'Seconde chronique mère :',
            'chroniqueMereIntrouvable'       => 'Chronique mère non renseignée ou non reconnue',
            'aucunBareme'                    => 'aucun barème défini',
            'baremeIntrouvable'              => 'Ligne %numLigne% : barème non renseigné ou non reconnu',
            'dateDebutIndefinie'             => "Ligne %numLigne% : date de début d'application du barème indéfinie",
            'dateFinIndefinie'               => "Ligne %numLigne% : date de fin d'application du barème indéfinie",
            'finAvantDebut'                  => "Ligne %numLigne% : date de fin d'application du barème (%dateFin%) antérieure ou égale à la date de début (%dateDebut%)",
            'conflitDebutFinPrecedent'       => "Ligne %numLigne% : date de début d'application du barème (%dateDebut%) différente de la date fin du barème précédent (%dateFin%)",
            'uniteEntreeBaremeIncorrecte'    => "Ligne %numLigne% : unité d'entrée du barème («\u{a0}%uniteEntreeBareme%\u{a0}») différente de l'unité de la chronique mère («\u{a0}%uniteChroniqueMere%\u{a0}»)",
            'uniteSortieBaremeIncorrecte'    => "Ligne %numLigne% : unité de sortie du barème («\u{a0}%uniteSortieBareme%\u{a0}») différente de l'unité de la chronique calculée («\u{a0}%uniteChroniqueFille%\u{a0}»)",
            'unitesSortieBaremesDifferentes' => "Les barèmes présentent des unités de sortie différentes, listées ci-dessous dans l'ordre :",
            'jeuBaremeIntrouvable'           => 'Jeu de barèmes introuvable',
        ],
        'submitted'              => [
            'submitted'  => "Le calcul de la chronique «\u{a0}<a href='_linkToChronique_'>_chroniqueName_</a>\u{a0}» a été envoyé et devrait prendre quelques instants.",
            'seeJobPage' => "Vous pouvez vérifier la fin du calcul depuis <a href='_linkToJobPage_'>la page des jobs</a> (job #_jobId_).",
        ],
        'confirmComputation'     => 'Si vous soumettez le calcul vous ne pourrez plus modifier le choix des chroniques mères ni les temps de propagation. Voulez-vous continuer ?',
        'cancelModifications'    => 'Annuler les modifications',
    ],
];

/*************************************************
 * IMPORT OF SHAPE
 ************************************************/

$shapeImport = [
    'shape.import'     => [
        'title'                       => 'Import de données géographiques',
        'step'                        => [
            '1' => 'Étape 1 : fichier de données géographiques',
            '2' => "Étape 2 : validation de l'import",
            '3' => "Étape 3 : fin de l'import",
        ],
        'question'                    => [
            'importType' => 'Que souhaitez-vous importer ?',
        ],
        'action'                      => [
            'newImport'         => 'Nouvel import',
            'doNotImport'       => 'Ne rien importer',
            'doImport'          => 'Importer les nouvelles données',
            'replace'           => 'Remplacer les données existantes',
            'keepExisting'      => 'Importer uniquement les nouvelles données',
            'overwriteExisting' => 'Importer les nouvelles données et remplacer les données existantes',
        ],
        'help'                        => [
            'quitWarning'        => "En cours d'import <b>merci de n'annuler que par le bouton prévu à cet effet</b>.",
            'file.shape'         => 'Une couche shape contient des données géographiques (au moins les fichiers <strong>shx</strong>, <strong>shp</strong> et <strong>dbf</strong>).',
            'requiredProjection' => '<b>Toutes les données doivent impérativement être projetées en Lambert93 - EPSG:2154.</b>',
            'sizeLimit'          => 'Le fichier envoyé ne doit pas dépasser 2 Mo.',
            'station'            => [
                'existingWithData'    => 'Station existante avec données géographiques|Stations existantes avec données géographiques',
                'existingWithoutData' => 'Station existante sans données géographiques|Stations existantes sans données géographiques',
                'nonExisting'         => 'Station inconnue|Stations inconnues',
            ],
            'courseau'           => [
                'new'       => "Nouveau cours d'eau|Nouveaux cours d'eau",
                'existing'  => "Cours d'eau existant|Cours d'eau existants",
                'nom'       => "(selon le nom du cours d'eau)",
            ],
            'bassin'             => [
                'new'         => 'Nouveau bassin|Nouveaux bassins',
                'existing'    => 'Bassin existant|Bassins existants',
                'nopeStation' => 'Bassin avec station exutoire inconnue|Bassins avec station exutoire inconnue',
            ],
        ],
        'info'                   => [
            'title'               => 'Import de données géographiques de type',
            'wasCanceled'         => "L'import de données géographiques a été annulé.",
            'noShapeImported'     => "Aucune donnée géographique n'a été importé.",
            'noPossibleImport'    => 'Aucune donnée ne peut être importée.',
            'shapeInsert'         => 'Donnée géographique ajoutée|Données géographiques ajoutées',
            'shapeUpdate'         => 'Donnée géographique mise à jour|Données géographiques mises à jour',
            'noData'              => 'Aucune donnée géographique présente',
            'nom'                 => 'Nom',
            'wontupload'          => '(<u>ces données ne peuvent pas être importées</u>)',
            'codeStationExutoire' => 'Code de la station exutoire',
            'codeStation'         => 'Code',
        ],
        'fileColumns'                 => '????',
        'thereAreErrors.cantContinue' => "L'import ne peut se poursuivre car il y a des erreurs.",
        'format'                      => [
            'bassin'   => <<<FORMAT
        <li><b>Un zip portant le même nom que tous les fichiers contenus, sans dossier.</b></li>
        <li><b>Colonnes attributaires attendues (l'orthographe est importante) - ordre indifférent :</b></li>
        <ul>
            <li><b>nom</b>, pour le nom du bassin ;</li>
            <li><b>code_exu</b>, pour le code de la station exutoire du bassin, peut être vide, le code est une chaîne de caractère</b></li>
        </ul>
FORMAT
            ,
            'coursEau' => <<<FORMAT
        <li><b>Un zip portant le même nom que tous les fichiers contenus, sans dossier.</b></li>
        <li><b>Colonnes attributaires attendues (l'orthographe est importante) - ordre indifférent (sur la base du format Carthage) :</b></li>
        <ul>
            <li><b>toponyme</b>, pour le nom du cours d'eau ;</li>
            <li><b>code_hydro</b>, pour le code hydro du cours d'eau, peut être vide, le code est une chaîne de caractère</b> ;</li>
            <li><b>classifica</b>, pour l'ordre de Strahler du cours d'eau, peut être vide, le classifica est un nombre.</li>
        </ul>

FORMAT
            ,
            'station'  => <<<FORMAT
        <li><b>Un zip portant le même nom que tous les fichiers contenus, sans dossier.</b></li>
        <li><b>Colonne attributaire attendue (l'orthographe est importante) :</b></li>
        <ul>
            <li><b>code</b>, pour le code de la station, le code est une chaîne de caractère.</li>
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
        'chroniqueMere'                  => 'Chronique mère',
        'paramConversion'                => 'Seuil de lacune',
        'paramConversionHelp'            => "Intervalle de temps <b><u>en minutes</u></b> entre deux plages de mesures successives qui, si il est dépassé, donne lieu à l'insertion de lacunes.<br>" .
            'Les lacunes sont insérées à + et − 1 seconde des plages qui les entourent.',
        'paramConversionTitle'           => 'Nombre de minutes, 1 minute minimum.',
        'paramConversionError'           => '1 minute minimum.',
        'saveAndConvert'                 => 'Sauvegarder et convertir',
        'cancelModifications'            => 'Annuler les modifications',
        'confirmConversion'              => 'Si vous soumettez la conversion vous ne pourrez plus modifier la chronique mère. Voulez-vous continuer ?',
        'noChroniqueMere'                => 'Aucune chronique discontinue avec cette unité sur cette station.',
        'success(%link%, %chronique%)'   => "La conversion de la chronique «\u{a0}<a href=\"%link%\">%chronique%</a>\u{a0}» a été envoyée et devrait prendre quelques instants.",
        'seeJobPage(%jobPage%, %jobId%)' => 'Vous pouvez vérifier la fin de la conversion depuis <a href="%jobPage%">la page des jobs</a> (job #%jobId%).',
        'CSRFerror'                      => 'La validité du formulaire a expiré et a été réinitialisée, veuillez ré-envoyer le formulaire.',
    ],
];

/*************************************************
 * COLORS
 ************************************************/

$colors = [
    'colors' => [
        'titles'=> [
            'qualite'       => 'Styles des qualités',
            'discontinuous' => 'Marqueurs pour les plages des chroniques discontinues',
            'checkpoint'    => 'Points de contrôle',
        ],
        'default'=> [
            'qualite'       => 'Pour la visualisation simple, les valeurs initiales sont : ' .
                'couleur #FF0000 (rouge) et épaisseur 5 pour les qualités traitées en lacune et ' .
                'couleur #0000FF (bleu) et épaisseur 1 pour les autres qualités.<br>' .
                'Pour mémoire : Pour la visualisation multiple, la même couleur, générée automatiquement, est utilisée ' .
                'pour toute la chronique (avec un effet de transparence pour les qualités traitées en lacune) et les ' .
                'épaisseurs sont de 5 pour les qualités traitées en lacune et de 1 sinon. ' .
                'Ces styles ne sont pas modifiables.',
            'discontinuous' => 'Pour la visualisation simple, les valeurs initiales sont : forme ●, épaisseur 2 et taille 5.<br>' .
                'Pour mémoire : Pour la visualisation multiple, les valeurs suivantes sont utilisées : ' .
                'forme ●, épaisseur 2 et taille 5. ' .
                "Ce style n'est pas modifiable.",
            'checkpoint'    => 'Pour la visualisation simple, les valeurs initiales sont : ' .
                'forme ○, épaisseur 2, couleur #008000 (vert) et taille 5.<br>' .
                'Pour mémoire : Les points de contrôle ne sont pas affichés sur la visualisation multiple.',
        ],
        'title'          => 'Styles de visualisation',
        'longTitle'      => 'Styles pour la visualisation simple',
        'success(%jeu%)' => "Les couleurs des qualitées du jeu «\u{a0}%jeu%\u{a0}» ont été enregistrées.",
        'color'          => 'Couleur',
        'thickness'      => 'Épaisseur des lignes',
        'gap'            => 'Cette qualité est traitée en lacune.',
        'shape'          => 'Forme',
        'stroke'         => 'Épaisseur du trait',
        'strokeHelp'     => 'Épaisseur du trait des formes non pleines uniquement.',
        'size'           => 'Taille',
        'save'           => 'Sauvegarder',
        'cancel'         => 'Annuler les modifications',
        'CSRFerror'      => 'La validité du formulaire a expiré et a été réinitialisée, veuillez ré-envoyer le formulaire.',
    ],
];

/*************************************************
 * VARIOUS MESSAGES
 ************************************************/

$various = [
    'data'        => [
        'management' => 'Gestion des données',
    ],
    'button' => [
      'add' => 'Créer',
    ],
    'admin'       => [
        'sendJson' => [
            'save'              => 'Sauvegarder',
            'saveMessage'       => 'Cocher et sauvegarder pour automatiser les envois',
            'send'              => 'Envoyer votre JSON',
            'download'          => 'Télécharger votre JSON',
            'result'            => 'Résultat',
            'resultMessage'     => 'Votre JSON est validé',
            'resultMessageFail' => "Votre JSON n'est pas valide !",
            'schema'            => 'Schéma de validation',
        ],
        'measuresImport' => 'Import de mesures',
        'controleImport' => 'Import et export de points de contrôle',
        'shapeImport'    => 'Import de données géographiques',
        'help'           => [
            'astuce'                              => '<strong style="color:#0e90d2; font-size: 10px">Astuce : Cliquer sur la ligne pour afficher la liste déroulante existante. Taper les premières lettres du mot recherché pour filtrer.</strong>',
            'from_the_observatory'                => "de l'observatoire",

            'inspireTheme' => [
                'nom' => "Correspond à la liste des thèmes INSPIRE officielle<strong> en anglais</strong> accessible <a href='https://inspire.ec.europa.eu/theme' target='_blank'>Ici</a> ",
            ],
            'topicCategory' => [
                'nom' => "Correspond à la liste des Catégories de thématiques INSPIRE officielle<strong> en anglais</strong> accessible <a href='https://inspire.ec.europa.eu/metadata-codelist/TopicCategory' target='_blank'>Ici</a> ",
            ],
            'theia' => [
                'theia-message'        => '<strong style="color:red; font-size: 10px">[Champ pour Theia/Ozcar]</strong><br> ',
                'warning-update-theia' => '<strong>Attention :</strong> Veuillez compléter les nouveaux champs pour Theia/Ozcar',
                'uriOzcarTheia'        => "Ce champs est la (ou les) variable correspondante sur le Thesaurus de Theia/Ozcar (vous pouvez  taper les lettres du mot recherché) des champs <strong>Milieu</strong> et de la <strong>Famille de Paramètre</strong> BDOH. Lien vers le Skosmos pour définir les variables : <a href='https://in-situ.theia-land.fr/skosmos/theia_ozcar_thesaurus/en/page/c_341ce66a' target='_blank'>Ici</a>",
            ],
            'observatoire'         => [
                'jeu'                                      => 'Le changement de jeu de codes qualité doit être réservé aux observatoires vides, sans aucune mesure importée.',
                'warning-observatory-has-sites'            => 'Cet observatoire ne peut pas être supprimé car il contient le site expérimental suivant :|Cet observatoire ne peut pas être supprimé car il contient les sites expérimentaux suivants :',
                'warning-observatory-has-scales'           => 'Cet observatoire possède le bareme suivant :|Cet observatoire possède les baremes suivants :',
                'warning-observatory-has-dois'             => 'Cet observatoire possède le DOI suivant :|Cet observatoire possède les DOI suivants :',
                'warning-observatory-has-basins'           => 'Cet observatoire possède le bassin suivant :|Cet observatoire possède les bassins suivants :',
                'warning-observatory-has-rivers'           => "Cet observatoire possède le cours d'eau suivant :|Cet observatoire possède les cours d'eau suivants :",
                'confirm-delete-observatory(%name%)'       => "Etes-vous sûr de vouloir supprimer l'observatoire «\u{a0}%name%\u{a0}» ?",
                'confirm-delete-observatory-info'          => "<strong style=\"color:red\">Les barèmes, bassins, cours d'eau et/ou DOI concernés seront également supprimés.</strong>",
                'warning-delete-observatory-info-redirect' => "Ceci est l'observatoire courant, si vous le supprimer vous serez redirigé sur la page d'accueil de tous les observatoires.",
                'titre-info'                               => 'Exemple: «AMMA-CATCH: a hydrological, meteorological and ecological observatory on West Africa»',
                'email-info'                               => 'Email de contact générique de cet observatoire.',
                'theiaCode-message'                        => "<strong style=\"color:red; font-size: 10px\">[Champ pour Theia/Ozcar]</strong><br>Correspond aux 4 premières lettres en majuscule de l'Observatoire. <br>Les identifiants font références au modèle de données pivot Theia/Ozcar (Annexe 1 page 15) : <a href='https://theia-ozcar.gricad-pages.univ-grenoble-alpes.fr/doc-producer/producer-documentation.html#modele-de-donnees-pivot-description-et-implementation-en-json' target='_blank'>Ici</a> ",
                'theiaPassword-message'                        => "Le mot de passe ne s'affiche pas dans ce champ pour des raisons de confidentialité. Le laisser en état pour le conserver.",
                'doi-principal-message'                    => " Theia/Ozcar demande un seul DOI pour ses ressources en ligne. Ajouter ici le DOI principal de l'observatoire. ",
            ],
            'dataset' => [
                'contact'                        => "<strong style=\"color:red; font-size: 10px\">[Champ pour Theia/Ozcar]</strong><br>Theia/Ozcar demande la présence obligatoire d'au moins un Project Leader",
                'description'                    => 'Le résumé doit décrire la ressource de façon compréhensible par l’utilisateur. Pour un producteur, il s’agit en particulier de définir au mieux l’information ou le phénomène représenté dans la donnée. On va donc y trouver des éléments de définition, mais aussi éventuellement une indication sommaire de la zone couverte ou le cas échéant, des informations sur les particularités de la version du jeu de données.',
                'genealogy'                      => "Décrit la généalogie d'un jeu de données, i.e. l’historique du jeu de données et, s’il est connu, le cycle de vie de celui-ci, depuis l’acquisition et la saisie de l’information jusqu’à sa compilation avec d’autres jeux et les variantes de sa forme actuelle. Il s’agit d’apporter une description littérale et concise soit de l’histoire du jeu de données, soit des  moyens,  procédures  ou  traitements  informatiques  mis  en  œuvre  au  moment  de l’acquisition dujeu de données.",
            ],
            'site'                 => [
                'warning-site-has-stations' => "Pour supprimer ce site expérimental vous devez d'abord en dissocier la station suivante :|Pour supprimer ce site expérimental vous devez d'abord en dissocier les stations suivantes :",
            ],
            'station'              => [
                'code'                             => 'Les caractères accentués et spéciaux de ce code seront automatiquement remplacés.',
                'codeAlternatif'                   => "Suite de codes de station séparés par des «\u{a0};\u{a0}» et du type «\u{a0}Format=CODE\u{a0}», utilisés pour des exports de chroniques dans des formats non-BDOH. Exemple : Hydro2 = MON_CODE_HYDRO2",
                'latlong'                          => 'Lambert93 - EPSG:2154 (mètres)',
                'warning-station-has-time-series'  => 'Cette station ne peut pas être supprimée car elle contient la chronique suivante :|Cette station ne peut pas être supprimée car elle contient les chroniques suivantes :',
                'warning-station-has-basins'       => 'Cette station est utilisée par le bassin suivant en tant que station exutoire :|Cette station est utilisée par les bassins suivants en tant que station exutoire :',
                'town-name-is-case-sensitive'      => '<u>Attention : le filtre de recherche est sensible à la casse, les communes ont toutes des majuscules à leur nom et les communes avec des noms composés ont des tirets et non des espaces ' .
                    '(ex. : Saint-Germain-sur-l\'Arbresle, Sainte-Foy-lès-Lyon, etc.), dans le doute faites une recherche sur une partie du nom qui ne peut pas avoir de majuscule ni de tiret ou faites une recherche sur le code postal.</u>',
                'confirm-delete-station(%name%)'   => "Etes-vous sûr de vouloir supprimer la station «\u{a0}%name%\u{a0}» ?",
                'confirm-delete-station-info'      => 'Les bassins concernés ne seront pas supprimés, ils perdront simplement leur station exutoire.',
            ],
            'chronique'            => [
                'code'                                   => 'Les caractères accentués et spéciaux de ce code seront automatiquement remplacés.',
                'libelle'                                => 'Un texte court décrivant la chronique de manière unique, spécifique...',
                'estVisible'                             => 'La chronique est-elle visible au public, oui ou non ?',
                'warning-has-calculated-time-series'     => 'Cette chronique ne peut pas être supprimée car elle est utilisée pour le calcul de la chronique suivante :|Cette chronique ne peut pas être supprimée car elle est utilisée pour le calcul des chroniques suivantes :',
                'warning-has-converted-time-series'      => 'Cette chronique ne peut pas être supprimée car elle est utilisée pour la conversion de la chronique suivante :|Cette chronique ne peut pas être supprimée car elle est utilisée pour la conversion des chroniques suivantes :',
                'warning-no-export-options-were-defined' => "<strong>Attention :</strong> les options d'export à pas de temps fixe n'ont pas encore été définies pour cette chronique. Des valeurs par défaut ont été automatiquement assignées. Merci de les vérifier/éditer puis d'enregistrer les modifications.",
                'contains-n-mesures(%nb%)'               => 'Cette chronique contient une mesure.|Cette chronique contient %nb% mesures.',
                'contains-n-plages(%nb%)'                => 'Cette chronique contient une plage de mesure.|Cette chronique contient %nb% plages de mesure.',
                'contains-n-controles(%nb%)'             => 'Cette chronique contient un point de controle.|Cette chronique contient %nb% points de controle.',
                'confirm-delete-time-series(%name%)'     => "Etes-vous sûr de vouloir supprimer la chronique «\u{a0}%name%\u{a0}» ?",
                'confirm-delete-time-series-info'        => '<u>Les mesures, plages de mesures et/ou points de contrôle de cette chronique seront également supprimés.</u>',
                'update-unite-cumul'                     => "Si vous autorisez l'export de cumuls pour cette chronique pensez à vérifier ou mettre à jour l'unité des cumuls ci-dessous.",
                'warning-no-milieu-defined'              => "<strong>Attention :</strong> Cette chronique n'a pas de milieu (la premier milieu est sélectionné par défaut). Vous devez sélectionner un milieu pour cette chronique et enregistrer la modification.",
            ],
            'commune'              => [
                'warning-commune-has-stations'   => 'Cette commune est utilisée par la station suivante :|Cette commune est utilisée par les stations suivantes :',
                'confirm-delete-commune(%name%)' => "Etes-vous sûr de vouloir supprimer la commune «\u{a0}%name%\u{a0}» ?",
                'confirm-delete-commune-info'    => 'Les stations concernées ne seront pas supprimées, elles perdront simplement leur commune.',
            ],
            'doi'                  => [
                'identifiant' => "Exemple d'identifiant : 10.17180/MON.OBSERVATOIRE",
                'description' => 'Détails sur les données telles que : auteurs, année, titre et établissement (INRAE habituellement), le DOI est rajouté automatiquement au descriptif.',
            ],
            'partenaire'           => [
                'warning-partenaire-has-time-series'   => 'Ce partenaire ne peut pas être supprimé car il est utilisé par la chronique suivante en tant que producteur :|Ce partenaire ne peut pas être supprimé car il est utilisé par les chroniques suivantes en tant que producteur :',
                'warning-partenaire-has-observatories' => "Ce partenaire ne peut pas être supprimé car il est utilisé par l'observatoire suivant :|Ce partenaire ne peut pas être supprimé car il est utilisé par les observatoires suivants :",
                'partenaire-has-time-series'           => 'Ce partenaire est utilisé par la chronique suivante en tant que producteur :|Ce partenaire est utilisé par les chroniques suivantes en tant que producteur :',
                'partenaire-has-observatories'         => "Ce partenaire est utilisé par l'observatoire suivant :|Ce partenaire est utilisé par les observatoires suivants :",
                'confirm-delete-partenaire(%name%)'    => "Etes-vous sûr de vouloir supprimer le partenaire «\u{a0}%name%\u{a0}» ?",
                'confirm-delete-partenaire-info'       => 'Les chroniques et/ou observatoires concernés ne seront pas supprimés, ils perdront simplement leur partenaire ou producteur.',
                'help-scanR'                           => 'Identifiant ScanR (ID) sur le site <a href="https://scanr.enseignementsup-recherche.gouv.fr/" target="_blank">https://scanr.enseignementsup-recherche.gouv.fr/</a> Ex : Unité PIAF, ID: 200017466P</strong>',
                'help-boolean'                         => '<strong style="color:red; font-size: 10px">[Champ pour Theia/Ozcar]</strong> <br/>NB : Theia/Ozcar nécessite au moins un financeur par Observatoire',
            ],
            'familleParametres'    => [
                'warning-familleParametres-has-children' => 'Cette famille de paramètres ne peut pas être supprimée car elle est utilisée par la famille suivante (en tant que famille mère) :|Cette famille de paramètres ne peut pas être supprimée car elle est utilisée par les familles suivantes (en tant que famille mère) :',
                'warning-familleParametres-has-types'    => 'Cette famille de paramètres ne peut pas être supprimée car elle est utilisée par le type de paramètre suivante :|Cette famille de paramètres ne peut pas être supprimée car elle est utilisée par les types de paramètres suivants :',
                'help-prefix'                            => "Le préfixe est utilisé pour ordonner les familles de même niveau (par exemple : 001, 002, 003, ...), il n'est pas affiché.",
                'help-familleParente'                    => 'Les familles filles et descendantes ne sont pas présentes dans cette liste de sélection. Vous pouvez aussi supprimer la famille mère en utilisant la petite croix à droite.',
            ],
            'typeParametre'        => [
                'warning-typeParametre-has-time-series' => 'Ce type de paramètre ne peut pas être supprimé car il est utilisé par la chronique suivante :|Ce type de paramètre ne peut pas être supprimé car il est utilisé par les chroniques suivantes :',
                'warning-no-family-defined'             => "<strong>Attention :</strong> Ce type de paramètre n'a pas de famille de paramètres (la première famille est sélectionnée par défaut). Vous devez sélectionner une famille de paramètres pour ce type et enregistrer la modification.",
            ],
            'unite'                => [
                'warning-unit-has-time-series'     => 'Cette unité ne peut pas être supprimée car elle est utilisée par la chronique suivante :|Cette unité ne peut pas être supprimée car elle est utilisée par les chroniques suivantes :',
                'warning-unit-has-scales'          => 'Cette unité ne peut pas être supprimée car elle est utilisée par le barème suivant :|Cette unité ne peut pas être supprimée car elle est utilisée par les barèmes suivants :',
                'warning-unit-has-parameter-types' => 'Cette unité est utilisée par le type de paramètre suivant :|Cette unité est utilisée par les types de paramètre suivants :',
                'confirm-delete-unit(%name%)'      => "Etes-vous sûr de vouloir supprimer l'unité «\u{a0}%name%\u{a0}» ?",
                'confirm-delete-unit-info'         => 'Les types de paramètre concernés ne seront pas supprimés, ils perdront simplement cette unité.',
            ],
        ],
    ],
    'utilisateur' => [
        'newAccountMail' => [
            'body'   => "Bonjour,

Un compte vient d'être créé pour vous sur BDOH.

Votre identifiant est l'adresse e-mail à laquelle vous recevez ce message et le mot de passe est «\u{a0}password\u{a0}», nous vous invitons à le changer dès votre première connexion.

Vous pouvez dès à présent préciser vos besoins d'accès aux données depuis le lien suivant : ",
            'footer' => 'Cet e-mail est généré automatiquement, merci de ne pas y répondre.',
            'title'  => 'Compte ouvert sur BDOH',
        ],
    ],
    'export'      => [
        'exportMail' => [
            'body'               => 'Veuillez trouver en pièce jointe votre export de chronique.',
            'footer'             => 'Cet e-mail est généré automatiquement, merci de ne pas y répondre.',
            'title(%chronique%)' => "BDOH : Export de la chronique %chronique%|Export des chroniques «\u{a0}%chronique%\u{a0}»",
        ],
    ],
    'backToBdoh'             => 'Retourner sur BDOH',
    'forAllTheObservatories' => 'Pour tous les observatoires',
    'title_edit_defined'     => "%entity% «\u{a0}%name%\u{a0}»",
    'title_create_undefined' => '%entity%',
    'title_delete_defined'   => "%entity% «\u{a0}%name%\u{a0}»",
    'new'                    => 'Nouveau',
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($entitiesActions, $fieldSets, $fileFormats, $measureImport, $various, $controleImport, $transformation, $shapeImport, $conversion, $colors);
