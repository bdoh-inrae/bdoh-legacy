// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
define([
    'jquery',
    './style.css'
], ($) => {

    $(() => {
        $(document).on('mouseover.data-hover-tab', '[data-hover-tab]', (ev) => {
           $(ev.currentTarget).tab('show');
        });
    });

});
