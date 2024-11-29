<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

/**
 * @author Alex Milenin
 * @email  admin@azrr.info
 * @copyright Copyright (c)Alex Milenin (https://azrr.info/)
 */

namespace Azurre\Component\Http;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use function error_get_last;
use function is_array;
use function is_string;

/**
 * Simple http client
 */
class Client
{
    /**#@+
     * HTTP methods
     */
    const METHOD_GET     = 'GET';
    const METHOD_POST    = 'POST';
    const METHOD_PUT     = 'PUT';
    const METHOD_HEAD    = 'HEAD';
    const METHOD_DELETE  = 'DELETE';
    const METHOD_CONNECT = 'CONNECT';
    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_TRACE   = 'TRACE';
    const METHOD_PATCH   = 'PATCH';
    /**#@-*/

    protected ?string $url = null;
    protected string $method = self::METHOD_GET;
    protected array|string|null $data = null;
    protected array $defaultHeaders = [];
    protected array $responseCookies = [];
    protected array $headers = [];
    protected bool $isJson = false;
    protected bool $ignoreErrors = true;
    protected bool $verifySSL = false;
    protected bool $followLocation = true;
    protected int $timeout = 60;
    protected ?string $proxy = null;
    protected ?int $statusCode = null;
    protected array $responseHeaders = [];
    protected ?string $responseProcessed = null;
    protected ?array $rawResponseHeaders = null;
    protected string|null|false $response = null;
    protected int $bufferSize = 8192;
    //protected int $bufferSize = 8192 * 100;

    public function isFollowLocation(): bool
    {
        return $this->followLocation;
    }

    public function setFollowLocation(bool $followLocation): static
    {
        $this->followLocation = $followLocation;
        return $this;
    }

    public function get(string $url, array $data = []): static
    {
        $url .= $data ? ((parse_url($url, PHP_URL_QUERY) ? '&' : '?') . http_build_query($data)) : '';
        $this->url = $url;
        $this->method = static::METHOD_GET;
        $this->data = null;
        return $this;
    }

    public function post(string $url, array $data = []): static
    {
        $this->url = $url;
        $this->method = static::METHOD_POST;
        $this->data = $data;
        if (!$this->isJson) {
            $this->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        }
        return $this;
    }

    public function put(string $url, array $data = []): static
    {
        $this->url = $url;
        $this->method = static::METHOD_PUT;
        $this->data = $data;
        if (!$this->isJson) {
            $this->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        }
        return $this;
    }

    public function delete(string $url): static
    {
        $this->url = $url;
        $this->method = static::METHOD_DELETE;
        return $this;
    }

    public function setIsJson(bool $isJson = true): static
    {
        $this->isJson = $isJson;
        if ($isJson) {
            $this->addHeader('Content-Type', 'application/json');
        }
        return $this;
    }

    /**
     * @throws Exception
     */
    public function request(
        string $url,
        string $method = self::METHOD_GET,
        array|string $data = null,
        array $headers = []
    ): static {
        //$http_response_header = null;
        $this->reset();
        $streamContext = $this->makeStreamContext($url, $method, $data, $headers);
        $this->response = @file_get_contents($url, false, $streamContext);
        $error = error_get_last();
        if ($error && !empty($error['message'])) {
            $error = $error['message'];
        }
        if ($error) {
            throw new Exception($error);
        }
        $this->parseHeaders($http_response_header);
        return $this;
    }

    protected function makeStreamContext(
        string $url,
        string $method,
        array|string $data = null,
        array $headers = []
    ) {
        $contextData = ['http' => ['follow_location' => $this->followLocation]];
        $method = strtoupper($method);
        if ($data) {
            if (is_array($data)) {
                $content = $this->isJson ? json_encode($data) : http_build_query($data);
            } else {
                $content = $data;
            }
            $headers['Content-Length'] = strlen($content);
            $contextData['http']['content'] = $content;
        }
        if (!isset($headers['Host'])) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host) {
                $headers['Host'] = $host;
            }
        }
        $contextData['http'] += [
            'method' => $method,
            'header' => $this->makeHeaders(array_merge($this->defaultHeaders, $headers)),
            'timeout' => $this->timeout,
            'ignore_errors' => $this->ignoreErrors
        ];
        if ($this->proxy) {
            $contextData['http']['proxy'] = $this->proxy;
            $contextData['http']['request_fulluri'] = true;
        }
        if (!$this->verifySSL) {
            $contextData['ssl'] = [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ];
        }

        return stream_context_create($contextData);
    }

    protected function parseHeaders(array|null $responseHeaders): static
    {
        if ($responseHeaders !== null) {
            // @todo Handle several headers (redirects)
            $this->rawResponseHeaders = $responseHeaders;
            $this->responseCookies = [];
            foreach ($responseHeaders as $header) {
                $headerChunks = preg_split('/:\s/', $header, 2);
                $chunksCount = count($headerChunks);
                if ($chunksCount === 2) {
                    [$key, $value] = $headerChunks;
                    $key = trim($key);
                    $value = trim($value);
                    if (isset($this->responseHeaders[$key])) {
                        if (!is_array($this->responseHeaders[$key])) {
                            $tmp = $this->responseHeaders[$key];
                            $this->responseHeaders[$key] = [$tmp];
                        }
                        $this->responseHeaders[$key][] = $value;
                    } else {
                        $this->responseHeaders[$key] = $value;
                    }
                    if (strtolower($key) === 'set-cookie') {
                        [$cookieName, $cookieData] = explode('=', $value, 2);
                        $cookieName = trim($cookieName);
                        $cookieDataArray = explode(';', $cookieData);
                        $cookieValue = urldecode(trim(reset($cookieDataArray)));
                        if (isset($this->responseCookies[$cookieName])) {
                            if (!is_array($this->responseCookies[$cookieName])) {
                                $tmp = $this->responseCookies[$cookieName];
                                $this->responseCookies[$cookieName] = [$tmp];
                            }
                            $this->responseCookies[$cookieName][] = $cookieValue;
                        } else {
                            $this->responseCookies[$cookieName] = $cookieValue;
                        }
                    }
                } elseif ($chunksCount === 1) {
                    $this->statusCode = (int)preg_replace(
                        '/.*?\s(\d+)\s.*/',
                        "\\1",
                        reset($headerChunks)
                    );
                }
            }
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function download(string $url, string $destinationPath, ?callable $progressCallback = null)
    {
        $streamContext = $this->makeStreamContext($url, $this->method, $this->data, $this->headers);
        $readStream = @fopen($url, 'rb', false, $streamContext);
        if (!$readStream) {
            $error = error_get_last();
            throw new Exception('Error opening URL for download: ' . ($error['message'] ?? 'Unknown error'));
        }

        $metaData = stream_get_meta_data($readStream);
        $this->parseHeaders($metaData['wrapper_data']);
        $fileSize = null;
        foreach ($metaData['wrapper_data'] as $header) {
            if (stripos($header, 'Content-Length:') === 0) {
                $fileSize = (int)trim(substr($header, 15));
            }
        }

        $writeStream = @fopen($destinationPath, 'wb');
        if (!$writeStream) {
            fclose($readStream);
            throw new Exception("Error opening destination file: $destinationPath");
        }

        $bytesDownloaded = 0;
        try {
            while (!feof($readStream)) {
                $chunk = fread($readStream, $this->bufferSize);
                if ($chunk === false) {
                    throw new Exception("Error reading data from URL");
                }

                $bytesWritten = fwrite($writeStream, $chunk);
                if ($bytesWritten === false) {
                    throw new Exception("Error writing data to file");
                }

                $bytesDownloaded += $bytesWritten;

                if (is_callable($progressCallback)) {
                    $progressCallback($bytesDownloaded, $fileSize);
                }
            }
        } finally {
            fclose($readStream);
            fclose($writeStream);
        }

        return $this;
    }

    protected function makeHeaders(array $headers): string
    {
        $headersString = '';
        if (!empty($headers['Cookie']) && is_array($headers['Cookie'])) {
            $headers['Cookie'] = http_build_query($headers['Cookie'], '', ';');
        }
        foreach ($headers as $key => $value) {
            $headersString .= "$key: $value\r\n";
        }
        return $headersString;
    }

    /**
     * @throws Exception
     */
    public function execute(callable $callback = null): static
    {
        $this->request($this->url, $this->method, $this->data, $this->headers);
        if (is_callable($callback)) {
            $callback($this);
        }
        return $this;
    }

    public function getResponse(bool $proceed = true): bool|string|null
    {
        if ($proceed) {
            if (!$this->responseProcessed) {
                if ($this->getResponseHeaders('Content-Encoding') === 'gzip') {
                    if (!function_exists('\gzdecode')) {
                        throw new RuntimeException('Install zlib extension or use getResponse(false)');
                    }
                    $this->responseProcessed = gzdecode($this->response);
                } else {
                    return $this->response;
                }
            }
            return $this->responseProcessed;
        }
        return $this->response;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getResponseHeaders(string $key = null): ?array
    {
        if ($key) {
            return $this->responseHeaders[$key] ?? null;
        }
        return $this->responseHeaders;
    }

    public function getRawResponseHeaders(): ?array
    {
        return $this->rawResponseHeaders;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function verifySSL(bool $enable = true): static
    {
        $this->verifySSL = $enable;
        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Fetch the content even on failure status code
     */
    public function setIgnoreErrors(bool $ignoreErrors = true): static
    {
        $this->ignoreErrors = $ignoreErrors;
        return $this;
    }

    public function getHeader(string $key): ?string
    {
        return $this->headers[$key] ?? null;
    }

    /** @deprecated Use addHeader instead */
    public function setHeader(string $key, string $value): static
    {
        return $this->addHeader($key, $value);
    }

    public function addHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function removeHeader(string $key): static
    {
        unset($this->headers[$key]);
        return $this;
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function getCookies(): array|string|null
    {
        return $this->headers['Cookie'] ?? null;
    }

    /** @deprecated Use setCookies instead */
    public function setCookie(array|string $name, string $value = null): static
    {
        return $this->setCookies($name, $value);
    }

    public function setCookies(array|string $name, string $value = null): static
    {
        if (is_array($name)) {
            $this->headers['Cookie'] = $name;
            return $this;
        }
        if (is_string($name) && $value) {
            if (!isset($this->headers['Cookie'])) {
                $this->headers['Cookie'] = [];
            }
            $this->headers['Cookie'][$name] = $value;
            return $this;
        }
        throw new InvalidArgumentException('Invalid cookie data');
    }

    public function getResponseCookies(?string $key = null): array|string|null
    {
        if ($key) {
            return $this->responseCookies[$key] ?? null;
        }
        return $this->responseCookies;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function setProxy(string $proxy): static
    {
        $this->proxy = $proxy;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    public function getData(): array|string|null
    {
        return $this->data;
    }

    public function setData(array|string $data): static
    {
        $this->data = $data;
        return $this;
    }

    protected function reset(): static
    {
        $this->response = null;
        $this->rawResponseHeaders = null;
        $this->responseHeaders = [];
        $this->responseCookies = [];
        $this->statusCode = null;

        return $this;
    }

    public static function create(): static
    {
        return new static;
    }

    public function __toString(): string
    {
        return (string)$this->getResponse(false);
    }
}
