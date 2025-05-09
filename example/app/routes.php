<?php

use AS2\Server;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

return function (App $app) {
    /**
     * AS2 Receiver
     */
    $app->post(
        '/as2/HttpReceiver',
        function (Request $request, Response $response, Server $server) {
            // $server = new Server(
            //     $manager,
            //     $PartnerRepository,
            //     $MessageRepository
            // );

            // $message = file_get_contents(__DIR__ . '/tmp/phpas2_aXFQKQ');
            // $payload = \AS2\Utils::parseMessage($message);
            // $serverRequest = new ServerRequest(
            //     'POST',
            //     'http:://localhost',
            //     $payload['headers'],
            //     $payload['body'],
            //     '1.1',
            //     [
            //         'REMOTE_ADDR' => '127.0.0.1'
            //     ]
            // );
            // return $server->execute($serverRequest);
    
            return $server->execute($request);

            // foreach($result->getHeaders() as $name => $values) {
            //     foreach ($values as $value) {
            //         @header(sprintf('%s: %s', $name, $value), false);
            //     }
            // }
            //
            // return $response->withBody($response->getBody());
        }
    );
    $app->get('/hello/{name}', function ($name, Response $response) {
        $response->getBody()->write('Hello ' . $name);
        return $response;
    });
};
