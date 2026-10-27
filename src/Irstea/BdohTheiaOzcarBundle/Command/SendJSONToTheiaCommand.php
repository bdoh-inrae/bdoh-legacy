<?php

namespace Irstea\BdohTheiaOzcarBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Irstea\BdohDataBundle\Command\AbstractJobCommand;
use Irstea\BdohTheiaOzcarBundle\Service\EnvoiJSON;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SendJSONToTheiaCommand extends AbstractJobCommand
{
    const COMMAND_NAME = 'theia:send:json:data';

    /** @var EnvoiJSON */
    private $envoieJSON;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName(self::COMMAND_NAME)
        ->setDescription('Commande pour envoyer les données à Theia/OZCAR par Observatoire')
        ->addArgument('codeTheia', InputArgument::REQUIRED, 'Theia/OZCAR Identifier (4 letters)');

        // NOOP
    }

    /**
     * SendJSONToTheiaCommand constructor.
     */
    public function __construct(
        ManagerRegistry $doctrine,
        TokenStorageInterface $tokenStorage,
        EnvoiJSON $envoieJSON
    ) {
        parent::__construct($doctrine, $tokenStorage);
        $this->envoieJSON = $envoieJSON;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '==================================================================================',
            'Welcome you can send your datas to Theia/OZCAR with your Theia/OZCAR Identifier ',
            '===================================================================================',
        ]);
        $questions = [];
        $argumentUser = ['codeTheia'];

        foreach ($argumentUser as $argumentUtilisateur) {
            if (!$input->getArgument($argumentUtilisateur)) {
                $question = new Question('Please enter the ' . $argumentUtilisateur . ':');
                $question->setValidator(function ($firstname) {
                    if (empty($firstname)) {
                        throw new \Exception('Argument can not be empty');
                    }

                    return $firstname;
                });

                $questions[$argumentUtilisateur] = $question;
            }
        }
        foreach ($questions as $name => $question) {
            $answer = $this->getHelper('question')->ask($input, $output, $question);
            $input->setArgument($name, $answer);
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \Exception
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $theiaCode = $input->getArgument('codeTheia');

        $envoi = $this->envoieJSON->sendJsonTheia($theiaCode);
        [$unix, $key, $message, $responseBody, $result, $seri] = $envoi;

        // message d'erreur à faire apparaitre dans les résumés des jobs
        if ($unix === 0) {
            $output->writeln('Envoi réussi');
        } elseif ($key !== null) {
            //si le validateur json ne fonctionne pas
            $output->writeln([
                '==================================== ERROR JSON SCHEMA==================================================== ',
            ]);
            foreach ($result->getErrors() as $erreurs) {
                $key = $erreurs->keyword();
                $dataPoitner = $erreurs->dataPointer();
                $dataPointerString = implode('.', $dataPoitner);
                $dataErr = $erreurs->data();
                $dataString = json_encode($dataErr);
                $output->writeln([
                        'Keywords : ' . $key,
                        'DataPointer : ' . $dataPointerString,
                        'Data : ' . $dataString,
                    ]);
            }
            $output->writeln([
                '==================================== JSON ==================================================== ',
                $seri,
            ]);
        } elseif ($message !== null) {
            //si l'envoie ne fonctionne pas
            $output->writeln([
                '==================================== GuzzleException ==================================================== ',
                'Message : ' . $message,
                'Body response : ' . $responseBody,
                '==================================== JSON ==================================================== ',
                $seri,
            ]);
        }

        return $unix;
    }
}
