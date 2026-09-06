<?php

namespace App\Enums;

enum PreferredContactChannel: string
{
    case InstitutionalEmail = 'institutional_email';
    case PersonalEmail = 'personal_email';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::InstitutionalEmail => 'Correo institucional',
            self::PersonalEmail => 'Correo personal',
            self::Phone => 'Teléfono',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $channel) => ['value' => $channel->value, 'label' => $channel->label()],
            self::cases(),
        );
    }
}

