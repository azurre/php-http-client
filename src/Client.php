<?php
/**
 * @author Alex Milenin
 * @email  admin@azrr.info
 * @copyright Copyright (c)Alex Milenin (https://azrr.info/)
 */

namespace Azurre\Component\Http;

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

    protected
        $method,
        $url,
        $data,
        $isJson,
        $proxy,
        $response,
        $responseProcessed,
        $rawResponseHeaders,
        $responseHeaders,
        $responseCookies,
        $statusCode,
        $timeout = 60,
        $followLocation = true,
        $verifySSL = false,
        $ignoreErrors = true,
        $headers = [],
        $defaultHeaders = [];

    /**
     * @return bool
     */
    public function isFollowLocation()
    {
        return $this->followLocation;
    }

    /**
     * @param bool $followLocation
     * @return static
     */
    public function setFollowLocation($followLocation)
    {
        $this->followLocation = (bool)$followLocation;
        return $this;
    }

    /**
     * @param string $url
     * @param array $data
     * @return $this
     */
    public function get($url, array $data = [])
    {
        $url .= $data ? ((parse_url($url, PHP_URL_QUERY) ? '&' : '?') . http_build_query($data)) : '';
        $this->url = $url;
        $this->method = static::METHOD_GET;
        $this->data = null;
        return $this;
    }

    /**
     * @param string $url
     * @param array $data
     * @return $this
     */
    public function post($url, array $data = [])
    {
        $this->url = $url;
        $this->method = static::METHOD_POST;
        $this->data = $data;
        if (!$this->isJson) {
            $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        }
        return $this;
    }

    /**
     * @param string $url
     * @param array $data
     * @return $this
     */
    public function put($url, array $data = [])
    {
        $this->url = $url;
        $this->method = static::METHOD_PUT;
        $this->data = $data;
        if (!$this->isJson) {
            $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        }
        return $this;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function delete($url)
    {
        $this->url = $url;
        $this->method = static::METHOD_DELETE;
        return $this;
    }

    /**
     * @param bool $isJson
     * @return $this
     */
    public function setIsJson($isJson = true)
    {
        $this->isJson = (bool)$isJson;
        if ($isJson) {
            $this->setHeader('Content-Type', 'application/json');
        }
        return $this;
    }

    /**
     * @param string $url
     * @param string $method
     * @param array|null $data
     * @param array $headers
     * @return $this
     * @throws \Exception
     */
    public function request($url, $method = self::METHOD_GET, array $data = null, array $headers = [])
    {
        $http_response_header = null;
        $this->reset();
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
        $this->response = @file_get_contents($url, false, stream_context_create($contextData));
        $error = \error_get_last();
        if ($error && !empty($error['message'])) {
            $error = $error['message'];
        }
        if ($error) {
            throw new \RuntimeException($error);
        }
        $this->parseHeaders($http_response_header);
        return $this;
    }

    /**
     * @param array $responseHeaders
     * @return $this
     */
    protected function parseHeaders($responseHeaders)
    {
        if ($responseHeaders !== null) {
            // @todo Handle several headers (redirects)
            $this->rawResponseHeaders = $responseHeaders;
            $this->responseCookies = [];
            foreach ($responseHeaders as $i => $header) {
                $headerChunks = preg_split('/:\s/', $header, 2);
                $chunksCount = count($headerChunks);
                if ($chunksCount === 2) {
                    list($key, $value) = $headerChunks;
                    $value = trim($value);
                    if ($key === 'Set-Cookie') {
                        list($cookieName, $cookieValue) = explode('=', $value);
                        $this->responseCookies[$cookieName] = $cookieValue;
                    } else {
                        $this->responseHeaders[$key] = [];
                    }
                    $this->responseHeaders['Cookies'] = $this->responseCookies;
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
     * @param array $headers
     * @return string
     */
    protected function makeHeaders($headers)
    {
        $headersString = '';
        if (!empty($headers['Cookie']) && \is_array($headers['Cookie'])) {
            $headers['Cookie'] = http_build_query($headers['Cookie'], null, ';');
        }
        foreach ($headers as $key => $value) {
            $headersString .= "{$key}: {$value}\r\n";
        }
        return $headersString;
    }

    /**
     * Execute request
     *
     * @param \Closure $callback
     * @return $this
     * @throws \Exception
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
     * @param bool $proceed
     * @return string
     */
    public function getResponse($proceed = true)
    {
        if ($proceed) {
            if (!$this->responseProcessed) {
                if ($this->getResponseHeaders('Content-Encoding') === 'gzip') {
                    if (!function_exists('\gzdecode')) {
                        throw new \RuntimeException('Install zlib extension or use getResponse(false)');
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
     * @return array
     */
    public function getRawResponseHeaders()
    {
        return $this->rawResponseHeaders;
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
     * @param bool $enable
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
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Fetch the content even on failure status code
     *
     * @param bool $ignoreErrors
     * @return static
     */
    public function setIgnoreErrors($ignoreErrors = true)
    {
        $this->ignoreErrors = (bool)$ignoreErrors;
        return $this;
    }

    /**
     * @param string $key
     * @return string
     */
    public function getHeader($key)
    {
        return isset($this->headers[$key]) ? $this->headers[$key] : null;
    }

    /**
     * Set header key
     *
     * @param string $key
     * @param string $value
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
     * @return $this
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @return array|string|null
     */
    public function getCookies()
    {
        return isset($this->headers['Cookie']) ? $this->headers['Cookie'] : null;
    }

    /**
     * @param array|string $name
     * @param string $value
     * @return $this
     */
    public function setCookie($name, $value = null)
    {
        if (\is_array($name)) {
            $this->headers['Cookie'] = $name;
            return $this;
        }
        if (\is_string($name) && $value) {
            if (!isset($this->headers['Cookie'])) {
                $this->headers['Cookie'] = [];
            }
            $this->headers['Cookie'][$name] = (string)$value;
            return $this;
        }
        throw new \InvalidArgumentException('Invalid cookie data');
    }

    /**
     * @param string $key
     * @return array|string
     */
    public function getResponseCookies($key = null)
    {
        if ($key) {
            $key = (string)$key;
            return isset($this->responseCookies[$key]) ? $this->responseCookies[$key] : null;
        }
        return $this->responseCookies;
    }

    /**
     * @return string
     */
    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * @param string $proxy
     * @return $this
     */
    public function setProxy($proxy)
    {
        $this->proxy = (string)$proxy;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param string $method
     *
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     *
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return static
     */
    protected function reset()
    {
        $this->response = null;
        $this->rawResponseHeaders = null;
        $this->responseHeaders = null;
        $this->responseCookies = null;
        $this->statusCode = null;
        return $this;
    }

    /**
     * @return $this
     */
    public static function create()
    {
        return new static;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->getResponse(false);
    }
}
