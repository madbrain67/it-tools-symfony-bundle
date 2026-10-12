<?php

declare(strict_types=1);

namespace Plateformweb\ItToolsBundle\Transport;

use Plateformweb\ItTools\Exception\TransportException;
use Plateformweb\ItTools\File;
use Plateformweb\ItTools\Transport\HttpRequest;
use Plateformweb\ItTools\Transport\HttpResponse;
use Plateformweb\ItTools\Transport\TransportInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transport du SDK base sur le composant HttpClient de Symfony : profiler, timeouts et mocks du framework s'appliquent.
 */
final class SymfonyHttpTransport implements TransportInterface
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $headers = $request->headers;
        $options = [
            'timeout' => $request->timeout,
            'max_duration' => $request->timeout,
            'max_redirects' => 0,
            'verify_peer' => $request->verifyPeer,
            'verify_host' => $request->verifyPeer,
        ];

        if ($request->isMultipart()) {
            $part = new FormDataPart([...$request->fields, ...$this->dataParts($request->files)]);
            $headers = [...$headers, ...$part->getPreparedHeaders()->toArray()];
            $options['body'] = $part->bodyToIterable();
        } elseif (null !== $request->body) {
            $options['body'] = $request->body;
        }
        $options['headers'] = $headers;

        try {
            $response = $this->httpClient->request($request->method, $request->url, $options);
            $status = $response->getStatusCode();
            $responseHeaders = array_map(static fn (array $values): string => implode(', ', $values), $response->getHeaders(false));
            $body = $response->getContent(false);
        } catch (ExceptionInterface $e) {
            throw new TransportException(sprintf('Requete %s %s impossible : %s', $request->method, $request->url, $e->getMessage()), 0, $e);
        }

        return new HttpResponse($status, $responseHeaders, $body);
    }

    /**
     * @param array<string, File|list<File>> $files
     *
     * @return array<string, DataPart|list<DataPart>>
     */
    private function dataParts(array $files): array
    {
        $parts = [];
        foreach ($files as $name => $file) {
            $parts[$name] = \is_array($file)
                ? array_map($this->toDataPart(...), array_values($file))
                : $this->toDataPart($file);
        }

        return $parts;
    }

    private function toDataPart(File $file): DataPart
    {
        return new DataPart($file->contents(), $file->filename, $file->mimeType);
    }
}
