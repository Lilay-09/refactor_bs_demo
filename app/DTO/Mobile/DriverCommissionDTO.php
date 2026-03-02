<?php

namespace App\DTO\Mobile;


class DriverCommissionDTO
{
    /**
     * @param string $date
     * @param DisbursementDTO[] $list
     */
    public function __construct(
        public readonly string $date,
        public readonly string $type,
        public readonly array $list,  // <-- must be array of DisbursementDTO
    ) {}

    public static function fromModel(object|array $data): self
    {
        $arr = (array) $data;
        return new self(
            date: $arr['date'],
            type: $arr['type'],
            list: $data['list']//array_map(fn($item) => DisbursementDTO::fromArray($item), $arr['list'] ?? [])
        );
    }


    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'list' => array_map(fn ($d) => $d->toArray(), $this->list),
        ];
    }
}

