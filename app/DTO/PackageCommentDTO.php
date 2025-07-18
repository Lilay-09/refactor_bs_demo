<?php

abstract class PackageCommentDTO {
    public function __construct(
        public readonly int $id,
        public readonly string $threadId,
        public readonly string $topic,
        public readonly mixed $data,
        public readonly string $data_type,
    ) {}

    public static function fromModel(array $comment): self
    {
        return new static(
            id: $comment['id'] ?? 0,
            threadId: $comment['thread_id'] ?? '',
            topic: $comment['topic'] ?? '',
            data: $comment['data'] ?? new stdClass(),
            data_type: $comment['data_type'] ?? 'text',
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
