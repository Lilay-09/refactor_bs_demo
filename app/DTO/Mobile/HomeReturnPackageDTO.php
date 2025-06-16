<?php

use App\Models\Order;
class HomeReturnPackageDTO{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $avatar = null,
    ) {}

    public static function fromModel(Order $order): self
    {
        return new self(
            id: $order->id,
            name: $order->name,
            email: $order->email,
            avatar: $order->avatar_url ?? null,
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
