<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Command;

use Irstea\BdohBundle\Command\Helper\DialogHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Base command for Bdoh project.
 */
abstract class Command extends ContainerAwareCommand
{
    /**
     * @var Symfony\Component\Translation\TranslatorInterface
     */
    protected $translator;

    /**
     * @return Symfony\Component\Translation\TranslatorInterface
     */
    protected function getTranslator()
    {
        if (null === $this->translator) {
            $this->translator = $this->getContainer()->get('translator');
        }

        return $this->translator;
    }

    /**
     * @return Irstea\BdohBundle\Command\Helper\DialogHelper
     */
    protected function getDialogHelper()
    {
        $dialog = $this->getHelperSet()->get('dialog');
        if (!$dialog || get_class($dialog) !== 'Irstea\BdohBundle\Command\Helper\DialogHelper') {
            $this->getHelperSet()->set($dialog = new DialogHelper($this->getTranslator()));
        }

        return $dialog;
    }

    /**
     * Shortcut to translate a message.
     *
     * @param mixed $message
     * @param mixed $catalog
     */
    protected function trans($message, array $params = [], $catalog = 'commands')
    {
        return $this->getTranslator()->trans($message, $params, $catalog);
    }

    /**
     * For execute Sql file in command.
     *
     * @param mixed $output
     */
    protected function importFile($output, array $fileNames)
    {
        if ($fileNames !== null) {
            foreach ((array) $fileNames as $fileName) {
                $fileName = realpath($fileName);

                if (!file_exists($fileName)) {
                    throw new \InvalidArgumentException(
                        sprintf("SQL file '<info>%s</info>' does not exist.", $fileName)
                    );
                } elseif (!is_readable($fileName)) {
                    throw new \InvalidArgumentException(
                        sprintf("SQL file '<info>%s</info>' does not have read permissions.", $fileName)
                    );
                }

                $output->write(sprintf("Processing file '<info>%s</info>'... ", $fileName));
                $sql = file_get_contents($fileName);

                try {
                    $this->conn->executeStatement($sql);
                    $output->writeln('OK!');
                } catch (\Exception $e) {
                    $output->writeln('Error!');
                    //$output->writeln($e->getMessage());
                }
            }
        }
    }
}
