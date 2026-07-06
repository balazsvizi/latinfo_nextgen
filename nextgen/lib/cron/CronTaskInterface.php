<?php
declare(strict_types=1);

interface CronTaskInterface
{
    /** Egyedi azonosító (pl. venue_geocode). */
    public function name(): string;

    /** Emberi olvasható név a logban. */
    public function label(): string;

    /** Minimum várakozás két futás között másodpercben. */
    public function intervalSeconds(): int;

    /**
     * Feladat futtatása.
     *
     * @param array<string, mixed> $options CLI/HTTP paraméterek (--all, batch_size, stb.)
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function run(array $options = []): array;
}
