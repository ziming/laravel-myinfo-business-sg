<?php

namespace Ziming\LaravelMyinfoBusinessSg\Services;

use Illuminate\Support\Facades\Log;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\Compression\Deflate;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;

/**
 * @internal
 */
final class MyinfoBusinessSecurityService
{
    /**
     * Verify JWS.
     *
     * @param  string  $accessToken
     * @return mixed|null
     * @throws \Exception
     */
    public static function verifyJWS(string $accessToken)
    {
        $algorithmManager = new AlgorithmManager([new RS256]);
        $jwk = JWKFactory::createFromCertificateFile(config('laravel-myinfo-business-sg.public_cert_path'));
        $jwsVerifier = new JWSVerifier($algorithmManager);
        $serializerManager = new JWSSerializerManager([new CompactSerializer]);

        $jws = $serializerManager->unserialize($accessToken);
        $verified = $jwsVerifier->verifyWithKey($jws, $jwk, 0);

        return $verified ? json_decode($jws->getPayload(), true) : null;
    }

    /**
     * Generate Authorization Header.
     *
     * @param  string  $url
     * @param  array  $params
     * @param  string  $method
     * @param  string  $contentType
     * @param  string  $authType
     * @param  string  $appId
     * @param  string  $passphrase
     * @param  string  $realm
     * @return string
     * @throws \Exception
     */
    public static function generateAuthorizationHeader(
        string $url,
        array $params,
        string $method,
        string $contentType,
        string $authType,
        string $appId,
        string $passphrase,
        string $realm
    ): string {
        if ($authType === 'L2') {
            return self::generateSHA256withRSAHeader($url, $params, $method, $contentType, $appId, $passphrase, $realm);
        }

        return '';
    }

    /**
     * Generate SHA256 with RSA Header.
     *
     * @param  string  $url
     * @param  array  $params
     * @param  string  $method
     * @param  string  $contentType
     * @param  string  $appId
     * @param  string  $passphrase
     * @param  string  $realm
     * @return string
     * @throws \Exception
     */
    private static function generateSHA256withRSAHeader(
        string $url,
        array $params,
        string $method,
        string $contentType,
        string $appId,
        string $passphrase,
        string $realm
    ) {
        $nonce = random_int(PHP_INT_MIN, PHP_INT_MAX);

        $timestamp = (int) round(microtime(true) * 1000);

        $defaultApexHeaders = [
            'apex_l2_eg_app_id' => $appId,
            'apex_l2_eg_nonce' => $nonce,
            'apex_l2_eg_signature_method' => 'SHA256withRSA',
            'apex_l2_eg_timestamp' => $timestamp,
            'apex_l2_eg_version' => '1.0',
        ];

        if ($method === 'POST' && $contentType !== 'application/x-www-form-urlencoded') {
            $params = [];
        }

        $baseParams = array_merge($defaultApexHeaders, $params);
        ksort($baseParams);

        $baseParamsStr = http_build_query($baseParams);
        $baseParamsStr = urldecode($baseParamsStr);

        $baseString = "{$method}&{$url}&{$baseParamsStr}";

        if (config('laravel-myinfo-business-sg.debug_mode')) {
            Log::debug('Base String (Pre Signing): '.$baseString);
        }

        $privateKey = openssl_pkey_get_private(config('laravel-myinfo-business-sg.private_key_path'), $passphrase);

        openssl_sign($baseString, $signature, $privateKey, 'sha256WithRSAEncryption');

        $signature = base64_encode($signature);

        $strApexHeader = 'PKI_SIGN timestamp="' . $timestamp .
            '",nonce="' . $nonce .
            '",app_id="' . $appId .
            '",signature_method="RS256"'.
            '",signature="' . $signature .
            '"';

        return $strApexHeader;
    }

    /**
     * @param  string  $personDataToken
     * @param  string  $privateKeyPath
     * @return string
     * @throws \Exception
     */
    public static function decryptJWE(string $personDataToken, string $privateKeyPath)
    {
        $jwk = JWKFactory::createFromKeyFile(
            $privateKeyPath,
            config('laravel-myinfo-business-sg.client_secret')
        );

        $serializerManager = new JWESerializerManager([
            new \Jose\Component\Encryption\Serializer\CompactSerializer,
        ]);

        $jwe = $serializerManager->unserialize($personDataToken);

        $keyEncryptionAlgorithmManager = new AlgorithmManager([new RSAOAEP]);

        $contentEncryptionAlgorithmManager = new AlgorithmManager([new A256GCM]);

        $compressionMethodManager = new CompressionMethodManager([new Deflate]);

        $jweDecrypter = new JWEDecrypter(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compressionMethodManager
        );

        $recipient = 0;

        $jweDecrypter->decryptUsingKey($jwe, $jwk, $recipient);

        $payload = $jwe->getPayload();

        $payload = str_replace('"', '', $payload);

        return $payload;
    }
}
