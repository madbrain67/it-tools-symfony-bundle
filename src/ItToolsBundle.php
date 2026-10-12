<?php

declare(strict_types=1);

namespace Plateformweb\ItToolsBundle;

use Plateformweb\ItTools\Client;
use Plateformweb\ItTools\Config;
use Plateformweb\ItToolsBundle\Command\PingCommand;
use Plateformweb\ItToolsBundle\Transport\SymfonyHttpTransport;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class ItToolsBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('api_key')
                    ->info('Cle publique (pk_...).')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('api_secret')
                    ->info('Cle privee (sk_...), a renseigner telle qu\'affichee. Laisser null en mode navigateur.')
                    ->defaultNull()
                ->end()
                ->scalarNode('base_uri')
                    ->info('Racine de l\'API.')
                    ->defaultValue(Config::DEFAULT_BASE_URI)
                    ->cannotBeEmpty()
                ->end()
                ->floatNode('timeout')
                    ->info('Delai maximal d\'une requete, en secondes.')
                    ->defaultValue(30.0)
                    ->min(0.1)
                ->end()
                ->booleanNode('verify_peer')
                    ->info('Verification du certificat TLS. A desactiver uniquement en developpement local.')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    /**
     * @param array{api_key: string, api_secret: ?string, base_uri: string, timeout: float, verify_peer: bool} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set('plateformweb_it_tools.config', Config::class)
            ->args([
                $config['api_key'],
                $config['api_secret'],
                $config['base_uri'],
                $config['timeout'],
                $config['verify_peer'],
            ]);

        $services->set('plateformweb_it_tools.transport', SymfonyHttpTransport::class)
            ->args([service('http_client')]);

        $services->set('plateformweb_it_tools.client', Client::class)
            ->args([service('plateformweb_it_tools.config'), service('plateformweb_it_tools.transport')]);

        $services->alias(Client::class, 'plateformweb_it_tools.client');
        $services->alias(Config::class, 'plateformweb_it_tools.config');

        $services->set(PingCommand::class)
            ->args([service('plateformweb_it_tools.client')])
            ->tag('console.command');
    }
}
