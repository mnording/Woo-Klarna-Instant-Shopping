<?php
/**
 * Copyright 2014 Klarna AB
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * File containing the Connector class.
 */

namespace Klarna\Rest\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Klarna\Rest\Transport\Exception\ConnectorException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Transport connector used to authenticate and make HTTP requests against the
 * Klarna APIs.
 */
class Connector implements ConnectorInterface
{
    /**
     * HTTP transport client.
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * Merchant ID.
     *
     * @var string
     */
    protected $merchantId;

    /**
     * Shared secret.
     *
     * @var string
     */
    protected $sharedSecret;

    /**
     * HTTP user agent.
     *
     * @var UserAgent
     */
    protected $userAgent;

    /**
     * Constructs a connector instance.
     *
     * Example usage:
     *
     *     $client = new \GuzzleHttp\Client(['base_url' => 'https://api.klarna.com']);
     *     $connector = new \Klarna\Transport\Connector($client, '0', 'sharedSecret');
     *
     *
     * @param ClientInterface    $client       HTTP transport client
     * @param string             $merchantId   Merchant ID
     * @param string             $sharedSecret Shared secret
     * @param UserAgentInterface $userAgent    HTTP user agent to identify the client
     */
    public function __construct(
        ClientInterface $client,
        $merchantId,
        $sharedSecret,
        UserAgentInterface $userAgent = null
    ) {
        $this->client = $client;
        $this->merchantId = $merchantId;
        $this->sharedSecret = $sharedSecret;

        if ($userAgent === null) {
            $userAgent = UserAgent::createDefault();
        }
        $this->userAgent = $userAgent;
    }

    /**
     * Creates a request object.
     *
     * @param string $url     URL
     * @param string $method  HTTP method
     *
     * @return RequestInterface
     */
    public function createRequest($url, $method = 'GET', array $headers = [], $body = null)
    {
        $headers = array_merge($headers, ['User-Agent' => strval($this->userAgent)]);
        return new Request($method, $url, $headers, $body);
    }

    /**
     * Sends the request.
     *
     * @param RequestInterface $request Request to send
     * @param string[] $options Request options
     *
     * @throws ConnectorException If the API returned an error response
     * @throws RequestException   When an error is encountered
     * @throws \LogicException    When the adapter does not populate a response
     *
     * @return ResponseInterface
     */
    public function send(RequestInterface $request, array $options = [])
    {
        $options['auth'] = [$this->merchantId, $this->sharedSecret, 'basic'];

        try {
            return $this->client->send($request, $options);
        } catch (RequestException $e) {
            if (!$e->hasResponse()) {
                throw $e;
            }

            $response = $e->getResponse();

            if (!in_array('application/json', $response->getHeader('Content-Type'))) {
                throw $e;
            }

            $data = \json_decode($response->getBody(), true);

            if (!is_array($data) || !array_key_exists('error_code', $data)) {
                throw $e;
            }

            throw new ConnectorException($data, $e);
        }
    }

    /**
     * Gets the HTTP transport client.
     *
     * @return ClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Gets the user agent.
     *
     * @return UserAgentInterface
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Factory method to create a connector instance.
     *
     * @param string             $merchantId   Merchant ID
     * @param string             $sharedSecret Shared secret
     * @param string             $baseUrl      Base URL for HTTP requests
     * @param UserAgentInterface $userAgent    HTTP user agent to identify the client
     *
     * @return self
     */
    public static function create(
        $merchantId,
        $sharedSecret,
        $baseUrl = self::EU_BASE_URL,
        UserAgentInterface $userAgent = null
    ) {
        $client = new Client(['base_uri' => $baseUrl]);

        return new static($client, $merchantId, $sharedSecret, $userAgent);
    }
}
