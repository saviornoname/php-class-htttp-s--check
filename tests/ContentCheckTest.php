<?php

namespace Tests\Unit;

use Dron\ContentCheck;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ContentCheckTest extends TestCase
{
    /**
     * Default params for test
     */
    private array $defaultParams = [
        'host' => '',
        'protocol' => 'http',            // "http" | "https"
        'port' => 80,
        'type' => 'GET',                 // "POST" | "GET"
        'auth' => [],                    // false | {}
        'header' => [],
        'form' => [],
        'content' => '',                 // base64_encode
        'include_exclude' => 'include',  // exclude | include
        'ipv6' => false,                 // true | false
        'maintenance' => '',             // base64_encode
        'allow_redirects' => true,       // true | false
        'http_codes' => [200, 301, 302, 304, 307, 308],
        'timeout' => 10,
    ];

    /**
     * getClassChecker
     *
     * @param  mixed $params
     * @return void
     */
    private function getClassChecker(array $params = [])
    {
        return new ContentCheck($params);
    }

    /**
     * getPrivateMethod by Reflection lass
     *
     * @param  mixed $method
     * @return void
     */
    private function getPrivateMethod(string $method)
    {
        $class = new ReflectionClass('Dron\ContentCheck');
        $method = $class->getMethod($method);
        $method->setAccessible(true);

        return $method;
    }
    
    /**
     * template for input and output message
     *
     * @param  mixed $inputFile
     * @param  mixed $outputFile
     * @return void
     */
    private function template($inputFile, $outputFile)
    {
        $checker = $this->getClassChecker($this->defaultParams);
        $outMessage = $checker->check(include $inputFile);

        $outMessage['request'] = $checker->getRequestParams();
        $outMessage['params'] = json_decode($outMessage['params']);

        file_put_contents($outputFile, json_encode($outMessage,  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS));
        return $outMessage;
    }

    /**
     * check code,mainresultr, url when port and protocol the same
     * check position
     * check type result and time
     * 
     * @return void
     */
    public function testMessage1()
    {
        $outMessage = $this->template('message1.php', 'tests/output_message1.json');

        $this->assertSame($outMessage['http_code'], 200);
        $this->assertNotSame($outMessage['main_result'], 'down');
        $this->assertSame($outMessage['request']['uri'], 'https://emcomponents.net/test_input4/');
        $this->assertSame($outMessage['content_position'], 2);
        $this->assertSame($outMessage['maintenance_position'], 83);
        $this->assertIsInt($outMessage['main_result']);
        $this->assertIsInt($outMessage['total_time']);
    }

    /**
     * check main result
     * @return void
     */
    public function testMessage2()
    {
        $outMessage = $this->template('message2.php', 'tests/output_message2.json');

        $this->assertSame($outMessage['main_result'], 'down');
    }

    /**
     * check work without hoost
     * @return void
     */
    public function testMessage3()
    {
        $outMessage = $this->template('message3.php', 'tests/output_message3.json');

        $this->assertSame($outMessage['error'], 'Error code: input-data-incorrect object_id: 123123');
    }

    /**
     * check work with incorrecr http-code
     * 
     * @return void
     */
    public function testMessage4()
    {
        $outMessage = $this->template('message4.php', 'tests/output_message4.json');

        $this->assertSame($outMessage['error'], 'Error code: http-code-incorrect object_id: 123123');
    }

    /**
     * test different port and protoccol
     * @return void
     */
    public function testMessage5()
    {
        $outMessage = $this->template('message5.php', 'tests/output_message5.json');

        $this->assertSame($outMessage['request']['uri'], 'https://emcomponents.net:80/test_input4/');
        $this->assertSame($outMessage['error'], 'Error code: cURL error 35: error:1408F10B:SSL routines:ssl3_get_record:wrong version number (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://emcomponents.net:80/test_input4/ object_id: 123123');
    }
    /**
     * 
     * test bad parametr
     * @return void
     */
    public function testMessage6()
    {
        $outMessage = $this->template('message6.php', 'tests/output_message6.json');

        $this->assertSame($outMessage['request']['uri'], 'http://emcomponents.net/test_input4/');
        $this->assertSame($outMessage['error'], 'Error code: http-code-incorrect object_id: 123123');
    }


    /**
     * test6
     * 
     * test for default option and getRquestParams
     *
     * @return void
     */
    public function testConstructor()
    {
        $checker = $this->getClassChecker(
            [
                'type' => 'GET',
                'auth' => [],
                'header' => [],
            ]
        );

        $outMessage = $checker->check(include 'message6.php');

        $outMessage['request'] = $checker->getRequestParams();

        $this->assertSame($checker->getDefaultParams()['type'], 'GET');
        $this->assertSame($checker->getDefaultParams()['port'], 80);
        $this->assertSame($checker->getDefaultParams()['http_codes'], [200, 301, 302, 304, 307, 308]);
    }

    /**
     * testUriFromParams
     *
     * @return void
     */
    public function testUriFromParams()
    {
        $method = $this->getPrivateMethod('getUriFromParams');
        $checker = $this->getClassChecker();
        $checker->check(include 'message5.php');

        $resulte = $method->invoke($checker);
        $this->assertSame($resulte->getHost(), 'emcomponents.net');
        $this->assertSame($resulte->getPort(), 80);
        $this->assertSame($resulte->getPath(), '/test_input4/');
        $this->assertSame($resulte->getScheme(), 'https');
    }

    /**
     * testMethodFromParams
     *
     * @return void
     */
    public function testMethodFromParams()
    {
        $method = $this->getPrivateMethod('getMethodFromParams');
        $method->setAccessible(true);
        $checker = $this->getClassChecker(
            ['type' => 'GET']
        );
        $checker->check(include 'message6.php');

        $this->assertSame($method->invoke($checker), 'GET');
    }

    /**
     * testProtocolFromParams
     *
     * @return void
     */
    public function testProtocolFromParams()
    {
        $method = $this->getPrivateMethod('getProtocolFromParams');
        $method->setAccessible(true);
        $checker = $this->getClassChecker();
        $checker->check(include 'message6.php');

        $this->assertSame($method->invoke($checker), 'http');
    }

    /**
     * testValidationAuth
     *
     * @return void
     */
    public function testValidationAuth()
    {
        $method = $this->getPrivateMethod('validationAuth');
        $method->setAccessible(true);
        $checker = $this->getClassChecker();

        $this->assertSame(
            $method->invoke(
                $checker,
                [
                    'login' => 'login_text',
                    'password' => 'password_text'
                ]
            ),
            false
        );

        $this->assertSame(
            $method->invoke(
                $checker,
                [
                    'lon' => 'login_text',
                    'password' => 'password_text'
                ]
            ),
            true
        );

        $this->assertSame(
            $method->invoke(
                $checker,
                [
                    'login' => 'login_text',
                    'password' => 'password_text',
                    'secret' => 'password_text',
                ]
            ),
            true
        );
    }

    /**
     * testValidationHeaderForm
     *
     * @return void
     */
    public function testValidationHeaderForm()
    {
        $method = $this->getPrivateMethod('validationHeaderForm');
        $method->setAccessible(true);
        $checker = $this->getClassChecker();

        $this->assertSame(
            $method->invoke(
                $checker,
                [
                    [
                        'name' => 'password_text',
                        'value' => 'login_text',
                    ],
                    [
                        'name' => 'password_text',
                        'value' => 'login_text',
                    ]
                ]
            ),
            false
        );

        $this->assertSame(
            $method->invoke(
                $checker,
                [
                    [
                        'value' => 'login_text',
                        'na' => 'password_text'
                    ],
                    [
                        'vaue' => 'login_text',
                        'name' => 'password_text'
                    ]
                ]
            ),
            true
        );

        $this->assertSame(
            $method->invoke(
                $checker,
                [
                    [
                        'value' => 'login_text',
                        'name' => 'password_text'
                    ],
                    [
                        'name' => 'password_text'
                    ]
                ]
            ),
            true
        );
    }
}
