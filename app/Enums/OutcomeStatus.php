<?php

namespace App\Enums;

enum OutcomeStatus: string
{
    case Pending = 'pending';
    case Final = 'final';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
