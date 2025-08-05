<?php

namespace App\Enums;

enum Subject: string
{
    case NOTE = 'Note';
    case QUOTE = 'Quote';
    case POEM = 'Poem';
    case INSTRUCTIONS = 'Instructions';
    case SONG = 'Song';
//    case HAIKU = 'Haiku';
//    case BLOG = 'Blog';
    case TRIVIA = 'Trivia';
    case JOKE = 'Joke';
    case RECIPE = 'Recipe';
    case CHEAT_SHEET = 'Cheat Sheet';

    public static function options(): array
    {
        return array_map(
            fn(self $subject) => [
                'label' => $subject->value,
                'value' => $subject->name,
            ],
            self::cases()
        );
    }

    public static function labels(): array
    {
        return array_column(self::options(), 'label', 'value');
    }

    public function placeholder(): string
    {
        return match ($this) {
            self::NOTE => 'e.g. Do not forget',
            self::QUOTE => 'e.g. Be yourself - Oscar Wilde',
            self::POEM => 'e.g. The Road Not Taken by Robert Frost',
            self::INSTRUCTIONS => 'e.g. How to Brew Tea',
            self::SONG => 'e.g. Disenchanted - My Chemical Romance',
//            self::HAIKU => 'e.g. Old pond... a frog jumps in',
//            self::BLOG => 'e.g. 5 Lessons I Learned from Freelancing',
            self::TRIVIA => 'e.g. Chemistry',
            self::JOKE => 'e.g. Green Joke',
            self::RECIPE => 'e.g. Caldereta',
            self::CHEAT_SHEET => 'e.g. Cheat Sheet',
        };
    }

    public static function placeholders(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->name => $case->placeholder()])
            ->all();
    }
}
