<?php

namespace Irstea\BdohBundle\Job;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * L'exécuteur de la file de tâches, à la place de « jms-job-queue:run » de l'ancien BDOH : il prend
 * les tâches en attente par priorité, jamais deux d'une même file à la fois (une file par chronique,
 * une pour les exports), au plus --max-concurrent-jobs en tout, et lance chacune dans son propre
 * processus, une commande de la console. Il s'arrête au bout de --max-runtime secondes, une fois ses
 * tâches finies ; systemd le relance (dev/plans/2026-10-23_jobs-exports.md).
 *
 * Un seul exécuteur tourne à la fois, ce que garantit un verrou de PostgreSQL : il n'y a pas de
 * course entre deux exécuteurs pour une même tâche, et les tâches restées « en cours » au démarrage
 * sont celles d'un exécuteur arrêté, marquées incomplètes.
 */
#[AsCommand(name: 'bdoh:jobs:run', description: 'Exécute la file de tâches')]
class RunJobsCommand extends Command
{
    /** Clé du verrou de PostgreSQL réservé à l'exécuteur */
    private const LOCK_KEY = 4210521;

    /** @var array<int, array{id: int, queue: string, process: Process, start: int}> */
    private $running = [];

    private $stopping = false;

    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/bin/console')] private string $console,
        #[Autowire('%kernel.environment%')] private string $environment
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-runtime', 'r', InputOption::VALUE_REQUIRED, "Durée de marche, en secondes, avant de s'arrêter", 900)
            ->addOption('max-concurrent-jobs', 'j', InputOption::VALUE_REQUIRED, 'Tâches menées en même temps', 2)
            ->addOption('idle-time', null, InputOption::VALUE_REQUIRED, 'Attente quand rien ne reste à lancer, en secondes', 2)
            ->addOption('worker-name', null, InputOption::VALUE_REQUIRED, "Nom de l'exécuteur, noté dans les tâches");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maxRuntime = (int) $input->getOption('max-runtime');
        $maxJobs = (int) $input->getOption('max-concurrent-jobs');
        $idleTime = (int) $input->getOption('idle-time');
        $workerName = substr($input->getOption('worker-name') ?? gethostname() . '-' . getmypid(), 0, 50);

        $connection = $this->em->getConnection();
        if (!$connection->fetchOne('SELECT pg_try_advisory_lock(' . self::LOCK_KEY . ')')) {
            $output->writeln('Un autre exécuteur tourne déjà.');

            return Command::FAILURE;
        }

        if (\function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT] as $signal) {
                pcntl_signal($signal, function () use ($output) {
                    $output->writeln('Arrêt demandé : les tâches en cours finissent.');
                    $this->stopping = true;
                });
            }
        }

        $this->markStaleJobsIncomplete($output);

        $start = time();
        while (!$this->stopping && time() - $start < $maxRuntime) {
            $this->checkRunningJobs($output);
            $started = $this->startJobs($workerName, $maxJobs, $output);
            if (!$started) {
                sleep($idleTime);
            }
        }

        while ($this->running) {
            $this->checkRunningJobs($output);
            usleep(200000);
        }

        $connection->executeQuery('SELECT pg_advisory_unlock(' . self::LOCK_KEY . ')');

        return Command::SUCCESS;
    }

    private function markStaleJobsIncomplete(OutputInterface $output): void
    {
        foreach ($this->em->getRepository(Job::class)->findBy(['state' => Job::STATE_RUNNING]) as $job) {
            $output->writeln(sprintf('%s laissée en cours par un exécuteur arrêté : incomplète.', $job));
            $job->setState(Job::STATE_INCOMPLETE);
        }
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * Lance des tâches tant qu'il y a de la place ; rend le nombre de tâches lancées.
     */
    private function startJobs(string $workerName, int $maxJobs, OutputInterface $output): int
    {
        $started = 0;
        while (!$this->stopping && \count($this->running) < $maxJobs) {
            /** @var JobRepository $repo */
            $repo = $this->em->getRepository(Job::class);
            $busyQueues = array_values(array_unique(array_map(fn ($r) => $r['queue'], $this->running)));
            $job = $repo->findStartableJob($busyQueues);
            if ($job === null) {
                break;
            }

            // Prise seulement si elle attend toujours : une annulation depuis les pages de suivi peut
            // l'avoir devancée (JobManager::cancel, qui ne vise de même qu'une tâche pas commencée).
            $now = new \DateTime();
            $claimed = $this->em->getConnection()->executeStatement(
                "UPDATE jms_jobs SET state = :running, workerName = :worker, startedAt = :now, checkedAt = :now
                  WHERE id = :id AND state = :pending",
                ['running' => Job::STATE_RUNNING, 'worker' => $workerName, 'now' => $now, 'id' => $job->getId(),
                 'pending' => Job::STATE_PENDING],
                ['now' => 'datetime']
            );
            $this->em->clear();
            if (!$claimed) {
                continue;
            }

            // Comme JMS : php console --env=… [--verbose] commande --jms-job-id=… arguments ; sans
            // la verbosité de l'exécuteur, que Symfony passe aux enfants (SHELL_VERBOSITY) et qui,
            // avec --quiet, viderait leur sortie.
            $command = [PHP_BINARY, $this->console, '--env=' . $this->environment];
            if ($output->isVerbose()) {
                $command[] = '--verbose';
            }
            $command = [...$command, $job->getCommand(), '--jms-job-id=' . $job->getId(), ...array_map('strval', $job->getArgs())];
            $process = new Process($command, null, ['SHELL_VERBOSITY' => false]);
            $process->setTimeout(null);
            $process->start();
            $output->writeln(sprintf('%s lancée.', $job));

            $this->running[$job->getId()] = ['id' => $job->getId(), 'queue' => $job->getQueue(), 'process' => $process, 'start' => time()];
            ++$started;
        }

        return $started;
    }

    private function checkRunningJobs(OutputInterface $output): void
    {
        foreach ($this->running as $id => $data) {
            $job = $this->em->find(Job::class, $id);
            $process = $data['process'];

            if ($job === null || !$job->isRunning()) {
                // Effacée, ou changée par un autre que l'exécuteur : il n'y a plus rien à y écrire.
                if (!$process->isRunning()) {
                    $output->writeln(sprintf('Tâche %d finie, mais plus en cours dans la base : laissée telle quelle.', $id));
                    unset($this->running[$id]);
                }
                continue;
            } elseif ($job->getMaxRuntime() > 0 && time() - $data['start'] > $job->getMaxRuntime()) {
                $process->stop(5);
                $job->setOutput($process->getOutput());
                $job->setErrorOutput($process->getErrorOutput());
                $job->setState(Job::STATE_TERMINATED);
                $output->writeln(sprintf('%s arrêtée : durée maximale dépassée.', $job));
            } elseif ($process->isRunning()) {
                $job->setOutput($process->getOutput());
                $job->setErrorOutput($process->getErrorOutput());
                $job->checked();
                $this->em->flush();
                continue;
            } else {
                $job->setExitCode($process->getExitCode());
                $job->setOutput($process->getOutput());
                $job->setErrorOutput($process->getErrorOutput());
                $job->setRuntime(time() - $data['start']);
                $job->setState($process->getExitCode() === 0 ? Job::STATE_FINISHED : Job::STATE_FAILED);
                $output->writeln(sprintf('%s finie, code de sortie %d.', $job, $process->getExitCode()));
            }

            $this->em->flush();
            unset($this->running[$id]);
        }
        $this->em->clear();
    }
}
