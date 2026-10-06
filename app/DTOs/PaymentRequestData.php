<?php

namespace App\DTOs;

readonly class PaymentRequestData
{
    public function __construct(
        public int $userId,
        public ?int $videoId,
        public float $amount,
        public string $phoneNumber,
        public string $gateway,
        public ?string $description = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) ($data['user_id'] ?? 0),
            videoId: isset($data['video_id']) ? (int) $data['video_id'] : null,
            amount: (float) ($data['amount'] ?? 0),
            phoneNumber: (string) ($data['phone_number'] ?? ''),
            gateway: (string) ($data['gateway'] ?? 'sonicpesa'),
            description: $data['description'] ?? null
        );
    }
}
