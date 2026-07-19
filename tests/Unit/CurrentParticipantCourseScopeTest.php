<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Support\CurrentParticipantCourseScope;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class CurrentParticipantCourseScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 18, 12, 0, 0, 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_keeps_the_first_open_contract_until_its_end(): void
    {
        $person = $this->person(
            statusData: [
                'teilnehmer_id' => '5-004570201',
                'teilnehmer_nr' => '004570201',
                'vertraege' => [
                    $this->contract('5-004570201', '004570201', '2026/08/03', '2028/07/19'),
                    $this->contract('5-004570200', '004570200', '2025/08/01', '2026/07/18'),
                ],
            ],
            programData: $this->programData('5-004570200', '004570200', '2025/08/01', '2026/07/18'),
        );

        $overview = CurrentParticipantCourseScope::currentContractOverviewFor($person);
        $identifiers = CurrentParticipantCourseScope::identifiersFor($person);

        $this->assertSame('5-004570200', $overview['teilnehmer_id']);
        $this->assertSame('004570200', $overview['teilnehmer_nr']);
        $this->assertSame('2025/08/01', $overview['beginn']);
        $this->assertSame('2026/07/18', $overview['ende']);
        $this->assertSame('Erstes Programm', $overview['program_title']);
        $this->assertSame('5-004570200', $identifiers['teilnehmer_id']);
        $this->assertSame(['BLOCK-200'], $identifiers['tn_baustein_ids']);
    }

    public function test_it_strictly_prefers_the_contract_marked_current_by_the_api(): void
    {
        $older = $this->contract('5-004570200', '004570200', '2025/08/01', '2026/12/31');
        $older['is_current'] = false;
        $newer = $this->contract('5-004570201', '004570201', '2026/08/03', '2028/07/19', false);
        $newer['is_current'] = true;

        $person = $this->person(
            statusData: ['vertraege' => [$older, $newer]],
            programData: $this->programData('5-004570201', '004570201', '2026/08/03', '2028/07/19'),
        );

        $overview = CurrentParticipantCourseScope::currentContractOverviewFor($person);

        $this->assertSame('5-004570201', $overview['teilnehmer_id']);
        $this->assertSame('2026/08/03', $overview['beginn']);
    }

    public function test_it_switches_to_the_future_follow_up_contract_after_the_first_contract_ends(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 19, 12, 0, 0, 'Europe/Berlin'));

        $person = $this->person(
            statusData: [
                'vertraege' => [
                    $this->contract('5-004570200', '004570200', '2025/08/01', '2026/07/18'),
                    $this->contract('5-004570201', '004570201', '2026/08/03', '2028/07/19'),
                ],
            ],
            programData: $this->programData('5-004570201', '004570201', '2026/08/03', '2028/07/19'),
        );

        $identifiers = CurrentParticipantCourseScope::identifiersFor($person);

        $this->assertSame('5-004570201', $identifiers['teilnehmer_id']);
        $this->assertSame(['BLOCK-201'], $identifiers['tn_baustein_ids']);
    }

    public function test_it_uses_the_earliest_effective_end_if_contract_starts_are_missing(): void
    {
        $person = $this->person(
            statusData: [
                'vertraege' => [
                    $this->contract('5-004570201', '004570201', null, '2028/07/19'),
                    $this->contract('5-004570200', '004570200', null, '2026/07/18'),
                ],
            ],
            programData: $this->programData('5-004570200', '004570200', '2025/08/01', '2026/07/18'),
        );

        $overview = CurrentParticipantCourseScope::currentContractOverviewFor($person);

        $this->assertSame('5-004570200', $overview['teilnehmer_id']);
    }

    public function test_it_does_not_mix_program_data_from_another_contract_into_the_overview_or_filter(): void
    {
        $current = $this->contract('5-004570200', '004570200', '2025/08/01', '2026/07/18');
        $current['is_current'] = true;

        $person = $this->person(
            statusData: [
                'teilnehmer_id' => '5-004570201',
                'teilnehmer_nr' => '004570201',
                'vertraege' => [$current],
            ],
            programData: $this->programData('5-004570201', '004570201', '2026/08/03', '2028/07/19'),
        );

        $overview = CurrentParticipantCourseScope::currentContractOverviewFor($person);
        $identifiers = CurrentParticipantCourseScope::identifiersFor($person);

        $this->assertSame('5-004570200', $overview['teilnehmer_id']);
        $this->assertSame('004570200', $overview['teilnehmer_nr']);
        $this->assertSame('2025/08/01', $overview['beginn']);
        $this->assertSame('2026/07/18', $overview['ende']);
        $this->assertNull($overview['program_title']);
        $this->assertNull($overview['massnahme_id']);
        $this->assertSame('5-004570200', $identifiers['teilnehmer_id']);
        $this->assertSame([], $identifiers['tn_baustein_ids']);
        $this->assertSame([], $identifiers['klassen_ids']);
    }

    public function test_it_returns_every_contract_for_the_admin_profile_and_enriches_only_the_current_one(): void
    {
        $current = $this->contract('5-004570200', '004570200', '2025/08/01', '2026/07/18', false);
        $current['is_current'] = true;
        $followUp = $this->contract('5-004570201', '004570201', '2026/08/03', '2028/07/19');

        $person = $this->person(
            statusData: ['vertraege' => [$followUp, $current]],
            programData: $this->programData('5-004570200', '004570200', '2025/08/01', '2026/07/18'),
        );

        $contracts = CurrentParticipantCourseScope::contractOverviewsFor($person);

        $this->assertCount(2, $contracts);
        $this->assertSame('5-004570200', $contracts[0]['teilnehmer_id']);
        $this->assertTrue($contracts[0]['is_current']);
        $this->assertSame('current', $contracts[0]['contract_state']);
        $this->assertSame('Erstes Programm', $contracts[0]['program_title']);
        $this->assertSame('5-004570201', $contracts[1]['teilnehmer_id']);
        $this->assertFalse($contracts[1]['is_current']);
        $this->assertSame('open', $contracts[1]['contract_state']);
        $this->assertNull($contracts[1]['program_title']);
    }

    private function person(array $statusData, array $programData): Person
    {
        $person = new Person;
        $person->id = 42;
        $person->person_id = '5-0045702';
        $person->teilnehmer_id = $statusData['teilnehmer_id'] ?? null;
        $person->teilnehmer_nr = $statusData['teilnehmer_nr'] ?? null;
        $person->vorname = 'Alexander';
        $person->nachname = 'Zukewitsch';
        $person->geschlecht = 'M';
        $person->statusdata = $statusData;
        $person->programdata = $programData;

        return $person;
    }

    private function contract(
        string $participantId,
        string $participantNumber,
        ?string $start,
        string $end,
        bool $active = true,
    ): array {
        return [
            'teilnehmer_id' => $participantId,
            'teilnehmer_nr' => $participantNumber,
            'vertrag_beginn' => $start,
            'letzter_tag' => $end,
            'vertrag_ende' => $end,
            'kuendig_zum' => '',
            'is_active' => $active,
        ];
    }

    private function programData(
        string $participantId,
        string $participantNumber,
        string $start,
        string $end,
    ): array {
        $suffix = substr($participantNumber, -3);

        return [
            'teilnehmer_id' => $participantId,
            'teilnehmer_nr' => $participantNumber,
            'vertrag_beginn' => $start,
            'vertrag_ende' => $end,
            'langbez_m' => $participantNumber === '004570200' ? 'Erstes Programm' : 'Zweites Programm',
            'massnahme_id' => 'PROGRAM-'.$suffix,
            'tn_baust' => [
                [
                    'tn_baustein_id' => 'BLOCK-'.$suffix,
                    'klassen_id' => 'CLASS-'.$suffix,
                    'baustein_id' => 'MODULE-'.$suffix,
                ],
            ],
        ];
    }
}
