<?php

namespace Widget\Contents\Write;

use Closure;

final class Editor
{
    private Closure $transaction;
    private Closure $checkStatus;
    private Closure $hasContent;
    private Closure $getCid;
    private Closure $getType;
    private Closure $getStatus;
    private Closure $getDraft;
    private Closure $insert;
    private Closure $updateByCid;
    private Closure $deleteContent;
    private Closure $deleteFields;
    private Closure $moveAttachments;
    private Closure $syncMetas;
    private Closure $syncAttachments;
    private Closure $syncFields;
    private Closure $refresh;

    public function __construct(
        Closure $transaction,
        Closure $checkStatus,
        Closure $hasContent,
        Closure $getCid,
        Closure $getType,
        Closure $getStatus,
        Closure $getDraft,
        Closure $insert,
        Closure $updateByCid,
        Closure $deleteContent,
        Closure $deleteFields,
        Closure $moveAttachments,
        Closure $syncMetas,
        Closure $syncAttachments,
        Closure $syncFields,
        Closure $refresh
    ) {
        $this->transaction = $transaction;
        $this->checkStatus = $checkStatus;
        $this->hasContent = $hasContent;
        $this->getCid = $getCid;
        $this->getType = $getType;
        $this->getStatus = $getStatus;
        $this->getDraft = $getDraft;
        $this->insert = $insert;
        $this->updateByCid = $updateByCid;
        $this->deleteContent = $deleteContent;
        $this->deleteFields = $deleteFields;
        $this->moveAttachments = $moveAttachments;
        $this->syncMetas = $syncMetas;
        $this->syncAttachments = $syncAttachments;
        $this->syncFields = $syncFields;
        $this->refresh = $refresh;
    }

    public function transaction(callable $callback)
    {
        return ($this->transaction)($callback);
    }

    public function checkStatus(array &$contents): void
    {
        ($this->checkStatus)($contents);
    }

    public function hasContent(): bool
    {
        return (bool) ($this->hasContent)();
    }

    public function cid(): int
    {
        return (int) ($this->getCid)();
    }

    public function type(): string
    {
        return (string) ($this->getType)();
    }

    public function status(): string
    {
        return (string) ($this->getStatus)();
    }

    public function draft(): array
    {
        $draft = ($this->getDraft)();
        return is_array($draft) ? $draft : [];
    }

    public function insert(array $contents): int
    {
        return (int) ($this->insert)($contents);
    }

    public function updateByCid(array $contents, int $cid): int
    {
        return (int) ($this->updateByCid)($contents, $cid);
    }

    public function deleteContent(int $cid, bool $hasMetas): void
    {
        ($this->deleteContent)($cid, $hasMetas);
    }

    public function deleteFields(int $cid): int
    {
        return (int) ($this->deleteFields)($cid);
    }

    public function moveAttachments(int $fromCid, int $toCid): void
    {
        ($this->moveAttachments)($fromCid, $toCid);
    }

    public function syncMetas(int $cid, array $contents, bool $beforeCount, bool $afterCount): void
    {
        ($this->syncMetas)($cid, $contents, $beforeCount, $afterCount);
    }

    public function syncAttachments(int $cid, array $contents): void
    {
        ($this->syncAttachments)($cid, $contents);
    }

    public function syncFields(int $cid): void
    {
        ($this->syncFields)($cid);
    }

    public function refresh(int $cid): void
    {
        ($this->refresh)($cid);
    }
}
