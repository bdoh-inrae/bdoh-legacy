<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohSecurityBundle\Controller;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\NoResultException;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueContinueRepository;
use Irstea\BdohSecurityBundle\Entity\Favorite;
use Irstea\BdohSecurityBundle\Entity\Repository\FavoriteRepository;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Les favoris d'un compte et leurs étiquettes (dev/plans/2026-11-12_favorites.md). La page des favoris est celle du
 * compte, sans observatoire (`/favorites`, config/routes.yaml) : ses favoris traversent les observatoires. L'étoile et
 * la fenêtre d'export, elles, sont celles d'un observatoire : une chronique s'y cherche dans l'observatoire de la page,
 * et un export ne mêle pas deux observatoires.
 */
#[Route('/favorites')]
class FavoriteController extends Controller
{
    /**
     * La page des favoris, et ce qu'on y fait aux favoris cochés : poser ou retirer une étiquette, les retirer.
     */
    #[IsGranted(new Expression("is_granted('ROLE_USER')"))]
    public function listAction(Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $repository = $this->favorites();

        if ($request->isMethod('POST')) {
            $this->apply($request, $user, $repository);

            return $this->redirectToRoute('bdoh_portal_favorites');
        }

        $groups = $repository->findGroupedFor($user);
        $labels = [];
        foreach ($groups as $group) {
            foreach ($group['favorites'] as $favorite) {
                foreach ($favorite->getLabels() as $label) {
                    $labels[$label] = ($labels[$label] ?? 0) + 1;
                }
            }
        }
        uksort($labels, 'strnatcasecmp');

        return $this->render('@IrsteaBdohSecurity/Favorite/list.html.twig', [
            'groups'         => $groups,
            'labels'         => $labels,
            'labelMaxLength' => Favorite::LABEL_MAX_LENGTH,
        ]);
    }

    /**
     * L'étoile d'une chronique de l'observatoire de la page : `on` l'allume (1) ou l'éteint (0). Deux envois du même
     * état donnent le même résultat, un double clic compris.
     */
    #[Route('/{id}', name: 'bdoh_security_favorite_toggle', requirements: ['id' => '\d{1,9}'], methods: ['POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_USER')"))]
    public function toggleAction(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('favorite', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'invalid token'], Response::HTTP_FORBIDDEN);
        }
        $chronique = $this->chroniqueOfThisObservatoire($id);
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $on = '1' === $request->request->get('on');

        $favorite = $this->favorites()->findOneFor($user, $chronique);
        if ($on && null === $favorite) {
            $em = $this->getEm();
            $em->persist(new Favorite($user, $chronique));
            try {
                $em->flush();
            } catch (UniqueConstraintViolationException) {
                // Un autre envoi l'a posée entre-temps : elle est allumée, c'est ce qui était demandé
            }
        } elseif (!$on && null !== $favorite) {
            $this->getEm()->remove($favorite);
            $this->getEm()->flush();
        }

        return new JsonResponse(['favorite' => $on]);
    }

    /**
     * La fenêtre d'export des favoris cochés d'un observatoire (Modal/export.html.twig), que la page des favoris ouvre :
     * seulement les chroniques de l'observatoire de la page qui sont des favoris du compte, qu'il a le droit d'exporter
     * et qui ont des mesures, avec les rééchantillonnages que toutes permettent. Les autres sont nommées à part.
     */
    #[Route('/export', name: 'bdoh_security_favorites_export', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_USER')"))]
    public function exportAction(Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $ids = array_keys($this->favorites()->chroniqueIdsFor($user, self::ids($request->query->all('chroniques'))));

        $chroniques = $ids ? $this->getBdohRepo('Chronique')->createQueryBuilder('ch')
            ->andWhere('ch.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()->getResult() : [];
        usort($chroniques, fn (Chronique $a, Chronique $b) => strnatcasecmp((string) $a, (string) $b));

        $exportable = $notAllowed = $withoutMeasures = [];
        foreach ($chroniques as $chronique) {
            if (!$this->isGranted('DOWNLOAD', $chronique)) {
                $notAllowed[] = $chronique;
            } elseif (!$chronique->getDateDebutMesures() || !$chronique->getDateFinMesures()) {
                $withoutMeasures[] = $chronique;
            } else {
                $exportable[] = $chronique;
            }
        }

        [$samplings, $timeSteps] = $this->commonExportOptions($exportable);

        return $this->render('@IrsteaBdohSecurity/Favorite/export.html.twig', [
            'chroniques'      => $exportable,
            'notAllowed'      => $notAllowed,
            'withoutMeasures' => $withoutMeasures,
            'dateFirst'       => $exportable ? min(array_map(fn (Chronique $c) => $c->getDateDebutMesures(), $exportable)) : null,
            'dateLast'        => $exportable ? max(array_map(fn (Chronique $c) => $c->getDateFinMesures(), $exportable)) : null,
            'exportSamplings' => $samplings,
            'exportTimeSteps' => $timeSteps,
        ]);
    }

    private function favorites(): FavoriteRepository
    {
        return $this->getSecurityRepo('Favorite');
    }

    private function chroniqueOfThisObservatoire(int $id): Chronique
    {
        try {
            return $this->getBdohRepo('Chronique')->findOneById($id);
        } catch (NoResultException) {
            throw new NotFoundHttpException();
        }
    }

    /**
     * Ce que la page des favoris envoie : une action sur les favoris cochés, chacun du compte, jamais d'un autre.
     */
    private function apply(Request $request, Utilisateur $user, FavoriteRepository $repository): void
    {
        $flash = $this->getSession()->getFlashBag();
        $translator = $this->getTranslator();
        if (!$this->isCsrfTokenValid('favorites', (string) $request->request->get('_token'))) {
            $flash->add('error', $translator->trans('favorite.error.invalidForm'));

            return;
        }
        $favorites = $repository->findSomeFor($user, self::ids($request->request->all('favorites')));
        if (!$favorites) {
            $flash->add('error', $translator->trans('favorite.error.nothingChecked'));

            return;
        }

        $action = (string) $request->request->get('action');
        $em = $this->getEm();
        if ('remove' === $action) {
            foreach ($favorites as $favorite) {
                $em->remove($favorite);
            }
            $em->flush();
            $flash->add('success', $translator->trans('favorite.done.removed', ['%count%' => \count($favorites)]));

            return;
        }

        if (!\in_array($action, ['addLabel', 'removeLabel'], true)) {
            return;
        }
        $label = Favorite::normalizeLabel((string) $request->request->get('label'));
        if ('' === $label) {
            $flash->add('error', $translator->trans('favorite.error.noLabel'));

            return;
        }
        foreach ($favorites as $favorite) {
            'addLabel' === $action ? $favorite->addLabel($label) : $favorite->removeLabel($label);
        }
        $em->flush();
        $flash->add('success', $translator->trans(
            'addLabel' === $action ? 'favorite.done.labelAdded(%label%)' : 'favorite.done.labelRemoved(%label%)',
            // Les messages flash s'affichent en HTML (toastr) : l'étiquette, saisie, s'y échappe
            ['%label%' => htmlspecialchars($label)]
        ));
    }

    /**
     * Les identifiants envoyés par la page : des entiers que PostgreSQL accepte (integer), les autres écartés ; un
     * identifiant plus grand ferait échouer la requête au lieu de ne rien trouver.
     */
    private static function ids(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (\is_string($value) && preg_match('/^[1-9]\d{0,9}$/', $value) && (int) $value <= 2147483647) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    /**
     * Les rééchantillonnages et les pas de temps que toutes ces chroniques permettent, sous la forme qu'en donne la
     * page d'une chronique (ChroniqueController) ; aucun si l'une n'est pas continue, comme pour une discontinue seule.
     *
     * @param Chronique[] $chroniques
     */
    private function commonExportOptions(array $chroniques): array
    {
        if (!$chroniques) {
            return [[], []];
        }
        /** @var ChroniqueContinueRepository $repository */
        $repository = $this->getBdohRepo('ChroniqueContinue');
        $defaults = $this->getParameter('default_allowed_exports');
        $samplings = $timeSteps = null;
        foreach ($chroniques as $chronique) {
            if (!$chronique->isContinue()) {
                return [[], []];
            }
            $allowed = $repository->getAllowedExportParameters($chronique, $defaults);
            $theseSamplings = $theseTimeSteps = [];
            foreach ($allowed['samplings'] as $sampling) {
                $theseSamplings[strtolower($sampling->getCode())] = $sampling;
            }
            foreach ($allowed['timeSteps'] as $timeStep) {
                $theseTimeSteps[$timeStep->getId()] = $timeStep;
            }
            // L'ordre est celui de la première chronique, que getAllowedExportParameters trie
            $samplings = null === $samplings ? $theseSamplings : array_intersect_key($samplings, $theseSamplings);
            $timeSteps = null === $timeSteps ? $theseTimeSteps : array_intersect_key($timeSteps, $theseTimeSteps);
        }

        $en = $this->isLocaleEn();
        $exportSamplings = [];
        foreach ($samplings as $key => $sampling) {
            $exportSamplings[$key] = [
                'code'  => $sampling->getCode(),
                'label' => $en ? $sampling->getNomEn() : $sampling->getNom(),
            ];
        }
        $exportTimeSteps = [];
        foreach ($timeSteps as $id => $timeStep) {
            $exportTimeSteps[$id] = [
                'label'           => $en ? $timeStep->getLibelleEn() : $timeStep->getLibelle(),
                'approxSeconds'   => $timeStep->getApproxSecondes(),
                'askCumulRefHour' => $timeStep->getApproxSecondes() >= 86400,
            ];
        }
        // Une moyenne ou un cumul se choisissent dans une liste de pas : sans pas commun, ils ne se proposent pas
        if (!$exportTimeSteps) {
            unset($exportSamplings['mean'], $exportSamplings['cumulative']);
        }

        return [$exportSamplings, $exportTimeSteps];
    }
}
