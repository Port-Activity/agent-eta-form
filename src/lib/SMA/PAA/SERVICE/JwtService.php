<?php
namespace SMA\PAA\SERVICE;

use Firebase\JWT\JWT;

class JwtService
{
    public function __construct(string $privateKey, string $publicKey, int $time = null)
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
        $this->time = $time;
    }
    public function encode($data, $expiresInSeconds)
    {
        $ts = $this->time ?: time();
        $payload = array(
            "iat" => $ts,
            "nbf" => $ts,
            "exp" => $ts + $expiresInSeconds,
            "data" => $data
        );

        $jwt = JWT::encode($payload, $this->privateKey, 'RS256');
        return $jwt;
    }
    public function decodeAndVerifyValidity($jwt)
    {
        $ts = $this->time ?: time();
        $decoded = JWT::decode($jwt, $this->publicKey, array('RS256'));
        return (array)$decoded->data;
    }
}
