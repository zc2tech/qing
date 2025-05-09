<?php
require __DIR__ . '\..\vendor\autoload.php';

use AS2\CryptoHelper;

$infile = 'as2_raw_message.raw';
$outfile = 'decrypted.txt';

// The certification stuff
$public = file_get_contents('C:\SAPDevelop\PHP\phpas2\example\resources\phpas2_certificate.pem');
$private = array(file_get_contents('C:\SAPDevelop\PHP\phpas2\example\resources\phpas2_private.key'), "test");

$data = "as2_raw_message.raw";
$payload = CryptoHelper::decrypt(
    $data,
    $public,
    $private
);

echo $payload;

// echo phpinfo();