<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohConsultBundle\Statistics;

use Doctrine\DBAL\Connection;
use Irstea\BdohDataBundle\Entity\Chronique;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Le compteur des consultations de la page d'une chronique (Lou, 2026-11-12 et 16 ; dev/plans/2026-11-16_reopening.md,
 * point 6), selon les règles du Code of Practice for Research Data de COUNTER (version 1.0.1, section 10) :
 *  - un visiteur compte une fois par heure et par chronique : la session de COUNTER sans cookie, adresse IP, navigateur,
 *    date et heure, qui couvre aussi le double clic ;
 *  - les robots de la liste de COUNTER sont écartés, sauf les outils des chercheurs, que COUNTER demande de compter.
 * Aucune donnée personnelle gardée : l'empreinte d'une session (le hachage de l'adresse IP, du navigateur, de l'heure,
 * de la chronique et d'un sel tiré chaque jour) vit dans un cache jusqu'à la fin de l'heure ; la base ne garde que des
 * comptes par chronique et par jour (series_views). Les conditions de l'exemption de consentement de la CNIL pour la
 * mesure d'audience, et aucun cookie.
 */
final class ViewCounter
{
    /** Les outils des chercheurs, que la liste de COUNTER écarte et que son Code of Practice for Research Data compte */
    private const RESEARCH_TOOLS = '~python|curl|wget|java~i';

    private ?string $robots = null;

    public function __construct(
        private Connection $connection,
        #[Autowire(service: 'cache.series_views')] private CacheItemPoolInterface $sessions,
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/src/Irstea/BdohConsultBundle/Resources/counter-bots/counter_bots.txt')]
        private string $robotsFile
    ) {
    }

    /** Compte la consultation, sauf d'un robot ou d'une session déjà comptée ; dit si elle a compté. */
    public function count(Request $request, Chronique $chronique): bool
    {
        $agent = (string) $request->headers->get('User-Agent', '');
        if (!$request->isMethod('GET') || $this->isRobot($agent)) {
            return false;
        }
        // Un compteur en panne ne doit jamais empêcher la page de s'afficher
        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $key = 'v' . hash('sha256', implode("\0", [$this->salt($now), $request->getClientIp(), $agent, $now->format('YmdH'), $chronique->getId()]));
            $session = $this->sessions->getItem($key);
            if ($session->isHit()) {
                return false;
            }
            $this->sessions->save($session->set(true)->expiresAt($now->setTime((int) $now->format('G') + 1, 0)));
            $this->connection->executeStatement(
                'INSERT INTO series_views (chronique_id, day, views) VALUES (?, ?, 1)
                 ON CONFLICT (chronique_id, day) DO UPDATE SET views = series_views.views + 1',
                [$chronique->getId(), $now->format('Y-m-d')]
            );
            // Les empreintes échues restent en fichiers jusqu'à ce qu'on les retire : de temps en temps, ici
            if ($this->sessions instanceof PruneableInterface && random_int(1, 500) === 1) {
                $this->sessions->prune();
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Compteur des consultations : {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }

    public function isRobot(string $agent): bool
    {
        return (bool) preg_match($this->robots(), $agent);
    }

    /** Le sel du jour, tiré au hasard et oublié le lendemain : une empreinte ne se recalcule plus. */
    private function salt(\DateTimeImmutable $now): string
    {
        $item = $this->sessions->getItem('salt-' . $now->format('Ymd'));
        if (!$item->isHit()) {
            $this->sessions->save($item->set(bin2hex(random_bytes(16)))->expiresAt($now->setTime(0, 0)->modify('+1 day')));
        }

        return $item->get();
    }

    /** Les expressions de la liste de COUNTER, en une seule, sans tenir compte de la casse */
    private function robots(): string
    {
        if ($this->robots === null) {
            $lines = array_map('trim', file($this->robotsFile, FILE_IGNORE_NEW_LINES) ?: []);
            $kept = array_filter($lines, fn (string $line) => $line !== '' && $line[0] !== '#' && !preg_match(self::RESEARCH_TOOLS, $line));
            $this->robots = '~' . implode('|', $kept) . '~iu';
        }

        return $this->robots;
    }
}
