<?php

declare(strict_types=1);

namespace Model\Event\ReadModel\QueryHandlers;

use Model\Event\ReadModel\Helpers;
use Model\Event\ReadModel\Queries\CampStates;
use Nette\Caching\Cache;
use Skautis\Wsdl\WebServiceInterface;

final class CampStatesHandler
{
    private const CACHE_KEY = 'camp_states';

    private WebServiceInterface $eventWebservice;

    private Cache $cache;

    public function __construct(WebServiceInterface $eventWebservice, Cache $cache)
    {
        $this->eventWebservice = $eventWebservice;
        $this->cache           = $cache;
    }

    /** @return string[] */
    public function __invoke(CampStates $query): array
    {
        return $this->cache->load(self::CACHE_KEY, function () {
            return Helpers::getPairs(
                $this->eventWebservice->eventCampStateAll(),
            );
        });
    }
}
