<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

$selection = [
    'selection' => [
        'empty.key' => 'Rien',
        'empty.msg' => 'Ne rien sélectionner',
        'free.key'  => 'Libre',
        'free.msg'  => 'Sélection libre',
        'stop.key'  => 'Stop',
        'stop.msg'  => 'Stopper la sélection',

        'ask.key'     => 'Quel est votre choix ?',
        'ask.element' => 'Votre sélection libre : ',

        'error.empty'          => 'Veuillez sélectionner un élément de la liste.',
        'error.invalid(%key%)' => "«\u{a0}%key%\u{a0}» est invalide ! Veuillez sélectionner un élément de la liste.",
    ],
];

/*************************************************
 * ERRORS
 ************************************************/

$errors = [
];

/*************************************************
 * VARIOUS MESSAGES
 ************************************************/

$various = [
    'file_path'   => 'Chemin du fichier',
    'file_format' => 'Format du fichier',
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($selection, $errors, $various);
