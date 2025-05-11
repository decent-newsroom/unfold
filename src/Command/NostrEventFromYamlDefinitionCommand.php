<?php

declare(strict_types=1);

namespace App\Command;

use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(name: 'app:yaml_to_nostr', description: 'Traverses folders, converts YAML files to JSON using object mapping, and saves the result in Redis cache.')]
class NostrEventFromYamlDefinitionCommand extends Command
{
    const private_key = 'nsec17ygfd40ckdwmrl4mzhnzzdr3c8j5kvnavgrct35hglha9ue396dslsterv';

    public function __construct(private readonly CacheInterface $redisCache)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('folder', InputArgument::REQUIRED, 'The folder location to start scanning from.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $folder = $input->getArgument('folder');

        // Use Symfony Finder to locate YAML files recursively
        $finder = new Finder();
        $finder->files()
            ->in($folder)
            ->name('*.yaml')
            ->name('*.yml');

        if (!$finder->hasResults()) {
            $output->writeln('<comment>No YAML files found in the specified directory.</comment>');
            return Command::SUCCESS;
        }

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $output->writeln("<info>Processing file: $filePath</info>");
            $yamlContent = Yaml::parseFile($filePath);  // This parses the YAML file

            try {
                // Deserialize YAML content into an Event object
                $event = new Event();
                $event->setKind(30040);
                $event->setPublicKey('e00983324f38e8522ffc01d5c064727e43fe4c43d86a5c2a0e73290674e496f8');
                $tags = $yamlContent['tags'];
                $event->setTags($tags);

                $signer = new Sign();
                $signer->signEvent($event, NostrEventFromYamlDefinitionCommand::private_key);

                // Save to cache
                $slug = array_filter($tags, function ($tag) {
                    return ($tag[0] === 'd');
                });
                // Generate a Redis key
                $cacheKey = 'magazine-' . $slug[0][1];
                $cacheItem = $this->redisCache->getItem($cacheKey);
                $cacheItem->set($event);
                $this->redisCache->save($cacheItem);

                $output->writeln("<info>Saved index.</info>");
            } catch (\Exception $e) {
                $output->writeln("<error>Error deserializing YAML in file: $filePath. Message: {$e->getMessage()}</error>");
                continue;
            }
        }

        $output->writeln('<info>Conversion complete.</info>');
        return Command::SUCCESS;
    }
}
