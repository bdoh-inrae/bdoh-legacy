<?php
/*
 * Tient lieu de Theia/OZCAR pour dev/compare/compare.py theia : copié dans public/ le temps de la
 * comparaison, il garde chaque envoi des deux BDOH dans var/theia-receiver/ (méthode, adresse, type,
 * empreinte de l'authentification, fichiers du zip) et répond 201, ou 401 si le fichier « refuse »
 * de ce dossier existe.
 */

$dir = __DIR__ . '/../var/theia-receiver';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$body = file_get_contents('php://input');
$files = [];
$zipFile = tempnam(sys_get_temp_dir(), 'theia');
file_put_contents($zipFile, $body);
$zip = new ZipArchive();
if ($zip->open($zipFile) === true) {
    for ($i = 0; $i < $zip->numFiles; ++$i) {
        $files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }
    $zip->close();
}
unlink($zipFile);

$record = [
    'method'         => $_SERVER['REQUEST_METHOD'],
    'path'           => $_SERVER['PATH_INFO'] ?? '',
    'content_type'   => $_SERVER['CONTENT_TYPE'] ?? '',
    'authorization'  => hash('sha256', $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['PHP_AUTH_USER'] ?? '') . ':' . ($_SERVER['PHP_AUTH_PW'] ?? '')),
    'files'          => $files,
];
file_put_contents(sprintf('%s/%s.json', $dir, microtime(true)), json_encode($record));

if (file_exists($dir . '/refuse')) {
    http_response_code(401);
    echo 'Bad credentials';
} else {
    http_response_code(201);
    echo 'Created';
}
