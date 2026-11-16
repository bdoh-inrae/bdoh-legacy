<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohBundle\Doi;

use GuzzleHttp\Client;
use Irstea\BdohDataBundle\Entity\DataSet;
use Irstea\BdohDataBundle\Entity\Doi;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Les citations de BDOH (Lou, 2026-11-16 ; dev/plans/2026-11-16_before-kit.md, « Les blocs de citation » et « La
 * citation officielle »), une par sorte d'objet, de même forme, sans adresse électronique ni gestionnaires pour
 * auteurs :
 * - un observatoire : la citation officielle de son DOI principal, sinon celle que compose BDOH ;
 * - un jeu de données : celle de son propre DOI, sinon, s'il emprunte celui de son observatoire ou n'en a pas, celle
 *   que compose BDOH, avec « Fait partie de » et la citation de l'observatoire ;
 * - les autres DOI d'un observatoire, ni principal ni d'un jeu : leur citation officielle.
 *
 * La citation officielle vient de doi.org, en APA, dans la langue de la page, demandée par le serveur (le visiteur
 * n'appelle aucun tiers) et gardée un mois ; un échec est gardé une heure, et la citation composée par BDOH en tient
 * lieu. doi.org plutôt que l'API de DataCite : il répond aussi pour les articles, enregistrés chez Crossref. Ce qu'il
 * rend peut porter de petites balises (<scp>, <i>) : il n'en reste que du texte échappé et l'italique.
 *
 * Chaque citation est rendue en HTML sûr (`html`, pour les pages) et en texte (`text`, pour le README des exports).
 */
class Citations
{
    private const KEEP = 2592000;    // un mois
    private const RETRY = 3600;      // une heure, après un échec
    private const OPEN = "\u{E000}";  // les marques de l'italique, le temps d'échapper le reste
    private const CLOSE = "\u{E001}";

    public function __construct(
        #[Autowire(service: 'cache.doi')] private CacheInterface $cache,
        private UrlGeneratorInterface $router,
        private LoggerInterface $logger,
        #[Autowire('%env(DOI_RESOLVER)%')] private string $resolver,
    ) {
    }

    /** Un DOI sans son résolveur (cinq sont enregistrés avec « https://doi.org/ » devant). */
    public static function identifier(string $doi): string
    {
        return preg_replace('#^(?:https?://(?:dx\.)?doi\.org/|doi:)#i', '', trim($doi));
    }

    public static function url(string $doi): string
    {
        return 'https://doi.org/' . self::identifier($doi);
    }

    /**
     * La citation officielle d'un DOI, en HTML sûr, ou null si doi.org n'a pas répondu.
     */
    public function official(string $doi, string $locale): ?string
    {
        $id = self::identifier($doi);
        $locale = $locale === 'en' ? 'en-US' : 'fr-FR';

        return $this->cache->get('citation.' . hash('xxh128', $locale . ' ' . strtolower($id)), function (ItemInterface $item) use ($id, $locale) {
            try {
                $path = implode('/', array_map('rawurlencode', explode('/', $id)));
                $response = (new Client(['timeout' => 4, 'connect_timeout' => 2]))->get(rtrim($this->resolver, '/') . '/' . $path, [
                    'headers' => ['Accept' => "text/x-bibliography; style=apa; locale=$locale"],
                ]);
                $html = self::clean((string) $response->getBody());
                if ($html === '') {
                    throw new \RuntimeException('réponse vide');
                }
                $item->expiresAfter(self::KEEP);

                return $html;
            } catch (\Throwable $e) {
                $this->logger->warning('Citation de {doi} non obtenue : {message}', ['doi' => $id, 'message' => $e->getMessage()]);
                $item->expiresAfter(self::RETRY);

                return null;
            }
        });
    }

    /**
     * Ce que rend doi.org, en HTML sûr : le texte échappé, l'italique gardé s'il est bien formé, les adresses des DOI
     * en liens.
     */
    public static function clean(string $raw): string
    {
        $marked = preg_replace(['#<i\b[^>]*>#i', '#</i\s*>#i'], [self::OPEN, self::CLOSE], $raw);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($marked), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $balanced = substr_count($html, self::OPEN) === substr_count($html, self::CLOSE)
            && preg_match('/^[^' . self::OPEN . self::CLOSE . ']*(?:' . self::OPEN . '[^' . self::OPEN . self::CLOSE . ']*' . self::CLOSE . '[^' . self::OPEN . self::CLOSE . ']*)*$/u', $html);
        $html = $balanced ? str_replace([self::OPEN, self::CLOSE], ['<i>', '</i>'], $html) : str_replace([self::OPEN, self::CLOSE], '', $html);

        return self::linked($html);
    }

    /** Une citation en texte, pour le README. */
    public static function text(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return array{html: string, text: string, official: bool}
     */
    public function observatory(Observatoire $observatoire, string $locale, string $date = '[date]'): array
    {
        $main = $observatoire->getDoiPrincipal();
        $official = $main ? $this->official($main->getIdentifiant(), $locale) : null;
        if ($official !== null) {
            return $this->result($official, true);
        }
        $name = $this->observatoryName($observatoire, $locale);
        $where = $main
            ? self::url($main->getIdentifiant())
            : $this->router->generate('bdoh_home', ['_observatoire' => $observatoire->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->result($this->composed("$name. BDOH, INRAE.", $where, $main ? null : $date, $locale), false);
    }

    /**
     * @return array{html: string, text: string, official: bool, partOf: ?array}
     */
    public function dataset(DataSet $dataset, string $locale, string $date = '[date]'): array
    {
        $observatoire = $dataset->getObservatoire();
        $doi = $dataset->getDoi();
        $own = $doi && !self::borrows($dataset);
        $official = $own ? $this->official($doi->getIdentifiant(), $locale) : null;
        if ($official !== null) {
            return $this->result($official, true) + ['partOf' => null];
        }
        $title = ($locale === 'en' ? $dataset->getTitreEn() : null) ?: $dataset->getTitre();
        $where = $own
            ? self::url($doi->getIdentifiant())
            : $this->router->generate('bdoh_consult_dataset', ['_observatoire' => $observatoire->getSlug(), 'dataset' => $dataset->getUuid()], UrlGeneratorInterface::ABSOLUTE_URL);
        $head = rtrim(trim((string) $title), ' .:;,') . '. ' . $this->observatoryName($observatoire, $locale) . ', BDOH, INRAE.';

        return $this->result($this->composed($head, $where, $own ? null : $date, $locale), false)
            + ['partOf' => $own ? null : $this->observatory($observatoire, $locale, $date)];
    }

    /**
     * Les autres DOI d'un observatoire, ni son DOI principal, ni celui d'un de ses jeux de données, chacun avec sa
     * citation officielle, ou sa description et son adresse.
     *
     * @return array<array{html: string, text: string, official: bool}>
     */
    public function others(Observatoire $observatoire, string $locale): array
    {
        $taken = [$observatoire->getDoiPrincipal()];
        foreach ($observatoire->getDataSets() as $dataset) {
            $taken[] = $dataset->getDoi();
        }
        $out = [];
        foreach ($observatoire->getDois() as $doi) {
            if (in_array($doi, $taken, true)) {
                continue;
            }
            $official = $this->official($doi->getIdentifiant(), $locale);
            if ($official !== null) {
                $out[] = $this->result($official, true);
                continue;
            }
            $description = trim((string) (($locale === 'en' ? $doi->getDescriptionEn() : null) ?: $doi->getDescription()));
            $description = rtrim($description, ' .:;,');
            $out[] = $this->result($this->composed($description === '' ? '' : "$description.", self::url($doi->getIdentifiant()), null, $locale), false);
        }

        return $out;
    }

    /**
     * Un jeu de données qui emprunte le DOI de son observatoire : son DOI principal, ou un DOI d'observatoire
     * (« 10.17180/obs.… », les fiches de Recherche Data Gouv de chaque observatoire).
     */
    public static function borrows(DataSet $dataset): bool
    {
        $doi = $dataset->getDoi();

        return $doi !== null && ($doi === $dataset->getObservatoire()->getDoiPrincipal()
            || preg_match('#^10\.17180/obs\.#i', self::identifier($doi->getIdentifiant())) === 1);
    }

    /** « Observatoire Draix », sans le redoubler pour un nom qui le dit déjà. */
    private function observatoryName(Observatoire $observatoire, string $locale): string
    {
        $name = trim((string) $observatoire->getNom());
        if (preg_match('/^observ/i', $name)) {
            return $name;
        }

        return $locale === 'en' ? "$name observatory" : "Observatoire $name";
    }

    private function composed(string $head, string $url, ?string $date, string $locale): string
    {
        $html = htmlspecialchars(trim("$head $url"), ENT_QUOTES, 'UTF-8');
        if ($date !== null) {
            $html .= htmlspecialchars($locale === 'en' ? ", data downloaded on $date." : ", données téléchargées le $date.", ENT_QUOTES, 'UTF-8');
        }

        return self::linked($html);
    }

    /** Les adresses en liens, sans le point qui peut les suivre. */
    private static function linked(string $html): string
    {
        return preg_replace('#https?://[^\s<]+?(?=[.,;]?(?:\s|$|<))#u', '<a href="$0">$0</a>', $html);
    }

    private function result(string $html, bool $official): array
    {
        return ['html' => $html, 'text' => self::text($html), 'official' => $official];
    }
}
