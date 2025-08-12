<?php
namespace App\DTO;

use App\Models\CommentDescriptions;
use stdClass;
class PackageCommentDTO {
    public function __construct(
        public readonly int $id,
        public readonly string $threadId,
        public readonly string $topic,
        public readonly mixed $data,
        public readonly string $data_type,
        public readonly bool $isSelf,
        public readonly object|array|null $replyTo
    ) {}

    public static function fromModel(CommentDescriptions $comment): self
    {
        return new static(
            id: $comment->id,
            threadId: $comment->thread_id ?? '',
            topic: $comment->topic ?? '',
            data: $comment->data ?? new stdClass(),
            data_type: $comment->data_type ?? 'text',
            isSelf: $comment->isSelf ?? false,
            replyTo: $comment?->replyTo ?? null
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
