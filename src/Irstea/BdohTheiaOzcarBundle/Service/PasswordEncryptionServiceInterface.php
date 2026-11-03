<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

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
