<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohBundle\Manager\ObservatoireManagerInterface;
use Irstea\BdohDataBundle\Entity\Bareme;
use Irstea\BdohDataBundle\Entity\Bassin;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Commune;
use Irstea\BdohDataBundle\Entity\CoursEau;
use Irstea\BdohDataBundle\Entity\CriteriaClimat;
use Irstea\BdohDataBundle\Entity\CriteriaGeology;
use Irstea\BdohDataBundle\Entity\DataConstraint;
use Irstea\BdohDataBundle\Entity\DataSet;
use Irstea\BdohDataBundle\Entity\Doi;
use Irstea\BdohDataBundle\Entity\FamilleParametres;
use Irstea\BdohDataBundle\Entity\InspireTheme;
use Irstea\BdohDataBundle\Entity\JeuBareme;
use Irstea\BdohDataBundle\Entity\Milieu;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\ObservatoireRelatedInterface;
use Irstea\BdohDataBundle\Entity\Partenaire;
use Irstea\BdohDataBundle\Entity\PersonneTheia;
use Irstea\BdohDataBundle\Entity\SiteExperimental;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohDataBundle\Entity\TheiaCategories;
use Irstea\BdohDataBundle\Entity\TopicCategory;
use Irstea\BdohDataBundle\Entity\TypeFunding;
use Irstea\BdohDataBundle\Entity\TypeParametre;
use Irstea\BdohDataBundle\Entity\Unite;
use Irstea\BdohSecurityBundle\Entity\Role;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * {@inheritdoc}
 */
class BdohRightVoter implements VoterInterface
{
    /**
     * @var string[]
     */
    const SUPPORTED_ATTRIBUTES = [
        'LIST',
        'VIEW',
        'CREATE',
        'EDIT',
        'DELETE',

        'CONSULT_TIMESERIES',  // affichage des chroniques (gestion des chroniques cachées)
        'CONSULT_CHECKPOINTS', // affichage des points de contrôle (en fonction de la chronique)
        'DOWNLOAD',            // export des mesures (en fonction de la chronique ou de la station)
                               // et export des jeux de barèmes (depuis la page de la chronique)
        'UPLOAD_DATA',         // import et export des barèmes, import des mesures, import et export des points de contrôle,
                               // calcul des chroniques, conversion des chroniques et calcul des taux de remplissage
        'REMOVE_DATA',         // suppression des mesures (en fonction de la chronique)
        'UPLOAD_GIS',          // import des données géographiques (bassins, cours d'eau et stations)
                               // concrètement jamais utilisé directement (verification du droit en amont)

        'RIGHT',               // gestion des droits sur les utilisateurs

        'AJAX',                // listage en ajax depuis sonata pour des entities qui n'ont pas le droit list
    ];

    /**
     * @var string[]
     */
    const SUPPORTED_CLASSES = [
        'Observatoire',
        'SiteExperimental',
        'Station',
        'Chronique',
        'ChroniqueContinue',
        'ChroniqueCalculee',
        'ChroniqueConvertie',
        'ChroniqueDiscontinue',
        'Bareme',
        'DataSet',

        'Bassin',
        'CoursEau',
        'Commune',

        'Doi',
        'Partenaire',

        'JeuQualite',
        'Echantillonnage',
        'PasEchantillonnage',
        'FamilleParametres',
        'TypeParametre',
        'Unite',
        'TopicCategory',
        'CriteriaGeology',
        'CriteriaClimat',
        'DataConstraint',
        'TypeFunding',
        'TheiaCategories',
        'Milieu',
        'InspireTheme',

        'Utilisateur',
        'PersonneTheia',
        'Categorie',
        'ObjectifRecherche',
        'TypeTravaux',

        'JeuBareme',
    ];

    /**
     * @var ObservatoireManagerInterface|null
     */
    private $obsManager;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * BdohRightVoter constructor.
     *
     * @param ObservatoireManagerInterface $manager
     * @param EntityManager                $em
     */
    public function __construct(ObservatoireManagerInterface $manager, EntityManagerInterface $em)
    {
        $this->obsManager = $manager;
        $this->em = $em;
    }

    /**
     * @return Observatoire|null
     */
    private function getCurrentObservatoire()
    {
        return $this->obsManager->getCurrent();
    }

    /**
     * @param mixed $objectOrClass
     *
     * @return array [string, object, Observatoire, SiteExperimental[]]
     */
    private function normalizeClassAndObject($objectOrClass): array
    {
        if (\is_string($objectOrClass)) {
            $class = $objectOrClass;
            $object = null;
        } elseif (\is_object($objectOrClass)) {
            $class = \get_class($objectOrClass);
            $object = $objectOrClass;
        } else {
            return ['', null, null, null];
        }

        $class = explode('\\', $class);
        $class = array_pop($class);

        $obs = $object instanceof ObservatoireRelatedInterface ?
            $object->getObservatoire() : null;
        $obs = $obs ?? $this->getCurrentObservatoire();

        $sites = $object === null ? $obs->getSites() :
            (method_exists($object, 'getSites') ? $object->getSites() : []);

        return [$class, $object, $obs, $sites];
    }

    /**
     * Determines if User can list the objetcs of class Class.
     *
     * @param mixed $user
     * @param mixed $objectOrClass
     *
     * @return bool
     */
    public function canList($user, $objectOrClass): bool
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        list($class, $object, $observatoire, $sites) = $this->normalizeClassAndObject($objectOrClass);

        switch ($class) {
            case 'Chronique':
            case 'ChroniqueContinue':
            case 'ChroniqueCalculee':
            case 'ChroniqueConvertie':
            case 'ChroniqueDiscontinue':
            case 'JeuQualite':
            case 'Echantillonnage':
            case 'PasEchantillonnage':
            case 'FamilleParametres':
            case 'TypeParametre':
            case 'Unite':
            case 'Doi':
            case 'DataSet':
            case 'TopicCategory':
            case 'CriteriaGeology':
            case 'Milieu':
            case 'CriteriaClimat':
            case 'InspireTheme':
            case 'DataConstraint':
            case 'TypeFunding':
            case 'TheiaCategories':
            case 'Partenaire':
            case 'PersonneTheia':
            case 'Utilisateur':
            case 'Categorie':
            case 'ObjectifRecherche':
            case 'TypeTravaux':
                return $user->isGestionnaireDesObservatoires()
                    || $this->canCreate($user, $objectOrClass);
            case 'Observatoire':
                return $user->isGestionnaireDesObservatoires()
                    || $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $observatoire);
            case 'SiteExperimental':
            case 'Station':
            case 'Bareme':
            case 'Bassin':
            case 'CoursEau':
            case 'Commune':
                return $user->isGestionnaireDesObservatoires()
                    || $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $observatoire)
                    || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $sites);
        }

        return false;
    }

    /**
     * Determines if a User can create an object of class Class.
     *
     * WARNING : this function only checks basic right ;
     * actual answer should depend on data (ie the Site where a Station is created, etc.).
     *
     * @param mixed $user
     * @param mixed $objectOrClass
     *
     * @return bool
     */
    public function canCreate($user, $objectOrClass): bool
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        list($class, $object, $observatoire, $sites) = $this->normalizeClassAndObject($objectOrClass);

        switch ($class) {
            case 'ChroniqueContinue':
            case 'ChroniqueCalculee':
            case 'ChroniqueConvertie':
            case 'ChroniqueDiscontinue':
                return $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $observatoire)
                    || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $sites);

            case 'Station':
                return $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $observatoire)
                    || $user->securedHasRoleSite(Role::GESTIONNAIRE, $sites);
            case 'DataSet':
            case 'SiteExperimental':
            case 'Doi':
                return $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $observatoire);

            case 'Partenaire':
            case 'PersonneTheia':
            case 'Utilisateur':
                return $user->isGestionnaireDesObservatoires()
                    || $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $observatoire);
            case 'TheiaCategories':
            case 'TypeFunding':
            case 'TopicCategory':
            case 'CriteriaGeology':
            case 'CriteriaClimat':
            case 'InspireTheme':
            case 'Milieu':
            case 'DataConstraint':
            case 'Observatoire':
            case 'Commune':
            case 'FamilleParametres':
            case 'TypeParametre':
            case 'Unite':
                return $user->isGestionnaireDesObservatoires();
        }

        return false;
    }

    /**
     * Determines if User can modify Object.
     *
     * @param mixed $user
     * @param mixed $object
     *
     * @return bool
     */
    public function canEdit($user, $object)
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        if ($object instanceof Chronique) {
            return $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire())
                || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $object->getSites());
        }

        if ($object instanceof Station) {
            return $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire())
                || $user->securedHasRoleSite(Role::GESTIONNAIRE, $object->getSites());
        }

        if ($object instanceof SiteExperimental
            || $object instanceof Bassin
            || $object instanceof CoursEau
            || $object instanceof Doi || $object instanceof DataSet) {
            return $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire());
        }

        $current = $this->getCurrentObservatoire();

        if ($object instanceof Partenaire
            || $object instanceof Utilisateur) {
            return $user->isGestionnaireDesObservatoires()
                || $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $current);
        }

        if ($object instanceof Observatoire) {
            return $user->isGestionnaireDesObservatoires()
                || $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object);
        }

        if ($object instanceof Commune
            || $object instanceof FamilleParametres
            || $object instanceof TypeParametre
            || $object instanceof Unite || $object instanceof TopicCategory
            || $object instanceof CriteriaGeology || $object instanceof CriteriaClimat || $object instanceof DataConstraint
            || $object instanceof TypeFunding || $object instanceof PersonneTheia || $object instanceof TheiaCategories || $object instanceof Milieu || $object instanceof InspireTheme) {
            return $user->isGestionnaireDesObservatoires();
        }

        return false;
    }

    /**
     * Determines if User can delete Object.
     *
     * @param mixed $user
     * @param mixed $object
     *
     * @return bool
     */
    public function canDelete($user, $object)
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        if ($object instanceof Chronique) {
            return !$object->hasChildren()
                && ($user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire())
                        || $user->securedHasRoleSite(Role::GESTIONNAIRE, $object->getSites()));
        }

        if ($object instanceof Station) {
            return $object->getChroniques()->isEmpty()
                && ($user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire())
                        || $user->securedHasRoleSite(Role::GESTIONNAIRE, $object->getSites()));
        }

        if ($object instanceof SiteExperimental) {
            return $object->getStations()->isEmpty()
                && $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire());
        }

        if ($object instanceof Observatoire) {
            return $object->getSites()->isEmpty()
                && $user->isGestionnaireDesObservatoires();
        }

        if ($object instanceof Bassin
            || $object instanceof CoursEau
            || $object instanceof Doi || $object instanceof DataSet) {
            return $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire());
        }

        if ($object instanceof Partenaire) {
            $timeSeriesWithThisProducteur = $this->em->getRepository('Irstea\BdohDataBundle\Entity\Chronique')->findByProducteur($object);
            $observatoriesWithThisPartenaire = $this->em->getRepository('Irstea\BdohDataBundle\Entity\Observatoire')->findByPartenaire($object);

            return $user->isGestionnaireDesObservatoires()
                || (count($timeSeriesWithThisProducteur) === 0
                    && count($observatoriesWithThisPartenaire) === 0
                    && $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $this->getCurrentObservatoire()));
        }

        if ($object instanceof Commune || $object instanceof TopicCategory
            || $object instanceof CriteriaGeology || $object instanceof CriteriaClimat || $object instanceof DataConstraint
            || $object instanceof TypeFunding || $object instanceof PersonneTheia || $object instanceof TheiaCategories || $object instanceof Milieu
            || $object instanceof InspireTheme) {
            return $user->isGestionnaireDesObservatoires();
        }

        if ($object instanceof FamilleParametres) {
            $childrenFamilies = $this->em->getRepository('Irstea\BdohDataBundle\Entity\FamilleParametres')->findByFamilleParente($object);
            $parameterTypes = $this->em->getRepository('Irstea\BdohDataBundle\Entity\TypeParametre')->findByFamilleParametres($object);

            return count($childrenFamilies) === 0
                && count($parameterTypes) === 0
                && $user->isGestionnaireDesObservatoires();
        }

        if ($object instanceof TypeParametre) {
            $timeSeriesWithThisParametre = $this->em->getRepository('Irstea\BdohDataBundle\Entity\Chronique')->findByParametre($object);

            return count($timeSeriesWithThisParametre) === 0
                && $user->isGestionnaireDesObservatoires();
        }

        if ($object instanceof Unite) {
            $timeSeriesWithThisUnite = $this->em->getRepository('Irstea\BdohDataBundle\Entity\Chronique')->findByUnite($object);
            $scalesWithThisUnite = $this->em->getRepository('Irstea\BdohDataBundle\Entity\Bareme')->findByUnite($object);

            return count($timeSeriesWithThisUnite) === 0
                && count($scalesWithThisUnite) === 0
                && $user->isGestionnaireDesObservatoires();
        }

        if ($object instanceof Bareme) {
            if ($user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire())) {
                return true;
            }
        }

        return false;
    }

    /**
     * affichage des chroniques (gestion des chroniques cachées).
     *
     * @param mixed $user
     * @param mixed $object
     *
     * @return bool
     */
    public function canConsultTimeseries($user, $object)
    {
        return ($object instanceof Chronique)
            && ($object->getEstVisible()
                    || (($user instanceof Utilisateur)
                            && ($user->isGestionnaireDesObservatoires()
                                    || $user->securedHasRoleObservatoire([Role::GESTIONNAIRE, Role::UTILISATEUR], $object->getObservatoire())
                                    || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $object->getObservatoire()->getSites())
                                    || $user->securedHasRoleChronique(Role::UTILISATEUR, $object))));
    }

    /**
     * affichage des points de contrôle (en fonction de la chronique).
     *
     * @param mixed $user
     * @param mixed $object
     *
     * @return bool
     */
    public function canConsultCheckpoints($user, $object)
    {
        return ($object instanceof Chronique)
            && ($user instanceof Utilisateur)
            && ($user->isGestionnaireDesObservatoires()
                    || $user->securedHasRoleObservatoire([Role::GESTIONNAIRE, Role::UTILISATEUR], $object->getObservatoire())
                    || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $object->getObservatoire()->getSites())
                    || $user->securedHasRoleChronique(Role::UTILISATEUR, $object));
    }

    /**
     * export des mesures (en fonction de la chronique ou de la station)
     * et export des jeux de barèmes (depuis la page de la chronique).
     *
     * @param mixed $user
     * @param mixed $object
     *
     * @return bool
     */
    public function canDownload($user, $object)
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        if ($object instanceof Chronique) {
            return $user->isGestionnaireDesObservatoires()
                || $user->securedHasRoleObservatoire([Role::GESTIONNAIRE, Role::UTILISATEUR], $object->getObservatoire())
                || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $object->getObservatoire()->getSites())
                || $user->securedHasRoleChronique(Role::UTILISATEUR, $object);
        }

        if ($object instanceof Station) {
            return $user->isGestionnaireDesObservatoires()
                || $user->securedHasRoleObservatoire([Role::GESTIONNAIRE, Role::UTILISATEUR], $object->getObservatoire())
                || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $object->getObservatoire()->getSites());
        }

        if ($object instanceof JeuBareme) {
            return $user->isGestionnaireDesObservatoires()
                || $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire())
                || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $object->getObservatoire()->getSites());
        }

        return false;
    }

    /**
     * import et export des barèmes, import des mesures, import et export des points de contrôle,
     * calcul des chroniques, conversion des chroniques et calcul des taux de remplissage.
     *
     * ATTENTION :
     * - l'import de mesure est interdit sur les chroniques converties -> géré dans le contôleur.
     * - l'import de points de contrôle est interdit sur les chroniques discontinues et converties -> géré dans le contrôleur.
     *
     * @param mixed $user
     * @param mixed $object
     *
     * @return bool
     */
    public function canUploadData($user, $object)
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        return ($object instanceof Chronique || $object instanceof Station || $object instanceof SiteExperimental)
            && ($user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire())
                    || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $object->getSites()));
    }

    /**
     * suppression des mesures (en fonction de la chronique).
     *
     * @param mixed $user
     * @param mixed $object
     *
     * @return bool
     */
    public function canRemoveData($user, $object)
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        return ($object instanceof Chronique)
            && (!$object->isCalculee())
            && (!$object->isConvertie())
            && ($user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire())
                    || $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $object->getSites()));
    }

    /**
     * import des données géographiques (bassins, cours d'eau et stations)
     * concrètement jamais utilisé directement (verification du droit en amont).
     *
     * @param mixed $user
     * @param mixed $object
     *
     * @return bool
     */
    public function canUploadGis($user, $object)
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        return $object instanceof ObservatoireRelatedInterface
            && $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire());
    }

    /**
     * Determines if User can grant or remove Right on Object.
     *
     * @param mixed  $user
     * @param string $right
     * @param string $object
     *
     * @return bool
     */
    public function canGiveOrRemoveRightOn($user, $right, $object)
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        if ($right === Role::GESTIONNAIRE && $object instanceof Observatoire) {
            return $user->isGestionnaireDesObservatoires();
        }

        if (!($object instanceof ObservatoireRelatedInterface)) {
            return false;
        }

        if ($user->securedHasRoleObservatoire(Role::GESTIONNAIRE, $object->getObservatoire())) {
            return true;
        }

        if ($right === Role::CONTRIBUTEUR && ($object instanceof SiteExperimental)) {
            return $user->securedHasRoleSite(Role::GESTIONNAIRE, $object);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, mixed $object, array $attributes): int
    {
        if (in_array('RIGHT', $attributes, true) && is_array($object)) {
            $actualObject = $object[1];
        } else {
            $actualObject = $object;
        }

        $class = \is_object($actualObject) ? get_class($actualObject) : (string) $actualObject;
        if (!$this->supportsClass($class)) {
            return self::ACCESS_ABSTAIN;
        }

        $user = $token->getUser();

        $default = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {
            if ($this->supportsAttribute($attribute)) {
                if ($this->voteOn($user, $attribute, $object)) {
                    return self::ACCESS_GRANTED;
                }
                $default = self::ACCESS_DENIED;
            }
        }

        return $default;
    }

    /**
     * @param mixed $user
     * @param mixed $attribute
     * @param mixed $object
     *
     * @return bool
     */
    private function voteOn($user, $attribute, $object): bool
    {
        switch ($attribute) {
            case 'LIST':
                return $this->canList($user, $object);
            case 'VIEW':
                return $this->canEdit($user, $object);
            case 'CREATE':
                return $this->canCreate($user, $object);
            case 'EDIT':
                return $this->canEdit($user, $object);
            case 'DELETE':
                return $this->canDelete($user, $object);

            case 'CONSULT_TIMESERIES':
                return $this->canConsultTimeseries($user, $object);
            case 'CONSULT_CHECKPOINTS':
                return $this->canConsultCheckpoints($user, $object);
            case 'DOWNLOAD':
                return $this->canDownload($user, $object);
            case 'UPLOAD_DATA':
                return $this->canUploadData($user, $object);
            case 'REMOVE_DATA':
                return $this->canRemoveData($user, $object);
            case 'UPLOAD_GIS':
                return $this->canUploadGis($user, $object);

            case 'RIGHT':
                /*
                 * Convention:
                 * $object[0] is right to grant,
                 * $object[1] is object on which right is to be granted
                 */
                return is_array($object) && $this->canGiveOrRemoveRightOn($user, $object[0], $object[1]);

            case 'AJAX':
                return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAttribute($attribute)
    {
        return \in_array($attribute, self::SUPPORTED_ATTRIBUTES, true);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        $names = explode('\\', $class);
        $name = array_pop($names);

        return \in_array($name, self::SUPPORTED_CLASSES, true);
    }
}
