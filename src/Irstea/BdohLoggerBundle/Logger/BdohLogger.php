<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Logger;

use Doctrine\ORM\EntityManager;
use Irstea\BdohDataBundle\Entity\Bareme;
use Irstea\BdohDataBundle\Entity\Bassin;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueConvertie;
use Irstea\BdohDataBundle\Entity\Commune;
use Irstea\BdohDataBundle\Entity\CoursEau;
use Irstea\BdohDataBundle\Entity\CriteriaClimat;
use Irstea\BdohDataBundle\Entity\CriteriaGeology;
use Irstea\BdohDataBundle\Entity\DataConstraint;
use Irstea\BdohDataBundle\Entity\DataSet;
use Irstea\BdohDataBundle\Entity\Doi;
use Irstea\BdohDataBundle\Entity\FamilleParametres;
use Irstea\BdohDataBundle\Entity\InspireTheme;
use Irstea\BdohDataBundle\Entity\Milieu;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Partenaire;
use Irstea\BdohDataBundle\Entity\PersonneTheia;
use Irstea\BdohDataBundle\Entity\SiteExperimental;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohDataBundle\Entity\TheiaCategories;
use Irstea\BdohDataBundle\Entity\TopicCategory;
use Irstea\BdohDataBundle\Entity\TypeFunding;
use Irstea\BdohDataBundle\Entity\TypeParametre;
use Irstea\BdohDataBundle\Entity\Unite;
use Irstea\BdohLoggerBundle\Entity\Historique;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministration;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationBareme;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationBassin;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationChronique;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationCommune;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationCoursEau;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationCriteriaClimate;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationCriteriaGeology;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationDataConstraint;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationDataSet;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationDoi;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationFamilleParametres;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationInspireTheme;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationMilieu;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationObservatoire;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationPartenaire;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationPersonneTheia;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationSiteExperimental;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationStation;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationTheiaCategories;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationTopicCategory;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationTypeFunding;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationTypeParametre;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationUnite;
use Irstea\BdohLoggerBundle\Entity\HistoriqueAdministrationUtilisateur;
use Irstea\BdohLoggerBundle\Entity\HistoriqueBaremesExport;
use Irstea\BdohLoggerBundle\Entity\HistoriqueBaremesImport;
use Irstea\BdohLoggerBundle\Entity\HistoriqueCalculsChroniques;
use Irstea\BdohLoggerBundle\Entity\HistoriqueConversionsChroniques;
use Irstea\BdohLoggerBundle\Entity\HistoriqueDonnees;
use Irstea\BdohLoggerBundle\Entity\HistoriqueDonneesExport;
use Irstea\BdohLoggerBundle\Entity\HistoriqueDonneesImport;
use Irstea\BdohLoggerBundle\Entity\HistoriqueDonneesPointControle;
use Irstea\BdohLoggerBundle\Entity\HistoriqueDonneesSuppression;
use Irstea\BdohLoggerBundle\Entity\HistoriqueSig;
use Irstea\BdohLoggerBundle\Entity\HistoriqueTransformations;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class BdohLogger.
 */
class BdohLogger
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * BdohLogger constructor.
     *
     * @param EntityManager       $entityManager
     * @param TranslatorInterface $translator
     */
    public function __construct(EntityManager $entityManager, TranslatorInterface $translator)
    {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
    }

    /**
     * @param Historique   $historique
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param string       $description
     *
     * @return Historique
     */
    protected function initiateHistorique(
        Historique $historique,
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $description = null
    ) {
        $historique->setAuteur($author);
        $historique->setDate($date->setTimezone(new \DateTimeZone('UTC')));
        $historique->setObservatoire($obs);

        //Default description ; usefull only for pure Historique
        //Will be overwritten by other subtypes by constructDescription (cf completeHistorique)
        $historique->setDescription($description);

        return $historique;
    }

    /**
     * @param Historique $historique
     */
    protected function completeHistorique(Historique $historique)
    {
        $historique->constructDescription($this->translator);
        $historique->constructLien();
        $this->entityManager->persist($historique);
        $this->entityManager->flush();
    }

    /**
     * @param HistoriqueAdministration $historique
     * @param $action
     *
     * @return HistoriqueAdministration
     */
    protected function enrichHistoriqueAdministration(HistoriqueAdministration $historique, $action)
    {
        $historique->setAction($action);

        return $historique;
    }

    /**
     * @param HistoriqueDonnees $historique
     * @param Chronique         $chronique
     * @param $debut
     * @param $fin
     * @param $nombreMesures
     *
     * @return HistoriqueDonnees
     */
    protected function enrichHistoriqueDonnees(HistoriqueDonnees $historique, Chronique $chronique, $debut, $fin, $nombreMesures)
    {
        $historique->setChronique($chronique);
        $historique->setDebut($debut);
        $historique->setFin($fin);
        $historique->setNombreMesures($nombreMesures);

        return $historique;
    }

    /**
     * @param HistoriqueSig $historique
     * @param int[]         $stats
     * @param mixed         $typeShape
     *
     * @return HistoriqueSig
     */
    protected function enrichHistoriqueSig(HistoriqueSig $historique, $stats, $typeShape)
    {
        $historique->setNombreInserts($stats[0]);
        $historique->setNombreMesures($stats[1]);
        $historique->setTypeShape($this->translator->trans($typeShape));

        return $historique;
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param null         $description
     */
    public function createHistorique(Utilisateur $author, \DateTime $date, Observatoire $obs, $description = null)
    {
        $this->completeHistorique(
            $this->initiateHistorique(new Historique(), $author, $date, $obs, $description)
        );
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param null $description
     */
    public function createHistoriqueAdministration(Utilisateur $author, \DateTime $date, Observatoire $obs, $action, $description = null)
    {
        $this->completeHistorique(
            $this->enrichHistoriqueAdministration(
                $this->initiateHistorique(new HistoriqueAdministration(), $author, $date, $obs, $description),
                $action
            )
        );
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param Chronique    $chronique
     * @param $debut
     * @param $fin
     * @param $nombreMesures
     * @param null $description
     */
    public function createHistoriqueDonnees(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        Chronique $chronique,
        $debut,
        $fin,
        $nombreMesures,
        $description = null
    ) {
        $this->completeHistorique(
            $this->enrichHistoriqueDonnees(
                $this->initiateHistorique(new HistoriqueDonnees(), $author, $date, $obs, $description),
                $chronique,
                $debut,
                $fin,
                $nombreMesures
            )
        );
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param int[]        $stats
     * @param mixed        $typeShape
     * @param null         $description
     */
    public function createHistoriqueSig(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $stats,
        $typeShape,
        $description = null
    ) {
        $this->completeHistorique(
            $this->enrichHistoriqueSig(
                $this->initiateHistorique(new HistoriqueSig(), $author, $date, $obs, $description),
                $stats,
                $typeShape
            )
        );
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Utilisateur $user
     * @param null        $description
     */
    public function createHistoriqueAdministrationUtilisateur(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Utilisateur $user,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationUtilisateur(), $author, $date, $obs, $description);
        $historique->setUtilisateur($user);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Bareme $bareme
     * @param null   $description
     */
    public function createHistoriqueAdministrationBareme(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Bareme $bareme,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationBareme(), $author, $date, $obs, $description);
        $historique->setBareme($bareme);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Commune $commune
     * @param null    $description
     */
    public function createHistoriqueAdministrationCommune(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Commune $commune,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationCommune(), $author, $date, $obs, $description);
        $historique->setCommune($commune);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param CoursEau $coursEau
     * @param null     $description
     */
    public function createHistoriqueAdministrationCoursEau(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        CoursEau $coursEau,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationCoursEau(), $author, $date, $obs, $description);
        $historique->setCoursEau($coursEau);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Bassin $bassin
     * @param null   $description
     */
    public function createHistoriqueAdministrationBassin(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Bassin $bassin,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationBassin(), $author, $date, $obs, $description);
        $historique->setBassin($bassin);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param DataSet $dataset
     * @param null    $description
     */
    public function createHistoriqueAdministrationDataSet(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        DataSet $dataset,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationDataSet(), $author, $date, $obs, $description);
        $historique->setDataSet($dataset);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Milieu $milieu
     * @param null   $description
     */
    public function createHistoriqueAdministrationMilieu(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Milieu $milieu,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationMilieu(), $author, $date, $obs, $description);
        $historique->setMilieu($milieu);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param PersonneTheia $personneTheia
     * @param null          $description
     */
    public function createHistoriqueAdministrationPersonneTheia(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        PersonneTheia $personneTheia,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationPersonneTheia(), $author, $date, $obs, $description);
        $historique->setPersonneTheia($personneTheia);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param TypeFunding $typeFunding
     * @param null        $description
     */
    public function createHistoriqueAdministrationTypeFunding(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        TypeFunding $typeFunding,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationTypeFunding(), $author, $date, $obs, $description);
        $historique->setTypefunding($typeFunding);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param TopicCategory $topicCategory
     * @param null          $description
     */
    public function createHistoriqueAdministrationTopicCategory(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        TopicCategory $topicCategory,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationTopicCategory(), $author, $date, $obs, $description);
        $historique->setTopicCategory($topicCategory);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param TheiaCategories $theiaCategories
     * @param null            $description
     */
    public function createHistoriqueAdministrationTheiaCategories(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        TheiaCategories $theiaCategories,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationTheiaCategories(), $author, $date, $obs, $description);
        $historique->setTheiaCategories($theiaCategories);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param InspireTheme $inspireTheme
     * @param null         $description
     */
    public function createHistoriqueAdministrationInspireTheme(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        InspireTheme $inspireTheme,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationInspireTheme(), $author, $date, $obs, $description);
        $historique->setInspireTheme($inspireTheme);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param DataConstraint $dataconstraint
     * @param null           $description
     */
    public function createHistoriqueAdministrationDataconstraint(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        DataConstraint $dataconstrain,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationDataConstraint(), $author, $date, $obs, $description);
        $historique->setDataconstraint($dataconstrain);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param CriteriaGeology $criteriageology
     * @param null            $description
     */
    public function createHistoriqueAdministrationCriteriaGeology(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        CriteriaGeology $criteriageology,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationCriteriaGeology(), $author, $date, $obs, $description);
        $historique->setCriteriaGeology($criteriageology);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param CriteriaClimat $criteriaclimat
     * @param null           $description
     */
    public function createHistoriqueAdministrationCriteriaClimate(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        CriteriaClimat $criteriaclimat,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationCriteriaClimate(), $author, $date, $obs, $description);
        $historique->setCriteriaClimat($criteriaclimat);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Partenaire $partenaire
     * @param null       $description
     */
    public function createHistoriqueAdministrationPartenaire(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Partenaire $partenaire,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationPartenaire(), $author, $date, $obs, $description);
        $historique->setPartenaire($partenaire);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Chronique $chronique
     * @param null      $description
     */
    public function createHistoriqueAdministrationChronique(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Chronique $chronique,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationChronique(), $author, $date, $obs, $description);
        $historique->setChronique($chronique);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Observatoire $cible
     * @param null         $description
     */
    public function createHistoriqueAdministrationObservatoire(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Observatoire $cible,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationObservatoire(), $author, $date, $obs, $description);
        $historique->setObservatoireCible($cible);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Doi  $doi
     * @param null $description
     */
    public function createHistoriqueAdministrationDoi(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Doi $doi,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationDoi(), $author, $date, $obs, $description);
        $historique->setDoi($doi);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param SiteExperimental $siteExperimental
     * @param null             $description
     */
    public function createHistoriqueAdministrationSiteExperimental(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        SiteExperimental $siteExperimental,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationSiteExperimental(), $author, $date, $obs, $description);
        $historique->setSiteExperimental($siteExperimental);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Station $station
     * @param null    $description
     */
    public function createHistoriqueAdministrationStation(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Station $station,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationStation(), $author, $date, $obs, $description);
        $historique->setStation($station);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur       $author
     * @param \DateTime         $date
     * @param Observatoire      $obs
     * @param mixed             $action
     * @param FamilleParametres $familleParametres
     * @param null              $description
     */
    public function createHistoriqueAdministrationFamilleParametres(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        FamilleParametres $familleParametres,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationFamilleParametres(), $author, $date, $obs, $description);
        $historique->setFamilleParametres($familleParametres);
        $historique->setFamilleParente($familleParametres->getFamilleParente());
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur   $author
     * @param \DateTime     $date
     * @param Observatoire  $obs
     * @param mixed         $action
     * @param TypeParametre $typeParametre
     * @param null          $description
     */
    public function createHistoriqueAdministrationTypeParametre(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        TypeParametre $typeParametre,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationTypeParametre(), $author, $date, $obs, $description);
        $historique->setTypeParametre($typeParametre);
        $historique->setFamilleParametres($typeParametre->getFamilleParametres());
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $action
     * @param Unite $unite
     * @param null  $description
     */
    public function createHistoriqueAdministrationUnite(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $action,
        Unite $unite,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueAdministrationUnite(), $author, $date, $obs, $description);
        $historique->setUnite($unite);
        $this->completeHistorique($this->enrichHistoriqueAdministration($historique, $action));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param Chronique    $chronique
     * @param $debut
     * @param $fin
     * @param $nombreMesures
     * @param $formatExport
     * @param $typeExport
     * @param $timestep
     * @param $fuseau
     * @param null $description
     */
    public function createHistoriqueDonneesExport(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        Chronique $chronique,
        $debut,
        $fin,
        $nombreMesures,
        $formatExport,
        $typeExport,
        $timestep,
        $fuseau,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueDonneesExport(), $author, $date, $obs, $description);
        $historique->setFormatExport($formatExport);
        $historique->setTypeExport($typeExport);
        $historique->setTimestep($timestep);
        $historique->setFuseau($fuseau);
        $this->completeHistorique($this->enrichHistoriqueDonnees($historique, $chronique, $debut, $fin, $nombreMesures));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param Chronique    $chronique
     * @param $debut
     * @param $fin
     * @param $nombreMesures
     * @param $formatImport
     * @param $fuseau
     * @param null $description
     */
    public function createHistoriqueDonneesImport(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        Chronique $chronique,
        $debut,
        $fin,
        $nombreMesures,
        $formatImport,
        $fuseau,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueDonneesImport(), $author, $date, $obs, $description);
        $historique->setFormatImport($formatImport);
        $historique->setFuseau($fuseau);
        $this->completeHistorique($this->enrichHistoriqueDonnees($historique, $chronique, $debut, $fin, $nombreMesures));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param Chronique    $chronique
     * @param $debut
     * @param $fin
     * @param $nombreMesures
     * @param $changedIntoGap
     * @param null $description
     */
    public function createHistoriqueDonneesSuppression(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        Chronique $chronique,
        $debut,
        $fin,
        $nombreMesures,
        $changedIntoGap,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueDonneesSuppression(), $author, $date, $obs, $description);
        $historique->setChangedIntoGap($changedIntoGap);
        $this->completeHistorique($this->enrichHistoriqueDonnees($historique, $chronique, $debut, $fin, $nombreMesures));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param Chronique    $chronique
     * @param $debut
     * @param $fin
     * @param $nombreMesures
     * @param string $action
     * @param mixed  $fuseau
     * @param null   $description
     */
    public function createHistoriqueDonneesPointControle(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        Chronique $chronique,
        $debut,
        $fin,
        $nombreMesures,
        $action,
        $fuseau,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueDonneesPointControle(), $author, $date, $obs, $description);
        $historique->setAction($action);
        $historique->setFuseau($fuseau);
        $this->completeHistorique($this->enrichHistoriqueDonnees($historique, $chronique, $debut, $fin, $nombreMesures));
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param null         $description
     */
    public function createHistoriqueTransformations(Utilisateur $author, \DateTime $date, Observatoire $obs, $description = null)
    {
        $this->completeHistorique($this->initiateHistorique(new HistoriqueTransformations(), $author, $date, $obs, $description));
    }

    /**
     * @param $historique
     * @param $name
     * @param $inputUnit
     * @param $outputUnit
     * @param $xMin
     * @param $xMax
     *
     * @return mixed
     */
    public function enrichHistoriqueBaremes($historique, $name, $inputUnit, $outputUnit, $xMin, $xMax)
    {
        $historique->setName($name);
        $historique->setInputUnit($inputUnit);
        $historique->setOutputUnit($outputUnit);
        $historique->setMinAbscissa($xMin);
        $historique->setMaxAbscissa($xMax);

        return $historique;
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $baremeName
     * @param $inputUnit
     * @param $outputUnit
     * @param $xMin
     * @param $xMax
     * @param $dateCreation
     * @param $nValues
     * @param null $description
     */
    public function createHistoriqueBaremesImport(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $baremeName,
        $inputUnit,
        $outputUnit,
        $xMin,
        $xMax,
        $dateCreation,
        $nValues,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueBaremesImport(), $author, $date, $obs, $description);
        $historique->setAction('historique.actions.BaremeImport');
        $this->completeHistorique(
            $this->enrichHistoriqueBaremesImport(
                $this->enrichHistoriqueBaremes($historique, $baremeName, $inputUnit, $outputUnit, $xMin, $xMax),
                $dateCreation,
                $nValues
            )
        );
    }

    /**
     * @param HistoriqueBaremesImport $historique
     * @param $dateCreation
     * @param $nValues
     *
     * @return HistoriqueBaremesImport
     */
    protected function enrichHistoriqueBaremesImport(HistoriqueBaremesImport $historique, $dateCreation, $nValues)
    {
        $historique->setDateCreation($dateCreation);
        $historique->setNValues($nValues);

        return $historique;
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $baremeName
     * @param $inputUnit
     * @param $outputUnit
     * @param $xMin
     * @param $xMax
     * @param $dateCreation
     * @param $nValues
     * @param null $description
     */
    public function createHistoriqueBaremesExport(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        $baremeName,
        $inputUnit,
        $outputUnit,
        $xMin,
        $xMax,
        $dateCreation,
        $nValues,
        $description = null
    ) {
        $historique = $this->initiateHistorique(new HistoriqueBaremesExport(), $author, $date, $obs, $description);
        $historique->setAction('historique.actions.BaremeExport');
        $this->completeHistorique(
            $this->enrichHistoriqueBaremesExport(
                $this->enrichHistoriqueBaremes($historique, $baremeName, $inputUnit, $outputUnit, $xMin, $xMax),
                $dateCreation,
                $nValues
            )
        );
    }

    /**
     * @param HistoriqueBaremesExport $historique
     * @param $dateCreation
     * @param $nValues
     *
     * @return HistoriqueBaremesExport
     */
    protected function enrichHistoriqueBaremesExport(HistoriqueBaremesExport $historique, $dateCreation, $nValues)
    {
        $historique->setDateCreation($dateCreation);
        $historique->setNValues($nValues);

        return $historique;
    }

    /**
     * @param Utilisateur  $author
     * @param \DateTime    $date
     * @param Observatoire $obs
     * @param $chroniqueCalculee
     */
    public function createHistoriqueCalculChronique(Utilisateur $author, \DateTime $date, Observatoire $obs, $chroniqueCalculee)
    {
        /** @var HistoriqueCalculsChroniques $historique */
        $historique = $this->initiateHistorique(new HistoriqueCalculsChroniques(), $author, $date, $obs);
        $historique->serializeDescription($chroniqueCalculee);
        $historique->setAction('historique.actions.CalculChronique');
        $this->completeHistorique($historique);
    }

    /**
     * @param Utilisateur        $author
     * @param \DateTime          $date
     * @param Observatoire       $obs
     * @param ChroniqueConvertie $chroniqueConvertie
     * @param null               $description
     */
    public function createHistoriqueConversionChronique(
        Utilisateur $author,
        \DateTime $date,
        Observatoire $obs,
        ChroniqueConvertie $chroniqueConvertie,
        $description = null
    ) {
        /** @var HistoriqueConversionsChroniques $historique */
        $historique = $this->initiateHistorique(new HistoriqueConversionsChroniques(), $author, $date, $obs, $description);
        $historique->setAction('historique.actions.ConversionChronique');
        $historique->setChroniqueConvertie($chroniqueConvertie);
        $historique->setChroniqueMere($chroniqueConvertie->getConversion()->getEntree());
        $historique->setParamConversion($chroniqueConvertie->getConversion()->getJeuConversionActuel()->getParamConversion());
        $this->completeHistorique($historique);
    }
}
