<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

$entities = require 'entitiesSingulars.fr.php';
$fields = require 'fields.fr.php';

/*************************************************
 * CREATION AND EDITION OF A USER
 ************************************************/

$user = [
    'security.utilisateur' => [
        'create'                       => [
            'title'       => 'Créer un compte sur BDOH',
            'submit'      => 'Créer le compte',
            'isCompleted' => "Votre compte BDOH a bien été créé. Un e-mail vient de vous être envoyé : suivez le lien qu'il contient pour confirmer votre adresse et vous connecter.",
            'info'        => [
                'isTheFirstStep' => "Le compte que vous allez créer sera valable sur toute l'application BDOH. Pour pouvoir télécharger des données il faudra, une fois connecté, faire <b>des demandes d'accès spécifiques</b> sur les observatoires qui vous intéressent via le formulaire de demande d'accès aux données ou contacter un gestionnaire.",
                'yourIdentifier' => "L'<b>adresse e-mail</b> sera votre <u>identifiant de connexion</u> : vous vous connecterez par un lien envoyé à cette adresse, sans mot de passe.",
            ],
        ],
        'edit'                         => [
            'title'                 => 'Modifier mon compte BDOH',
            'submit'                => 'Enregistrer les modifications',
            'isCompleted'           => 'Votre compte BDOH a été mis à jour.',
            'emailChanged(%email%)' => "Votre adresse a changé : confirmez-la par le lien de connexion qui vient d'être envoyé à «\u{a0}%email%\u{a0}».",
            'info'                  => [
                'changeEmail' => "Changer d'adresse e-mail vous déconnecte : vous vous reconnecterez par un lien envoyé à la nouvelle adresse.",
            ],
        ],
        'right'                        => [
            'title'       => 'Gestion des droits : attribution des rôles',
            'role'        => 'Attribution des rôles',
            'submit'      => "Modifier les rôles de l'utilisateur",
            'isCompleted' => "Les rôles de l'utilisateur ont été mis à jour.",
        ],
        'all'                          => [
            'title'                        => "Gestion des droits : choix d'un utilisateur",
            'action'                       => 'Modifier ses rôles',
            'besoin'                       => 'Besoin',
            'gestionnaireObservatoires'    => 'Gestionnaire de TOUS les observatoires',
            'gestionnaireObservatoire'     => "Gestionnaire de l'observatoire",
            'gestionnaireSite'             => 'Gestionnaire de site',
            'contributeurSite'             => 'Contributeur',
            'utilisateurDeConfiance'       => 'Utilisateur de confiance',
            'utilisateurChronique'         => 'Utilisateur de chronique',
            'OfObservatory(%observatory%)' => "De l'observatoire %observatory%",
            'OtherUsers'                   => 'Les autres',
        ],
        'loginInfo'                    => 'Informations de connexion',
        'personalInfo'                 => 'Informations personnelles',
        'newRightMail'                 => [
            'body'  => [
                'hello'    => 'Bonjour',
                'part1'    => ',


Vous recevez cet e-mail car de nouveaux droits vous ont été attribués sur BDOH.',
                'part2'    => "Vous pouvez accédez à l'application depuis l'adresse suivante :",
                'youAre1'  => 'Vous êtes désormais : ',
                'youAre2'  => ', vous êtes désormais : ',
            ],
            'title' => ' BDOH : Nouveaux droits',
        ],
        'newAccountMail'               => [
            'body'  => 'a créé un compte sur BDOH.

Vous pouvez lui attribuer un rôle sur votre observatoire deppuis la page suivante :',
            'title' => 'BDOH : nouvel utilisateur',
        ],
        'newMailUtilisateur'           => [
            'body'  => [
                'hello'        => 'Bonjour',
                'step1'        => "Vous avez créé un compte sur BDOH. Il est valable pour toute l'application.",
                'confirm'      => 'Pour confirmer votre adresse et vous connecter, suivez ce lien, valable 15 minutes et une seule fois :',
                'confirmLater' => 'Passé ce délai, demandez un nouveau lien depuis la page de connexion de BDOH.',
                'step2'        => "Pour pouvoir télécharger des données il faudra, une fois connecté, faire des demandes d'accès spécifiques sur les observatoires qui vous intéressent via le formulaire de demande d'accès aux données ou contacter un gestionnaire :",
                'step3'        => "Conformément aux articles 39 et 40 de la loi Informatique et Libertés modifiée par la loi n° 2004-801 du 6 août 2004 relative à la protection des personnes physiques à l'égard des traitements de données à caractère personnel, vous disposez d'un droit d'accès et de rectification des données vous concernant.",
                'step4'        => 'Pour toute demande de rectification veuillez vous adresser à : bdoh.support@lists.irstea.fr.',
            ],
            'title' => 'Bienvenue sur BDOH',
        ],
        'newNeedMail'                  => [
            'body'  => "a exprimé une nouvelle demande d'accès aux données de votre observatoire sur BDOH.

Vous pouvez lui attribuer un rôle sur votre observatoire deppuis la page suivante :",
            'title' => 'BDOH : nouveau besoin',
        ],
        'mailFooter'                   => 'Vous recevez cet e-mail parce que vous possédez le droit de valider les utilisateurs pour votre observatoire.

Cet e-mail est généré automatiquement, merci de ne pas y répondre.',
        'gestionnaireDesObservatoires' => 'Gestionnaire de TOUS les observatoires',
        'gestionnaireObservatoire'     => "Gestionnaire de l'observatoire",
        'utilisateurObservatoire'      => "Utilisateur de confiance sur l'observatoire",
        'gestionnaireSite'             => "Gestionnaire d'un ou plusieurs sites expérimentaux",
        'contributeurSite'             => 'Contributeur sur un ou plusieurs sites expérimentaux',
        'utilisateurChronique'         => 'Utilisateur limité à une ou plusieurs chroniques',
        'gestion'                      => 'Gestion des droits',
        'creation'                     => 'Nouvel utilisateur',
        'aboutTheObservatory'          => "Pour l'observatoire",
        'validityDate'                 => "Ces droits sont valables jusqu'au :",
        'noValidityDate'               => 'pas de date de fin de validité',
    ],
];

/*************************************************
 * CREATION OF A 'BESOIN'
 ************************************************/

$besoin = [
    'security.besoin' => [
        'create'                => [
            'title'       => "Demande d'accès aux données",
            'submit'      => 'Envoyer la demande',
            'isCompleted' => "Votre demande d'accès a bien été enregistrée, un administrateur va y répondre.",
            'info'        => [
            ],
        ],
        'scope(%observatoire%)' => "Cette demande <u>ne concernera que</u> l'observatoire <b>«\u{a0}%observatoire%\u{a0}»</b>.",
    ],
];

/*************************************************
 * VARIOUS MESSAGES
 ************************************************/

$various = [
    'contactGestionnaire'       => 'Pour contacter un gestionnaire de cet observatoire adressez-vous à :',
    'userManual(%link%)'        => "Pour plus d'informations sur l'utilisation de BDOH vous pouvez consulter <a href=\"%link%\">la documentation de BDOH</a>.",
    'technicalContact'          => "Pour les demandes techniques concernant l'utilisation de BDOH veuillez contacter <a href=\"mailto:bdoh.support@lists.irstea.fr\">bdoh.support@lists.irstea.fr</a>.",

    'security' => [
        'login_form'      => [
            'connection'    => 'Connexion',
            'title'         => 'Connexion à BDOH',
            'info'          => "Saisissez l'adresse e-mail de votre compte : BDOH vous y envoie un lien de connexion, valable 15 minutes. Il n'y a plus de mot de passe.",
            'username'      => 'Adresse e-mail professionnelle',
            'connectTo'     => 'Recevoir un lien de connexion',
            'noAccount'     => 'Pas encore de compte ?',
            'createAccount' => 'Créer un compte',
        ],
        'login_check'     => [
            'info(%email%)' => "Vous allez vous connecter à BDOH avec le compte «\u{a0}%email%\u{a0}».",
            'submit'        => 'Se connecter',
        ],
        'login_link'      => [
            'invalidForm'   => 'Le formulaire a expiré : demandez de nouveau votre lien.',
            'sent(%email%)' => "Si un compte BDOH existe pour l'adresse «\u{a0}%email%\u{a0}», un lien de connexion vient d'y être envoyé. Il est valable 15 minutes.",
            'mail'          => [
                'title'  => 'BDOH : votre lien de connexion',
                'hello'  => 'Bonjour',
                'body'   => 'Pour vous connecter à BDOH, suivez ce lien, valable 15 minutes et une seule fois :',
                'notYou' => "Si vous n'avez pas demandé ce lien, ignorez ce message : sans lui, personne ne peut se connecter à votre compte.",
                'footer' => 'Cet e-mail est généré automatiquement, merci de ne pas y répondre.',
            ],
        ],
        'tooManyRequests' => 'Trop de demandes en peu de temps : réessayez dans quelques minutes.',
    ],
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($entities, $fields, $user, $besoin, $various);
