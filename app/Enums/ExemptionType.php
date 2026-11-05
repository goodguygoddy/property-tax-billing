<?php

namespace App\Enums;

enum ExemptionType: string
{
    /** No homestead exemption. */
    case None = 'none';

    /** A fixed dollar amount is deducted from the value (floored at zero). */
    case Fixed = 'fixed';

    /** A percentage of the value is deducted. */
    case Percent = 'percent';
}
