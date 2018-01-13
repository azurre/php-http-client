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

    protected
        $method,
        $data,
        $headers,
        $responseHeaders,
        $statusCode,
        $errorMessage,
        $timeout = 60,
        $verifySSL = false,
        $ignoreErrors = 1,
        $defaultHeaders = [];

    /**
     * @param string $url
     *
     * @return string
     */
    public function get($url)
    {
        return $this->request($url, static::METHOD_GET);
    }

    /**
     * @param string $url
     * @param string $method
     * @param array  $data
     * @param array  $headers
     *
     * @return string
     */
    public function request($url, $method = self::METHOD_GET, $data = null, $headers = [])
    {
        $this->errorMessage = '';
        $contextData = [
            'http' => [
                'method'        => strtoupper($method),
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
        if ($data) {
            $contextData['http']['content'] = $data;
        }
        $error = static::catchWarning(function () use ($url, $contextData, &$response) {
            $response = file_get_contents($url, false, stream_context_create($contextData));
            $this->errorMessage = $response ? '' : error_get_last();

            return empty($response);
        });

        isset($http_response_header) ? $this->parseHeaders($http_response_header) : $this->statusCode = -1;
        if ($error) {
            return false;
        }

        return $response;
    }

    /**
     * @param string $url
     * @param array  $postData
     *
     * @return string
     */
    public function post($url, $postData)
    {
        $postData = http_build_query($postData);
        $defaultHeaders = [
            'Content-Type'   => 'application/x-www-form-urlencoded',
            'Content-Length' => strlen($postData)
        ];
        return $this->request($url, 'POST', $postData, $defaultHeaders);
    }

    /**
     * @param array $responseHeaders
     *
     * @return $this
     */
    protected function parseHeaders($responseHeaders)
    {
        $this->headers = $responseHeaders;
        $this->statusCode = function_exists('http_response_code')
            ? http_response_code() : (int)preg_replace('/.*?\s(\d+)\s.*/', "\\1", $responseHeaders[0]);

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
     * Catch warning
     *
     * @param callable $callable
     *
     * @return false|array error data or false
     */
    public static function catchWarning($callable)
    {
        $error = false;
        set_error_handler(function ($errorCode, $errorMessage, $errorFile, $errorLine, $errorContext) use (&$error) {
            $error = [
                'errorCode'    => $errorCode,
                'errorMessage' => $errorMessage,
                'errorFile'    => $errorFile,
                'errorLine'    => $errorLine
            ];
        }, E_ALL);

        call_user_func($callable);
        restore_error_handler();

        return $error;
    }

    /**
     * Execute request
     */
    public function execute()
    {
        return $this->request($this->method, $this->data, $this->headers);
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
     * @return array
     */
    public function getResponseHeaders()
    {
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
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
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
     * @return $this
     */
    public static function init()
    {
        return new self;
    }
}