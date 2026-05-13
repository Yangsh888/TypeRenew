<?php

namespace Widget\Contents\Write;

final class App
{
    private Editor $editor;
    private bool $hasMetas;

    public function __construct(Editor $editor, bool $hasMetas)
    {
        $this->editor = $editor;
        $this->hasMetas = $hasMetas;
    }

    public function publish(array $contents): void
    {
        $this->editor->transaction(function () use ($contents) {
            $context = $this->createContext($contents);
            $this->resolvePublish($context);

            if ($context->realId <= 0) {
                return;
            }

            $this->syncPublish($context);
            $this->editor->refresh($context->realId);
        });
    }

    public function save(array $contents): int
    {
        return (int) $this->editor->transaction(function () use ($contents) {
            $context = $this->createContext($contents);
            $this->resolveSave($context);

            if ($context->realId <= 0) {
                $draft = $this->editor->draft();
                return (int) ($draft['cid'] ?? 0);
            }

            $this->syncSave($context);

            return $context->realId;
        });
    }

    private function createContext(array $contents): Context
    {
        $context = new Context($contents);
        $this->editor->checkStatus($context->contents);
        $context->isDraftToPublish = $this->isDraftType($this->editor->type());
        $context->wasPublished = $this->isVisiblePublish(
            $this->editor->status(),
            $this->editor->created()
        );
        $context->willPublish = $this->isVisiblePublish(
            (string) ($context->contents['status'] ?? ''),
            (int) ($context->contents['created'] ?? 0)
        );

        return $context;
    }

    private function resolvePublish(Context $context): void
    {
        if ($this->editor->hasContent()) {
            if (!$context->isDraftToPublish) {
                $draft = $this->editor->draft();
                $context->draftId = (int) ($draft['cid'] ?? 0);

                if ($context->draftId > 0) {
                    $this->editor->deleteContent($context->draftId, $this->hasMetas);
                    $this->editor->deleteFields($context->draftId);
                }
            }

            $this->editor->updateByCid($context->contents, $this->editor->cid());
            $context->realId = $this->editor->cid();
        } else {
            $context->realId = $this->editor->insert($context->contents);
        }

        if ($context->isDraftToPublish) {
            $draft = $this->editor->draft();
            $context->draftId = (int) ($draft['cid'] ?? 0);
        }
    }

    private function resolveSave(Context $context): void
    {
        $draft = $this->editor->draft();

        if (!empty($draft['cid'])) {
            if (!$this->isDraftType($this->editor->type())) {
                $context->contents['parent'] = $this->editor->cid();
                $context->contents['type'] = 'revision';
            }

            $context->realId = (int) $draft['cid'];
            $this->editor->updateByCid($context->contents, $context->realId);

            return;
        }

        if ($this->editor->hasContent()) {
            $context->contents['parent'] = $this->editor->cid();
            $context->contents['type'] = 'revision';
        }

        $context->realId = $this->editor->insert($context->contents);

        if (!$this->editor->hasContent() && $context->realId > 0) {
            $this->editor->refresh($context->realId);
        }
    }

    private function syncPublish(Context $context): void
    {
        if ($context->draftId > 0 && $context->draftId !== $context->realId) {
            $this->editor->moveAttachments($context->draftId, $context->realId);
        }

        if ($this->hasMetas) {
            $this->editor->syncMetas(
                $context->realId,
                $context->contents,
                !$context->isDraftToPublish && $context->wasPublished,
                $context->willPublish
            );
        }

        $this->editor->syncAttachments($context->realId, $context->contents);
        $this->editor->syncFields($context->realId);
    }

    private function syncSave(Context $context): void
    {
        if ($this->hasMetas) {
            $this->editor->syncMetas($context->realId, $context->contents, false, false);
        }

        if (!$this->editor->hasContent() || $context->isDraftToPublish) {
            $this->editor->syncAttachments($context->realId, $context->contents);
        }
        $this->editor->syncFields($context->realId);
    }

    private function isDraftType(string $type): bool
    {
        return (bool) preg_match('/_draft$/', $type);
    }

    private function isVisiblePublish(string $status, int $created): bool
    {
        return $status === 'publish' && $created > 0 && $created < time();
    }
}
