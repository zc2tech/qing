<?php


namespace App\Repositories;

use App\Models\Partner;
use AS2\PartnerRepositoryInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PartnerRepository implements PartnerRepositoryInterface
{
    /**
     * @var array
     */
    private $partners;
    protected LoggerInterface $logger;

    public function __construct(array $partners, LoggerInterface $logger)
    {
        $this->partners = $partners;
        $this->logger = $logger;
    }

    /**
     * @param  string  $id
     *
     * @return Partner
     */
    public function findPartnerById($id)
    {
        foreach ($this->partners as $partner) {
            if ($id === $partner['id']) {
                return new Partner($partner);
            }
        }

        throw new \RuntimeException(sprintf('Unknown partner `%s`.', $id));
    }
}
