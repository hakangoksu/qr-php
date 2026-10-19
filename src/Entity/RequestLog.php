<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One HTTP request. Its own table, so every field can be searched.
 * This class only describes the table. Rows are written and read with plain SQL.
 */
#[ORM\Entity]
#[ORM\Table(name: 'request_logs')]
#[ORM\Index(name: 'ix_request_logs_timestamp', columns: ['timestamp'])]
#[ORM\Index(name: 'ix_request_logs_source_timestamp', columns: ['source', 'timestamp'])]
#[ORM\Index(name: 'ix_request_logs_status_code_timestamp', columns: ['status_code', 'timestamp'])]
class RequestLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    // Microseconds, because many requests come in the same second. Doctrine's datetime
    // has no fractions, so the column is written out.
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, columnDefinition: 'DATETIME(6) NOT NULL')]
    private \DateTimeImmutable $timestamp;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 10)]
    private string $method;

    #[ORM\Column(length: 2048)]
    private string $path;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $statusCode;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 3)]
    private string $source;

    #[ORM\Column]
    private float $durationMs;

    private function __construct()
    {
    }
}
