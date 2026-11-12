<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

$entities = require 'entitiesSingulars.en.php';
$fields = require 'fields.en.php';

/*************************************************
 * CREATION AND EDITION OF A USER
 ************************************************/

$user = [
    'security.utilisateur' => [
        'create'                       => [
            'title'       => 'Register on BDOH',
            'submit'      => 'Register',
            'isCompleted' => 'Your BDOH account has been created. An e-mail has just been sent to you: follow its link to confirm your address and sign in.',
            'info'        => [
                'isTheFirstStep' => 'The account you are creating will be valid for the whole BDOH application. Open data can be downloaded without an account. To access closed data, once signed in, you will have to make <b>specific access requests</b> on observatories you are interested in by using the data access request form or by contacting a manager.',
                'yourIdentifier' => 'The <b>e-mail address</b> will be your <u>login</u>: you will sign in with a link sent to this address, without a password.',
            ],
        ],
        'edit'                         => [
            'title'                 => 'Modifiy my BDOH account',
            'submit'                => 'Save the changes',
            'isCompleted'           => 'Your BDOH account has been updated.',
            'emailChanged(%email%)' => "Your address has changed: confirm it with the sign-in link just sent to “\u{a0}%email%\u{a0}”.",
            'info'                  => [
                'changeEmail' => 'Changing your e-mail address signs you out: you will sign in again with a link sent to the new address.',
            ],
        ],
        'right'                        => [
            'title'       => 'Rights management: roles assignment',
            'role'        => 'Roles assignment',
            'submit'      => "Change user's roles",
            'isCompleted' => "The user's roles have been updated.",
        ],
        'all'                          => [
            'title'                        => 'Rights management: user selection',
            'action'                       => 'Change his roles',
            'besoin'                       => 'Need',
            'gestionnaireObservatoires'    => 'Manager of ALL the observatories',
            'gestionnaireObservatoire'     => 'Manager of the observatory',
            'gestionnaireSite'             => 'Site manager',
            'contributeurSite'             => 'Contributor',
            'utilisateurDeConfiance'       => 'Trusted user',
            'utilisateurChronique'         => 'User of time series',
            'OfObservatory(%observatory%)' => 'In observatory %observatory%',
            'OtherUsers'                   => 'Other users',
        ],
        'loginInfo'                    => 'Login information',
        'personalInfo'                 => 'Personnal information',
        'newRightMail'                 => [
            'body'  => [
                'hello'    => 'Hello',
                'part1'    => ',


You receive this e-mail because new rights have been assigned to you on BDOH.',
                'part2'    => 'You can access the application with the following link:',
                'youAre1'  => 'Your are now: ',
                'youAre2'  => ', you are now: ',
            ],
            'title' => ' BDOH: New rights',
        ],
        'newMailUtilisateur'           => [
            'body'  => [
                'hello'        => 'Hello',
                'step1'        => 'You have created an account on BDOH. It is valid for the whole application.',
                'confirm'      => 'To confirm your address and sign in, follow this link, valid for 15 minutes and once only:',
                'confirmLater' => 'After that, ask for a new link from the BDOH sign-in page.',
                'portal'       => "Choose the observatory whose data you want on the BDOH home page: you will find its access request and its managers there",
                'step2'        => 'Open data can be downloaded without an account. To access closed data, once signed in, you will have to make specific access requests on observatories you are interested in by using the data access request form or by contacting a manager:',
                'step3'        => "Accordance with Articles 39 and 40 of the law “\u{a0}Informatique et Libertés\u{a0}” amended by Law No. 2004-801 of 6 August 2004 on the protection of individuals with regard to the processing of personal data, you have a right to access and rectify data concerning you.",
                'step4'        => 'To request a correction please contact: <a href="mailto:bdoh.support@listes.inrae.fr">bdoh.support@listes.inrae.fr</a>.',
            ],
            'title' => 'Welcome to BDOH',
        ],
        'newNeedMail'                  => [
            'body'  => 'created a new data access request on your observatory on BDOH.

You can assign him/her a role in your observatory from the following page:',
            'title' => 'BDOH: new need',
        ],
        'mailFooter'                   => 'You receive this e-mail because you have the right to validate users for your observatory.

This e-mail is automatically generated, thank you for not replying.',
        'gestionnaireDesObservatoires' => 'Manager of ALL the observatories',
        'gestionnaireObservatoire'     => 'Manager of the observatory',
        'utilisateurObservatoire'      => 'Trusted user for the observatory',
        'gestionnaireSite'             => 'Manager of one or more experimental sites',
        'contributeurSite'             => 'Contributor of one or more experimental sites',
        'utilisateurChronique'         => 'User limited to one or more time series',
        'gestion'                      => 'Rights management',
        'creation'                     => 'New user',
        'aboutTheObservatory'          => 'For the observatory',
        'validityDate'                 => 'These rights are valid until the (D-M-Y):',
        'noValidityDate'               => 'no date of end of validity',
    ],
];

/*************************************************
 * CREATION OF A 'BESOIN'
 ************************************************/

$besoin = [
    'security.besoin' => [
        'create'                => [
            'title'       => 'Request for access to closed data',
            'submit'      => 'Send the request',
            'isCompleted' => 'Your request for data access has been registered, an administrator will respond.',
            'info'        => [
            ],
        ],
        'scope(%observatoire%)' => "This request <u>only applies to</u> the observatory <b>“\u{a0}%observatoire%\u{a0}”</b>.",
    ],
];

/*************************************************
 * VARIOUS MESSAGES
 ************************************************/

$various = [
    'contactGestionnaire'       => 'To contact a manager of this observatory please contact:',
    'userManual(%link%)'        => 'For more information about using BDOH you may read <a href="%link%">the BDOH documentaion</a>.',
    'technicalContact'          => 'For technical questions about using BDOH please contact <a href="mailto:bdoh.support@listes.inrae.fr">bdoh.support@listes.inrae.fr</a>.',

    // A user's favourites and their labels (dev/plans/2026-11-12_favorites.md)
    'favorite' => [
        'menu'      => 'My favourites',
        'title'     => 'My favourites',
        'add'       => 'Add to favourites',
        'remove'    => 'Remove from favourites',
        'intro'     => 'The time series you starred, grouped by observatory. Tick some, then export them together: one export per observatory, whose link is sent by e-mail.',
        'empty'     => 'No favourites yet: the star of a time series, on its page or in the lists, adds it here.',
        'checkAll'  => 'Tick all',
        'export'    => 'Export the selection',
        'labels'    => [
            'title'  => 'Labels',
            'hint'   => 'Clicking a label ticks its favourites.',
            'none'   => 'No labels: tick favourites, then name them to make a group.',
            'name'   => 'Label',
            'add'    => 'Label the ticked ones',
            'remove' => 'Remove the label from the ticked ones',
        ],
        'removeChecked' => 'Remove the ticked ones from favourites',
        'done'      => [
            'labelAdded(%label%)'   => 'Label “%label%” added to the ticked favourites.',
            'labelRemoved(%label%)' => 'Label “%label%” removed from the ticked favourites.',
            'removed'               => '{1}One favourite removed.|]1,Inf[%count% favourites removed.',
        ],
        'error'     => [
            'nothingChecked' => 'Tick some favourites first.',
            'noLabel'        => 'Give the label a name.',
            'invalidForm'    => 'The form has expired: please try again.',
        ],
        'exportModal' => [
            'notAllowed'   => 'Without a right on these time series, they cannot be exported:',
            'askAccess'    => 'Request access',
            'withoutMeasures' => 'These time series have no measures:',
            'nothing'      => 'None of the ticked time series can be exported.',
        ],
    ],

    'security' => [
        'login_form'      => [
            'connection'    => 'Sign in',
            'title'         => 'Sign in to BDOH',
            'info'          => 'Enter the e-mail address of your account: BDOH sends a sign-in link to it, valid for 15 minutes. There is no password any more.',
            'username'      => 'Professional e-mail address',
            'connectTo'     => 'Get a sign-in link',
            'noAccount'     => 'No account yet?',
            'createAccount' => 'Register',
        ],
        'login_check'     => [
            'info(%email%)' => "You are about to sign in to BDOH with the account “\u{a0}%email%\u{a0}”.",
            'submit'        => 'Sign in',
        ],
        'login_link'      => [
            'invalidForm'   => 'The form has expired: please ask for your link again.',
            'sent(%email%)' => "If a BDOH account exists for “\u{a0}%email%\u{a0}”, a sign-in link has just been sent to it. It is valid for 15 minutes.",
            'mail'          => [
                'title'  => 'BDOH: your sign-in link',
                'hello'  => 'Hello',
                'body'   => 'To sign in to BDOH, follow this link, valid for 15 minutes and once only:',
                'notYou' => 'If you did not ask for this link, ignore this message: without it, nobody can sign in to your account.',
                'footer' => 'This e-mail is generated automatically, please do not reply.',
            ],
        ],
        'tooManyRequests' => 'Too many requests in a short time: please try again in a few minutes.',
    ],
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($entities, $fields, $user, $besoin, $various);
