<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Util;

use Symfony\Component\HttpFoundation\Response;

/**
 * This class offers some helpers to send file via Http protocol.
 */
class HttpFileResponse extends Response
{
    protected $filePath;

    protected $removeFile;

    /**
     * Constructor.
     *
     * @param string $filePath    Path of the file to send
     * @param string $contentType The file content type
     * @param bool   $removeFile  If true, the file will be removed after sending
     */
    public function __construct($filePath, $contentType, $removeFile = false)
    {
        parent::__construct(
            '',
            200,
            [
                'Content-Type'        => $contentType,
                'Content-Disposition' => 'attachement; filename="' . basename($filePath) . '"',
            ]
        );

        $this->setFilePath($filePath);

        $this->removeFile = $removeFile;
    }

    /**
     * Shortcut of send() method.
     */
    public function __toString(): string
    {
        $this->send();

        return '';
    }

    /**
     * Sends the file content for the current web response.
     */
    public function sendFile()
    {
        readfile($this->filePath);

        if ($this->removeFile) {
            unlink($this->filePath);
        }
    }

    /**
     * Sends HTTP headers and file content.
     */
    public function send(bool $flush = true): static
    {
        $this->sendHeaders();
        $this->sendFile();

        return $this;
    }

    /**
     * Sets the file path.
     *
     * @param string $filePath
     */
    public function setFilePath($filePath)
    {
        if (false === is_readable($filePath)) {
            throw new \RuntimeException('"' . $filePath . '" is not a readable file');
        }
        $this->filePath = $filePath;
    }

    /**
     * Change the target name.
     *
     * @param string $name
     */
    public function changeName($name)
    {
        $this->headers->set('Content-Disposition', 'attachment;filename="' . $name);
    }
}
