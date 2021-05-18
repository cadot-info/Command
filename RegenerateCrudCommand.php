<?php

namespace App\Command;

use App\Service\FileFunctions;
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
        $ff = new FileFunctions();
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');
        $Rcontroller = false;
        $unik = uniqid();
        if ($arg1) {
            #suppression de l'entité
            $nom = ucfirst("$arg1");
            $min = strtolower($nom);
            $helper = $this->getHelper('question');
            $dateDir = "old/" .  date('Y-m-d_H-i-s') . '/';
            $fichiers = [
                'src/Controller/' . $nom . 'Controller.php',
                'src/Form/' . $nom . 'Type.php',
                'templates/' . $min . '/new.html.twig',
                'templates/' . $min . '/show.html.twig',
                'templates/' . $min . '/index.html.twig',
                'templates/' . $min . '/_delete_form.html.twig',
                'templates/' . $min . '/_form.html.twig',
                'templates/' . $min . '/edit.html.twig'
            ];
            $resfichiers = [];
            foreach ($fichiers as $fichier) {
                if (file_exists($fichier)) {
                    $controller = file_get_contents($fichier);
                    if (strpos($controller, '*****no_regenerate*****') !== false) {
                        $io->note($fichier . ' marqué comme à ne pas regénérer');
                        //creation des réperoires pour déplacer le fichier
                        $ff->move($fichier, '/tmp/' . $unik . '/' . $fichier);
                        $resfichiers[] = $fichier;
                    } else {
                        $ff->move($fichier, $dateDir . $fichier);
                    }
                }
            }
            $io->note("Save all in " . $dateDir . "/$nom ");
            #pour effacer lien avec le formtype
            $io->note("Update of Composer");
            //$process = new Process(['composer', 'update']);
            //$process->run();
            // executes after the command finishes
            //if (!$process->isSuccessful()) {
            //    throw new ProcessFailedException($process);
            //}
            #regenerate entitie
            $io->note("Recreate new CRUD $nom");
            $phpBinaryFinder = new PhpExecutableFinder();
            $phpBinaryPath = $phpBinaryFinder->find();
            $process = new Process([$phpBinaryPath, '/app/bin/console', 'make:crud', $nom]);
            $process->run();
            //si le fichier a été marqué comme non regenerate on le remet en place
            foreach ($resfichiers as $fichier) {
                $ff->move('/tmp/' . $unik . '/' . $fichier, $fichier);
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
