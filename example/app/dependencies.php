<?php

use App\Repositories\MessageRepository;
use App\Repositories\PartnerRepository;
use AS2\Management;
use Monolog\Logger;

function _newLogger($settings): Logger
{
    $logger = new Logger('app');
    if (! empty($settings['logHandlers'])) {
        foreach ($settings['logHandlers'] as $handler) {
            $logger->pushHandler($handler);
        }
    }

    return $logger;
}

function _newManager(Logger $log,$settings): Management
{
    $manager = new Management($settings['management']);
    $manager->setLogger($log);
    return $manager;
}


function _getContainerArr($c)
{
    $logger = _newLogger($c);
    $partners =  require __DIR__ . '/../config/partners.php';
    return [
        'MessageRepository' =>  new MessageRepository([
            'path' => $c['storage']['path'] . '/messages',
        ]),
        'PartnerRepository' =>  new PartnerRepository($partners,$logger),
        'Logger' => $logger,
        'manager' => _newManager($logger,$c)
    ];
}
