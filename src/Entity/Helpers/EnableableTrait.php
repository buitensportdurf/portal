<?php

namespace App\Entity\Helpers;

use Doctrine\ORM\Mapping as ORM;

trait EnableableTrait
{
    #[ORM\Column(options: ['default' => true])]
    public bool $enabled = true;
}
