<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohTheiaOzcarBundle\Model;

class Result
{
    /**
     * @var DataFile
     */
    private $dataFile;

    //------------------------------------------------------------

    public function __construct()
    {
        $this->dataFile = new DataFile();
    }

    //---------------------------------------------------------

    /**
     * @return DataFile
     */
    public function getDataFile()
    {
        return $this->dataFile;
    }

    /**
     * @param DataFile $dataFile
     */
    public function setDataFile(DataFile $dataFile)
    {
        $this->dataFile = $dataFile;
    }
}
