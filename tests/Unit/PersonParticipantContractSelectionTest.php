<?php

namespace Tests\Unit;

use App\Models\Person;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PersonParticipantContractSelectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_person_uses_the_new_contract_from_its_real_start_despite_a_long_old_grace_period(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 12, 0, 0, 'Europe/Berlin'));

        $person = new Person;
        $person->statusdata = [
            'vertraege' => [
                [
                    'teilnehmer_id' => 'OLD',
                    'vertrag_beginn' => '2024/07/18',
                    'vertrag_ende' => '2026/07/17',
                    'is_current' => true,
                ],
                [
                    'teilnehmer_id' => 'NEW',
                    'vertrag_beginn' => '2026/08/03',
                    'vertrag_ende' => '2028/07/19',
                    'is_current' => false,
                ],
            ],
        ];

        $contract = $person->currentParticipantContract(14, 20 * 356);

        $this->assertSame('NEW', $contract['teilnehmer_id']);
        $this->assertSame(
            Carbon::create(2028, 7, 19, 23, 59, 59, 'Europe/Berlin')->timestamp,
            $person->portalRoleSortTimestamp(14, 20 * 356)
        );
    }
}
