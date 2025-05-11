<?php

namespace App\Command;

use App\Service\NostrClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'articles:get',
    description: 'Pull articles from a default relay',
)]
class GetArticlesCommand extends Command
{
    public function __construct(private readonly NostrClient $nostrClient)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $this->nostrClient->getLongFormContent();

        return Command::SUCCESS;
    }
}
