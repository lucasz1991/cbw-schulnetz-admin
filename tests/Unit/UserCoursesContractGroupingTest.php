<?php

namespace Tests\Unit;

use App\Livewire\Admin\UserProfile\UserCourses;
use App\Models\Course;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class UserCoursesContractGroupingTest extends TestCase
{
    public function test_it_groups_modules_by_contract_and_keeps_legacy_rows_separate(): void
    {
        $person = new Person;
        $person->id = 42;
        $person->person_id = '5-0045702';
        $person->vorname = 'Alexander';
        $person->nachname = 'Zukewitsch';

        $user = new User;
        $user->setRelation('persons', collect([$person]));

        $contracts = [
            $this->contract(42, '5-004570200', true),
            $this->contract(42, '5-004570201', false),
        ];

        $component = new class extends UserCourses
        {
            /**
             * @param  array<int, array<string, mixed>>  $contracts
             */
            public function group(User $user, array $contracts, Collection $courses): array
            {
                $this->user = $user;

                return $this->buildContractCourseGroups($contracts, $courses);
            }
        };

        $groups = $component->group($user, $contracts, collect([
            $this->course(10, 42, '5-004570200'),
            $this->course(11, 42, null),
        ]));

        $this->assertCount(3, $groups);
        $this->assertSame('5-004570200', $groups[0]['contract']['teilnehmer_id']);
        $this->assertSame([10], $groups[0]['courses']->pluck('id')->all());
        $this->assertSame('5-004570201', $groups[1]['contract']['teilnehmer_id']);
        $this->assertTrue($groups[1]['courses']->isEmpty());
        $this->assertTrue($groups[2]['contract']['is_unassigned']);
        $this->assertSame([11], $groups[2]['courses']->pluck('id')->all());
    }

    public function test_a_contract_without_an_id_does_not_absorb_legacy_modules(): void
    {
        $person = new Person;
        $person->id = 42;

        $user = new User;
        $user->setRelation('persons', collect([$person]));

        $component = new class extends UserCourses
        {
            public function group(User $user, array $contracts, Collection $courses): array
            {
                $this->user = $user;

                return $this->buildContractCourseGroups($contracts, $courses);
            }
        };

        $groups = $component->group($user, [
            $this->contract(42, null, true),
        ], collect([
            $this->course(12, 42, null),
        ]));

        $this->assertCount(2, $groups);
        $this->assertTrue($groups[0]['courses']->isEmpty());
        $this->assertTrue($groups[1]['contract']['is_unassigned']);
        $this->assertSame([12], $groups[1]['courses']->pluck('id')->all());
    }

    private function contract(int $personPk, ?string $participantId, bool $current): array
    {
        return [
            'person_pk' => $personPk,
            'person_id' => '5-0045702',
            'teilnehmer_id' => $participantId,
            'teilnehmer_nr' => $participantId ? substr($participantId, 2) : null,
            'beratung_id' => null,
            'beginn' => null,
            'ende' => null,
            'is_current' => $current,
            'contract_state' => $current ? 'current' : 'open',
        ];
    }

    private function course(int $id, int $personPk, ?string $participantId): Course
    {
        $course = new Course;
        $course->id = $id;
        $course->_person_id = $personPk;
        $course->_enrollment_id = $id + 100;
        $course->_enrollment_teilnehmer_id = $participantId;

        return $course;
    }
}
