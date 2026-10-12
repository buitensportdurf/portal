<?php

namespace App\Recurrence\Plan;

use App\Recurrence\SeriesField;
use Symfony\Component\Translation\TranslatableMessage;

final readonly class FieldChange
{
    public function __construct(
        public SeriesField $field,
        public mixed       $old,
        public mixed       $new,
    ) {}

    public function oldText(): string|TranslatableMessage
    {
        return $this->field->format($this->old);
    }

    public function newText(): string|TranslatableMessage
    {
        return $this->field->format($this->new);
    }
}
