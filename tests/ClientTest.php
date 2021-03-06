<?php

use Onurrr\Viacoin;
use Onurrr\Viacoin\Exceptions;
use GuzzleHttp\Psr7\Response;

class ClientTest extends TestCase
{
    /**
     * Set up test.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->viacoind = new Viacoin\Client();
    }

    /**
     * Test url parser.
     *
     * @param string $url
     * @param string $scheme
     * @param string $host
     * @param int    $port
     * @param string $user
     * @param string $pass
     *
     * @return void
     *
     * @dataProvider urlProvider
     */
    public function testUrlParser($url, $scheme, $host, $port, $user, $pass)
    {
        $viacoind = new Viacoin\Client($url);

        $this->assertInstanceOf(Viacoin\Client::class, $viacoind);

        $base_uri = $viacoind->getConfig('base_uri');

        $this->assertEquals($base_uri->getScheme(), $scheme);
        $this->assertEquals($base_uri->getHost(), $host);
        $this->assertEquals($base_uri->getPort(), $port);

        $auth = $viacoind->getConfig('auth');
        $this->assertEquals($auth[0], $user);
        $this->assertEquals($auth[1], $pass);
    }

    /**
     * Data provider for url expander test.
     *
     * @return array
     */
    public function urlProvider()
    {
        return [
            ['https://localhost', 'https', 'localhost', 8332, '', ''],
            ['https://localhost:8000', 'https', 'localhost', 8000, '', ''],
            ['http://localhost', 'http', 'localhost', 8332, '', ''],
            ['http://localhost:8000', 'http', 'localhost', 8000, '', ''],
            ['http://testuser@127.0.0.1:8000/', 'http', '127.0.0.1', 8000, 'testuser', ''],
            ['http://testuser:testpass@localhost:8000', 'http', 'localhost', 8000, 'testuser', 'testpass'],
        ];
    }

    /**
     * Test url parser with invalid url.
     *
     * @return array
     */
    public function testUrlParserWithInvalidUrl()
    {
        try {
            $viacoind = new Viacoin\Client('cookies!');

            $this->expectException(Exceptions\ClientException::class);
        } catch (Exceptions\ClientException $e) {
            $this->assertEquals('Invalid url', $e->getMessage());
        }
    }

    /**
     * Test client getter and setter.
     *
     * @return void
     */
    public function testClientSetterGetter()
    {
        $viacoind = new Viacoin\Client('http://old_client.org');
        $this->assertInstanceOf(Viacoin\Client::class, $viacoind);

        $base_uri = $viacoind->getConfig('base_uri');
        $this->assertEquals($base_uri->getHost(), 'old_client.org');

        $oldClient = $viacoind->getClient();
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $oldClient);

        $newClient = new \GuzzleHttp\Client(['base_uri' => 'http://new_client.org']);
        $viacoind->setClient($newClient);

        $base_uri = $viacoind->getConfig('base_uri');
        $this->assertEquals($base_uri->getHost(), 'new_client.org');
    }

    /**
     * Test ca config option.
     *
     * @return void
     */
    public function testCaOption()
    {
        $viacoind = new Viacoin\Client();

        $this->assertEquals(null, $viacoind->getConfig('ca'));

        $viacoind = new Viacoin\Client([
            'ca' => __FILE__,
        ]);

        $this->assertEquals(__FILE__, $viacoind->getConfig('verify'));
    }

    /**
     * Test simple request.
     *
     * @return void
     */
    public function testRequest()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $response = $this->viacoind
            ->setClient($guzzle)
            ->request(
                'getblockheader',
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f'
            );

        $this->assertEquals(self::$getBlockResponse, $response->get());
    }

    /**
     * Test async request.
     *
     * @return void
     */
    public function testAsyncRequest()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Viacoin\ViacoindResponse $response) {
                return $response->get() == self::$getBlockResponse;
            }),
        ]);

        $promise = $this->viacoind
            ->setClient($guzzle)
            ->requestAsync(
                'getblockheader',
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f',
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();
    }

    /**
     * Test magic request.
     *
     * @return void
     */
    public function testMagic()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $response = $this->viacoind
            ->setClient($guzzle)
            ->getBlockHeader(
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f'
            );

        $this->assertEquals(self::$getBlockResponse, $response->get());
    }

    /**
     * Test magic request.
     *
     * @return void
     */
    public function testAsyncMagic()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Viacoin\ViacoindResponse $response) {
                return $response->get() == self::$getBlockResponse;
            }),
        ]);

        $promise = $this->viacoind
            ->setClient($guzzle)
            ->getBlockHeaderAsync(
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f',
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();
    }

    /**
     * Test viacoind exception.
     *
     * @return void
     */
    public function testViacoindException()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(200),
        ]);

        try {
            $response = $this->viacoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\ViacoindException::class);
        } catch (Exceptions\ViacoindException $e) {
            $this->assertEquals(self::$rawTransactionError['message'], $e->getMessage());
            $this->assertEquals(self::$rawTransactionError['code'], $e->getCode());
        }
    }

    /**
     * Test async viacoind exception.
     *
     * @return void
     */
    public function testAsyncViacoindException()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(200),
        ]);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Exceptions\ViacoindException $exception) {
                return $exception->getMessage() == self::$rawTransactionError['message'] &&
                    $exception->getCode() == self::$rawTransactionError['code'];
            }),
        ]);

        $promise = $this->viacoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();
    }

    /**
     * Test request exception with error code.
     *
     * @return void
     */
    public function testRequestExceptionWithServerErrorCode()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(500),
        ]);

        try {
            $this->viacoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\ViacoindException::class);
        } catch (Exceptions\ViacoindException $exception) {
            $this->assertEquals(
                self::$rawTransactionError['message'],
                $exception->getMessage()
            );
            $this->assertEquals(
                self::$rawTransactionError['code'],
                $exception->getCode()
            );
        }
    }

    /**
     * Test async request exception with error code.
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithServerErrorCode()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(500),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\ViacoindException $exception) {
                return $exception->getMessage() == self::$rawTransactionError['message'] &&
                    $exception->getCode() == self::$rawTransactionError['code'];
            }),
        ]);

        $promise = $this->viacoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait(false);
    }

    /**
     * Test request exception with empty response body.
     *
     * @return void
     */
    public function testRequestExceptionWithEmptyResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            new Response(500),
        ]);

        try {
            $response = $this->viacoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\ClientException::class);
        } catch (Exceptions\ClientException $exception) {
            $this->assertEquals(
                $this->error500(),
                $exception->getMessage()
            );
            $this->assertEquals(500, $exception->getCode());
        }
    }

    /**
     * Test async request exception with empty response body.
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithEmptyResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            new Response(500),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\ClientException $exception) {
                return $exception->getMessage() == $this->error500() &&
                    $exception->getCode() == 500;
            }),
        ]);

        $promise = $this->viacoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait(false);
    }

    /**
     * Test request exception with response.
     *
     * @return void
     */
    public function testRequestExceptionWithResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithResponse(),
        ]);

        try {
            $response = $this->viacoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\ViacoindException::class);
        } catch (Exceptions\ViacoindException $exception) {
            $this->assertEquals(
                self::$rawTransactionError['message'],
                $exception->getMessage()
            );
            $this->assertEquals(
                self::$rawTransactionError['code'],
                $exception->getCode()
            );
        }
    }

    /**
     * Test async request exception with response.
     *
     * @expectedException GuzzleHttp\Exception\RequestException
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithResponse(),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\ViacoindException $exception) {
                return $exception->getMessage() == self::$rawTransactionError['message'] &&
                    $exception->getCode() == self::$rawTransactionError['code'];
            }),
        ]);

        $promise = $this->viacoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait();
    }

    /**
     * Test request exception with no response.
     *
     * @return void
     */
    public function testRequestExceptionWithNoResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithoutResponse(),
        ]);

        try {
            $response = $this->viacoind
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\ClientException::class);
        } catch (Exceptions\ClientException $exception) {
            $this->assertEquals(
                'test',
                $exception->getMessage()
            );
            $this->assertEquals(0, $exception->getCode());
        }
    }

    /**
     * Test async request exception with no response.
     *
     * @expectedException GuzzleHttp\Exception\RequestException
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithNoResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithoutResponse(),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\ClientException $exception) {
                return $exception->getMessage() == 'test' &&
                    $exception->getCode() == 0;
            }),
        ]);

        $promise = $this->viacoind
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait();
    }

    public function testToBtc()
    {
        $this->assertEquals(0.00005849, Viacoin\Client::toBtc(310000 / 53));
    }

    public function testToSatoshi()
    {
        $this->assertEquals(5849, Viacoin\Client::toSatoshi(0.00005849));
    }

    public function testToFixed()
    {
        $this->assertSame('1', Viacoin\Client::toFixed(1.2345678910, 0));
        $this->assertSame('1.23', Viacoin\Client::toFixed(1.2345678910, 2));
        $this->assertSame('1.2345', Viacoin\Client::toFixed(1.2345678910, 4));
        $this->assertSame('1.23456789', Viacoin\Client::toFixed(1.2345678910, 8));
    }
}
