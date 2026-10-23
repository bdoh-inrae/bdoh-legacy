<?php
/*
 * Sort en JSON les métadonnées Doctrine de toutes les entités, sous une forme qui ne dépend pas de
 * la version de Doctrine : le même script tourne dans l'ancien BDOH (PHP 7.1, ORM 2) et dans le
 * nouveau (PHP 8.4, ORM 3), et les deux sorties doivent être identiques.
 *
 *   docker exec -i bdoh-reference-app php < dev/port/dump-metadata.php > ancien.json
 *   docker exec -i bdoh-app php < dev/port/dump-metadata.php > nouveau.json
 *
 * Écrit pour PHP 7.1 : pas de syntaxe plus récente.
 */

$root = getcwd();
if (is_file("$root/app/AppKernel.php")) {
    require "$root/app/autoload.php";
    require_once "$root/app/AppKernel.php";
    $kernel = new AppKernel('prod', false);
} else {
    require "$root/vendor/autoload.php";
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv("$root/.env");
    $kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
}
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();

/** Lit une clé d'un tableau ou d'un objet de correspondance (ArrayAccess sous ORM 3). */
function get($mapping, $key, $default = null)
{
    if (is_array($mapping)) {
        return array_key_exists($key, $mapping) && $mapping[$key] !== null ? $mapping[$key] : $default;
    }

    return isset($mapping[$key]) ? $mapping[$key] : $default;
}

function joinColumns($columns, $nullable = true)
{
    $out = [];
    foreach ($columns ?: [] as $c) {
        $out[] = [
            'name'                 => get($c, 'name'),
            'referencedColumnName' => get($c, 'referencedColumnName'),
            'nullable'             => $nullable && (bool) get($c, 'nullable', true),
            'unique'               => (bool) get($c, 'unique', false),
            'onDelete'             => get($c, 'onDelete') ? strtoupper(get($c, 'onDelete')) : null,
            'columnDefinition'     => get($c, 'columnDefinition'),
        ];
    }

    return $out;
}

$classes = [];
foreach ($em->getMetadataFactory()->getAllMetadata() as $meta) {
    if (strpos($meta->getName(), 'JMS\\') === 0) {
        continue; // file de tâches de l'ancien, remplacée
    }
    $fields = [];
    foreach ($meta->fieldMappings as $name => $f) {
        $fields[$name] = [
            'type'             => get($f, 'type'),
            'column'           => get($f, 'columnName'),
            'nullable'         => (bool) get($f, 'nullable', false),
            'unique'           => (bool) get($f, 'unique', false),
            'length'           => get($f, 'length'),
            'precision'        => get($f, 'precision') ?: null,
            'scale'            => get($f, 'scale') ?: null,
            'id'               => (bool) get($f, 'id', false),
            'options'          => get($f, 'options', []),
            'columnDefinition' => get($f, 'columnDefinition'),
            'inherited'        => get($f, 'inherited'),
        ];
    }
    ksort($fields);
    $associations = [];
    foreach ($meta->associationMappings as $name => $a) {
        $joinTable = get($a, 'joinTable');
        $cascade = get($a, 'cascade', []);
        sort($cascade);
        $orderBy = [];
        foreach (get($a, 'orderBy', []) as $field => $direction) {
            // ORM 3 range le sens dans une énumération (ASCENDING, DESCENDING)
            $direction = strtoupper(is_object($direction) ? $direction->name : $direction);
            $orderBy[$field] = substr($direction, 0, $direction[0] === 'A' ? 3 : 4);
        }
        $associations[$name] = [
            'type'          => get($a, 'type'),
            'target'        => get($a, 'targetEntity'),
            'mappedBy'      => get($a, 'mappedBy'),
            'inversedBy'    => get($a, 'inversedBy'),
            'owning'        => (bool) get($a, 'isOwningSide', false),
            'fetch'         => get($a, 'fetch'),
            'cascade'       => $cascade,
            'orphanRemoval' => (bool) get($a, 'orphanRemoval', false),
            'joinColumns'   => joinColumns(get($a, 'joinColumns')),
            'joinTable'     => $joinTable ? [
                'name'               => get($joinTable, 'name'),
                // Colonnes d'une table de jointure : toujours NOT NULL dans la base, ce que
                // l'ORM 3 écrit et que l'ORM 2 laisse implicite.
                'joinColumns'        => joinColumns(get($joinTable, 'joinColumns'), false),
                'inverseJoinColumns' => joinColumns(get($joinTable, 'inverseJoinColumns'), false),
            ] : null,
            'orderBy'       => $orderBy ?: null,
            'indexBy'       => get($a, 'indexBy'),
            'inherited'     => get($a, 'inherited'),
        ];
    }
    ksort($associations);
    $discriminator = $meta->discriminatorColumn;
    $map = $meta->discriminatorMap;
    ksort($map);
    $sequence = $meta->sequenceGeneratorDefinition;
    $callbacks = $meta->lifecycleCallbacks;
    ksort($callbacks);
    $classes[$meta->getName()] = [
        'table'           => [
            'name'              => $meta->getTableName(),
            'schema'            => get($meta->table, 'schema'),
            'indexes'           => get($meta->table, 'indexes', []),
            'uniqueConstraints' => get($meta->table, 'uniqueConstraints', []),
        ],
        'rootEntity'      => $meta->rootEntityName,
        'repository'      => $meta->customRepositoryClassName,
        'mappedSuperclass' => (bool) $meta->isMappedSuperclass,
        'readOnly'        => (bool) $meta->isReadOnly,
        'inheritance'     => $meta->inheritanceType,
        'discriminator'   => $discriminator ? [
            'name'   => get($discriminator, 'name'),
            'type'   => get($discriminator, 'type'),
            'length' => get($discriminator, 'length'),
        ] : null,
        'discriminatorMap'   => $map,
        'discriminatorValue' => $meta->discriminatorValue,
        'identifier'      => $meta->identifier,
        'generator'       => $meta->generatorType,
        'sequence'        => $sequence ? [
            'name'           => $sequence['sequenceName'],
            'allocationSize' => (int) $sequence['allocationSize'],
            'initialValue'   => (int) $sequence['initialValue'],
        ] : null,
        'lifecycleCallbacks' => $callbacks,
        'entityListeners' => $meta->entityListeners,
        'fields'          => $fields,
        'associations'    => $associations,
    ];
}
ksort($classes);
echo json_encode($classes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
