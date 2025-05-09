<?php

use AS2\PartnerInterface;

$resources = __DIR__.'/../resources';

// local certificates
// openssl_pkcs12_read(file_get_contents($resources.'/phpas2_cert.p12'), $local_dev, 'test');

$local_dev = [
    'cert' => file_get_contents($resources.'/phpas2_certificate.pem'),
    'pkey' => file_get_contents($resources.'/phpas2_private.key'),
];
// $local_zc2tech = [
//     'cert' => file_get_contents($resources.'/zc2tech.xyz.pem'),
//     'pkey' => file_get_contents($resources.'/zc2tech.xyz.key'),
// ];

// mendelson key3
openssl_pkcs12_read(file_get_contents($resources.'/key3.pfx'), $key3, 'test');

// $local_dev = [
//     'cert' => file_get_contents($resources.'/phpas2.cer'),
//     'pkey' => file_get_contents($resources.'/dpdns.key'),
// ];

$prod_t15 = file_get_contents($resources.'/prod.t15.julian.com.cer');
$test_t15 = file_get_contents($resources.'/test.t15.julian.com1.cer');
return [
    [
        'id' => 'testacig.ariba.juilian.com',
        'email' => 't15_prod@julian.com',
        'target_url' => 'http://localhost:8080/as2/HttpReceiver',
        'certificate' => $prod_t15,
        'private_key' => '',
        // 'private_key_pass_phrase' => 'password',
        // 'content_type' => 'application/edi-x12',
        'content_type' => 'Text/Plain',
        'compression' => false,
        'signature_algorithm' => 'sha256',
        'signature_algorithm_required' => false,
        'encryption_algorithm' => '3des',
        'content_transfer_encoding' => 'base64',
        'mdn_mode' => PartnerInterface::MDN_MODE_SYNC,
        //'mdn_options' => 'signed-receipt-protocol=optional, pkcs7-signature; signed-receipt-micalg=optional, sha256',
    ],
    [
        'id' => 'ZZARIBATEST',
        'email' => 'aribatest@example.com',
        'target_url' => 'https://testacig.ariba.com/as2/as2',
        'verify_ssl_site' => $resources.'/testacig.ariba.com.cer',
        'certificate' => file_get_contents($resources.'/aribacloudintegration-test.ariba.com.cer'),
        'private_key' => '',
        'auth' => 'Basic',
        'auth_user' => 'P',
        'auth_password' => '',
        // 'private_key_pass_phrase' => 'password',
        // 'content_type' => 'application/edi-x12',
        'content_type' => 'application/x12',
        'compression' => false,
        'signature_algorithm' => 'sha256',
        'signature_algorithm_required' => false,
        'encryption_algorithm' => '3des',
        'content_transfer_encoding' => 'base64',
        'mdn_mode' => PartnerInterface::MDN_MODE_SYNC,
        // 'mdn_options' => 'signed-receipt-protocol=optional, pkcs7-signature; signed-receipt-micalg=optional, sha256',
    ],

    [
        'id' => 'test.t15',
        'email' => 't15_test@julian.com',
        'target_url' => 'http://localhost:11080/as2/HttpReceiver',
        'certificate' => $test_t15,
        'private_key' => '',
        // 'private_key_pass_phrase' => 'password',
        // 'content_type' => 'application/edi-x12',
        'content_type' => 'Text/Plain',
        'compression' => false,
        'signature_algorithm' => 'sha256',
        'signature_algorithm_required' => false,
        'encryption_algorithm' => '3des',
        'content_transfer_encoding' => 'base64',
        'mdn_mode' => PartnerInterface::MDN_MODE_SYNC,
        'mdn_options' => 'signed-receipt-protocol=optional, pkcs7-signature; signed-receipt-micalg=optional, sha256',
    ],
    [
        'id' => 'phpas2_win',
        'email' => 'phpas2@example.com',
        'target_url' => 'http://127.0.0.1:8000',
        'certificate' => $local_dev['cert'] ?: null,
        'private_key' => $local_dev['pkey'] ?: null,
        'private_key_pass_phrase' => 'test',
        // 'content_type' => 'application/edi-x12',
        'content_type' => 'application/EDI-Consent',
        'compression' => true,
        'signature_algorithm' => 'sha256',
        'encryption_algorithm' => '3des',
        'mdn_mode' => PartnerInterface::MDN_MODE_SYNC,
        //'mdn_options' => 'signed-receipt-protocol=optional, pkcs7-signature; signed-receipt-micalg=optional, sha256',
    ],
    [
        'id' => 'zc2tech_gen3',
        'email' => 'zc2tech_gen3@example.com',
        'target_url' => 'https://zc2tech.xyz:8043',
        // 'certificate' => $local_zc2tech['cert'] ?: null,
        // 'private_key' => $local_zc2tech['pkey'] ?: null,
        'private_key_pass_phrase' => 'test',
        'content_type' => 'application/edi-x12',
        //'content_type' => 'application/EDI-Consent',
        'compression' => true,
        'signature_algorithm' => 'sha256',
        'encryption_algorithm' => '3des',
        'mdn_mode' => PartnerInterface::MDN_MODE_SYNC,
        'mdn_options' => 'signed-receipt-protocol=optional, pkcs7-signature; signed-receipt-micalg=optional, sha256',
    ],
];
