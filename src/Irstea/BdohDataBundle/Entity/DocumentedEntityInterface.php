<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Interface DocumentedEntityInterface.
 *
 * Une entité avec des documents à uploader/downloader.
 */
interface DocumentedEntityInterface
{
    /**
     * Retourne un préfixe de nom de fichier.
     *
     * @return string
     */
    public function getDocumentFilePrefix();

    /**
     * Retourne la liste des champs contenant des documents.
     *
     * @return string[]
     */
    public function getDocumentFields();
}
