# Simple http client
A small, lightweight, zero-dependency HTTP client designed to simplify sending HTTP requests effortlessly

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
        ->setCookies('cookieName','cookieValue')
        ->verifySSL(false) // Accept sef-signed certificates
        ->setTimeout(10)
        ->setIsJson()
        ->execute();
} catch (\Exception $e) {
    echo $e->getMessage();
}
```

### File download (No memory leak)
```PHP
$progressCallback = function ($downloaded, $total) {
    if ($total) {
        $percent = round(($downloaded / $total) * 100, 2);
        echo "Downloaded: $downloaded / $total bytes ($percent%)\r\n";
    } else {
        echo "Downloaded: $downloaded bytes\r\n";
    }
};
$dst = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '__' . time() . '_test.mp4';
//$testUrl = 'https://freetestdata.com/wp-content/uploads/2022/02/Free_Test_Data_1MB_MP4.mp4';
$testUrl = 'https://freetestdata.com/wp-content/uploads/2022/02/Free_Test_Data_10MB_MP4.mp4';

$time = microtime(true);
$request1 = Client::create()->download($testUrl, $dst, $progressCallback);
//$request2 = Client::create()->get($testUrl)->execute();

$duration = round(microtime(true) - $time, 2);
$memory = round(memory_get_peak_usage() / (1024 * 1024), 2);
echo PHP_EOL;
echo "Memory usage: {$memory} Mb" . PHP_EOL;
echo "Duration: {$duration} sec" . PHP_EOL;
```

```
Memory usage: 0.72 Mb
Duration: 2.35 sec
```
vs
```
Memory usage: 12.62 Mb
Duration: 3.59 sec
```
