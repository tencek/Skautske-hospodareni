<?php

declare(strict_types=1);

namespace Model\Google\ReadModel\Queries;

use Model\Common\UnitId;
use Model\Google\ReadModel\QueryHandlers\UnitOAuthListQueryHandler;

/** @see UnitOAuthListQueryHandler */
final class UnitOAuthListQuery
{
    private UnitId $unitId;

    public function __construct(UnitId $unitId)
    {
        $this->unitId = $unitId;
    }

    public function getUnitId(): UnitId
    {
        return $this->unitId;
    }
}
