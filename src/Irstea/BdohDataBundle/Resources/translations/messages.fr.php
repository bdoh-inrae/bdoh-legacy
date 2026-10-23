<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

$entities = require __DIR__ . '/entitiesSingulars.fr.php';
$fields = require __DIR__ . '/fields.fr.php';

/*************************************************
 * 'CHRONIQUE' TYPES
 ************************************************/

$chroniqueTypes = [
    'chronique.type.continue'    => 'Chronique continue',
    'chronique.type.discontinue' => 'Chronique discontinue',
    'chronique.type.calculee'    => 'Chronique calculée',
    'chronique.type.convertie'   => 'Chronique convertie',
    'type.continue'              => 'continue',
    'type.discontinue'           => 'discontinue',
    'type.calculee'              => 'calculée',
    'type.convertie'             => 'convertie',
];

/*************************************************
 * FILE FORMATS
 ************************************************/

$fileFormats = [
    'export' => [
        'Bdoh'      => 'BDOH',
        'Qtvar'     => 'Hydro2 - QTVAR',
        'Vigilance' => 'Vigilance',
    ],
];

/*************************************************
 * EXPORTS
 ************************************************/

$export = [
    'measure.export'  => [
        'title'        => '[0,1] Exporter la chronique | ]1,Inf] Exporter les chroniques',
        'help'         => 'Aide',
        'help-title'   => 'Aide - Export de chroniques',
        'help-content' => <<<HELP
<ul>
    <li>Le choix des dates/heures de début et fin de la ou des chronique(s) à exporter\u{a0}est exprimé en UTC.</li>
    <li>L'export par défaut est l'export à l'identique :
        <ul>
            <li>pas de modification du pas de temps par rapport à la chronique stockée (le pas de temps est potentiellement variable)</li>
            <li>on peut exporter plusieurs chroniques d'une même station</li>
            <li>il n'y a pas de limitation de longueur des chroniques et l'export se fait par téléchargement direct.</li>
        </ul>
    </li>
    <li>Un export à pas de temps fixe (avec interpolation linéaire ou calcul de moyenne ou de cumul) est possible pour certaines chroniques :</li>
    <ul>
        <li>on ne peut exporter qu'une chronique à la fois</li>
        <li>de façon à limiter l'encombrement du serveur qui réalise les calculs, la longueur maximale d'un export est de 2 ans par minute de pas de temps, soit 1051200 valeurs (~ 10<sup>6</sup> lignes)</li>
        <li>en cas de besoin d'export de chronique plus long, il faudra réaliser l'export en plusieurs fois</li>
        <li>le fichier est envoyé par e-mail en pièce jointe</li>
        <li>Les options pour l'export à pas de temps fixe sont : (i) pour l'export avec interpolation linéaire, un pas de temps compris entre 1 et 1440 minutes ; (ii) pour l'export avec calcul de moyenne ou cumul, un des pas de temps proposés dans la liste déroulante. L'export commence à une heure ronde. L'option «\u{a0}événement\u{a0}» calcule la moyenne ou le cumul sur toute la durée de l'événement (1 seule valeur résultat).</li>
    </ul>
    <li>On exporte un fichier par chronique.</li>
    <li>Pour l'export de chroniques continues à l'identique ou à pas de temps fixe avec interpolation linéaire, plusieurs formats de fichier sont possibles (BDOH standard ou Hydro2 QTVAR).</li>
    <li>Pour l'export de moyennes, cumuls ou chroniques discontinues, le format unique est le format BDOH discontinu.</li>
</ul>
HELP
        ,
        'underway'     => "L'export de chronique est en cours. Vous recevrez prochainement le fichier par e-mail.",
        'error'        => [
            'abort'                               => "Pour des raisons techniques l'export de mesures n'a pas pu se faire.",
            'badExportType'                       => "Export de chronique(s) : échantillonnage à l'export incorrect.",
            'badTimeStep'                         => 'Export de chronique(s) : Pas de temps incorrect.',
            'badTimeStep(%min%, %max%)'           => 'Export de chronique(s) : Le pas de temps doit être compris entre %min% et %max%.',
            'badTimeStepForExportType'            => "Export de chronique(s) : Pas de temps incorrect pour ce type d'export.",
            'badTimezone'                         => 'Export de chronique(s) : fuseau horaire incorrect.',
            'badBeginDate'                        => 'Export de chronique(s) : date de début incorrecte.',
            'badCumulStartTime'                   => 'Export de chronique(s) : heure de début des cumuls incorrecte.',
            'badEndDate'                          => 'Export de chronique(s) : date de fin incorrecte.',
            'badFormat'                           => 'Export de chronique(s) : format incorrect.',
            'noChronique'                         => 'Export de chronique(s) : aucune chronique sélectionnée.',
            'badChronique(%chroniqueId%)'         => "La chronique %chroniqueId% n'existe pas ou n'est pas accessible.\n",
            'isNotSupported(%chronique%, Plage)'  => "La chronique continue «\u{a0}%chronique%\u{a0}» n'est pas supportée par ce format d'export.\n",
            'isNotSupported(%chronique%, Mesure)' => "La chronique discontinue «\u{a0}%chronique%\u{a0}» n'est pas supportée par ce format d'export.\n",
            'measureDirectionNotSpecified'        => 'Choix des date à exporter (en début ou fin des intervalles) non spécifié.',
            'timeStepNotSpecified'                => 'Pas de temps non spécifié.',
            'noStartingTime'                      => "Veuillez préciser à quel type d'instant les mesures exportées doivent commencer.",
        ],
        'job_queued(%job_id%)' => "L'export de chronique est en cours. Vous recevrez prochainement le fichier par e-mail. (Job #%job_id%)",
        'report'               => [
            'headline' => [
                '1(%format%, %date%)' => "Export du %date% UTC au format «\u{a0}%format%\u{a0}»",
                '2(%begin%, %end%)'   => "Période d'extraction : du %begin% UTC au %end% UTC",
                '3(%timezone%)'       => 'Fuseau horaire : %timezone% ',
            ],

            'chronique(%chronique%)' => "Chronique «\u{a0}%chronique%\u{a0}» :",

            'chroniqueCalculee(%chronique%)' => "Chronique calculée «\u{a0}%chronique%\u{a0}» :",

            'measures(%number%,%first%,%last%)' => '[0,0]Aucune mesure présente|' .
                '[1,1]Une mesure présente|' .
                ']1,Inf]%number% mesures présentes, du %first% au %last%',

            'file(%file%)' => 'Fichier : %file%',

            'doi' => [
                'header(%observatoire%)'    => "DOI associés à l'observatoire «\u{a0}%observatoire%\u{a0}» :",
                'item(%doi%,%description%}' => 'dx.doi.org/%doi% [ %description% ]',
            ],

            'resampled' => [
                'header'                   => 'Export réalisé à pas de temps fixe',
                'type(%type%)'             => "Type d'échantillonnage : %type%",
                'timestep'                 => 'Pas de temps :',
                'timestep-var(%timestep%)' => '[0,1]%timestep% minute|]1,Inf]%timestep% minutes',
                'count(%count%)'           => '[0,0]Aucune mesure exportée|' .
                    '[1,1]Une mesure exportée|' .
                    ']1,Inf]%count% mesures exportées',
                'types'                    => [
                    'instantaneous' => 'interpolation linéaire',
                    'mean'          => 'moyenne',
                    'cumulative'    => 'cumul',
                ],
            ],

            'chronique.lq_ld'               => [
                'options'                      => [
                    'true_value'  => "utilisation des valeurs d'origine",
                    'half_value'  => 'valeurs divisées par 2',
                    'placeholder' => 'valeurs remplacées par la constante ',
                    'gap'         => 'valeurs traitées comme des mesures invalides',
                ],
            ],

            'calculated' => [
                'input'                       => [
                    'unique(%chronique%)'     => "Chronique mère unique : «\u{a0}%chronique%\u{a0}»",
                    'first(%chronique%)'      => "Première chronique mère : «\u{a0}%chronique%\u{a0}»",
                    'second(%chronique%)'     => "Seconde chronique mère : «\u{a0}%chronique%\u{a0}»",
                ],
                'limit_policy(%limitPolicy%)' => 'Transformation des limites de quantification / détection : %limitPolicy%',

                'bareme' => [
                    'header(%bareme%)'             => "Barème «\u{a0}%bareme%\u{a0}»",
                    'created(%dateCreation%)'      => ', crée le %dateCreation% UTC',
                    'applied'                      => ', appliqué ',
                    'from(%dateDebut%)'            => 'à partir du %dateDebut% UTC',
                    'range(%dateDebut%,%dateFin%)' => 'du %dateDebut% UTC au %dateFin% UTC',
                ],
            ],

            'error'                  => "Une erreur s'est produite pendant l'export :",
            'chronique.noHydro2Code' => "    -> ATTENTION : aucun «\u{a0}code Hydro2\u{a0}» trouvé ; «\u{a0}code station\u{a0}» utilisé.\n\n",
        ],
    ],
    'controle.export' => [
        'title' => 'Exportation de points de contrôle',
    ],
    'bareme.export'   => [
        'report' => [
            'main(%format%, %date%, %chronicle%)' => "Export du %date% UTC pour la chronique calculée «\u{a0}%chronicle%\u{a0}»\n" .
                "Les qualités sont au format «\u{a0}%format%\u{a0}»",
            'bareme'                              => [
                'base(%bareme%, %dateCreation%, %inputUnit%, %outputUnit%, %number%, %min%, %max%)' => "\n\nBarème «\u{a0}%bareme%\u{a0}» créé le %dateCreation% UTC\n" .
                    "    -> Unité d'entrée : %inputUnit%\n" .
                    "    -> Unité de sortie : %outputUnit%\n" .
                    "    -> %number% lignes exportées pour des valeurs en abscisse allant de %min% à %max%\n",
                'parentChronicle(%parentChronicle%)'                                                => "    -> Chronique mère : «\u{a0}%parentChronicle%\u{a0}»\n",
                'applyFromTo(%dateBegin%, %dateEnd%)'                                               => "    -> Appliqué du %dateBegin% au %dateEnd%\n",
                'applyFrom(%dateBegin%)'                                                            => "    -> Appliqué à partir du %dateBegin%\n",
                'notApplied'                                                                        => "    -> Non appliqué pour cette chronique calculée\n",
                'outputFile(%file%)'                                                                => '    -> Fichier : %file%',
            ],
        ],
        'error'  => [
            'badBareme(%id%)' => 'Barème n°%id% inconnu',
        ],
    ],
];

$jobs = [
    'job'             => [
        'title'         => [
            'list'       => 'Jobs',
            'show(%id%)' => 'Job n°%id%',
        ],
        'state'         => [
            'new'        => 'Nouveau',
            'pending'    => 'En attente',
            'ready'      => 'Prêt',
            'canceled'   => 'Annulé',
            'running'    => 'En cours',
            'finished'   => 'Réussi',
            'failed'     => 'Échec',
            'terminated' => 'Temps limite dépassé',
            'incomplete' => 'Erreur',
        ],
        'state.tooltip' => [
            'new'        => "Le job vient d'être créé.",
            'pending'    => 'Le job attends sa date programmée ou la fin des jobs dont il dépend.',
            'ready'      => 'Le job sera exécuté dès que possible.',
            'canceled'   => 'Le job a été annulé par son propriétaire ou un administrateur.',
            'running'    => "Le job est en cours d'exécution.",
            'finished'   => 'Le job a été exécuté avec succès.',
            'failed'     => 'Le job a échoué.',
            'terminated' => 'Le job a dépassé la limite de temps imparti.',
            'incomplete' => "Une erreur indépendante du job s'est produite.",
        ],
        'id'            => 'Numéro',
        'owner'         => 'Demandeur',
        'observatoire'  => 'Observatoire',
        'chronicles'    => 'Chronique(s)',
        'created_at'    => 'Créé le',
        'started_at'    => 'Commencé le',
        'closed_at'     => 'Terminé le',
        'queue'         => 'Queue',
        'priority'      => 'Priorité',
        'command'       => 'Commande',
        'arguments'     => 'Arguments',
        'runtime'       => "Temps d'exécution (s)",
        'output'        => 'Message(s)',
        'error_output'  => "Message(s) d'erreur",
        'exit_code'     => 'Code de sortie',
        'actions'       => 'Actions',
        'button.retry'  => 'Relancer',
        'description'   => [
            'bdoh:compute:filling_rates' => 'Calcul des taux de remplissage',
            'bdoh:compute:chronique'     => "Calcul d'une chronique",
            'bdoh:convert:chronique'     => "Conversion d'une chronique",
            'bdoh:export:identical'      => "Export de mesures à l'identique",
            'bdoh:export:instantaneous'  => 'Export de mesures avec interpolation linéaire',
            'bdoh:export:mean'           => 'Export de mesures avec calcul de moyennes',
            'bdoh:export:cumulative'     => 'Export de mesures avec calcul de cumuls',
            'theia:send:json:data'       => "Envoi du JSON de l'observatoire à Theia/OZCAR",
        ],
        'refreshInfo'   => "Pensez à rafraîchir la page pour visualiser l'évolution des jobs.",
        'refreshPage'   => 'Rafraîchir la page',
        'jobList'       => 'Liste des jobs',
    ],
    'job.description' => 'Description',
    'job.state'       => 'État',
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($entities, $fields, $chroniqueTypes, $fileFormats, $export, $jobs);
