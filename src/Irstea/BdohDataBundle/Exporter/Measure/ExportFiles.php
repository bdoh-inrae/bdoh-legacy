<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Les archives d'export, servies par un lien pendant KEEP_DAYS jours, sous un jeton impossible à deviner. Le
 * jeton se lit dans les arguments de la tâche qui écrit l'archive : aucune table de plus (CLAUDE.md, structure
 * de la base gardée).
 */
class ExportFiles
{
    public const KEEP_DAYS = 7;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/exports')] private string $directory
    ) {
    }

    public function newToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function path(string $token): string
    {
        return $this->directory . '/' . $token . '.zip';
    }

    /**
     * Crée le dossier s'il manque et retire les archives qui ont fait leur temps. Appelé par chaque export :
     * sans export, rien n'arrive sur le disque, et rien ne presse d'en retirer.
     */
    public function prepare(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Impossible de créer ' . $this->directory);
        }
        foreach (glob($this->directory . '/*.zip') ?: [] as $file) {
            if (filemtime($file) < time() - self::KEEP_DAYS * 86400) {
                @unlink($file);
            }
        }
    }
}
