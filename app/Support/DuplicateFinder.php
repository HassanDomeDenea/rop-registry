<?php

namespace App\Support;

use App\Models\Patient;

/**
 * Finds registered patients that may be the same baby as the one being entered.
 *
 * Names are compared after removing Arabic diacritics and spelling variants, so that
 * "أحمد" and "احمد" match. A shared date of birth alone is also reported, because
 * twins and re-registered babies were both found in the paper records.
 */
class DuplicateFinder
{
    /**
     * @return list<array{id: int, name: string, file_number: string|null, dob: string|null, ga_weeks: int|null, unverified: bool, reason: string}>
     */
    public function find(string $name, ?string $dob, ?int $ignoreId = null): array
    {
        $needle = $this->normalize($name);

        if (mb_strlen($needle) < 3 && $dob === null) {
            return [];
        }

        $matches = [];

        foreach (Patient::query()->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->get() as $patient) {
            $reason = $this->reason($needle, $dob, $patient);

            if ($reason === null) {
                continue;
            }

            $matches[] = [
                'id' => $patient->id,
                'name' => $patient->name,
                'file_number' => $patient->file_number,
                'dob' => $patient->dob?->format('Y-m-d'),
                'ga_weeks' => $patient->ga_weeks,
                'unverified' => $patient->unverified,
                'reason' => $reason,
            ];

            if (count($matches) === 8) {
                break;
            }
        }

        return $matches;
    }

    protected function reason(string $needle, ?string $dob, Patient $patient): ?string
    {
        $candidate = $this->normalize($patient->name);
        $sameBirthDate = $dob !== null && $patient->dob?->format('Y-m-d') === $dob;
        $similarName = mb_strlen($needle) >= 3 && $this->namesMatch($needle, $candidate);

        return match (true) {
            $similarName && $sameBirthDate => 'name_and_dob',
            $similarName => 'name',
            $sameBirthDate => 'dob',
            default => null,
        };
    }

    protected function namesMatch(string $first, string $second): bool
    {
        if ($first === $second) {
            return true;
        }

        $firstTokens = explode(' ', $first);
        $secondTokens = explode(' ', $second);

        // The given name and the father's name together identify most babies.
        if (count($firstTokens) >= 2 && count($secondTokens) >= 2
            && array_slice($firstTokens, 0, 2) === array_slice($secondTokens, 0, 2)) {
            return true;
        }

        similar_text($first, $second, $percent);

        return $percent >= 85;
    }

    /**
     * Reduce a name to a form in which common spelling variants are equal.
     */
    public function normalize(string $name): string
    {
        $name = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $name) ?? $name;
        $name = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? $name;

        return trim(mb_strtolower($name));
    }
}
