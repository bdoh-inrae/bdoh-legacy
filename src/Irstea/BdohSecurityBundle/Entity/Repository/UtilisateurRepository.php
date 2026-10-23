<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Entity\Repository;

use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Repository\EntityRepository;
use Irstea\BdohSecurityBundle\Entity\Role;
use Irstea\BdohSecurityBundle\Entity\RoleObservatoire;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Class UtilisateurRepository.
 */
class UtilisateurRepository extends EntityRepository implements UserProviderInterface, UserLoaderInterface
{
    /**
     * Forces to retrieve the 'password' and 'salt' fields of a given 'Utilisateur'.
     */
    public function getPasswordSalt(Utilisateur $user)
    {
        $sql = 'SELECT password, salt FROM utilisateur WHERE id = ' . $user->getId();

        return $this->getEntityManager()->getConnection()->fetchNumeric($sql);
    }

    /**
     * Part of UserProviderInterface, since Symfony 5.3. The e-mail is a bound parameter, no longer
     * inserted into the query (dev/differences.md).
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            return $this->createQueryBuilder('u')
                ->where('u.email = :email')
                ->setParameter('email', $identifier)
                ->getQuery()
                ->getSingleResult();
        } catch (\Exception $e) {
            throw new UserNotFoundException('');
        }
    }

    /**
     * @param string $username
     */
    public function loadUserByUsername($username)
    {
        return $this->loadUserByIdentifier($username);
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        $class = get_class($user);

        if (!$this->supportsClass($class)) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * @param Utilisateur $user
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateUser(Utilisateur $user)
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class): bool
    {
        return $this->getEntityName() === $class || is_subclass_of($class, $this->getEntityName());
    }

    /**
     * @param Observatoire $obs
     *
     * @throws \Exception
     *
     * @return array
     */
    public function findUsersRelatedToTheObservatory(Observatoire $obs)
    {
        // les utilisateurs liés à l'observatoire
        $sqlUsers = <<<'SQL'
SELECT u.id, u.nom, u.prenom, u.email,
CASE WHEN u.roleadmin IS NOT NULL THEN TRUE ELSE FALSE END as isgestionnairedetouslesobservatoires,
CASE WHEN r1.id IS NOT NULL THEN TRUE ELSE FALSE END as isgestionnairedelobservatoire,
array_to_string(array_agg(DISTINCT s1.nom ORDER BY s1.nom) FILTER (WHERE s1.nom IS NOT NULL),'#####') as sitesgestionnaire,
array_to_string(array_agg(DISTINCT s2.nom ORDER BY s2.nom) FILTER (WHERE s2.nom IS NOT NULL),'#####') as sitescontributeur,
CASE WHEN r4.id IS NOT NULL THEN TRUE ELSE FALSE END as isutilisateurdeconfiance,
CASE WHEN c.id IS NOT NULL AND s3.id IS NOT NULL THEN TRUE ELSE FALSE END as isutilisateurdechroniques,
CASE WHEN b.id IS NOT NULL THEN TRUE ELSE FALSE END as hasbesoins
FROM utilisateur u
LEFT JOIN role r1 ON r1.utilisateur_id = u.id AND r1.observatoire_id = :observatoire AND r1.valeur = 'gestionnaire'
LEFT JOIN role r2 ON r2.utilisateur_id = u.id AND r2.valeur = 'gestionnaire'
LEFT JOIN siteexperimental s1 ON r2.site_id = s1.id AND s1.observatoire_id = :observatoire
LEFT JOIN role r3 ON r3.utilisateur_id = u.id AND r3.valeur = 'contributeur'
LEFT JOIN siteexperimental s2 ON r3.site_id = s2.id AND s2.observatoire_id = :observatoire
LEFT JOIN role r4 ON r4.utilisateur_id = u.id AND r4.observatoire_id = :observatoire AND r4.valeur = 'utilisateur'
LEFT JOIN role r5 ON r5.utilisateur_id = u.id AND r5.valeur = 'utilisateur' AND r5.chronique_id IS NOT NULL
LEFT JOIN chronique c ON r5.chronique_id = c.id
LEFT JOIN stations_sites ss ON ss.station_id = c.station_id
LEFT JOIN siteexperimental s3 ON ss.site_id = s3.id AND s3.observatoire_id = :observatoire
LEFT JOIN besoin b ON b.utilisateur_id = u.id AND b.observatoire_id = :observatoire AND (b.traite IS NULL OR b.traite = FALSE)
WHERE u.roleadmin IS NOT NULL
OR r1.id IS NOT NULL
OR (r2.id IS NOT NULL AND s1.id IS NOT NULL)
OR (r3.id IS NOT NULL AND s2.id IS NOT NULL)
OR r4.id IS NOT NULL
OR (r5.id IS NOT NULL AND c.id IS NOT NULL AND s3.id IS NOT NULL)
OR b.id IS NOT NULL
GROUP BY u.id, u.nom, u.prenom, u.email,
isgestionnairedetouslesobservatoires, isgestionnairedelobservatoire, isutilisateurdeconfiance, isutilisateurdechroniques, hasbesoins
ORDER BY u.nom ASC, u.prenom ASC;
SQL;
        $users = $this->getEntityManager()->getConnection()->executeQuery($sqlUsers, [$obs->getId()])->fetchAllAssociative();

        $userIds = [];
        foreach ($users as &$user) {
            // les ids des utilisateurs liés à l'observatoire
            $userIds[] = $user['id'];
            // reconstruction des tableaux sitesgestionnaire et sitescontributeur
            if ($user['sitesgestionnaire']) {
                $user['sitesgestionnaire'] = explode('#####', $user['sitesgestionnaire']);
            }
            if ($user['sitescontributeur']) {
                $user['sitescontributeur'] = explode('#####', $user['sitescontributeur']);
            }
        }

        // les utilisateurs non liés à l'observatoire
        $sqlAutres = <<<'SQL'
SELECT u.nom, u.prenom, u.email
FROM utilisateur u
WHERE u.id NOT IN (:userids)
ORDER BY u.nom ASC, u.prenom ASC;
SQL;
        $sqlAutres = str_replace(':userids', implode(',', $userIds), $sqlAutres);
        $autres = $this->getEntityManager()->getConnection()->executeQuery($sqlAutres)->fetchAllAssociative();

        return [$users, $autres];
    }

    /**
     * @param Observatoire $obs
     *
     * @return string[]
     */
    public function getValidatorMails(Observatoire $obs)
    {
        $qb = $this->createQueryBuilder('u')
            ->from(RoleObservatoire::class, 'ro')
            ->select('u.email')
            ->andWhere('ro.observatoire = :observatoire')
            ->andWhere('ro.utilisateur = u')
            ->andWhere('ro.valeur = :gestionnaire')
            ->setParameter('observatoire', $obs)
            ->setParameter('gestionnaire', Role::GESTIONNAIRE)
            ->getQuery();

        $results = $qb->getResult();
        $toReturn = [];
        foreach ($results as $result) {
            $toReturn[] = $result['email'];
        }

        return $toReturn;
    }

    /**
     * @param Observatoire $observatoire
     *
     * @return array
     */
    public function findGestionnairesByObservatoire(Observatoire $observatoire)
    {
        $query = $this->createQueryBuilder('u')
            ->join('u.rolesObservatoire', 'ro')
            ->andWhere('ro.observatoire = :obs')
            ->andWhere('ro.valeur = :role')
            ->addOrderBy('u.nom', 'ASC')
            ->setParameter('obs', $observatoire)
            ->setParameter('role', 'gestionnaire');

        return $query->getQuery()->getResult();
    }

    /**
     * @param Observatoire $obs
     *
     * @return array
     */
    public function findDerniereConnection(\Irstea\BdohDataBundle\Entity\Observatoire $obs)
    {
        $query = $this->createQueryBuilder('u')
            ->join('u.rolesObservatoire', 'ro')
            ->andWhere('ro.observatoire = :obs')
            ->addOrderBy('u.nom', 'ASC')
            ->setParameter('obs', $obs);
        $results = $query->getQuery()->getResult();

        $users = [];
        foreach ($results as $result) {
            $users[$result->getEmail()] = [
                'nom'    => $result->getNom(),
                'prenom' => $result->getPrenom(),
                'date'   => $result->getDerniereConnection(),
            ];
        }

        $query = $this->createQueryBuilder('u')
            ->join('u.rolesSite', 'rs')
            ->join('rs.site', 's')
            ->andWhere('s.observatoire = :obs')
            ->addOrderBy('u.nom', 'ASC')
            ->setParameter('obs', $obs);
        $results = $query->getQuery()->getResult();

        foreach ($results as $result) {
            $users[$result->getEmail()] = [
                'nom'    => $result->getNom(),
                'prenom' => $result->getPrenom(),
                'date'   => $result->getDerniereConnection(),
            ];
        }

        $query = $this->createQueryBuilder('u')
            ->join('u.rolesChronique', 'rc')
            ->join('rc.chronique', 'c')
            ->join('c.station', 'st')
            ->join('st.sites', 's')
            ->andWhere('s.observatoire = :obs')
            ->addOrderBy('u.nom', 'ASC')
            ->setParameter('obs', $obs);
        $results = $query->getQuery()->getResult();

        foreach ($results as $result) {
            $users[$result->getEmail()] = [
                'nom'    => $result->getNom(),
                'prenom' => $result->getPrenom(),
                'date'   => $result->getDerniereConnection(),
            ];
        }

        return $users;
    }
}
