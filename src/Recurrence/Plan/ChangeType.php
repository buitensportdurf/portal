<?php

namespace App\Recurrence\Plan;

enum ChangeType: string
{
    case Create = 'create';
    case Move = 'move';
    case Update = 'update';
    case Unchanged = 'unchanged';
    case Cancel = 'cancel';
    case Delete = 'delete';
}
