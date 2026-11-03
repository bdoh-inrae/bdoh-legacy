// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
define([], () => {
    'use strict';

    function Observatoire(name) {
        this.name = name;
    }

    Observatoire.prototype.toString = function () {
        return this.name;
    };

    return Observatoire;
});
