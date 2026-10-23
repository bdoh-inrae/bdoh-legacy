<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Exception;

use Irstea\BdohBundle\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Thrown when trying to view a chronique with unficcient rights.
 */
class ForbiddenException extends AccessDeniedHttpException implements Exception
{
    public function __construct()
    {
        parent::__construct('You have no sufficient rights to see this Chronique');
    }
}
