<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Irstea\BdohDataBundle\Entity\ChroniqueConvertie;

/**
 * Class ChroniqueConvertieRepository.
 */
class ChroniqueConvertieRepository extends ChroniqueContinueRepository
{
    /**
     * @param ChroniqueConvertie $chronique
     *
     * @throws \Exception
     *
     * @return int
     */
    public function convertChronique(ChroniqueConvertie $chronique)
    {
        if (!$chronique
            || !($conversion = $chronique->getConversion())
            || !($choniqueMere = $conversion->getEntree())
            || !($defaultGapQualite = $chronique->getDefaultGapQualite())) {
            return 1;
        }

        $chroniqueId = $chronique->getId();
        $gapId = $defaultGapQualite->getId();
        $intervalle = $conversion->getJeuConversionActuel()->getParamConversion();

        $connection = $this->getEntityManager()->getConnection();
        $connection->beginTransaction();
        try {
            // récupération des plages de la chronique mère ; les valeurs, en texte, repartent telles
            // quelles dans les mesures insérées (en nombres, PHP les écrirait à 14 chiffres)
            $sqlSelectPlages = <<<'SQL'
SELECT debut, debut - interval '1 second' as debut_moins_1, fin, fin - interval '1 second' as fin_moins_1,
       fin + interval '1 second' as fin_plus_1, fin + (interval '1 seconds' * :intervalle) as fin_plus_intervalle,
       valeur::text, minimum::text, maximum::text, qualite_id
FROM plage WHERE chronique_id = :id ORDER BY debut ASC
SQL;
            $plages = $connection->executeQuery(
                $sqlSelectPlages,
                [
                    'intervalle' => $intervalle,
                    'id'         => $choniqueMere->getId(),
                ]
            );

            // conversion des plages en mesures
            $mesures = [];
            $lastPlage = null;
            foreach ($plages->iterateNumeric() as $plage) {
                // la plage en cours
                list($debut, $debut_moins_1, $fin, $fin_moins_1, $fin_plus_1,
                    $fin_plus_intervalle, $valeur, $minimum, $maximum, $qualite_id) = $plage;
                $valeur = $valeur === null ? 'NULL' : $valeur;
                $minimum = $minimum === null ? 'NULL' : $minimum;
                $maximum = $maximum === null ? 'NULL' : $maximum;
                if ($lastPlage) {
                    // la plage précédente
                    list($last_debut, $last_debut_moins_1, $last_fin, $last_fin_moins_1, $last_fin_plus_1,
                        $last_fin_plus_intervalle, $last_valeur, $last_minimum, $last_maximum, $last_qualite_id) = $lastPlage;
                    $last_valeur = $last_valeur === null ? 'NULL' : $last_valeur;
                    $last_minimum = $last_minimum === null ? 'NULL' : $last_minimum;
                    $last_maximum = $last_maximum === null ? 'NULL' : $last_maximum;

                    // date de fin de la plage précédente à utiliser :
                    $last_fin_ajout = $last_fin;
                    // si la date de fin de la plage précédente est égale à la date de début de la plage courante
                    // on prend la date de fin de la plage précédente moins 1 seconde
                    if ($last_fin === $debut) {
                        $last_fin_ajout = $last_fin_moins_1;
                    }
                    $last_debut_date = new \DateTime($last_debut, new \DateTimeZone('UTC'));
                    $last_fin_ajout_date = new \DateTime($last_fin_ajout, new \DateTimeZone('UTC'));
                    if ($last_debut_date < $last_fin_ajout_date) {
                        // ajout de la valeur de fin de la plage précédente
                        // uniquement si la fin reste strictement posterieur à la date de début de la plage précédente
                        $mesures[] = "(nextval('mesure_id_seq'), $last_qualite_id, $chroniqueId, '$last_fin_ajout', $last_valeur, $last_minimum, $last_maximum, TRUE)";
                    }

                    // si l'intervalle entre la date de fin de la plage précédente et la date de début de la plage courante
                    // est supérieur à l'intervalle des lacunes, on rajoute 2 lacunes à +/- 1 seconde
                    $last_fin_plus_intervalle_date = new \DateTime($last_fin_plus_intervalle, new \DateTimeZone('UTC'));
                    $debut_date = new \DateTime($debut, new \DateTimeZone('UTC'));
                    if ($last_fin_plus_intervalle_date < $debut_date) {
                        $mesures[] = "(nextval('mesure_id_seq'), $gapId, $chroniqueId, '$last_fin_plus_1', NULL, NULL, NULL, TRUE)";
                        $mesures[] = "(nextval('mesure_id_seq'), $gapId, $chroniqueId, '$debut_moins_1', NULL, NULL, NULL, TRUE)";
                    }
                }
                // ajout de la valeur de début de la plage en cours
                $mesures[] = "(nextval('mesure_id_seq'), $qualite_id, $chroniqueId, '$debut', $valeur, $minimum, $maximum, TRUE)";
                $lastPlage = $plage;
            }
            // ajout de la dernière mesure (date de fin de la dernière plage)
            // uniquement si il ne s'agit pas d'une plage ponctuelle
            if ($lastPlage) {
                // la plage précédente
                list($last_debut, $last_debut_moins_1, $last_fin, $last_fin_moins_1, $last_fin_plus_1,
                    $last_fin_plus_intervalle, $last_valeur, $last_minimum, $last_maximum, $last_qualite_id) = $lastPlage;
                $last_valeur = $last_valeur === null ? 'NULL' : $last_valeur;
                $last_minimum = $last_minimum === null ? 'NULL' : $last_minimum;
                $last_maximum = $last_maximum === null ? 'NULL' : $last_maximum;
                if ($last_debut !== $last_fin) {
                    $mesures[] = "(nextval('mesure_id_seq'), $last_qualite_id, $chroniqueId, '$last_fin', $last_valeur, $last_minimum, $last_maximum, TRUE)";
                }
            }

            // suppression des anciennes mesures de la chronique convertie
            $sqlDeleteMesures = 'DELETE FROM mesure WHERE chronique_id = :id AND estcalculee';
            $connection->executeStatement($sqlDeleteMesures, ['id' => $chroniqueId]);

            // insertion des nouvelles mesures
            if (count($mesures) !== 0) {
                $sqlInsertMesures = "INSERT INTO mesure (id, qualite_id, chronique_id, date, valeur, minimum, maximum, estcalculee) VALUES \n" .
                    implode(",\n", $mesures);
                $connection->executeStatement($sqlInsertMesures);
            }

            // mise à jour des metadata de la chronique convertie
            $sqlUpdateMetadata = 'SELECT bdoh_update_measures_metadata(:id)';
            $connection->executeStatement($sqlUpdateMetadata, ['id' => $chroniqueId]);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        return 0;
    }
}
