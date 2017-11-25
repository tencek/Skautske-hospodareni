<?php

namespace Model\Cashbook\Handlers\Cashbook;

use Model\Cashbook\Commands\Cashbook\LockChit;
use Model\Cashbook\Repositories\ICashbookRepository;

class LockChitHandler
{

    /** @var ICashbookRepository */
    private $cashbooks;

    public function __construct(ICashbookRepository $cashbooks)
    {
        $this->cashbooks = $cashbooks;
    }

    public function handle(LockChit $command): void
    {
        $cashbook = $this->cashbooks->find($command->getCashbookId());

        $cashbook->lockChit($command->getChitId(), $command->getUserId());

        $this->cashbooks->save($cashbook);
    }

}
