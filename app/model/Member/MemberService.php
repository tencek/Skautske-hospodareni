<?php

declare(strict_types=1);

namespace Model;

use Skautis\Wsdl\WebServiceInterface;
use function array_key_exists;
use function asort;
use function natcasesort;

class MemberService
{
    /** @var WebServiceInterface @todo Create anticorruption layer */
    private $organizationWebservice;

    public function __construct(WebServiceInterface $organizationWebservice)
    {
        $this->organizationWebservice = $organizationWebservice;
    }


    /**
     * @param mixed[] $participants
     * @return string[]
     */
    public function getAll(int $unitId, bool $onlyDirectMember, array $participants) : array
    {
        $all = $this->organizationWebservice->PersonAll(['ID_Unit' => $unitId, 'OnlyDirectMember' => $onlyDirectMember]);
        $ret = [];

        if (empty($participants)) {
            foreach ($all as $people) {
                $ret[$people->ID] = $people->DisplayName;
            }
        } else { //odstranení jiz oznacených
            $check = [];
            foreach ($participants as $p) {
                $check[$p->ID_Person] = true;
            }
            foreach ($all as $p) {
                if (array_key_exists($p->ID, $check)) {
                    continue;
                }

                $ret[$p->ID] = $p->DisplayName;
            }
        }
        natcasesort($ret);
        return $ret;
    }

    /**
     * vytvoří pole jmen s ID pro combobox
     * @param bool $OnlyDirectMember - vybrat pouze z aktuální jednotky?
     * @return string[]
     */
    public function getCombobox(bool $OnlyDirectMember = false, ?int $ageLimit = null) : array
    {
        return $this->getPairs($this->organizationWebservice->PersonAll(['OnlyDirectMember' => $OnlyDirectMember]), $ageLimit);
    }

    /**
     * vrací pole osob ID => jméno
     * @param \stdClass[]|\stdClass $data - vráceno z PersonAll
     * @return string[]
     */
    private function getPairs($data, ?int $ageLimit = null) : array
    {
        if ($data instanceof \stdClass) {
            $data = [$data];
        }
        $res = [];
        $now = new \DateTime();
        foreach ($data as $p) {
            if ($ageLimit !== null) {
                if ($p->Birthday === null) {
                    continue;
                }
                $birth    = new \DateTime($p->Birthday);
                $interval = $now->diff($birth);
                $diff     = $interval->format('%y');
                if ($diff < $ageLimit) {
                    continue;
                }
            }
            $res[$p->ID] = $p->DisplayName;
        }
        asort($res);
        return $res;
    }
}
