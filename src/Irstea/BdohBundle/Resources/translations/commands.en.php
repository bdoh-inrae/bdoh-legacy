<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

$selection = [
    'selection' => [
        'empty.key' => 'Nothing',
        'empty.msg' => 'Select none',
        'free.key'  => 'Free',
        'free.msg'  => 'Free selection',
        'stop.key'  => 'Stop',
        'stop.msg'  => 'Stop selection',

        'ask.key'     => 'What is your choice?',
        'ask.element' => 'Your free selection: ',

        'error.empty'          => 'Please select an item from the list.',
        'error.invalid(%key%)' => "“\u{a0}%key%\u{a0}” is invalid! Please select an item from the list.",
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
    'file_path'   => 'File path',
    'file_format' => 'File format',
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($selection, $errors, $various);
