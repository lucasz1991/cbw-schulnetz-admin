<?php

namespace Tests\Unit;

use App\Models\UserRequest;
use PHPUnit\Framework\TestCase;

class UserRequestMakeupExamLabelTest extends TestCase
{
    public function test_it_formats_the_current_makeup_exam_options(): void
    {
        $retake = new UserRequest([
            'exam_modality' => UserRequest::EXAM_MODALITY_RETAKE,
            'fee_cents' => 5000,
        ]);
        $improvement = new UserRequest([
            'exam_modality' => UserRequest::EXAM_MODALITY_IMPROVEMENT,
            'fee_cents' => 3000,
        ]);

        $this->assertSame('Interne Wiederholungsprüfung – 50,00 €', $retake->makeup_exam_option_label);
        $this->assertSame('Interne Nachprüfung – 30,00 €', $improvement->makeup_exam_option_label);
    }

    public function test_it_uses_the_persisted_fee_for_historic_requests(): void
    {
        $request = new UserRequest([
            'exam_modality' => UserRequest::EXAM_MODALITY_RETAKE,
            'fee_cents' => 2000,
        ]);

        $this->assertSame('Interne Wiederholungsprüfung – 20,00 €', $request->makeup_exam_option_label);
        $this->assertSame('20,00 €', $request->fee_formatted);
    }
}
