<?php
/*
 * Tient lieu de doi.org pour dev/compare/compare.py pages (« nouveau seul : citations ») : copié dans public/ le
 * temps de la vérification, il rend pour tout DOI une citation reconnaissable, dans la langue demandée, avec ce que
 * doi.org peut rendre et que BDOH doit réduire : une balise <scp>, de l'italique, une entité. Le réseau du
 * développement n'atteint pas doi.org : sans lui, seul le repli de BDOH serait éprouvé.
 */

$doi = ltrim(rawurldecode($_SERVER['PATH_INFO'] ?? ''), '/');
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if ($doi === '' || !str_contains($accept, 'text/x-bibliography')) {
    http_response_code(406);
    exit;
}
$english = str_contains($accept, 'locale=en');
header('Content-Type: text/x-bibliography; charset=utf-8');
echo 'Essai, A. (2020). <i>Citation officielle ' . htmlspecialchars($doi) . '</i> ['
    . ($english ? 'Data set' : 'Jeu de données') . ']. <scp>Éditeur</scp> &amp; Cie. https://doi.org/'
    . htmlspecialchars($doi) . "\n";
