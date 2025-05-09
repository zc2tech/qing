<?php

namespace AS2;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\VarDumper\VarDumper;

class Server
{
    /**
     * @var Management
     */
    protected $manager;

    /**
     * @var PartnerRepositoryInterface
     */
    protected $partnerRepository;

    /**
     * @var MessageRepositoryInterface
     */
    protected $messageRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Server constructor.
     */
    public function __construct(
        Management $management,
        PartnerRepositoryInterface $partnerRepository,
        MessageRepositoryInterface $messageRepository
    ) {
        $this->manager = $management;
        $this->partnerRepository = $partnerRepository;
        $this->messageRepository = $messageRepository;
    }

    /**
     * Function receives AS2 requests from partner.
     * Checks whether it's an AS2 message or an MDN and acts accordingly.
     *
     * @return Response
     */
    public function execute(?ServerRequestInterface $request)
    {
        $responseStatus = 200;
        $responseHeaders = [];
        $responseBody = null;

        $message = null;

        try {
            if ($request->getMethod() !== 'POST') {
                return new Response(200, [], 'To submit an AS2 message, you must POST the message to this URL.');
            }

            $this->getLogger()->debug(
                sprintf(
                    'Received an HTTP POST from `%s`.',
                    isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown'
                )
            );

            foreach (['message-id', 'as2-from', 'as2-to'] as $header) {
                if (!$request->hasHeader($header)) {
                    throw new \InvalidArgumentException(sprintf('Missing required header `%s`.', $header));
                }
            }

            // $requestBody = $request->getBody()->getContents();
            // // Save raw message for debugging
            // file_put_contents('as2_raw_message.raw', $requestBody);

            // Get the message id, sender and receiver AS2 IDs
            $messageId = trim($request->getHeaderLine('message-id'), '<>');
            $senderId = $request->getHeaderLine('as2-from');
            $receiverId = $request->getHeaderLine('as2-to');
            //$this->logger->debug('origin log:', $request->getHeaders());
            // file_put_contents('origin_header.json', json_encode($request->getHeaders()));

            $this->getLogger()->debug('Check payload to see if its an AS2 Message or ASYNC MDN.');

            // Load the request header and body as a MIME Email Message
            $payload = MimePart::fromPsrMessage($request);

            // $this->getLogger()->debug("\n headers====" . $payload->getHeaders());
            // $this->getLogger()->debug("\\ body====" . $payload->getBodyString());

            // If this is an MDN, get the message ID and check if it exists
            if ($payload->isReport()) {
                $this->getLogger()->info(
                    sprintf(
                        'Asynchronous MDN received for AS2 message `%s` to organization `%s` from partner `%s`.',
                        $messageId,
                        $receiverId,
                        $senderId
                    )
                );

                // Get Original Message-Id
                $origMessageId = null;
                foreach ($payload->getParts() as $part) {
                    if ($part->getParsedHeader('content-type', 0, 0) === 'message/disposition-notification') {
                        $bodyPayload = MimePart::fromString($part->getBodyString());
                        $origMessageId = trim($bodyPayload->getParsedHeader('original-message-id', 0, 0), '<>');
                    }
                }

                $message = $this->messageRepository->findMessageById($origMessageId);
                if (!$message) {
                    throw new \RuntimeException('Unknown AS2 MDN received. Will not be processed');
                }

                // TODO: check if mdn already exists
                $this->manager->processMdn($message, $payload);
                $this->messageRepository->saveMessage($message);

                $responseBody = 'AS2 ASYNC MDN has been received';
            } else {
                // Process the received AS2 message from partner

                // Raise duplicate message error in case message already exists in the system
                $message = $this->messageRepository->findMessageById($messageId);
                if ($message) {
                    throw new \RuntimeException('An identical message has already been sent to our server');
                }

                $sender = $this->findPartner($senderId);
                $receiver = $this->findPartner($receiverId);

                // Create a new message
                $message = $this->messageRepository->createMessage();
                $message->setMessageId($messageId);
                $message->setDirection(MessageInterface::DIR_INBOUND);
                $message->setStatus(MessageInterface::STATUS_IN_PROCESS);
                $message->setSender($sender);
                $message->setReceiver($receiver);
                $message->setHeaders($payload->getHeaderLines());

                try {

                    // Process the received payload to extract the actual message from partner
                    $mimePart = $this->manager->processMessage($message, $payload);

                    $message->setPayload($mimePart);

                    // If MDN enabled than send notification
                    // Create MDN if it requested by partner
                    $mdnMode = $sender->getMdnMode();

                    if ($mdnMode && ($mdn = $this->manager->buildMdn($message))) {
                        $mdnMessageId = trim($mdn->getHeaderLine('message-id'), '<>');
                        $message->setMdnPayload($mdn->toString());
                        if ($mdnMode === PartnerInterface::MDN_MODE_SYNC) {
                            $this->getLogger()->debug(
                                sprintf(
                                    'Synchronous MDN with id `%s` sent as answer to message `%s`.',
                                    $mdnMessageId,
                                    $messageId
                                )
                            );
                            $responseHeaders = $mdn->getHeaders();
                            $responseBody = $mdn->getBodyString();
                        } else {
                            $this->getLogger()->debug(
                                sprintf(
                                    'Asynchronous MDN with id `%s` sent as answer to message `%s`.',
                                    $mdnMessageId,
                                    $messageId
                                )
                            );

                            // TODO: async, event, queue, etc.
                            $this->manager->sendMdn($message);
                        }
                    }

                    $message->setStatus(MessageInterface::STATUS_SUCCESS);
                } catch (\Exception $e) {
                    $message->setStatus(MessageInterface::STATUS_ERROR);
                    $message->setStatusMsg($e->getMessage());

                    throw $e;
                } finally {
                    $this->messageRepository->saveMessage($message);
                }
            }
        } catch (\Exception $e) {
            $this->getLogger()->critical($e->getMessage());
            if ($message !== null) {
                // TODO: check
                // Build the mdn for the message based on processing status
                $mdn = $this->manager->buildMdn($message, null, $e->getMessage());
                $responseHeaders = $mdn->getHeaders();
                $responseBody = $mdn->getBodyString();
            } else {
                $responseStatus = 500;
                $responseBody = $e->getMessage();
            }
        }

        if (empty($responseBody)) {
            $responseBody = 'AS2 message has been received';
        }
        // file_put_contents('responseBody_sent.log',$responseBody);
        return new Response($responseStatus, $responseHeaders, $responseBody);
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = $this->manager->getLogger();
        }

        if (!$this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param  string  $id
     *
     * @return PartnerInterface
     */
    protected function findPartner($id)
    {
        $partner = $this->partnerRepository->findPartnerById($id);
        if (!$partner) {
            throw new \RuntimeException(sprintf('Unknown AS2 Partner with id `%s`.', $id));
        }

        return $partner;
    }
}
