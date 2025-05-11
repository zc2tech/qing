# PHPAS2 is a php-based implementation of the EDIINT AS2 standard

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zc2tech/qing.svg?style=flat-square)](https://packagist.org/packages/zc2tech/qing)
[![Build Status](https://github.com/zc2tech/qing/actions/workflows/ci.yml/badge.svg)](https://github.com/zc2tech/qing)
[![Total Downloads](https://img.shields.io/packagist/dt/zc2tech/qing.svg?style=flat-square)](https://packagist.org/packages/zc2tech/qing)
[![License](https://poser.pugx.org/zc2tech/qing/license)](https://packagist.org/packages/zc2tech/qing)

This application enables you to transmit and receive AS2 messages with EDI-X12, EDIFACT, XML, or binary payloads
between trading partners.

## Requirements

* php >= 8.3
* ext-openssl
* ext-zlib

## Installation

```
composer require zc2tech/qing
```

## Usage

* [Documentation](./docs/index.md)
* [Example](./example)

Basic example

```bash
cd example

composer install

chmod +x ./bin/console

# start a server to receive messages in 8000 port
php -S 127.0.0.1:8000 ./public/index.php

# send a test message
php bin/console send-message --from mycompanyAS2 --to phpas2_win

# send a file
php bin/console send-message --from mycompanyAS2 --to phpas2_win --file /path/to/the/file 
```

This library is a fork of [tiamo/phpas2](https://github.com/tiamo/phpas2) which did not 
release updates since September 2023.
I updated code to adapt to recent php/library: php8, Slim 4, Symfony 7 .

AS2 is a transport protocol specified in [RFC 4130](http://www.ietf.org/rfc/rfc4130.txt).
AS2 version 1.1 adding compression is specified in [RFC 5402](http://www.ietf.org/rfc/rfc5402.txt).
The MDN is specified in [RFC 3798](http://www.ietf.org/rfc/rfc3798.txt).
Algorithm names are defined in [RFC 5751](https://www.ietf.org/rfc/rfc5751.txt) (S/MIME 3.2) which supersedes [RFC 3851](https://www.ietf.org/rfc/rfc3851.txt) (S/MIME 3.1);

