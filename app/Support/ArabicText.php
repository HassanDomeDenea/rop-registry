<?php

namespace App\Support;

use Illuminate\Database\Query\Expression;

/**
 * Compares Arabic text without its common spelling variants, so that a search for
 * "احمد" finds "أحمد" and "فاطمه" finds "فاطمة".
 */
class ArabicText
{
    /**
     * Letters that are typed interchangeably, and the tatweel that only stretches a word.
     *
     * @var array<string, string>
     */
    protected const VARIANTS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي', 'ـ' => '',
    ];

    /**
     * Fold a search term the same way foldedColumn() folds the stored text.
     */
    public static function fold(string $text): string
    {
        return mb_strtolower(strtr($text, self::VARIANTS));
    }

    /**
     * Get the SQL expression that folds a text column. Pass column names only, never user input.
     *
     * @param  literal-string  $column
     * @return Expression<literal-string&non-falsy-string>
     */
    public static function foldedColumn(string $column): Expression
    {
        $expression = "lower({$column})";

        foreach (self::VARIANTS as $from => $to) {
            $expression = "replace({$expression}, '{$from}', '{$to}')";
        }

        return new Expression($expression);
    }

    /**
     * Get the LIKE pattern that finds a term anywhere in a folded column.
     */
    public static function pattern(string $term): string
    {
        return '%'.self::fold($term).'%';
    }
}
