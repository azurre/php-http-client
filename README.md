# Simple http client
Simple PHP 5.4+ HTTP client that makes it easy to send HTTP requests

## Installation

```bash
composer require azurre/php-http-client
```

## Usage 

### GET request

```php
require __DIR__ . '/vendor/autoload.php';
use \Azurre\Component\Http\Client;

$request = Client::create()->get('http://example.com/')->execute();
echo $request->getResponse();
```

### POST request
```php
require __DIR__ . '/vendor/autoload.php';
use \Azurre\Component\Http\Client;

$data = [
    'key' => 'some key',
    'comment' => 'Some string'
];
try {
    $request = Client::create()->post('http://example.com/', $data)->execute();
} catch (\Exception $e) {
    echo $e->getMessage();
}
var_dump($request->getStatusCode());
var_dump($request->getResponseCookies());
var_dump($request->getResponseHeaders());
echo $request->getResponse();
```

Output
```
int(200)
Array
(
    [Cache-Control] => max-age=604800
    [Cookies] => Array
        (
            [sid] => d80242705b7878712f36d8f0
        )
    [Content-Type] => text/html; charset=UTF-8
    [Date] => Thu, 27 Dec 2018 18:35:01 GMT
    [Etag] => "1541025663+ident"
    [Expires] => Thu, 03 Jan 2019 18:35:01 GMT
    [Last-Modified] => Fri, 09 Aug 2013 23:54:35 GMT
    [Server] => ECS (dca/2470)
    [Vary] => Accept-Encoding
    [X-Cache] => HIT
    [Content-Length] => 1270
    [Connection] => close
)
```

### JSON API request
```php
require __DIR__ . '/vendor/autoload.php';
use \Azurre\Component\Http\Client;

$data = [
    'string' => 'Some string',
    'numbers' => [1, 2, 3]
];
try {
    $request = Client::create();
    $request
        ->post('http://example.com/', $data)
        ->setProxy('tcp://192.168.0.2:3128')
        ->setHeader('X-SECRET-TOKEN', 'f36d8f0f53aefb121531567849')
        ->setCookie('cookieName','cookieValue')
        ->verifySSL(false) // Accept sef-signed certificates
        ->setTimeout(10)
        ->setIsJson()
        ->execute();
} catch (\Exception $e) {
    echo $e->getMessage();
}
```