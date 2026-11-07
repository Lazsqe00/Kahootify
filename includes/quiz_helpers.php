<?php
/**
 * Helper functions for quiz questions and answers.
 */

if (!function_exists('normalize_quiz_text')) {
    /**
     * Normalizes a text string for quiz comparison:
     * - Trims surrounding whitespace
     * - Lowercases with UTF-8 support
     * - Normalizes Vietnamese NFD (decomposed combining diacritics) to NFC (precomposed)
     * - Normalizes curly/smart quotes
     * - Collapses multiple internal whitespaces into a single space
     */
    function normalize_quiz_text(?string $str): string {
        $str = trim((string)$str);
        if ($str === '') {
            return '';
        }

        // Lowercase UTF-8 first so base characters become lowercase
        $str = mb_strtolower($str, 'UTF-8');

        // Normalize Vietnamese NFD (canonical decomposed) to NFC (precomposed)
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($str, Normalizer::FORM_C);
            if ($normalized !== false) {
                $str = $normalized;
            }
        } else {
            // Comprehensive map of Vietnamese combining diacritics
            // Acute: \xCC\x81, Grave: \xCC\x80, Hook: \xCC\x89, Tilde: \xCC\x83, Dot: \xCC\xA3
            // Circumflex: \xCC\x82 (â, ê, ô), Breve: \xCC\x86 (ă), Horn: \xCC\x9B (ơ, ư)
            static $nfdToNfc = [
                // a + circumflex + tone
                "a\xCC\x82\xCC\x81" => 'ấ', "a\xCC\x81\xCC\x82" => 'ấ',
                "a\xCC\x82\xCC\x80" => 'ầ', "a\xCC\x80\xCC\x82" => 'ầ',
                "a\xCC\x82\xCC\x89" => 'ẩ', "a\xCC\x89\xCC\x82" => 'ẩ',
                "a\xCC\x82\xCC\x83" => 'ẫ', "a\xCC\x83\xCC\x82" => 'ẫ',
                "a\xCC\x82\xCC\xA3" => 'ậ', "a\xCC\xA3\xCC\x82" => 'ậ',

                // a + breve + tone
                "a\xCC\x86\xCC\x81" => 'ắ', "a\xCC\x81\xCC\x86" => 'ắ',
                "a\xCC\x86\xCC\x80" => 'ằ', "a\xCC\x80\xCC\x86" => 'ằ',
                "a\xCC\x86\xCC\x89" => 'ẳ', "a\xCC\x89\xCC\x86" => 'ẳ',
                "a\xCC\x86\xCC\x83" => 'ẵ', "a\xCC\x83\xCC\x86" => 'ẵ',
                "a\xCC\x86\xCC\xA3" => 'ặ', "a\xCC\xA3\xCC\x86" => 'ặ',

                // e + circumflex + tone
                "e\xCC\x82\xCC\x81" => 'ế', "e\xCC\x81\xCC\x82" => 'ế',
                "e\xCC\x82\xCC\x80" => 'ề', "e\xCC\x80\xCC\x82" => 'ề',
                "e\xCC\x82\xCC\x89" => 'ể', "e\xCC\x89\xCC\x82" => 'ể',
                "e\xCC\x82\xCC\x83" => 'ễ', "e\xCC\x83\xCC\x82" => 'ễ',
                "e\xCC\x82\xCC\xA3" => 'ệ', "e\xCC\xA3\xCC\x82" => 'ệ',

                // o + circumflex + tone
                "o\xCC\x82\xCC\x81" => 'ố', "o\xCC\x81\xCC\x82" => 'ố',
                "o\xCC\x82\xCC\x80" => 'ồ', "o\xCC\x80\xCC\x82" => 'ồ',
                "o\xCC\x82\xCC\x89" => 'ổ', "o\xCC\x89\xCC\x82" => 'ổ',
                "o\xCC\x82\xCC\x83" => 'ỗ', "o\xCC\x83\xCC\x82" => 'ỗ',
                "o\xCC\x82\xCC\xA3" => 'ộ', "o\xCC\xA3\xCC\x82" => 'ộ',

                // o + horn + tone
                "o\xCC\x9B\xCC\x81" => 'ớ', "o\xCC\x81\xCC\x9B" => 'ớ',
                "o\xCC\x9B\xCC\x80" => 'ờ', "o\xCC\x80\xCC\x9B" => 'ờ',
                "o\xCC\x9B\xCC\x89" => 'ở', "o\xCC\x89\xCC\x9B" => 'ở',
                "o\xCC\x9B\xCC\x83" => 'ỡ', "o\xCC\x83\xCC\x9B" => 'ỡ',
                "o\xCC\x9B\xCC\xA3" => 'ợ', "o\xCC\xA3\xCC\x9B" => 'ợ',

                // u + horn + tone
                "u\xCC\x9B\xCC\x81" => 'ứ', "u\xCC\x81\xCC\x9B" => 'ứ',
                "u\xCC\x9B\xCC\x80" => 'ừ', "u\xCC\x80\xCC\x9B" => 'ừ',
                "u\xCC\x9B\xCC\x89" => 'ử', "u\xCC\x89\xCC\x9B" => 'ử',
                "u\xCC\x9B\xCC\x83" => 'ữ', "u\xCC\x83\xCC\x9B" => 'ữ',
                "u\xCC\x9B\xCC\xA3" => 'ự', "u\xCC\xA3\xCC\x9B" => 'ự',

                // Single modifications (circumflex/breve/horn)
                "a\xCC\x82" => 'â',
                "a\xCC\x86" => 'ă',
                "e\xCC\x82" => 'ê',
                "o\xCC\x82" => 'ô',
                "o\xCC\x9B" => 'ơ',
                "u\xCC\x9B" => 'ư',

                // Single tone on base vowels
                "a\xCC\x81" => 'á', "a\xCC\x80" => 'à', "a\xCC\x89" => 'ả', "a\xCC\x83" => 'ã', "a\xCC\xA3" => 'ạ',
                "ă\xCC\x81" => 'ắ', "ă\xCC\x80" => 'ằ', "ă\xCC\x89" => 'ẳ', "ă\xCC\x83" => 'ẵ', "ă\xCC\xA3" => 'ặ',
                "â\xCC\x81" => 'ấ', "â\xCC\x80" => 'ầ', "â\xCC\x89" => 'ẩ', "â\xCC\x83" => 'ẫ', "â\xCC\xA3" => 'ậ',
                "e\xCC\x81" => 'é', "e\xCC\x80" => 'è', "e\xCC\x89" => 'ẻ', "e\xCC\x83" => 'ẽ', "e\xCC\xA3" => 'ẹ',
                "ê\xCC\x81" => 'ế', "ê\xCC\x80" => 'ề', "ê\xCC\x89" => 'ể', "ê\xCC\x83" => 'ễ', "ê\xCC\xA3" => 'ệ',
                "i\xCC\x81" => 'í', "i\xCC\x80" => 'ì', "i\xCC\x89" => 'ỉ', "i\xCC\x83" => 'ĩ', "i\xCC\xA3" => 'ị',
                "o\xCC\x81" => 'ó', "o\xCC\x80" => 'ò', "o\xCC\x89" => 'ỏ', "o\xCC\x83" => 'õ', "o\xCC\xA3" => 'ọ',
                "ô\xCC\x81" => 'ố', "ô\xCC\x80" => 'ồ', "ô\xCC\x89" => 'ổ', "ô\xCC\x83" => 'ỗ', "ô\xCC\xA3" => 'ộ',
                "ơ\xCC\x81" => 'ớ', "ơ\xCC\x80" => 'ờ', "ơ\xCC\x89" => 'ở', "ơ\xCC\x83" => 'ỡ', "ơ\xCC\xA3" => 'ợ',
                "u\xCC\x81" => 'ú', "u\xCC\x80" => 'ù', "u\xCC\x89" => 'ủ', "u\xCC\x83" => 'ũ', "u\xCC\xA3" => 'ụ',
                "ư\xCC\x81" => 'ứ', "ư\xCC\x80" => 'ừ', "ư\xCC\x89" => 'ử', "ư\xCC\x83" => 'ữ', "ư\xCC\xA3" => 'ự',
                "y\xCC\x81" => 'ý', "y\xCC\x80" => 'ỳ', "y\xCC\x89" => 'ỷ', "y\xCC\x83" => 'ỹ', "y\xCC\xA3" => 'ỵ',
            ];
            $str = strtr($str, $nfdToNfc);
        }

        // Normalize quotes and dashes
        $str = str_replace(['“', '”', '‘', '’', '`'], ['"', '"', "'", "'", "'"], $str);

        // Collapse multiple whitespace
        $str = preg_replace('/\s+/u', ' ', $str);

        return trim($str);
    }
}

if (!function_exists('is_text_answer_correct')) {
    /**
     * Determines if user's input text answer matches any of the accepted correct answers.
     * Supports:
     * - Case-insensitive and whitespace-tolerant comparison
     * - Unicode NFC/NFD equivalence
     * - Flexible punctuation at ends (e.g. typing "Hà Nội." for "Hà Nội")
     * - Multiple alternative answers separated by semicolons, pipes, or slashes (e.g. "Hà Nội / Hanoi")
     *
     * @param string $userText The answer submitted by the user.
     * @param array $correctAnswers List of correct answer strings.
     * @return bool
     */
    function is_text_answer_correct(string $userText, array $correctAnswers): bool {
        $normUser = normalize_quiz_text($userText);
        if ($normUser === '') {
            return false;
        }

        // Punctuation stripped version
        $normUserNoPunct = trim($normUser, ".,!?\"';:-");

        foreach ($correctAnswers as $correctAnsText) {
            $rawCorrect = (string)$correctAnsText;
            if (trim($rawCorrect) === '') {
                continue;
            }

            // Check direct match or split by separators (; / |)
            $variants = [$rawCorrect];
            if (preg_match('/[;\/|]/u', $rawCorrect)) {
                $parts = preg_split('/[;\/|]+/u', $rawCorrect);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $variants[] = $part;
                    }
                }
            }

            foreach ($variants as $variant) {
                $normCorrect = normalize_quiz_text($variant);
                if ($normCorrect === '') {
                    continue;
                }

                // 1. Exact match after normalization
                if ($normUser === $normCorrect) {
                    return true;
                }

                // 2. Match ignoring trailing punctuation
                $normCorrectNoPunct = trim($normCorrect, ".,!?\"';:-");
                if ($normUserNoPunct !== '' && $normUserNoPunct === $normCorrectNoPunct) {
                    return true;
                }
            }
        }

        return false;
    }
}
