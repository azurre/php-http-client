<?php
/**
 * @date    10.03.2015
 * @version 0.1
 * @author  Aleksandr Milenin azrr.mail@gmail.com
 */
namespace Azurre\Component;

/**
 * Simple http client
 */
class Http
{
    const METHOD_GET    = 'GET';
    const METHOD_POST   = 'POST';
    const METHOD_PUT    = 'PUT';
    const METHOD_HEAD   = 'HEAD';
    const METHOD_DELETE = 'DELETE';

    const STATUS_OK = 200;
    const STATUS_NOT_FOUND = 404;

    protected
        $method,
        $url,
        $data,
        $raw,
        $response,
        $rawResponseHeaders,
        $responseHeaders,
        $cookies,
        $statusCode,
        $error,
        $timeout = 60,
        $verifySSL = false,
        $ignoreErrors = 1,
        $headers = [],
        $defaultHeaders = [];

    /**
     * @param string $url
     *
     * @return $this
     */
    public function get($url)
    {
        $this->url = $url;
        $this->method = static::METHOD_GET;
        return $this;
    }

    /**
     * @param string $url
     * @param array  $data
     *
     * @return $this
     */
    public function post($url, $data = [])
    {
        $this->url = $url;
        $this->method = static::METHOD_POST;
        $this->data = $data;
        $this->setHeader('Content-Type','application/x-www-form-urlencoded');
        return $this;
    }

    /**
     * @param string $url
     * @param array  $data
     *
     * @return $this
     */
    public function put($url, $data = [])
    {
        $this->url = $url;
        $this->method = static::METHOD_PUT;
        $this->data = $data;
        $this->setHeader('Content-Type','application/x-www-form-urlencoded');
        return $this;
    }

    /**
     * @param string $url
     *
     * @return $this
     */
    public function delete($url)
    {
        $this->url = $url;
        $this->method = static::METHOD_DELETE;
        return $this;
    }

    /**
     * @param string $url
     * @param string $method
     * @param array  $data
     * @param array  $headers
     *
     * @return $this
     */
    public function request($url, $method = self::METHOD_GET, $data = null, $headers = [])
    {
        $this->response = $this->error = null;
        $method = strtoupper($method);
        if ($data) {
            if (!$this->raw) {
                $data = http_build_query($data);
            }
            $this->setHeader('Content-Length', strlen($data));
            $contextData['http']['content'] = $data;
        }

        if(!isset($headers['Host'])) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host) {
                $this->setHeader('Host', $host);
            }
        }

        $contextData = [
            'http' => [
                'method'        => $method,
                'header'        => $this->makeHeaders(array_merge($this->defaultHeaders, $headers)),
                'timeout'       => $this->timeout,
                'ignore_errors' => $this->ignoreErrors
            ]
        ];
        if (!$this->verifySSL) {
            $contextData['ssl'] = [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ];
        }
        try {
            $this->response = @file_get_contents($url, false, stream_context_create($contextData));
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->statusCode = -1;
            return $this;
        }

        $error = error_get_last();
        $this->error = empty($error['message']) ? null : $error['message'];

        isset($http_response_header) ? $this->parseHeaders($http_response_header) : $this->statusCode = -1;

        return $this;
    }

    /**
     * @param array $responseHeaders
     *
     * @return $this
     */
    protected function parseHeaders($responseHeaders)
    {
        $this->rawResponseHeaders = $responseHeaders;
        $this->cookies = [];
        foreach ($responseHeaders as $header) {
            list($key, $value) = explode(':', $header);
            $value = trim($value);
            if ($key === 'Set-Cookie') {
                $this->cookies[] = $value;
            } else {
                $this->responseHeaders[$key] = $value;
            }
            $this->responseHeaders['Cookies'] = $this->cookies;
        }
        $this->statusCode = (int)preg_replace('/.*?\s(\d+)\s.*/', "\\1", $responseHeaders[0]);
        return $this;
    }

    /**
     * @param array $headers
     *
     * @return string
     */
    protected function makeHeaders($headers)
    {
        $headersString = '';
        foreach ($headers as $key => $value) {
            $headersString .= "{$key}: {$value}\r\n";
        }

        return $headersString;
    }

    /**
     * Execute request
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function execute($callback = null)
    {
        $this->request($this->url, $this->method, $this->data, $this->headers);
        if (is_callable($callback)) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Retrieve processed response
     *
     * @return string
     */
    public function getResponseProcessed()
    {
        if ($this->getResponseHeaders('Content-Encoding') === 'gzip') {
            return gzdecode($this->getResponse());
        }
        return $this->getResponse();
    }

    /**
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Retrieve response headers
     *
     * @param string|null $key
     *
     * @return array
     */
    public function getResponseHeaders($key = null)
    {
        if ($key) {
            return isset($this->responseHeaders[$key]) ? $this->responseHeaders[$key] : null;
        }
        return $this->responseHeaders;
    }

    /**
     * Retrieve response code
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return null|string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param bool $enable
     *
     * @return $this
     */
    public function verifySSL($enable = true)
    {
        $this->verifySSL = (bool)$enable;

        return $this;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set header key
     *
     * @param $key
     * @param $value
     *
     * @return $this
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set headers
     *
     * @param array $headers
     *
     * @return $this
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set raw request flag
     *
     * @param bool $enable
     *
     * @return $this
     */
    public function setRaw($enable = true)
    {
        $this->raw = (bool)$enable;
        return $this;
    }

    /**
     * @return $this
     */
    public static function init()
    {
        return new self;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getResponseProcessed();
    }
}