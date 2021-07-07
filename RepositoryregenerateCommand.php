<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RepositoryregenerateCommand extends Command
{
    protected static $defaultName = 'crudmick:regenRepository';
    protected static $defaultDescription = 'recreate a repository for a entity';

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('entity', InputArgument::OPTIONAL, 'Name of entity');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entity = ucfirst($input->getArgument('entity'));

        if (!$entity) {
            $io->error('please give me a entity');
            exit();
        }
        $repo = '<?php

namespace App\Repository;

use App\Entity\\' . $entity . ';
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ' . $entity . '|null find($id, $lockMode = null, $lockVersion = null)
 * @method ' . $entity . '|null findOneBy(array $criteria, array $orderBy = null)
 * @method ' . $entity . '[]    findAll()
 * @method ' . $entity . '[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ' . $entity . 'Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ' . $entity . '::class);
    }
}
';
        file_put_contents('/app/src/Repository/' . $entity . 'Repository.php', $repo);


        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
