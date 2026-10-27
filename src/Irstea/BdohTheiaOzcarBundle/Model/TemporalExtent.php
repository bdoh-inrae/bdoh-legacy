<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

class TemporalExtent
{
    /**
     * @var string|null
     */
    private $dateBeg = '';

    /**
     * @var string|null
     */
    private $dateEnd = '';

    //@todo actuellement mis en string ou null mais en réalité il est obligatoire

    /**
     * La date d'un horodatage (strtotime) ; sans mesures, la chronique n'en a pas (false, reçu ici en
     * '') : PHP 7 faisait alors rendre null à date(), avec un avertissement, d'où « Z ».
     */
    private static function utc(?string $timestamp): string
    {
        return (is_numeric($timestamp) ? date('Y-m-d\TH:i:s.00', (int) $timestamp) : '') . 'Z';
    }

    /**
     * @return string|null
     */
    public function getDateBeg(): ?string
    {
        return $this->dateBeg;
    }

    /**
     * @param string|null $dateBeg
     */
    public function setDateBeg(?string $dateBeg): void
    {
        $dateBeg = self::utc($dateBeg);
        $this->dateBeg = $dateBeg;
    }

    /**
     * @return string|null
     */
    public function getDateEnd(): ?string
    {
        return $this->dateEnd;
    }

    /**
     * @param string|null $dateEnd
     */
    public function setDateEnd(?string $dateEnd): void
    {
        $dateEnd = self::utc($dateEnd);
        $this->dateEnd = $dateEnd;
    }
}
