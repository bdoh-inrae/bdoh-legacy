// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
define([], () => {
    'use strict';

    function Serie(type, measures, values, style, label, showLabel) {
        this.type = type;
        this.measures = measures;
        this.values = values;
        this.style = style;
        this.label = label;
        this.showLabel = showLabel;
    }

    Serie.prototype.dispose = function() {
        this.measures = this.values = this.type = null;
    };

    return Serie;
});
