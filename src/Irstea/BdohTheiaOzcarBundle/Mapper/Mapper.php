<?php

namespace Irstea\BdohTheiaOzcarBundle\Mapper;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\DataSet as DataSetEntity;
use Irstea\BdohDataBundle\Entity\Observatoire as ObservatoireEntity;
use Irstea\BdohDataBundle\Entity\Partenaire as PartenaireEntity;
use Irstea\BdohDataBundle\Entity\PersonneTheia;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Entity\Repository\DataSetRepository;
use Irstea\BdohDataBundle\Entity\Repository\TheiaCategoriesRepository;
use Irstea\BdohTheiaOzcarBundle\Model\Contact;
use Irstea\BdohTheiaOzcarBundle\Model\DataConstraint;
use Irstea\BdohTheiaOzcarBundle\Model\Datasets;
use Irstea\BdohTheiaOzcarBundle\Model\Datasets as DataSetDTO;
use Irstea\BdohTheiaOzcarBundle\Model\FeatureOfInterest;
use Irstea\BdohTheiaOzcarBundle\Model\FormatPivot;
use Irstea\BdohTheiaOzcarBundle\Model\Fundings;
use Irstea\BdohTheiaOzcarBundle\Model\Observations;
use Irstea\BdohTheiaOzcarBundle\Model\ObservedProperty;
use Irstea\BdohTheiaOzcarBundle\Model\OnlineResource;
use Irstea\BdohTheiaOzcarBundle\Model\Organisation;
use Irstea\BdohTheiaOzcarBundle\Model\Producer as ProducerDTO;
use Irstea\BdohTheiaOzcarBundle\Model\SpatialExtent;
use Irstea\BdohTheiaOzcarBundle\Model\TemporalExtent;
use Doctrine\Persistence\ManagerRegistry;
use Irstea\BdohDataBundle\Entity\TheiaCategories;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Mapper implements MapperInterface
{
    private $registry;

    private $generateurUrl;

    public function __construct(ManagerRegistry $registry, UrlGeneratorInterface $generateurUrl)
    {
        $this->registry = $registry;
        $this->generateurUrl = $generateurUrl;
    }

    //------------------mapFormatPivot-----------------------------------------------

    public function mapFormatPivotInterface(ObservatoireEntity $entity): FormatPivot
    {
        $formatpivot = new FormatPivot();
        $this->mapFormatPivot($entity, $formatpivot);

        return $formatpivot;
    }

    private function mapFormatPivot(ObservatoireEntity $entity, FormatPivot $formatpivot): FormatPivot
    {
        $codeObservatoire = $entity->getTheiaCode();
        foreach ($entity->getDataSets() as $dataSet) {
            $formatpivot->setDatasets(
                $this->mapMetadata($dataSet, $codeObservatoire)
            );
        }
        $formatpivot->setProducer($this->mapProducer($entity, $formatpivot->getProducer()));

        return $formatpivot;
    }

    //------------------------- mapMetaData ---------------------------------------------------

    private function mapMetadata(DataSetEntity $entity, string $codeObservatoire): DataSetDTO
    {
        $dto = new Datasets();
        $dto->setDatasetId($codeObservatoire . '_DAT_' . $entity->getId());

        //conditionne en fonction du champs en anglais
        if ($entity->getTitreEn() === null) {
            $dto->metadata->setTitle($entity->getTitre());
        } else {
            $dto->metadata->setTitle($entity->getTitreEn());
        }

        //conditionne en fonction du champs en anglais
        if ($entity->getDescriptionEn() === null) {
            $dto->metadata->setDescription($entity->getDescription());
        } else {
            $dto->metadata->setDescription($entity->getDescriptionEn());
        }

        //conditionne en fonction du champs en anglais
        if ($entity->getGenealogieEn() === null) {
            $dto->metadata->setDatasetLineage($entity->getGenealogie());
        } else {
            $dto->metadata->setDatasetLineage($entity->getGenealogieEn());
        }

        $dto->metadata->setInspireTheme($entity->getInspireTheme());
        $dto->metadata->setSpatialExtent($this->mapSpatialExtends($entity));

        // conditionne le mapping du Online ressource en cas de DOI non présent
        $dto->metadata->setOnlineResource($this->mapOnlineResourceDataset($entity));

        foreach ($entity->getTopicCategories() as $topicCat) {
            $dto->metadata->addTopicCategories($topicCat->getNom());
        }
        foreach ($entity->getPortailSearchCriteriaClimat() as $criteriaclimat) {
            $dto->metadata->portalSearchCriteria->addClimate($criteriaclimat->getNom());
        }
        foreach ($entity->getPortailSearchCriteriaGeology() as $criteriaGeology) {
            $dto->metadata->portalSearchCriteria->addGeology($criteriaGeology->getNom());
        }
        $dto->metadata->setDataConstraint($this->mapDataConstraint($entity));

        foreach ($entity->getPrincipalInvestigators() as $personneTheia) {
            $role = 'Principal investigator';
            $dto->metadata->addContact($this->mapContact($personneTheia, $role));
        }
        foreach ($entity->getDataCollectors() as $personneTheia) {
            $role = 'Data collector';
            $dto->metadata->addContact($this->mapContact($personneTheia, $role));
        }
        foreach ($entity->getDataManagers() as $personneTheia) {
            $role = 'Data manager';
            $dto->metadata->addContact($this->mapContact($personneTheia, $role));
        }
        foreach ($entity->getProjectMembers() as $personneTheia) {
            $role = 'Project member';
            $dto->metadata->addContact($this->mapContact($personneTheia, $role));
        }
        foreach ($entity->getChroniques() as $chronique) {
            $dto->addObservations($this->mapObservation($chronique, $codeObservatoire));
        }

        return $dto;
    }

    private function mapDataConstraint(DataSetEntity $dataSet): DataConstraint
    {
        $dataConstraint = new DataConstraint();
        $dataConstraint->setAccessUseConstraint($dataSet->getDataConstraint());
        $dataConstraint->setUrlDataPolicy($this->generateurUrl->generate('bdoh_cgu_observatoires', [], UrlGeneratorInterface::ABSOLUTE_URL));

        return $dataConstraint;
    }

    private function mapOnlineResourceDataset(DataSetEntity $dataSet): OnlineResource
    {
        $nomDataset = $dataSet->getUuid();
        $onlineResource = new OnlineResource();

        $onlineResource->setDoi($dataSet->getDoi());
        $onlineResource->setUrlDownload($this->generateurUrl->generate('bdoh_consult_dataset', ['dataset' => "$nomDataset"], UrlGeneratorInterface::ABSOLUTE_URL));
        $onlineResource->setUrlInfo($this->generateurUrl->generate('bdoh_consult_observatoire', [], UrlGeneratorInterface::ABSOLUTE_URL));

        return $onlineResource;
    }

    private function mapOnlineResourceProducer(ObservatoireEntity $observatoire): OnlineResource
    {
        $onlineResource = new OnlineResource();

        $onlineResource->setDoi($observatoire->getDoiPrincipal());
        $onlineResource->setUrlDownload($this->generateurUrl->generate('bdoh_consult_observatoire', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $onlineResource->setUrlInfo($this->generateurUrl->generate('bdoh_consult_observatoire', [], UrlGeneratorInterface::ABSOLUTE_URL));

        return $onlineResource;
    }

    //------------------------------mapContactTest-------------------------------------

    private function mapContact(PersonneTheia $personneTheia, string $role): Contact
    {
        $partenaire = $personneTheia->getPartenaire();
        $orgadto = new Organisation();
        $contact = new Contact($orgadto);
        $organisation = $this->mapOrganisation($partenaire, $orgadto);
        $contact->setOrganisation($organisation);
        $contact->setFirstName($personneTheia->getPrenom());
        $contact->setLastName($personneTheia->getNom());
        $contact->setEmail($personneTheia->getEmail());
        $contact->setRole($role);

        return $contact;
    }

    //-----------------------------mapProducer----------------------------

    private function mapProducer(ObservatoireEntity $entity, ProducerDTO $dtoPro): ProducerDTO
    {
        $dtoPro->setProducerId($entity->getTheiaCode());
        $dtoPro->setName($entity->getNom());
        if ($entity->getTitreEn() === null) {
            $dtoPro->setTitle($entity->getTitre());
        } else {
            $dtoPro->setTitle($entity->getTitreEn());
        }

        //conditionne en fonction du champs en anglais
        if ($entity->getDescriptionEn() === null) {
            $dtoPro->setDescription($entity->getDescription());
        } else {
            $dtoPro->setDescription($entity->getDescriptionEn());
        }

        $dtoPro->setEmail($entity->getEmail());

        foreach ($entity->getDataManagers() as $personneTheia) {
            $role = 'Data manager';
            $dtoPro->setContact($this->mapContact($personneTheia, $role));
        }

        $dtoPro->setOnlineResource($this->mapOnlineResourceProducer($entity));
        $dtoPro->setContact($this->mapContact($entity->getProjectLeader(), 'Project leader'));

        foreach ($entity->getPartenaires() as $fundings) {
            if ($fundings->getEstFinanceur() == true) {
                $dtoPro->setFundings($this->mapFundings($fundings));
            }
        }

        return $dtoPro;
    }

    //--------------------------------mapOrganisation--------------------------

    private function mapOrganisation(PartenaireEntity $partenaire, Organisation $organisation): Organisation
    {
        $organisation->setName($partenaire->getNom());
        if ($partenaire->getIso() == 'FR') {
            $organisation->setIdScanR($partenaire->getScanR());
        } else {
            $organisation->setIdScanR(null);
        }
        $organisation->setIso3166($partenaire->getIso());
        $organisation->setRole('Research group');

        return $organisation;
    }

    //------------------------------MapObservation------------------------

    private function mapObservation(Chronique $entity, string $codeObservatoire): Observations
    {
        $dtoobservation = new Observations();
        $dtoobservation->setFeatureOfInterest($this->mapFeatureOfInterest($entity));
        $dtoobservation->setObservedProperty($this->mapObservedProperty($entity));
        $dtoobservation->setTemporalExtent($this->mapTemporalExtends($entity));
        $dtoobservation->setObservationId($codeObservatoire . '_OBS_' . $entity->getChronique()->getId());
        $dtoobservation->setDataType('Numeric');
        $dtoobservation->setProcessingLevel('Quality-controlled data');

        return $dtoobservation;
    }

    private function mapFeatureOfInterest(Chronique $chronique): FeatureOfInterest
    {
        $chroniqueId = $chronique->getId();
        /** @var ChroniqueRepository $chroniquerepo */
        $chroniquerepo = $this->registry->getRepository(Chronique::class);
        $point = $chroniquerepo->getPoint($chroniqueId);
        $featureOfInterest = new FeatureOfInterest();
        $featureOfInterest->samplingFeature->setType('Feature');
        $featureOfInterest->samplingFeature->setProperties(((object) null));
        $featureOfInterest->samplingFeature->setName($chronique->getStation()->getNom());
        $featureOfInterest->samplingFeature->geometry->setCoordinates($point);
        $featureOfInterest->samplingFeature->geometry->setType('Point');

        return $featureOfInterest;
    }

    private function mapTemporalExtends(Chronique $chronique): TemporalExtent
    {
        $temporalExtends = new TemporalExtent();
        $temporalExtends->setDateBeg(strtotime($chronique->getDateDebutMesures()));
        $temporalExtends->setDateEnd(strtotime($chronique->getDateFinMesures()));

        return $temporalExtends;
    }

    private function mapFundings(PartenaireEntity $partenaire): Fundings
    {
        $fundings = new Fundings();
        $fundings->setIso3166($partenaire->getIso());
        $fundings->setName($partenaire->getNom());
        if ($partenaire->getEstFinanceur()) {
            $fundings->setType($partenaire->getTypeFundings());
        }
        if ($partenaire->getIso() === 'FR') {
            $fundings->setIdScanR($partenaire->getScanR());
        } else {
            $fundings->setIdScanR(null);
        }

        return $fundings;
    }

    private function mapSpatialExtends(DataSetEntity $dataSet): SpatialExtent
    {
        $datasetId = $dataSet->getId();
        /** @var DataSetRepository $datasetrepo */
        $datasetrepo = $this->registry->getRepository(DataSetEntity::class);
        $rectangle = $datasetrepo->getReclangleEnglobant($datasetId);
        $tableauRectangle = [$rectangle];
        $spatial = new SpatialExtent();

        $spatial->setType('Feature');
        $spatial->setProperties(((object) null));

        $spatial->geometry->setType('Polygon');
        $spatial->geometry->setCoordinates($tableauRectangle);

        return $spatial;
    }

    //------------------Contient la conversion du Theia catégorie--------------------------------
    private function mapObservedProperty(Chronique $chronique): ObservedProperty
    {
        $observedProperty = new ObservedProperty();
        $observedProperty->setUnit($chronique->getUnite());

        //conditionne le champs en fonction du champ en anglais
        if ($chronique->getLibelleEn() === null) {
            $observedProperty->setName($chronique->getLibelle());
        } else {
            $observedProperty->setName($chronique->getLibelleEn());
        }

        $milieu = $chronique->getMilieu();
        //message si la chronique ne contient pas de milieu
        if ($milieu === null) {
            $message = 'N/A (A definir dans votre chronique)';
        } else {
            $message = $milieu;
        }
        $familleParametre = $chronique->getParametre()->getFamilleParametres();
        /** @var TheiaCategoriesRepository $theiaCategrepository */
        $theiaCategrepository = $this->registry->getRepository(TheiaCategories::class);
        $triTheiaPourChronique = $theiaCategrepository->findOneBy(['milieu' => $milieu, 'familleParametre' => $familleParametre]);

        //conditionnement s'il n'y a pas de correspondance dans le theiaCagorie
        if ($triTheiaPourChronique === null) {
            $tableauURI = ['Votre chronique ne contient pas un URI TheiaCategorie correspondant au milieu : ' . $message . ' + famille de parametre : ' . $familleParametre];
        } else {
            $tableauURI = $triTheiaPourChronique->getUriOzcarTheia();
        }

        $observedProperty->setTheiaCategories($tableauURI);

        return $observedProperty;
    }
}
