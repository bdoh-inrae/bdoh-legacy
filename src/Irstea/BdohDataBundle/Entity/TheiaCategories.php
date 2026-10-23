<?php

namespace Irstea\BdohDataBundle\Entity;

class TheiaCategories
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $nom;

    /**
     * @var FamilleParametres
     */
    protected $familleParametre;

    /**
     * @var Milieu
     */
    protected $milieu;

    /**
     * @var string[]
     */
    protected $uriOzcarTheia = [];

    //-----------------getter et settter--------------------------------------------------------------

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getNom(): ?string
    {
        return $this->nom = $this->getMilieu() . ' / ' . $this->getFamilleParametre();
    }

    /**
     * @param string $nom
     */
    public function setNom(string $nom)
    {
        $this->nom = $this->getMilieu() . ' / ' . $this->getFamilleParametre();
    }

    /**
     * @return FamilleParametres|null
     */
    public function getFamilleParametre(): ?FamilleParametres
    {
        return $this->familleParametre;
    }

    /**
     * @param FamilleParametres $familleParametre
     */
    public function setFamilleParametre(FamilleParametres $familleParametre): void
    {
        $this->familleParametre = $familleParametre;
    }

    /**
     * @return Milieu|null
     */
    public function getMilieu(): ?Milieu
    {
        return $this->milieu;
    }

    /**
     * @param Milieu $milieu
     */
    public function setMilieu(Milieu $milieu): void
    {
        $this->milieu = $milieu;
    }

    /**
     * @return string[]
     */
    public function getUriOzcarTheia(): array
    {
        return $this->uriOzcarTheia;
    }

    /**
     * @param string[] $uriOzcarTheia
     */
    public function setUriOzcarTheia(array $uriOzcarTheia): void
    {
        $this->uriOzcarTheia = $uriOzcarTheia;
    }

    /**
     * To String.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getNom() ?: '';
    }
}
