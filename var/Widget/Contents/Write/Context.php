<?php

namespace Widget\Contents\Write;

final class Context
{
    public array $contents;
    public int $realId = 0;
    public int $draftId = 0;
    public bool $isDraftToPublish = false;
    public bool $wasPublished = false;
    public bool $willPublish = false;

    public function __construct(array $contents)
    {
        $this->contents = $contents;
    }
}
