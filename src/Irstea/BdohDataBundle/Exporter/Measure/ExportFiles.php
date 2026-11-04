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
        #[Autowire('%kernel.project_dir%/var/exports')] private string $directory,
        #[Autowire('%env(int:EXPORTS_MAX_BYTES)%')] private int $maxBytes
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
     * L'archive existe et n'a pas fait son temps, que le prochain export l'ait retirée ou non.
     */
    public function isAvailable(string $token): bool
    {
        $path = $this->path($token);

        return is_file($path) && filemtime($path) >= $this->limit();
    }

    /**
     * Crée le dossier s'il manque et retire les archives qui ont fait leur temps, et les fichiers d'une écriture
     * coupée (<jeton>.zip.XXXXXX). Au-delà de la place permise, les plus anciennes partent, même avant leur
     * terme : la page de suivi dit alors que le lien a expiré. Appelé par chaque export : sans export, rien
     * n'arrive sur le disque.
     */
    public function prepare(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Impossible de créer ' . $this->directory);
        }
        $files = [];
        foreach (glob($this->directory . '/*.zip*') ?: [] as $file) {
            if (filemtime($file) < $this->limit()) {
                @unlink($file);
            } else {
                $files[$file] = filemtime($file);
            }
        }

        asort($files);
        $total = array_sum(array_map('filesize', array_keys($files)));
        foreach (array_keys($files) as $file) {
            if ($total <= $this->maxBytes) {
                break;
            }
            $total -= filesize($file);
            @unlink($file);
        }
    }

    private function limit(): int
    {
        return time() - self::KEEP_DAYS * 86400;
    }
}
