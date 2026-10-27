// Repris de l'ancien BDOH : même Encore, mêmes paquets (package-lock.json), mêmes fichiers compilés,
// sous le Node de Debian 13 (make assets). Les fichiers de Sonata 3 et ceux des formulaires de
// l'administration ne se compilent plus (dev/decisions.md, « JavaScript de l'administration »).
const Encore = require('@symfony/webpack-encore');
const LiveReloadPlugin = require('webpack-livereload-plugin');
const glob = require('glob');
const path = require('path');
const fs = require('fs');
const _ = require('lodash');

// Génère la configuration avec l'aide de webpack-encore
Encore
    .setOutputPath('public/assets/')
    .setPublicPath('/assets')
    .cleanupOutputBeforeBuild()

    .enableLessLoader()
    .enableSourceMaps(!Encore.isProduction())

    .autoProvideVariables({
        '$': 'jquery',
        'jQuery': 'jquery',
        'window.jQuery': 'jquery',
        'moment': 'moment',
        'window.moment': 'moment',
        '_': 'lodash',
        'window._': 'lodash'
    })

    .addEntry('locales/en', ['@IrsteaBdohInternationalisationBundle/locales/en'])
    .addEntry('locales/fr', ['@IrsteaBdohInternationalisationBundle/locales/fr'])

    .createSharedEntry('vendor', ['jquery', 'lodash', 'moment'])

    .addPlugin(new LiveReloadPlugin())
;

const bundleAliases = findBundleAliases();

const bundleEntries = findBundleEntries(bundleAliases, /^@IrsteaBdoh(\w*)Bundle/);
_.each(bundleEntries, (module, entry) => Encore.addEntry(entry, module));

const config = Encore.getWebpackConfig();

_.assign(
    config.resolve.alias,
    bundleAliases,
    {
        // celui de BazingaJsTranslationBundle 2.6, que la version 8 remplace par un autre, qui demande intl-messageformat
        'translator$': `${__dirname}/src/Irstea/BdohInternationalisationBundle/Resources/assets/vendor/bazinga-js-translation/translator.js`
    }
);

module.exports = config;

/**
 * @returns {{string: string}}
 */
function findBundleAliases() {
    const aliases = {};

    // Enregistre les dossiers 'Resources/assets' de nos bundles sous la forme '@BundleName'
    for (const assetPath of glob.sync(`${__dirname}/src/**/*Bundle/Resources/assets/`)) {
        const bundlePath = assetPath.substr(0, assetPath.lastIndexOf('Resources/assets'));
        const [bundleClassFile] = glob.sync(`${bundlePath}*Bundle.php`);
        const bundleName = bundleClassFile.substr(bundlePath.length, bundleClassFile.length - bundlePath.length - 4);
        aliases[`@${bundleName}`] = assetPath.substr(0, assetPath.length - 1);
    }

    return aliases;
}

/**
 * @param {{}} aliases
 * @param {RegExp} nameRegex
 * @returns {{string: string}}
 */
function findBundleEntries(aliases, nameRegex) {
    const entries = {};
    _.each(aliases, (assetPath, alias) => {
        const snakeAlias = _.snakeCase(alias.replace(nameRegex, '$1'));

        for (const entryPath of glob.sync(`${assetPath}/entries/**/*.js`)) {
            const modulePath = extractModulePath(entryPath);
            if (!modulePath) {
                continue;
            }
            const moduleName = alias + modulePath.substr(assetPath.length);
            let entryName = snakeAlias + modulePath.substr(assetPath.length + 8);
            if (entryName[0] === '/') {
                entryName = entryName.substr(1);
            }
            entries[entryName] = moduleName;
        }
    });
    return entries;
}

/**
 * @param {string} entryPath
 * @returns {string|boolean}
 */
function extractModulePath(entryPath) {
    if (path.basename(entryPath) === 'index.js') {
        return path.dirname(entryPath);
    }
    if (fs.existsSync(`${path.dirname(entryPath)}/index.js`)) {
        return false;
    }
    return entryPath.substr(0, entryPath.length - 3);
}
