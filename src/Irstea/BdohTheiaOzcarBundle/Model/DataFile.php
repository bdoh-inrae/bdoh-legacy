<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

class DataFile
{
    /**
     * @var string
     */
    private $name;

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'fichier.txt';
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }
}
