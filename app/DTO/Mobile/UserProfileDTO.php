<?php 
namespace App\DTO\Mobile;
class UserProfileDTO{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $phone,
        public readonly ?string $email = null,
        public readonly ?string $address = null,
        public readonly ?string $image_url = null,
    ) {}
}