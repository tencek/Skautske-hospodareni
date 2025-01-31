<?php

declare(strict_types=1);

namespace Model\Auth\Resources;

use Nette\StaticClass;

final class Education
{
    use StaticClass;

    public const TABLE = 'EV_EventEducation';

    public const ACCESS_DETAIL = [self::class, 'EV_EventEducationOther_DETAIL'];

    public const ACCESS_PARTICIPANTS = [self::class, 'EV_ParticipantEducation_ALL_EventEducation'];
    public const UPDATE_PARTICIPANT  = [self::class, 'EV_ParticipantEducation_UPDATE_EventEducation'];

    public const UPDATE_REAL_BUDGET_SPENDING = [self::class, 'GR_Statement_UPDATE_EventEducationReal'];
}
