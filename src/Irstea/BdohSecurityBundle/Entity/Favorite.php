<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohSecurityBundle\Entity;

use Irstea\BdohDataBundle\Entity\Chronique;

/**
 * Une chronique mise en favori par un compte, et les étiquettes que le compte y a posées : des noms, qui font des
 * groupes de favoris à exporter ensemble (dev/plans/2026-11-12_favorites.md).
 */
class Favorite
{
    /** La longueur d'une étiquette, au plus */
    public const LABEL_MAX_LENGTH = 50;

    private ?int $id = null;

    /** @var string[] */
    private array $labels = [];

    public function __construct(private Utilisateur $utilisateur, private Chronique $chronique)
    {
    }

    /**
     * Une étiquette telle qu'on la garde : sans espaces en trop ni caractères de contrôle (jsonb refuse le caractère
     * nul), coupée à sa longueur maximale ; vide si rien n'en reste, ou si elle n'est pas en UTF-8.
     */
    public static function normalizeLabel(string $label): string
    {
        $label = trim(preg_replace('/[\s\p{Cc}]+/u', ' ', $label) ?? '');

        return rtrim(mb_substr($label, 0, self::LABEL_MAX_LENGTH));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): Utilisateur
    {
        return $this->utilisateur;
    }

    public function getChronique(): Chronique
    {
        return $this->chronique;
    }

    /**
     * @return string[] dans l'ordre alphabétique
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function hasLabel(string $label): bool
    {
        return \in_array($label, $this->labels, true);
    }

    public function addLabel(string $label): void
    {
        $label = self::normalizeLabel($label);
        if ('' === $label || $this->hasLabel($label)) {
            return;
        }
        $labels = [...$this->labels, $label];
        natcasesort($labels);
        // Une nouvelle liste : Doctrine ne voit le changement d'un champ json qu'à une autre valeur
        $this->labels = array_values($labels);
    }

    public function removeLabel(string $label): void
    {
        $this->labels = array_values(array_filter($this->labels, fn ($l) => $l !== $label));
    }
}
