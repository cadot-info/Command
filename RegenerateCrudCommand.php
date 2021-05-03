<?php

namespace App\Command;

use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RegenerateCrudCommand extends Command
{
    protected static $defaultName = 'make:regenerate-crud';
    protected static $defaultDescription = 'remove and regerate the crud of entitie';

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('arg1', InputArgument::OPTIONAL, 'name of entitie');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = new Filesystem();

        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');
        $Rcontroller = false;
        $unik = uniqid();
        if ($arg1) {

            #suppression de l'entité
            $nom = ucfirst("$arg1");
            $min = strtolower($nom);
            $helper = $this->getHelper('question');
            $Qcontroller = new ConfirmationQuestion("Sauvegarder le controller?", false);
            if (file_exists('src/Controller/' . $nom . 'Controller.php')) {
                $Rcontroller = $helper->ask($input, $output, $Qcontroller);
                if ($Rcontroller) {
                    $io->note(sprintf('Sauvegarde old controller %s ', $nom . 'Controller.php')); //  + );
                    $fs->rename('src/Controller/' . $nom . 'Controller.php', '/tmp/' . $unik . $nom . 'Controller.old');
                } else {
                    $io->note("Remove old Controller $nom Controller ");
                    $fs->remove('src/Controller/' . $nom . 'Controller.php');
                }
            }
            $io->note("Remove old Form $nom Type");
            $fs->remove('src/Form/' . $nom . 'Type.php');
            $io->note("Remove old Templates $min");
            $fs->remove('templates/' . $min);
            #pour effacer lien avec le formtype
            $io->note("Update of Composer");
            $process = new Process(['composer', 'update']);
            $process->run();
            // executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            #regenerate entitie
            $io->note("Recreate new CRUD $nom");
            $phpBinaryFinder = new PhpExecutableFinder();
            $phpBinaryPath = $phpBinaryFinder->find();
            $process = new Process([$phpBinaryPath, '/app/bin/console', 'make:crud', $nom]);
            $process->run();
            //en fonction de la question sur controller on remet l'ancien controller
            if ($Rcontroller) {
                $io->note(sprintf('Move the old controller %s ', $nom . 'Controller.php')); //  + );
                $fs->remove('src/Controller/' . $nom . 'Controller.php');
                $fs->rename('/tmp/' . $unik . $nom . 'Controller.old', 'src/Controller/' . $nom . 'Controller.php');
            }


            // executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            } else {
                $io->success("$nom is updated");
            }
        } else
            $io->error('Please get the name of entitie');

        return Command::SUCCESS;
    }
}
