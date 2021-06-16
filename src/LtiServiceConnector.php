<?php

namespace Packback\Lti1p3;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Packback\Lti1p3\Interfaces\Cache;
use Packback\Lti1p3\Interfaces\LtiRegistrationInterface;
use Packback\Lti1p3\Interfaces\LtiServiceConnectorInterface;

class LtiServiceConnector implements LtiServiceConnectorInterface
{
    const NEXT_PAGE_REGEX = '/^Link:.*<([^>]*)>; ?rel="next"/i';

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    private $cache;
    private $registration;
    private $access_tokens = [];

    public function __construct(LtiRegistrationInterface $registration, Cache $cache = null)
    {
        $this->registration = $registration;
        $this->cache = $cache;
    }

    public function getAccessToken(array $scopes)
    {
        // Don't fetch the same key more than once.
        sort($scopes);
        $scope_key = md5(implode('|', $scopes));

        // Build up JWT to exchange for an auth token
        $client_id = $this->registration->getClientId();

        // Needs to be a unique key
        $accessTokenKey = $scope_key.'-'.$client_id;

        // Get Access Token from cache if it exists
        if ($this->cache->getAccessToken($accessTokenKey)) {
            return $this->cache->getAccessToken($accessTokenKey);
        }

        $jwt_claim = [
                "iss" => $client_id,
                "sub" => $client_id,
                "aud" => $this->registration->getAuthServer(),
                "iat" => time() - 5,
                "exp" => time() + 60,
                "jti" => 'lti-service-token' . hash('sha256', random_bytes(64))
        ];

        // Sign the JWT with our private key (given by the platform on registration)
        $jwt = JWT::encode($jwt_claim, $this->registration->getToolPrivateKey(), 'RS256', $this->registration->getKid());

        // Build auth token request headers
        $auth_request = [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $jwt,
            'scope' => implode(' ', $scopes)
        ];

        // Make request to get auth token
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $this->registration->getAuthTokenUrl());
        // curl_setopt($ch, CURLOPT_POST, 1);
        // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($auth_request));
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // $resp = curl_exec($ch);
        // $token_data = json_decode($resp, true);
        // curl_close ($ch);

        // Guzzle OAuth
        $url = $this->registration->getAuthTokenUrl();

        $this->client = new Client();

        $response = $this->client->post($url, [
            'timeout' => 10,
            'form_params' => $auth_request,
        ]);

        \Log::info($response->getBody()->__toString());

        $token_data = json_decode($response->getBody()->__toString(), true);

        // Cache access token
        $this->cache->cacheAccessToken($accessTokenKey, $token_data['access_token']);

        return $token_data['access_token'];
    }

    public function makeServiceRequest(array $scopes, $method, $url, $body = null, $contentType = 'application/json', $accept = 'application/json')
    {
        $ch = curl_init();
        $headers = [
            'Authorization: Bearer '.$this->getAccessToken($scopes),
            'Accept:'.$accept,
        ];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, strval($body));
            $headers[] = 'Content-Type: '.$contentType;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Request Error:'.curl_error($ch);
        }
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $resp_headers = substr($response, 0, $header_size);
        $resp_body = substr($response, $header_size);

        return [
            'headers' => array_filter(explode("\r\n", $resp_headers)),
            'body' => json_decode($resp_body, true),
        ];
    }
}
