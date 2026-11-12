<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohBundle\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * L'adresse d'un fichier servi par le site porte sa date (« ?v=1731412345 ») : un thème recompilé, une feuille ou un
 * script reconstruits par une mise à jour, un logo remplacé changent d'adresse, et le navigateur ne garde pas
 * l'ancien en cache. Un fichier absent garde son adresse telle quelle. (2026-11-12 ; dev/plans/2026-10-26_deployment.md,
 * « Feuilles de style en cache après une mise à jour »)
 */
class FileDateVersionStrategy implements VersionStrategyInterface
{
    public function __construct(private string $publicDir)
    {
    }

    public function getVersion(string $path): string
    {
        $file = $this->publicDir . '/' . ltrim((string) parse_url($path, PHP_URL_PATH), '/');

        return is_file($file) ? (string) filemtime($file) : '';
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);

        return '' === $version ? $path : $path . (str_contains($path, '?') ? '&' : '?') . 'v=' . $version;
    }
}
