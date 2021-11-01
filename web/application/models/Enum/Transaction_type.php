<?php

namespace Model\Enum;

use System\Emerald\Emerald_enum;

class Transaction_type extends Emerald_enum
{
    const DEBIT_ACTION = 0;
    const TOP_UP_ACTION = 1;
}
