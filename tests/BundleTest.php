<?php

declare(strict_types=1);

namespace Plateformweb\ItToolsBundle\Tests;

use PHPUnit\Framework\TestCase;
use Plateformweb\ItTools\Client;
use Plateformweb\ItTools\Config;
use Plateformweb\ItTools\Exception\NotFoundException;
use Plateformweb\ItTools\Exception\TransportException;
use Plateformweb\ItTools\File;
use Plateformweb\ItToolsBundle\Command\PingCommand;
use Plateformweb\ItToolsBundle\Tests\Fixtures\TestKernel;
use Plateformweb\ItToolsBundle\Transport\SymfonyHttpTransport;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BundleTest extends TestCase
{
    public function testConfigurationWiresAnAutowirableClient(): void
    {
        $kernel = new TestKernel(['api_key' => 'pk_a', 'api_secret' => 'sk_b', 'base_uri' => 'https://a.test/ignored', 'timeout' => 5, 'verify_peer' => false]);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            $client = $container->get(Client::class);
            $config = $container->get(Config::class);

            self::assertInstanceOf(Client::class, $client);
            self::assertSame($config, $client->config());
            self::assertSame('pk_a', $config->apiKey);
            self::assertSame('sk_b', $config->apiSecret);
            self::assertSame('https://a.test', $config->baseUri);
            self::assertSame(5.0, $config->timeout);
            self::assertFalse($config->verifyPeer);
            self::assertTrue($kernel->getContainer()->get('test.service_container')->has(PingCommand::class));
        } finally {
            $kernel->shutdown();
        }
    }

    public function testApiKeyIsRequired(): void
    {
        $kernel = new TestKernel([]);

        $this->expectException(InvalidConfigurationException::class);
        $kernel->boot();
    }

    public function testTransportSendsJsonThroughSymfonyHttpClient(): void
    {
        $captured = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"type":"success","result":"ok"}', ['http_code' => 200, 'response_headers' => ['Content-Type: application/json']]);
        });
        $client = Client::create('pk_a', 'sk_b', 'https://a.test', transport: new SymfonyHttpTransport($http));

        $result = $client->dev()->slugify('Été');

        self::assertSame('ok', $result['result']);
        self::assertSame('POST', $captured['method']);
        self::assertSame('https://a.test/api/v1/tools/dev/slugify', $captured['url']);
        self::assertSame('{"text":"Été"}', $captured['options']['body']);
        self::assertContains('X-Api-Secret: sk_b', $captured['options']['headers']);
        self::assertSame(0, $captured['options']['max_redirects']);
    }

    public function testTransportSendsMultipartWithFilesAndFields(): void
    {
        $captured = '';
        $headers = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured, &$headers): MockResponse {
            $body = $options['body'];
            $captured = '';
            if (\is_string($body)) {
                $captured = $body;
            } else {
                while ('' !== ($chunk = $body(16372))) {
                    $captured .= $chunk;
                }
            }
            $headers = $options['headers'];

            return new MockResponse('{"download_url":"/api/v1/pdf/download/x.pdf/tok"}', ['response_headers' => ['Content-Type: application/json']]);
        });
        $client = Client::create('pk_a', null, 'https://a.test', transport: new SymfonyHttpTransport($http));

        $client->pdf()->compress(File::fromContents('%PDF-data', 'in.pdf', 'application/pdf'), 'high');

        self::assertStringContainsString('name="pdf"; filename="in.pdf"', $captured);
        self::assertStringContainsString('%PDF-data', $captured);
        self::assertStringContainsString('name="level"', $captured);
        self::assertMatchesRegularExpression('/^Content-Type: multipart\/form-data; boundary=/mi', implode("\n", $headers));
    }

    public function testTransportDoesNotThrowOnHttpErrorButSdkMapsIt(): void
    {
        $http = new MockHttpClient(new MockResponse('{"message":"CERFA non disponible."}', ['http_code' => 404, 'response_headers' => ['Content-Type: application/json']]));
        $client = Client::create('pk_a', null, 'https://a.test', transport: new SymfonyHttpTransport($http));

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('CERFA non disponible.');
        $client->cerfa()->fields('zzz');
    }

    public function testNetworkFailureBecomesTransportException(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['error' => 'Connexion refusee']));
        $client = Client::create('pk_a', null, 'https://a.test', transport: new SymfonyHttpTransport($http));

        $this->expectException(TransportException::class);
        $client->dev()->uuid();
    }

    public function testPingCommandReportsSuccessAndFailure(): void
    {
        $ok = new MockHttpClient(new MockResponse('{"type":"success","result":{}}', ['response_headers' => ['Content-Type: application/json']]));
        $tester = new CommandTester(new PingCommand(Client::create('pk_a', null, 'https://a.test', transport: new SymfonyHttpTransport($ok))));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('cles acceptees', $tester->getDisplay());

        $denied = new MockHttpClient(new MockResponse('{"message":"Cle API invalide ou revoquee."}', ['http_code' => 401, 'response_headers' => ['Content-Type: application/json']]));
        $tester = new CommandTester(new PingCommand(Client::create('pk_a', null, 'https://a.test', transport: new SymfonyHttpTransport($denied))));
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('HTTP 401', $tester->getDisplay());
        self::assertStringNotContainsString('pk_a', $tester->getDisplay());
    }
}
