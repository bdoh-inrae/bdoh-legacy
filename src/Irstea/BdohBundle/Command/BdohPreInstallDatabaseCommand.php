<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BdohPreInstallDatabaseCommand extends Command
{
    protected $conn;

    protected function configure(): void
    {
        $this->setName('bdoh:preinstall:database');
        $this->setDescription('Installs PostGis and schemas temp, bdoh');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->conn = $this->getContainer()->get('doctrine')->getEntityManager()->getConnection();

        $output->write(sprintf('<info>Create language : plpgsql</info>...'));
        try {
            $createLanguage = 'create language plpgsql;';
            $this->conn->executeStatement($createLanguage);
            $output->writeln('Ok');
        } catch (\Exception $e) {
            $output->writeln('Error!');
            //$output->writeln($e->getMessage());
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Installation PostGis</info>'));

        $this->importFile(
            $output,
            [
                '/usr/share/postgresql/8.4/contrib/postgis-1.5/postgis.sql',
                '/usr/share/postgresql/8.4/contrib/postgis-1.5/spatial_ref_sys.sql',
            ]
        );

        $output->writeln('');
        $output->write(sprintf('<info>Create schema : bdoh</info>...'));
        try {
            $createSchemaBdoh = 'CREATE SCHEMA bdoh;';
            $this->conn->executeStatement($createSchemaBdoh);
            $output->writeln('Ok');
        } catch (\Exception $e) {
            $output->writeln('Error!');
            //$output->writeln($e->getMessage());
        }

        $output->writeln('');
        $output->write(sprintf('<info>Create schema : temp</info>...'));
        try {
            $createSchemaTemp = 'CREATE SCHEMA temp;';
            $this->conn->executeStatement($createSchemaTemp);
            $output->writeln('Ok');
        } catch (\Exception $e) {
            $output->writeln('Error!');
            //$output->writeln($e->getMessage());
        }
    }
}
