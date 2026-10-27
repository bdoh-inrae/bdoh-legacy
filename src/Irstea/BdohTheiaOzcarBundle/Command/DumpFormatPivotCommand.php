<?php

namespace Irstea\BdohTheiaOzcarBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohTheiaOzcarBundle\Service\SerializerJSON;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Le format pivot de chaque observatoire qui a un code Theia, tel que BDOH l'envoie à Theia/OZCAR.
 * L'ancien l'écrivait par le sérialiseur de Symfony, autrement que l'envoi, sans l'observatoire que
 * demandent les adresses du format pivot, et pour tous les observatoires, dont ceux qu'il ne pouvait
 * pas écrire (dev/differences.md).
 */
class DumpFormatPivotCommand extends Command
{
    /** @var ManagerRegistry */
    private $registry;

    /** @var SerializerJSON */
    private $serializerJson;

    /** @var RouterInterface */
    private $router;

    public function __construct(ManagerRegistry $registry, SerializerJSON $serializerJson, RouterInterface $router)
    {
        parent::__construct('theia:dump:pivot');

        $this->registry = $registry;
        $this->serializerJson = $serializerJson;
        $this->router = $router;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $observatoires = array_filter(
            $this->registry->getRepository(Observatoire::class)->findAll(),
            fn (Observatoire $observatoire) => $observatoire->getTheiaCode() !== null
        );

        $output->writeln('Dumping <info>' . count($observatoires) . '</info> format(s) pivot(s):');

        foreach ($observatoires as $observatoire) {
            // comme l'envoi (EnvoiJSON)
            $this->router->getContext()->setParameter('_observatoire', $observatoire->getSlug());
            $output->writeln($this->serializerJson->serialize($observatoire), OutputInterface::OUTPUT_RAW);
        }

        return 0;
    }
}
