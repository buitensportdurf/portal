<?php

namespace App\Recurrence\Plan;

use App\Recurrence\SeriesField;

final readonly class FieldChange
{
    public function __construct(
        public SeriesField $field,
        public mixed       $old,
        public mixed       $new,
    ) {}

    public function oldText(): string
    {
        return $this->field->format($this->old);
    }

    public function newText(): string
    {
        return $this->field->format($this->new);
    }
}
