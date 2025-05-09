<?php

namespace AS2;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\ASN1;
use phpseclib3\File\X509;

/**
 * TODO: Implement pure methods without "openssl_pkcs7"
 * check openssl_pkcs7 doesn't work with binary data.
 */
class CryptoHelper
{
    /**
     * Extract the message integrity check (MIC) from the digital signature.
     *
     * @param  MimePart|string  $payload
     * @param  string  $algo  Default is SHA256
     * @param  bool  $includeHeaders
     *
     * @return string
     */
    public static function calculateMIC(MimePart|string $payload, $algo = 'sha256', $includeHeaders = true)
    {
        $algo = $algo ?? 'sha256';
        $digestAlgorithm = str_replace('-', '', strtolower($algo));
        if (!\in_array($digestAlgorithm, hash_algos(), true)) {
            throw new \InvalidArgumentException(sprintf('(MIC) Invalid hash algorithm `%s`.', $digestAlgorithm));
        }

        if (!$payload instanceof MimePart) {
            $payload = MimePart::fromString($payload);
        }

        // file_put_contents('calMID.log',$payload . "\n");
        $digest = base64_encode(
            hash(
                $digestAlgorithm,
                $includeHeaders ? $payload . "\n" : $payload->getBodyString(),
                true
            )
        );

        return $digest . ', ' . $algo;
    }

    /**
     * Sign data which contains mime headers.
     *
     * @param  MimePart|string  $data
     * @param  string  $publicKey
     * @param  array|string  $privateKey
     * @param  array  $headers
     * @param  array  $micAlgo
     *
     * @return MimePart
     */
    public static function signPure($data, $publicKey, $privateKey = null, $headers = [], $micAlgo = null)
    {
        if (!\is_array($privateKey)) {
            $privateKey = [$privateKey, false];
        }

        $singAlg = 'sha256';

        /** @var RSA\PrivateKey $private */
        // $private = RSA::load($privateKey[0], $privateKey[1])
        //     ->withPadding(RSA::SIGNATURE_PKCS1)
        //     ->withHash($singAlg)
        //     ->withMGFHash($singAlg);

        $signature = $private->sign($data);

        $certInfo = self::loadX509($publicKey);

        // dd(
        //     array_keys($certInfo['tbsCertificate'])
        // );

        $digestAlgorithm = ASN1Helper::OID_SHA256;

        $payload = ASN1Helper::encode(
            [
                'contentType' => ASN1Helper::OID_SIGNED_DATA,
                'content' => [
                    'version' => 1,
                    'digestAlgorithms' => [
                        [
                            'algorithm' => $digestAlgorithm,
                            'parameters' => null,
                        ],
                    ],
                    'contentInfo' => [
                        'contentType' => ASN1Helper::OID_DATA,
                    ],
                    'certificates' => [
                        $certInfo,
                    ],
                    // 'crls' => [],
                    'signers' => [
                        [
                            'version' => '1',
                            'sid' => [
                                'issuerAndSerialNumber' => [
                                    'issuer' => $certInfo['tbsCertificate']['issuer'],
                                    'serialNumber' => $certInfo['tbsCertificate']['serialNumber'],
                                ],
                            ],
                            'digestAlgorithm' => [
                                'algorithm' => $digestAlgorithm,
                                'parameters' => null,
                            ],
                            // 'signedAttrs' => [
                            //     [
                            //         'type' => ASN1Helper::OID_PKCS9_CONTENT_TYPE,
                            //         'value' => [
                            //             [
                            //                 'objectIdentifier' => ASN1Helper::OID_DATA,
                            //             ],
                            //         ],
                            //     ],
                            //     [
                            //         'type' => ASN1Helper::OID_PKCS9_SIGNING_TIME,
                            //         'value' => [
                            //             [
                            //                 // RFC 2822
                            //                 'utcTime' => date('r'),
                            //             ],
                            //         ],
                            //     ],
                            //     [
                            //         'type' => ASN1Helper::OID_PKCS9_MESSAGE_DIGEST,
                            //         'value' => [
                            //             [
                            //                 'octetString' => hex2bin('C87DBCDCBD05AF3F07738633AA1A5CADFED3A7674F3626F9407770ECE490D56A'),
                            //             ],
                            //         ],
                            //     ],
                            //     // [
                            //     //     'type' => ASN1Helper::OID_PKCS9_SMIME_CAPABILITIES,
                            //     //     'value' => [
                            //     //         // [
                            //     //         //     "objectIdentifier" => ASN1Helper::OID_AES_256_CBC,
                            //     //         // ],
                            //     //         // [
                            //     //         //     "objectIdentifier" => ASN1Helper::OID_AES_192_CBC,
                            //     //         // ],
                            //     //         // [
                            //     //         //     "objectIdentifier" => ASN1Helper::OID_AES_128_CBC,
                            //     //         // ],
                            //     //         // [
                            //     //         //     "objectIdentifier" => ASN1Helper::OID_DES_EDE3_CBC,
                            //     //         // ],
                            //     //         // [
                            //     //         //     "objectIdentifier" => ASN1Helper::OID_DES_CBC,
                            //     //         // ],
                            //     //         // [
                            //     //         //     "objectIdentifier" => ASN1Helper::OID_RC2_CBC,
                            //     //         //     // "integer" => 80,
                            //     //         // ],
                            //     //         // [
                            //     //         //     "objectIdentifier" => ASN1Helper::OID_RC2_CBC,
                            //     //         //     "integer" => 40,
                            //     //         // ],
                            //     //         // [
                            //     //         //     "objectIdentifier" => ASN1Helper::OID_RC2_CBC,
                            //     //         //     "integer" => 28,
                            //     //         // ],
                            //     //     ],
                            //     // ],
                            // ],
                            'signatureAlgorithm' => [
                                'algorithm' => ASN1Helper::OID_RSA_ENCRYPTION,
                                'parameters' => null,
                            ],
                            'signature' => $signature,
                            // 'unsignedAttrs' => []
                        ],
                    ],
                ],
            ],
            ASN1Helper::getSignedDataMap()
        );

        $payload = Utils::encodeBase64($payload);

        $signatureMime = new MimePart([
            'Content-Transfer-Encoding' => 'base64',
            'Content-Disposition' => 'attachment; filename="smime.p7s"',
            'Content-Type' => 'application/pkcs7-signature; name=smime.p7s; smime-type=signed-data',
        ], $payload);

        $boundary = '=_' . sha1(uniqid('', true));

        $result = new MimePart([
            'MIME-Version' => '1.0',
            'Content-type' => 'multipart/signed; protocol="application/pkcs7-signature"; micalg=' . $singAlg . '; boundary="----' . $boundary . '"',
        ] + $headers);
        $result->addPart($data);
        $result->addPart($signatureMime);

        // echo $result;
        // exit;

        return $result;
    }

    /**
     * TODO: extra certs.
     *
     * @param  MimePart|string  $payload
     */
    public static function verifyPure($payload, $publicKey, $extraCerts = []): bool
    {
        if (\is_string($payload)) {
            $payload = MimePart::fromString($payload);
        }

        $data = '';
        $signature = false;

        foreach ($payload->getParts() as $part) {
            if ($part->isPkc7Signature()) {
                $signature = $part->getBody();
            } else {
                $data = $part->toString();
            }
        }

        if (!$signature) {
            return false;
        }

        $verified = true;

        $signedData = ASN1Helper::decode(Utils::normalizeBase64($signature), ASN1Helper::getSignedDataMap());
        if ($signedData['contentType'] === ASN1Helper::OID_SIGNED_DATA) {
            /** @var RSA\PublicKey $public */
            // $public = PublicKeyLoader::load($publicKey)->withPadding(RSA::SIGNATURE_PKCS1);
            $public = PublicKeyLoader::load($publicKey);
            foreach ($signedData['content']['signers'] as $signer) {
                $verified &= $public->verify($data, $signer['signature']);
            }
        }

        return (bool) $verified;
    }

    /**
     * Sign data which contains mime headers.
     *
     * @param  MimePart|string  $data
     * @param  resource|string  $cert
     * @param  array|string  $privateKey
     * @param  array  $headers
     * @param  string  $micAlgo
     *
     * @return MimePart
     */
    public static function sign($data, $cert, $privateKey = null, $headers = [], $micAlgo = null)
    {
        $dataFile = self::getTempFilename($data . "\r\n");
        $tempFile = self::getTempFilename();

        $flags = PKCS7_DETACHED;

        // file_put_contents('temp_before_signed.log', $data);
        if (!openssl_pkcs7_sign($dataFile, $tempFile, $cert, $privateKey, $headers, $flags)) {
            throw new \RuntimeException(sprintf('Failed to sign S/Mime message. Error: "%s".', openssl_error_string()));
        }

        // Unfortunately, openssl_pkcs7_sign does not provide a direct 
        // way to control the formatting of the MIME output. However, 
        // you can work around this issue by post-processing 
        // the output to remove unnecessary line breaks.   


        $tempContent = file_get_contents($tempFile);
        // file_put_contents('temp_signed.log', $tempContent);
        // After signed, I won't tamper body any more, even with MimePart::toString() function.
        
        $payload = MimePart::fromString($tempContent, true);

        if ($micAlgo) {
            $contentType = $payload->getHeaderLine('content-type');
            $contentType = preg_replace('/micalg=(.+);/i', 'micalg="' . $micAlgo . '";', $contentType);

            /** @var MimePart $payload */
            $payload = $payload->withHeader('Content-Type', $contentType);
        }

        // replace x-pkcs7-signature > pkcs7-signature
        // foreach ($payload->getParts() as $key => $part) {
        //     if ($part->isPkc7Signature()) {
        //         $payload->removePart($key);
        //         $payload->addPart(
        //             $part->withoutRaw()->withHeader(
        //                 'Content-Type',
        //                 'application/pkcs7-signature; name=smime.p7s; smime-type=signed-data'
        //             )
        //         );
        //     }
        // }

        return $payload;
    }

    /**
     * @param  MimePart|string  $data
     * @param  array|null  $caInfo  Information about the trusted CA certificates to use in the verification process
     * @param  array  $rootCerts
     *
     * @return bool
     */
    public static function verify($data, $publicCert, $rootCerts = [])
    {
        if ($data instanceof MimePart) {
            $temp = MimePart::createIfBinaryPart($data);
            if ($temp !== null) {
                $data = $temp;
            }
            // file_put_contents('for_verify.raw', $data);
            $data = self::getTempFilename((string) $data);
        }
        $publicCertFile = self::getTempFilename($publicCert);
        // file_put_contents('public_cert.log', $publicCert);
        // if (!empty($caInfo)) {
        //     foreach ((array) $caInfo as $cert) {
        //         $rootCerts[] = self::getTempFilename($cert);
        //     }
        // }

        $flags = PKCS7_BINARY | PKCS7_NOSIGS;

        // if (empty($rootCerts)) {
        $flags |= PKCS7_NOVERIFY;
        // }

        // $outFile = self::getTempFilename();

        return openssl_pkcs7_verify(
            $data,
            $flags, // Flags
            null, // No specific output file
            [], // CA certificates - empty as we're using the provided cert
            $publicCertFile
        );

        // return openssl_pkcs7_verify($data, $flags, $outFile, $rootCerts) === true;
    }

    /**
     * @param  MimePart|string  $data
     * @param  array|string  $cert
     * @param  int|string  $cipher
     *
     * @return MimePart
     */
    public static function encrypt($data, $cert, $cipher = OPENSSL_CIPHER_AES_128_CBC)
    {
        $dataFile = self::getTempFilename((string) $data);

        if (\is_string($cipher)) {
            $cipher = strtoupper($cipher);
            $cipher = str_replace('-', '_', $cipher);
            if (\defined('OPENSSL_CIPHER_' . $cipher)) {
                $cipher = \constant('OPENSSL_CIPHER_' . $cipher);
            }
        }

        // file_put_contents('before encrypt.log',$data);
        $tempFile = self::getTempFilename();
        if (!openssl_pkcs7_encrypt($dataFile, $tempFile, $cert, [], PKCS7_BINARY, $cipher)) {
            throw new \RuntimeException(sprintf(
                'Failed to encrypt S/Mime message. Error: "%s".',
                openssl_error_string()
            ));
        }

        return MimePart::fromString(file_get_contents($tempFile), false);
    }

    /**
     * @param  MimePart|string  $data
     *
     * @return MimePart
     */
    public static function decrypt($data, $cert, $privateKey = null):MimePart
    {
        if ($data instanceof MimePart) {
            $data = self::getTempFilename((string) $data);
        }

        $temp = self::getTempFilename();
        // if (! openssl_pkcs7_decrypt($data, $temp, $cert, $privateKey)) {
        //     throw new \RuntimeException(sprintf('Failed to decrypt S/Mime message. Error: "%s".',
        //         openssl_error_string()));
        // }
        if (
            !openssl_cms_decrypt(
                $data,
                $temp,
                $cert,
                $privateKey,
                OPENSSL_ENCODING_DER
            )
        ) {
            throw new \RuntimeException(sprintf(
                'Failed to decrypt S/Mime message. Error: "%s".',
                openssl_error_string()
            ));
        }
        $raw_decrypted = file_get_contents($temp);
        // file_put_contents('raw_decrypted', $raw_decrypted);
        return MimePart::fromString($raw_decrypted);
    }

    /**
     * Compress data.
     *
     * @param  MimePart|string  $data
     * @param  string  $encoding
     *
     * @return MimePart
     */
    public static function compress($data, $encoding = null)
    {
        if ($data instanceof MimePart) {
            $content = $data->toString();
        } else {
            $content = is_file($data) ? file_get_contents($data) : $data;
        }

        if (empty($encoding)) {
            $encoding = MimePart::ENCODING_BASE64;
        }

        $headers = [
            'Content-Type' => MimePart::TYPE_PKCS7_MIME . '; name="smime.p7z"; smime-type=' . MimePart::SMIME_TYPE_COMPRESSED,
            'Content-Description' => 'S/MIME Compressed Message',
            'Content-Disposition' => 'attachment; filename="smime.p7z"',
            'Content-Transfer-Encoding' => $encoding,
        ];

        $content = ASN1Helper::encode(
            [
                'contentType' => ASN1Helper::OID_COMPRESSED_DATA,
                'content' => [
                    'version' => 0,
                    'compression' => [
                        'algorithm' => ASN1Helper::OID_ALG_ZLIB,
                    ],
                    'payload' => [
                        'contentType' => ASN1Helper::OID_DATA,
                        'content' => base64_encode(gzcompress($content)),
                    ],
                ],
            ],
            ASN1Helper::getContentInfoMap(),
            [
                'content' => ASN1Helper::getCompressedDataMap(),
            ]
        );

        if ($encoding === MimePart::ENCODING_BASE64) {
            $content = Utils::encodeBase64($content);
        }

        return new MimePart($headers, $content);
    }

    /**
     * Decompress data.
     *
     * @param  MimePart|string  $data
     *
     * @return MimePart
     */
    public static function decompress($data)
    {
        if ($data instanceof MimePart) {
            $data = $data->getBodyString();
        }

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $data = Utils::normalizeBase64($data);

        $payload = ASN1Helper::decode($data, ASN1Helper::getContentInfoMap());

        if ($payload['contentType'] === ASN1Helper::OID_COMPRESSED_DATA) {
            $compressed = ASN1Helper::decode($payload['content'], ASN1Helper::getCompressedDataMap());
            if (empty($compressed['compression']) || empty($compressed['payload'])) {
                throw new \RuntimeException('Invalid compressed data.');
            }
            $algorithm = $compressed['compression']['algorithm'];
            if ($algorithm === ASN1Helper::OID_ALG_ZLIB) {
                $data = (string) Utils::normalizeBase64($compressed['payload']['content']);
                $data = gzuncompress($data);
            }
        }

        return MimePart::fromString($data);
    }

    /**
     * Create a temporary file into temporary directory.
     *
     * @param  string  $content
     *
     * @return string The temporary file generated
     */
    public static function getTempFilename($content = null)
    {
        $dir = sys_get_temp_dir();
        $filename = tempnam($dir, 'phpas2_');
        if ($content) {
            file_put_contents($filename, $content);
        }

        return $filename;
    }

    private static function loadX509($cert)
    {
        $x509 = new X509();
        $certInfo = $x509->loadX509($cert);

        foreach ($certInfo['tbsCertificate']['extensions'] as &$extension) {
            if ($extension['extnId'] === 'id-ce-keyUsage') {
                $extension['extnValue'] = ASN1::encodeDER($extension['extnValue'], ASN1\Maps\KeyUsage::MAP);
            }
        }
        unset($extension);

        $certInfo['tbsCertificate']['signature'] = [
            'algorithm' => ASN1Helper::OID_SHA256,
        ];

        $certInfo['signatureAlgorithm'] = [
            'algorithm' => ASN1Helper::OID_SHA256_WITH_RSA_ENCRYPTION,
        ];
        $certInfo['signature'] = 'a';

        // $certInfo['tbsCertificate']['subjectPublicKeyInfo'] = [
        //     'algorithm' => [
        //         'algorithm' => ASN1Helper::OID_RSA_ENCRYPTION,
        //     ],
        //     'subjectPublicKey' => 'tsasdasdsadadasdasdt',
        // ];

        // $certInfo['tbsCertificate']['subjectPublicKeyInfo']['subjectPublicKey'] = "test";

        // $certInfo['signatureAlgorithm']['parameters'] = null;

        // dd($certInfo['tbsCertificate']['subjectPublicKeyInfo']);

        // $certInfo = $x509->getCurrentCert();

        // $certInfo['tbsCertificate']['signature']['parameters'] = null;
        // $certInfo['tbsCertificate']['subjectPublicKeyInfo']['algorithm']['parameters'] = null;
        // // // TODO: phpspeclib bug ?
        // $certInfo['tbsCertificate']['extensions'][0]['extnValue'] = hex2bin('030205A0');
        // $certInfo['signatureAlgorithm']['parameters'] = null;
        // $certInfo['signature'] = null;

        // dd($certInfo['signature']);

        return $certInfo;
    }
}
