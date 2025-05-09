# DOCUMENTATION

Please have a look at an example application based on Slim4 framework.
If you need to deploy to http server like nginx,apache, 
Please refer to Slim official document.

You can also create your own classes.

- Implement MessageRepository class based on \AS2\MessageRepositoryInterface
- Implement Message class based on \AS2\MessageInterface
- Implement PartnerRepository class based on \AS2\PartnerRepositoryInterface
- Implement Partner class based on \AS2\PartnerInterface

### Example Receive AS2 Message
```php
$manager = new \AS2\Management();

/** @var /AS2/MessageRepositoryInterface $messageRepository */
$messageRepository = new App\Repositories\MessageRepository();

/** @var /AS2/PartnerRepositoryInterface $partnerRepository */
$partnerRepository = new App\Repositories\PartnerRepository();

$server = new \AS2\Server($manager, $partnerRepository, $messageRepository);

/** @var \GuzzleHttp\Psr7\Response $response */
$response = $server->excecute();
```

### Example Send AS2 Message
```php

$manager = new \AS2\Management();

//loading conf files
$partners          = require __DIR__ . '/config/partners.php';

/** @var /AS2/MessageRepositoryInterface $messageRepository */
$messageRepository = new App\Repositories\MessageRepository(['path' => $storagePath . DIRECTORY_SEPARATOR . 'sent']);

/** @var /AS2/PartnerRepositoryInterface $partnerRepository */
$partnerRepository = new App\Repositories\PartnerRepository($partners);

// Init partners
$sender = $partnerRepository->findPartnerById('A');
$receiver = $partnerRepository->findPartnerById('B');

// Generate new message ID
$messageId = \AS2\Utils::generateMessageID($sender);
$rawMessage = '
Content-type: Application/EDI-X12
Content-disposition: attachment; filename=payload
Content-id: <test@test.com>

ISA*00~';

// Init new Message
$message = $messageRepository->createMessage();
$message->setMessageId($messageId);
$message->setSender($sender);
$message->setReceiver($receiver);

$payload = $manager->buildMessage($message, $rawMessage);
if ($response = $manager->sendMessage($message, $payload)){
    echo "OK \n";
}

$messageRepository->saveMessage($message);

```
