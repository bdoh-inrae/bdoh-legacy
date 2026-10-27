<?php

namespace Irstea\BdohTheiaOzcarBundle\Service;

class ZipJSON
{
    public function zipArchive(string $serializerJSON, string $filename, string $theiaCode)
    {
        $zip = new \ZipArchive();
        // le fichier, vide, vient de tempnam() : PHP 8 refuse d'y voir une archive sans OVERWRITE
        if ($zip->open($filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("cannot open <$filename>\n");
        }
        $zip->addFromString($theiaCode . '_en.json', $serializerJSON);

        // All files are added, so close the zip file.
        $zip->close();
    }
}
