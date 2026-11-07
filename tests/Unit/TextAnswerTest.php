<?php

require_once __DIR__ . '/../../includes/quiz_helpers.php';

class TextAnswerTest
{
    private function assert($condition, $message = 'Assertion failed')
    {
        if (!$condition) {
            throw new Exception($message);
        }
    }

    private function assertEquals($expected, $actual, $message = '')
    {
        if ($expected !== $actual) {
            $msg = $message ?: sprintf("Expected %s, got %s", var_export($expected, true), var_export($actual, true));
            throw new Exception($msg);
        }
    }

    public function testExactMatchAndCaseInsensitive()
    {
        $this->assert(is_text_answer_correct('hanoi', ['Hanoi']));
        $this->assert(is_text_answer_correct('HANOI', ['Hanoi']));
        $this->assert(is_text_answer_correct('Hà Nội', ['hà nội']));
        $this->assert(is_text_answer_correct('HÀ NỘI', ['Hà Nội']));
    }

    public function testWhitespaceTolerance()
    {
        // Leading/trailing whitespace
        $this->assert(is_text_answer_correct('  Hà Nội  ', ['Hà Nội']));
        // Multiple spaces between words
        $this->assert(is_text_answer_correct('Hà    Nội', ['Hà Nội']));
        $this->assert(is_text_answer_correct('Hà Nội', ['Hà   Nội']));
    }

    public function testVietnameseNfdNfcEquivalence()
    {
        // "Việt Nam" in NFD (decomposed) vs NFC (precomposed)
        $nfc = "Việt Nam";
        $nfd = "Vie\xCC\x82\xCC\xA3t Nam"; // e + circumflex + dot below

        $this->assert(is_text_answer_correct($nfd, [$nfc]), "User NFD should match correct answer NFC");
        $this->assert(is_text_answer_correct($nfc, [$nfd]), "User NFC should match correct answer NFD");
        $this->assert(is_text_answer_correct("ha\xCC\x80 no\xCC\x82\xCC\xA3i", ["Hà Nội"]), "Decomposed accented lowercase matches");
    }

    public function testTrailingPunctuationTolerance()
    {
        // User typed trailing dot or exclamation mark
        $this->assert(is_text_answer_correct('Hà Nội.', ['Hà Nội']));
        $this->assert(is_text_answer_correct('Hà Nội!', ['Hà Nội']));
        $this->assert(is_text_answer_correct('Hà Nội', ['Hà Nội.']));
    }

    public function testMultipleAcceptableAnswersSeparatedByDelimiters()
    {
        $correct = ['Hà Nội / Hanoi / Ha Noi'];
        $this->assert(is_text_answer_correct('Hà Nội', $correct));
        $this->assert(is_text_answer_correct('Hanoi', $correct));
        $this->assert(is_text_answer_correct('ha noi', $correct));

        $semiCorrect = ['Việt Nam; Vietnam; VN'];
        $this->assert(is_text_answer_correct('Việt Nam', $semiCorrect));
        $this->assert(is_text_answer_correct('vietnam', $semiCorrect));
        $this->assert(is_text_answer_correct('vn', $semiCorrect));
    }

    public function testMultipleCorrectAnswerRows()
    {
        $multipleRows = ['Hà Nội', 'Hanoi', 'Thủ đô Hà Nội'];
        $this->assert(is_text_answer_correct('hanoi', $multipleRows));
        $this->assert(is_text_answer_correct('hà nội', $multipleRows));
        $this->assert(is_text_answer_correct('thủ đô hà nội', $multipleRows));
    }

    public function testIncorrectAnswersRejected()
    {
        $this->assert(!is_text_answer_correct('', ['Hà Nội']));
        $this->assert(!is_text_answer_correct('   ', ['Hà Nội']));
        $this->assert(!is_text_answer_correct('Đà Nẵng', ['Hà Nội']));
        $this->assert(!is_text_answer_correct('Paris', ['London']));
    }

    public function testSubmitAnswerScriptIntegrity()
    {
        $submitPath = __DIR__ . '/../../modules/api/submit_answer.php';
        $content = file_get_contents($submitPath);

        // Check quiz_helpers included
        $this->assert(strpos($content, 'quiz_helpers.php') !== false, 'submit_answer.php must require quiz_helpers.php');

        // Check $data is passed to closure
        $this->assert(preg_match('/function\s*\([^)]*\)\s*use\s*\([^)]*\$data[^)]*\)/', $content) === 1,
            'Closure in submit_answer.php must include $data in use() statement');

        // Check is_text_answer_correct is called
        $this->assert(strpos($content, 'is_text_answer_correct') !== false,
            'submit_answer.php must call is_text_answer_correct');
    }
}
