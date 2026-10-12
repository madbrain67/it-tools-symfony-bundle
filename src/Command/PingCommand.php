<?php

declare(strict_types=1);

namespace Plateformweb\ItToolsBundle\Command;

use Plateformweb\ItTools\Client;
use Plateformweb\ItTools\Exception\ApiException;
use Plateformweb\ItTools\Exception\ItToolsException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'it-tools:ping', description: 'Verifie la configuration et les cles API IT-Tools par un appel reel (1 appel decompte du quota).')]
final class PingCommand extends Command
{
    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $baseUri = $this->client->config()->baseUri;

        try {
            $response = $this->client->dev()->textStatistics('ping');
        } catch (ApiException $e) {
            $io->error(sprintf('%s a repondu HTTP %d : %s', $baseUri, $e->statusCode(), $e->getMessage()));

            return Command::FAILURE;
        } catch (ItToolsException $e) {
            $io->error(sprintf('%s injoignable : %s', $baseUri, $e->getMessage()));

            return Command::FAILURE;
        }

        if ('success' !== ($response['type'] ?? null)) {
            $io->error(sprintf('%s a repondu une charge inattendue.', $baseUri));

            return Command::FAILURE;
        }

        $io->success(sprintf('API IT-Tools joignable (%s), cles acceptees.', $baseUri));

        return Command::SUCCESS;
    }
}
