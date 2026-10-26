<?php
/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */

namespace Irstea\BdohTheiaOzcarBundle\Service;

use Irstea\BdohTheiaOzcarBundle\Exception\PasswordException;

final class PasswordEncryptionService implements PasswordEncryptionServiceInterface
{
    private const BASE64_VARIANT = SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING;

    /** @var string */
    private $secretKey;

    public function __construct(string $secretKey)
    {
        try {
            $this->secretKey = sodium_hex2bin($secretKey);
        } catch (\Exception $ex) {
            throw new PasswordException('Theia secret key: ' . $ex->getMessage(), 0, $ex);
        }
        if (strlen($this->secretKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new PasswordException('The secret key must have a length of 32 bytes.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt(string $clearPassword): string
    {
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($clearPassword, $nonce, $this->secretKey);

            $base64Cipher = sodium_bin2base64($cipher, self::BASE64_VARIANT);
            $base64Nonce = sodium_bin2base64($nonce, self::BASE64_VARIANT);

            return $base64Cipher . '!' . $base64Nonce;
        } catch (\Exception $ex) {
            throw new PasswordException('Theia password: ' . $ex->getMessage(), 0, $ex);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decrypt(string $encryptedPassword): string
    {
        try {
            [$base64Cipher, $base64Nonce] = explode('!', $encryptedPassword);

            $cipher = sodium_base642bin($base64Cipher, self::BASE64_VARIANT);
            $nonce = sodium_base642bin($base64Nonce, self::BASE64_VARIANT);

            $result = sodium_crypto_secretbox_open($cipher, $nonce, $this->secretKey);
        } catch (\Exception $ex) {
            throw new PasswordException('Theia password: ' . $ex->getMessage(), 0, $ex);
        }

        if ($result === false) {
            throw new PasswordException('could not decrypt Theia password');
        }

        return $result;
    }
}
