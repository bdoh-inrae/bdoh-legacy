<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohAdminBundle\Importer;

/**
 * L'écriture du CSV temporaire d'un import, qui passe ensuite en base par COPY. Un disque plein ne doit pas
 * raccourcir l'import sans rien dire (dev/differences.md, « Import qui échoue en route »).
 */
trait CsvFileTrait
{
    /**
     * @param array $fields
     *
     * @throws \RuntimeException si le disque refuse la ligne
     */
    private function writeCsvLine(array $fields): void
    {
        if (false === fputcsv($this->csv, $fields, ';', '"', '\\')) {
            throw new \RuntimeException($this->cantWriteCsvMessage());
        }
    }

    /**
     * fputcsv ne signale pas une écriture partielle, le disque se remplissant au milieu d'une ligne : un
     * fichier complet finit par un saut de ligne.
     *
     * @throws \RuntimeException si la dernière ligne est coupée
     */
    private function checkCsvIsComplete(): void
    {
        clearstatcache(true, $this->csvPath);
        $size = (int) filesize($this->csvPath);
        if ($size > 0 && "\n" !== file_get_contents($this->csvPath, false, null, $size - 1)) {
            throw new \RuntimeException($this->cantWriteCsvMessage());
        }
    }

    private function cantWriteCsvMessage(): string
    {
        return $this->translator->trans('Error.file.cantWrite(%file%)', ['%file%' => $this->csvPath]);
    }
}
