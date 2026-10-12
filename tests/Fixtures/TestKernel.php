<?php

declare(strict_types=1);

namespace Plateformweb\ItToolsBundle\Tests\Fixtures;

use Plateformweb\ItToolsBundle\ItToolsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $itToolsConfig
     */
    public function __construct(private readonly array $itToolsConfig)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ItToolsBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/it-tools-bundle-test/' . md5(serialize($this->itToolsConfig)) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/it-tools-bundle-test/log';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false]);
            $container->loadFromExtension('it_tools', $this->itToolsConfig);
        });
    }
}
