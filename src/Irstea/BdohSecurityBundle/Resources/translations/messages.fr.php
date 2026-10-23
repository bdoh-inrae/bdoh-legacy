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
            'isCompleted' => 'Votre compte BDOH a bien été créé. Vous pouvez désormais vous connecter.',
            'info'        => [
                'isTheFirstStep' => "Le compte que vous allez créer sera valable sur toute l'application BDOH. Pour pouvoir télécharger des données il faudra, une fois connecté, faire <b>des demandes d'accès spécifiques</b> sur les observatoires qui vous intéressent via le formulaire de demande d'accès aux données ou contacter un gestionnaire.",
                'yourIdentifier' => "L'<b>adresse e-mail</b> sera votre <u>identifiant de connexion</u>.",
            ],
        ],
        'edit'                         => [
            'title'       => 'Modifier mon compte BDOH',
            'submit'      => 'Enregistrer les modifications',
            'isCompleted' => 'Votre compte BDOH a été mis à jour.',
            'info'        => [
                'changePassword' => 'Si vous desirez <u>changer votre mot de passe</u> remplissez les <u>3 champs suivants</u>',
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
                'hello' => 'Bonjour',
                'step1' => "Vous avez créé un compte sur BDOH. Il est valable pour toute l'application.",
                'step2' => "Pour pouvoir télécharger des données il faudra, une fois connecté, faire des demandes d'accès spécifiques sur les observatoires qui vous intéressent via le formulaire de demande d'accès aux données ou contacter un gestionnaire :",
                'step3' => "Conformément aux articles 39 et 40 de la loi Informatique et Libertés modifiée par la loi n° 2004-801 du 6 août 2004 relative à la protection des personnes physiques à l'égard des traitements de données à caractère personnel, vous disposez d'un droit d'accès et de rectification des données vous concernant.",
                'step4' => 'Pour toute demande de rectification veuillez vous adresser à : bdoh.support@lists.irstea.fr.',
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
    'User account has expired.' => 'Ce compte BDOH a expiré.',
    'Bad credentials.'          => "L'<u>adresse e-mail</u> ou le <u>mot de passe</u> sont erronés ou ce compte BDOH a expiré.",
    'contactGestionnaire'       => 'Pour contacter un gestionnaire de cet observatoire adressez-vous à :',
    'userManual(%link%)'        => "Pour plus d'informations sur l'utilisation de BDOH vous pouvez consulter <a href=\"%link%\">la documentation de BDOH</a>.",
    'technicalContact'          => "Pour les demandes techniques concernant l'utilisation de BDOH veuillez contacter <a href=\"mailto:bdoh.support@lists.irstea.fr\">bdoh.support@lists.irstea.fr</a>.",

    'security' => [
        'login_form' => [
            'connection'   => 'Connexion',
            'title'        => 'Connexion à BDOH',
            'username'     => 'Adresse e-mail professionnelle',
            'password'     => 'Mot de passe',
            'connectTo'    => 'Se connecter',
            'lostPassword' => 'Mot de passe oublié',
        ],
    ],
    'Utilisateur.password.badRepetition' => 'Mauvaise répétition du mot de passe.',
    'Utilisateur.password.badCurrent'    => 'Vous devez renseigner ce champ avec votre mot de passe actuel.',
    'Utilisateur.newPassword.mandatory'  => "Vous n'avez pas rempli ce champ.",
    'Utilisateur.oldPassword.mandatory'  => "Vous n'avez pas rempli ce champ.",
];

// Password resetting
$resetting = [
    'resetting' => [
        'password_already_requested' => 'Un nouveau mot de passe a déjà été demandé pour cet utilisateur dans les dernières 24 heures.',
        'isCompleted'                => 'Votre mot de passe à bien été modifié.',
        'check_email'                => "Un e-mail a été envoyé à l'adresse «\u{a0}%email%\u{a0}», il contient un lien pour réinitialiser votre mot de passe.",
        'request'                    => [
            'invalid_email' => "L'adresse e-mail «\u{a0}%email%\u{a0}» n'existe pas.",
            'email'         => 'Adresse e-mail',
            'submit'        => 'Réinitialiser votre mot de passe',
        ],
        'reset'                      => [
            'submit' => 'Modifier votre mot de passe',
        ],
        'flash'                      => [
            'success' => 'Le mot de passe a été réinitialisé avec succès',
        ],
        'email'                      => [
            'subject' => 'Réinitialiser votre mot de passe',
            'message' => "Bonjour %username%,

Pour réinitialiser votre mot de passe merci de vous rendre sur :
%confirmationUrl%

Cordialement,

L'équipe BDOH.",
        ],
    ],
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($entities, $fields, $user, $besoin, $various, $resetting);
