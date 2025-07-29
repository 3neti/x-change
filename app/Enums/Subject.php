<?php

namespace App\Enums;

enum Subject: string
{
    case QUOTE = 'Quote';
    case POEM = 'Poem';
    case INSTRUCTIONS = 'Instructions';
    case SONG = 'Song';
    case HAIKU = 'Haiku';
    case BLOG = 'Blog';

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
}
