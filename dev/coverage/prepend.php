<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

// Préfixé à chaque processus PHP le temps d'une passe (pcov.ini) : écrit à la fin du processus les lignes de src/
// qu'il a exécutées et celles qu'il a vues sans les exécuter. Mêmes lignes, même fichier : une page jouée mille
// fois n'en écrit qu'un. Rien ne s'écrit ici qui ne soit un chemin de src/ et un numéro de ligne.

if (\function_exists('pcov\start') && is_dir('/coverage/php')) {
    \pcov\start();
    // Enregistrée de l'intérieur d'une autre, cette fonction passe après celles de Symfony
    register_shutdown_function(static function (): void {
        register_shutdown_function(static function (): void {
            \pcov\stop();
            $seen = [];
            foreach (\pcov\collect(\pcov\all) as $file => $lines) {
                $seen[$file] = ['run' => array_keys($lines, 1, true), 'missed' => array_keys($lines, -1, true)];
            }
            $json = json_encode($seen);
            $path = '/coverage/php/' . md5($json) . '.json';
            if (!is_file($path)) {
                file_put_contents($path, $json, LOCK_EX);
            }
        });
    });
}
