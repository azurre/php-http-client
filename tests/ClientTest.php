<?php
/**
 * @author Alex Milenin
 * @email  admin@azrr.info
 * @date   27.12.2018
 */

namespace Azurre\Component\Http\Tests;

use \Azurre\Component\Http\Client;

/**
 * Class ClientTest
 */
class ClientTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
    public function statusCodeTest()
    {
        $request = Client::create();
        try {
            $request->get(BASE_URL)->execute()->getResponse();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
        $this->assertEquals(200, $request->getStatusCode());
    }

    /**
     * @test
     */
    public function dataTest()
    {
        $request = Client::create();
        $data = ['test' => ['data' => 123, 'message' => 'OK']];
        try {
            $request->post(BASE_URL, $data)->execute()->getResponse();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
        $response = json_decode($request->getResponse(), true);
        $this->assertEquals('OK', $response['POST']['test']['message']);
        $this->assertEquals('123', $response['POST']['test']['data']);
    }

    /**
     * @test
     */
    public function apiTest()
    {
        $request = Client::create();
        $data = ['test' => ['data' => 123, 'message' => 'OK']];
        try {
            $request->post(BASE_URL, $data)->setIsJson()->execute()->getResponse();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
        $response = json_decode($request->getResponse(), true);
        $this->assertEquals(json_encode($data), $response['INPUT']);
    }

    /**
     * @test
     */
    public function headersTest()
    {
        $request = Client::create();
        try {
            $request->get(BASE_URL)->setHeader('TEST', 'OK')->execute()->getResponse();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
        $response = json_decode($request->getResponse(), true);
        $this->assertEquals('OK', $response['SERVER']['HTTP_TEST']);
    }

    /**
     * @test
     */
    public function cookiesTest()
    {
        $request = Client::create();
        try {
            $request->get(BASE_URL)->setCookie(['test' => 'OK', 'test1' => 1])->execute()->getResponse();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
        $response = json_decode($request->getResponse(), true);
        $this->assertEquals('OK', $response['COOKIE']['test']);
        $this->assertEquals('1', $response['COOKIE']['test1']);
        $this->assertEquals('OK', $request->getResponseCookies('test'));
        $this->assertEquals('1', $request->getResponseCookies('test1'));
    }
}
