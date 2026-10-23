<?php
/*
 * Charge par Doctrine les premières entités de chaque classe (par identifiant) et sort leurs
 * valeurs en JSON : champs tels que les types de BDOH les rendent, associations du côté propriétaire
 * par identifiants.
 * Le même script tourne dans l'ancien BDOH et dans le nouveau, sur la même base ; les deux
 * sorties doivent être identiques. Vérifie la correspondance et les types, pas le code métier.
 *
 *   docker exec -i bdoh-reference-app-17 php < dev/port/dump-entities.php > ancien.json
 *   docker exec -i bdoh-app php < dev/port/dump-entities.php > nouveau.json
 *
 * Écrit pour PHP 7.1 : pas de syntaxe plus récente.
 */

date_default_timezone_set('Europe/Paris');
$limit = (int) (getenv('LIMIT') ?: 200);

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

function value($v)
{
    if ($v instanceof \DateTimeInterface) {
        return $v->format('Y-m-d H:i:s.u');
    }
    if (is_float($v)) {
        return ['float' => var_export($v, true)];
    }

    return $v;
}

function ids($em, $entity)
{
    if ($entity === null) {
        return null;
    }
    $meta = $em->getClassMetadata(get_class($entity));

    return implode('-', $meta->getIdentifierValues($entity));
}

$out = [];
$metas = $em->getMetadataFactory()->getAllMetadata();
usort($metas, function ($a, $b) {
    return strcmp($a->getName(), $b->getName());
});
foreach ($metas as $meta) {
    $class = $meta->getName();
    if (strpos($class, 'JMS\\') === 0 || $meta->isMappedSuperclass || $meta->getReflectionClass()->isAbstract()) {
        continue;
    }
    $order = [];
    foreach ($meta->getIdentifierFieldNames() as $id) {
        $order[$id] = 'ASC';
    }
    $rows = [];
    foreach ($em->getRepository($class)->findBy([], $order, $limit) as $entity) {
        if (get_class($entity) !== $class && strpos(get_class($entity), 'Proxies\\') === false) {
            continue; // une sous-classe : elle a sa propre entrée
        }
        $row = [];
        foreach ($meta->getFieldNames() as $field) {
            $row[$field] = value($meta->getFieldValue($entity, $field));
        }
        foreach ($meta->getAssociationNames() as $assoc) {
            if ($meta->isAssociationInverseSide($assoc)) {
                continue; // se déduit du côté propriétaire, vérifié dans sa propre classe
            }
            $target = $meta->getFieldValue($entity, $assoc);
            if ($meta->isCollectionValuedAssociation($assoc)) {
                $list = [];
                foreach ($target as $item) {
                    $list[] = ids($em, $item);
                }
                sort($list);
                $row[$assoc] = $list;
            } else {
                $row[$assoc] = ids($em, $target);
            }
        }
        ksort($row);
        $rows[] = $row;
    }
    $out[$class] = $rows;
    $em->clear();
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
