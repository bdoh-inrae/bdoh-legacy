<?php

namespace Irstea\BdohSecurityBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\SiteExperimental;
use Irstea\BdohSecurityBundle\Entity\Role;
use Irstea\BdohSecurityBundle\Entity\RoleChronique;
use Irstea\BdohSecurityBundle\Entity\RoleObservatoire;
use Irstea\BdohSecurityBundle\Entity\RoleSite;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class UtilisateurNewCreateCommand extends Command
{
    const COMMAND_NAME = 'security:users:create';
    const ROLE_ADMIN_FCT = 'FCT';
    const ROLE_ADMIN_TECH = 'TECH';

    /** ®@var ObjectManager $manager * */
    private $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Création Utilisateur Perso : GestionnaireDesObservatoires et AdminTech')
            ->addArgument('firstname', InputArgument::REQUIRED, 'User firstname')
            ->addArgument('lastname', InputArgument::REQUIRED, 'User firstname')
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('roleAdmin', InputArgument::REQUIRED, 'User roleAdmin')
            ->addArgument('addRoleToAll', InputArgument::OPTIONAL, 'Add gestionnaire role to all for this user');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '==============================',
            'Welcome you can create a User',
            '===============================',
        ]);
        $questions = [];
        $argumentUser = ['firstname', 'lastname', 'email', 'roleAdmin'];

        foreach ($argumentUser as $argumentUtilisateur) {
            if (!$input->getArgument($argumentUtilisateur)) {
                $question = new Question('Please enter the ' . $argumentUtilisateur . ':');
                if ($argumentUtilisateur === 'roleAdmin') {
                    $question->setValidator(function ($firstname) {
                        if (empty($firstname)) {
                            throw new \Exception('Argument can not be empty');
                        }
                        $roleAdmin = self::ROLE_ADMIN_FCT;
                        $roleTech = self::ROLE_ADMIN_TECH;
                        if ($firstname !== $roleAdmin && $firstname !== $roleTech) {
                            throw new \Exception('This Role does not exist');
                        }

                        return $firstname;
                    });
                } else {
                    $question->setValidator(function ($firstname) {
                        if (empty($firstname)) {
                            throw new \Exception('Argument can not be empty');
                        }

                        return $firstname;
                    });
                }

                $questions[$argumentUtilisateur] = $question;
            }
        }
        foreach ($questions as $name => $question) {
            $answer = $this->getHelper('question')->ask($input, $output, $question);
            $input->setArgument($name, $answer);
        }

        $questionAddRoleToAll = new ConfirmationQuestion(
            'Add role to all for this user (y/N)',
            false,
            '/^(y|j)/i'
        );
        $input->setArgument(
            'addRoleToAll',
            $this->getHelper('question')->ask($input, $output, $questionAddRoleToAll)
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln([
            '====================',
            'Command Create User',
            '====================',
        ]);
        $user = new Utilisateur();
        $user->setPrenom($input->getArgument('firstname'));
        $output->writeln('With firstname : ' . $user->getPrenom());
        $user->setNom($input->getArgument('lastname'));
        $output->writeln('With lastname : ' . $user->getNom());
        $user->setEmail($input->getArgument('email'));
        $output->writeln('With email : ' . $user->getEmail());
        // Pas de mot de passe : on se connecte par un lien envoyé par courriel (dev/differences.md)
        $user->setPassword('');
        $user->setSalt('');
        $user->setRoleAdmin($input->getArgument('roleAdmin'));
        // Tous les droits sur une réponse positive seulement : l'ancien les donnait aussi quand
        // l'argument manquait ou disait non, en mode non interactif (dev/differences.md).
        $addRoleToAll = $input->getArgument('addRoleToAll');
        if ($addRoleToAll === true || (is_string($addRoleToAll) && preg_match('/^(y|j|o|1|true)/i', $addRoleToAll))) {
            $this->addRoleToAll($user);
        }
        $this->manager->persist($user);
        $this->manager->flush();
        $output->writeln([
            '===============================================================================================',
            'Successful you have created the user : ' . $user->getPrenom() . ' ' . $user->getNom() . ' !!!',
            '===============================================================================================',
        ]);

        return 0;
    }

    private function addRoleToAll(Utilisateur $user): void
    {
        $sites = $this->manager->getRepository(SiteExperimental::class)->findAll();
        foreach ($sites as $site) {
            $roleSite = new RoleSite();
            $roleSite->setSite($site);
            $roleSite->setUtilisateur($user);
            $roleSite->setValeur(Role::GESTIONNAIRE);
            $user->addRoleSite($roleSite);
            $this->manager->persist($roleSite);
        }

        $chroniques = $this->manager->getRepository(Chronique::class)->findAll();
        foreach ($chroniques as $chronique) {
            $roleChronique = new RoleChronique();
            $roleChronique->setChronique($chronique);
            $roleChronique->setUtilisateur($user);
            $roleChronique->setValeur(Role::GESTIONNAIRE);
            $user->addRoleChronique($roleChronique);
            $this->manager->persist($roleChronique);
        }

        $observatoires = $this->manager->getRepository(Observatoire::class)->findAll();
        foreach ($observatoires as $observatoire){
            $roleObs = new RoleObservatoire();
            $roleObs->setObservatoire($observatoire);
            $roleObs->setValeur(Role::GESTIONNAIRE);
            $roleObs->setUtilisateur($user);
            $user->addRoleObservatoire($roleObs);
            $this->manager->persist($roleObs);
        }
    }
}
