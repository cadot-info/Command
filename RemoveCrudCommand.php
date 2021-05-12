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

class RemoveCrudCommand extends Command
{
    protected static $defaultName = 'entitie:remove';
    protected static $defaultDescription = 'remove entitie with controller, repository, form and template';

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

        if ($arg1) {
            $nom = ucfirst("$arg1");
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("Supprimer l'entité " . $nom . ", son controller, ses templates, son repository et son formtype (n)?", false);
            if ($helper->ask($input, $output, $question)) {
                #suppression de l'entité
                $min = strtolower($nom);
                $io->note(sprintf('Remove controller %s ', $nom . 'Controller.php')); //  + );
                $fs->remove('src/Controller/' . $nom . 'Controller.php');
                $io->note("Remove Repository $nom Repository");
                $fs->remove('src/Repository/' . $nom . 'Repository.php');
                $io->note("Remove Form $nom Type");
                $fs->remove('src/Form/' . $nom . 'Type.php');
                $io->note("Remove Templates $min");
                $fs->remove('templates/' . $min);
                $io->note("Remove Entity");
                $fs->remove('src/Entity/' . $nom . '.php');
            }
        } else
            $io->error('Please get the name of entitie');

        return Command::SUCCESS;
    }
}
