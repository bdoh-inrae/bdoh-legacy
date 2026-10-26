<?php

namespace Irstea\BdohTheiaOzcarBundle\Service;

use Irstea\BdohTheiaOzcarBundle\Exception\PasswordException;

interface PasswordEncryptionServiceInterface
{
    /**
     * @throws PasswordException
     */
    public function encrypt(string $clearPassword): string;

    /**
     * @throws PasswordException
     */
    public function decrypt(string $encryptedPassword): string;
}
