<?php

declare(strict_types=1);

namespace Dron;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\UriInterface;

final class ContentCheck
{
    /**
     * allow parrams for request
     */
    private array $allowParams = [
        'host',
        'protocol',
        'port',
        'type',
        'auth',
        'header',
        'form',
        'content',
        'include_exclude',
        'ipv6',
        'maintenance',
        'allow_redirects',
        'http_codes',
        'timeout',
    ];

    /**
     * Default params for request
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
     * Default output message
     */
    private array $defaultOutput = [
        'monitor_id' => null,
        'locations' => null,
        'current_location' => null,
        'frequency' => null,
        'transaction_id' => null,
        'cycle_id' => null,
        'type' => null,
        'date' => null,
        'params' => [],
        'main_result' => 'down',
        'date' => null,
        'content_position' => -1,
        'maintenance_position' => -1,
        'http_code' => 0,
        'total_time' => 0,
        'namelookup_time' => 0,
        'connect_time' => 0,
        'pretransfer_time' => 0,
        'starttransfer_time' => 0,
        'error' => '',
    ];

    private ClientInterface $client;
    private array $output;
    private array $params;
    private array $requestContainer = [];

    /**
     * Constructor
     */
    public function __construct(array $params = [])
    {
        $this->defaultParams = array_merge($this->defaultParams, $params);
        $history = Middleware::history($this->requestContainer);
        $handlerStack = HandlerStack::create();
        $handlerStack->push($history);

        $this->client = new Client([
            'handler' =>  $handlerStack,
            // RequestOptions::DEBUG => true,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::ON_STATS => function ($stats): void {
                $this->output['total_time'] = (int) ($stats->getHandlerStat('total_time') * 1000);
                $this->output['namelookup_time'] = (int) ($stats->getHandlerStat('namelookup_time') * 1000);
                $this->output['connect_time'] = (int) ($stats->getHandlerStat('connect_time') * 1000);
                $this->output['pretransfer_time'] = (int) ($stats->getHandlerStat('pretransfer_time') * 1000);
                $this->output['starttransfer_time'] = (int) ($stats->getHandlerStat('starttransfer_time') * 1000);
            },
        ]);
    }

    /**
     * You can set default parameters before check url
     *
     * @param array $params Default parameters
     *
     * @return array
     */
    public function setDefaultParams(array $params): array
    {
        $this->defaultParams = array_merge($this->defaultParams, $params);
        return $this->defaultParams;
    }

    /**
     * You can get default parameters
     * setDefaultParams
     *
     * @return array
     */
    public function getDefaultParams(): array
    {
        return $this->defaultParams;
    }

    /**
     * Main class method for check http url
     *
     * @param array $input Input Message
     *
     * @return array Output Message
     */
    public function check(array $input): array
    {
        $params = $this->validationParams(json_decode($input['params'], true));

        $this->params = array_merge($this->defaultParams, $params);
        $this->output = array_merge($this->defaultOutput, $input);

        try {
            $response = $this->client->request(
                $this->getMethodFromParams(),
                $this->getUriFromParams(),
                $this->getOptionsFromParams()
            );
            $this->checkResponse($response, $this->output);
        } catch (RequestException $throw) {
            $this->output['error'] = $throw->getMessage();
        } catch (Exception $throw) {
            $this->output['error'] = 'Error code: '
                . $throw->getMessage()
                . ' object_id: '
                . $this->output['monitor_id'];
        }

        $this->output['date'] = date('Y-m-d H:i:s');
        return $this->output;
    }

    /**
     * Return last response for testing
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * getRequestClient
     *
     * @return void
     */
    public function getRequestContainer()
    {
        return $this->requestContainer;
    }

    /**
     * getRequestClient
     *
     * @return void
     */
    public function getRequestParams()
    {
        $container = $this->getRequestContainer();
        if ($container && count($container)) {
            $request = $container[0]['request'] ?? null;
            return [
                'uri' => (string) $request->getUri(),
                'header' => $request->getHeaders(),
                'body' => (string) $request->getBody(),
            ];
        }
        return [
            'uri' => '',
            'header' => [],
            'body' => '',
        ];
    }

    // Getters

    /**
     * @throws Exception If host is not set
     */
    private function getUriFromParams(): UriInterface
    {
        if (!$this->params['host']) {
            throw new Exception('input-data-incorrect');
        }

        $parts = explode('/', $this->params['host']);
        $host = array_shift($parts);
        return Uri::fromParts([
            'host' => $host,
            'path' => implode('/', $parts),
            'scheme' => $this->getProtocolFromParams(),
            'port' => $this->params['port'] ?? $this->defaultParams['port'],
        ]);
    }

    /**
     * Getter for request method
     */
    private function getMethodFromParams(): string
    {
        return in_array($this->params['type'], ['POST', 'GET'])
            ? $this->params['type']
            : $this->defaultParams['type'];
    }

    /**
     * Getter for request protocol
     */
    private function getProtocolFromParams(): string
    {
        return in_array($this->params['protocol'], ['http', 'https'])
            ? $this->params['protocol']
            : $this->defaultParams['protocol'];
    }

    /**
     * Getter for request options
     */
    private function getOptionsFromParams(): array
    {
        return [
            RequestOptions::AUTH => array_values(
                $this->params['auth'] ?? []
            ),
            RequestOptions::HEADERS => array_column(
                $this->params['header'] ?? [],
                'value',
                'name'
            ),
            RequestOptions::FORM_PARAMS => array_column(
                $this->params['form'] ?? [],
                'value',
                'name'
            ),
            RequestOptions::ALLOW_REDIRECTS => $this->params['allow_redirects']
                ?? null,
            RequestOptions::TIMEOUT => $this->params['timeout'] ?? null,
            // RequestOptions::FORCE_IP_RESOLVE => $params['ipv6'] ? 'v6' : 'v4',
        ];
    }

    // Helpers

    /**
     * Checking Response
     */
    private function checkResponse(Response $response): void
    {
        $this->output['http_code'] = $response->getStatusCode();
        if (!in_array($this->output['http_code'], $this->params['http_codes'])) {
            throw new Exception('http-code-incorrect');
        }
        $content = $response->getBody()->getContents();
        $this->output['content_position'] =
            $this->findPosition((string) $content, $this->params['content']);
        $this->output['maintenance_position'] =
            $this->findPosition((string) $content, $this->params['maintenance']);

        if ($this->params['include_exclude'] === 'exclude') {
            if (
                $this->output['content_position'] === -1
                || $this->output['maintenance_position'] > -1
            ) {
                $this->output['main_result'] = $this->output['total_time'];
            }
        } else {
            if (
                $this->output['content_position'] > -1
                || $this->output['maintenance_position'] > -1
            ) {
                $this->output['main_result'] = $this->output['total_time'];
            }
        }
    }

    /**
     * Finds needle content and return position
     */
    private function findPosition(string $content, string $hashedNeedle): int
    {
        $needle = base64_decode($hashedNeedle);
        if (!$needle) {
            return -1;
        }
        $position = strpos($content, $needle);
        return $position === false ? -1 : $position;
    }

    /**
     * validationParams
     */
    private function validationParams(array $params): array
    {
        $params = $this->exludeNotAllowParams($params);

        if ($this->validationAuth($params['auth'] ?? []) || !is_array($params['auth'] ?? [])) {
            unset($params['auth']);
        }
        if ($this->validationHeaderForm($params['header'] ?? []) || !is_array($params['header'] ?? [])) {
            unset($params['header']);
        }
        if ($this->validationHeaderForm($params['form'] ?? []) || !is_array($params['form'] ?? [])) {
            unset($params['form']);
        }
        if (!in_array($params['include_exclude'] ?? '', ['exlude', 'include'])) {
            unset($params['include_exclude']);
        }
        if (!is_bool($params['ipv6'] ?? '')) {
            unset($params['ipv6']);
        }
        if (!is_array($params['http_codes'] ?? [])) {
            unset($params['http_codes']);
        }
        if (!is_integer($params['timeout'] ?? '')) {
            unset($params['timeout']);
        }
        if (!is_integer($params['port'] ?? '') || $params['port'] <= 0) {
            unset($params['port']);
        }

        return $params;
    }

    /**
     * validationHeaderForm
     *
     * @param  mixed $array
     * @return bool
     */
    private function validationHeaderForm(array $array, array $needleField =  ['name','value']): bool
    {
        foreach ($array as $value) {
            $res = (array_keys($value) === $needleField) ? false : true;

            if ($res) {
                return $res;
            }
        }

        return false;
    }

    /**
     * validationHeaderForm
     *
     * @param  mixed $array
     * @return bool
     */
    private function validationAuth(array $array, array $needleField = ['login','password']): bool
    {
        return (array_keys($array) === $needleField) ? false : true;
    }

    /**
     * exludeNotAllowParams
     *
     * @param  mixed $params
     * @return array
     */
    private function exludeNotAllowParams(array $params): array
    {
        foreach($params as $key => $value) {
            if(!in_array($key,$this->allowParams)) {
                unset($params[$key]);
            }
        }

        return $params;
    }
}
