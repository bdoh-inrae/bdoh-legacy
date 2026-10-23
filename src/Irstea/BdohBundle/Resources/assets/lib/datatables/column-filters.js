/*
 * © 2016-2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
const _ = require('lodash');

// Ajout d'une fonction pour gérer des filtres de recherche
$(document).on('init.dt.columnFilters', (ev) => {
    if (ev.namespace !== 'dt') {
        return;
    }
    const table = $(ev.target);
    const api = table.DataTable();
    const columns = api.columns();

    columns.header().each((header, index) => {
        const $header = $(header);
        const selector = $header.data('filter-with');
        if (!selector) {
            return;
        }
        const $control = $(selector);
        if (!$control.length) {
            // eslint-disable-next-line no-console
            console.error(`Filtering control not found: ${selector}`);
            return;
        }
        const multiSeparator = $header.data('value-separator');
        initColumnFilter($control, columns.column(index), multiSeparator);
    });
});

function initColumnFilter($control, column, multiSeparator) {
    const regexpBuilder = multiSeparator ? buildMultiSearchRegexp.bind(null, multiSeparator) : buildSingleSearchRegexp;
    const update = () => {
        const values = $control.val();
        const regexp = regexpBuilder(values);
        column.search(regexp, true, false).draw();
    };

    $control.on('change', update);

    if ($control.find('option').length === 0) {
        buildOptions($control, column, multiSeparator);
    }

    $control.select2({
        minimumResultsForSearch: 5,
        allowClear: true
    });

    update();
}

function buildOptions($control, column, multiSeparator) {
    const isMultiple = !!$control.prop('multiple');
    const values = getDistinctValues(column, multiSeparator);
    const defaultVal = getQueryString()[$control.attr('name')] || '';
    let defaultSearch = [];

    if (!isMultiple) {
        $('<option value="">-</option>').appendTo($control);
    }

    values.forEach((value) => {
        const searchRe = $.fn.dataTable.util.escapeRegex(value);
        $('<option></option>')
            .text(value)
            .attr('value', searchRe)
            .appendTo($control);
        if (value === defaultVal) {
            defaultSearch.push(searchRe);
        }
    });

    if (defaultSearch.length > 0) {
        if (!isMultiple) {
            defaultSearch = defaultSearch[0];
        }
        $control.val(defaultSearch);
    }
}

function getDistinctValues(column, multiSeparator) {
    let chain = _(column.cache('search').toArray())
        .filter();
    if (multiSeparator) {
        chain = chain
            .map((value) => value.split(multiSeparator))
            .flatten();
    }
    return chain
        .uniq()
        .sort((a, b) => {return a.localeCompare(b, 'fr', {'sensitivity':'base'});})
        .value();
}

function buildSingleSearchRegexp(values) {
    const re = joinRegexp(values);
    return re ? `^${re}$` : '';
}

function buildMultiSearchRegexp(multiSeparator, values) {
    const re = joinRegexp(values);
    return re ? `(?:^|${multiSeparator})${re}(?:$|${multiSeparator})` : '';
}

function joinRegexp(values) {
    if (_.isEmpty(values)) {
        return '';
    }
    if (_.isArray(values)) {
        return `(?:${values.join('|')})`;
    }
    return values;
}


let queryString = null;

function getQueryString() {
    if (!queryString) {
        queryString = extractParameters(window.location.search);
    }
    return queryString;
}

function extractParameters(queryString) {
    const parameters = {};
    for (const param of queryString.substr(1).split('&')) {
        const [name, value] = param.split('=', 2);
        parameters[name] = value ? decodeURIComponent(value.replace(/\+/g, ' ')) : null;
    }
    return parameters;
}
