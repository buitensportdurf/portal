<?php

namespace App\Service;

use JetBrains\PhpStorm\ArrayShape;

class LastRelease
{
    #[ArrayShape([
        "branch" => "string",
        "date" => "string",
        "commitHashShort" => "string",
        "commitHashLong" => "string",
        "commitDate" => "string",
    ])]
    private array $lastRelease = [];

    public function __construct(string $jsonFile)
    {
        if (file_exists($jsonFile)) {
            $this->lastRelease = json_decode(file_get_contents($jsonFile), true);
        }
    }

    public function getBranch(): string
    {
        return $this->lastRelease['branch'] ?? '-';
    }

    public function getDate(): string
    {
        return $this->lastRelease['date'] ?? '-';
    }

    public function getCommitHashShort(): string
    {
        return $this->lastRelease['commitHashShort'] ?? '-';
    }

    public function getCommitHashLong(): string
    {
        return $this->lastRelease['commitHashLong'] ?? '-';
    }

    public function getCommitDate(): string
    {
        return $this->lastRelease['commitDate'] ?? '-';
    }

}