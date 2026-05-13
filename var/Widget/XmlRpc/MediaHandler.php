<?php

namespace Widget\XmlRpc;

use IXR\Exception;
use Typecho\Common;
use Widget\Contents\Attachment\Admin as AttachmentAdmin;
use Widget\Contents\Attachment\Edit as AttachmentEdit;
use Widget\Upload;
use Widget\XmlRpc as XmlRpcWidget;

class MediaHandler extends AbstractHandler
{
    public function wpGetMediaLibrary(int $blogId, string $userName, string $password, array $struct): array
    {
        $input = [];

        if (!empty($struct['parent_id'])) {
            $input['parent'] = $struct['parent_id'];
        }

        if (!empty($struct['mime_type'])) {
            $input['mime'] = $struct['mime_type'];
        }

        $pageSize = 10;
        if (!empty($struct['number'])) {
            $pageSize = max(1, abs(intval($struct['number'])));
        }

        if (!empty($struct['offset'])) {
            $offset = abs(intval($struct['offset']));
            $input['page'] = intdiv($offset, $pageSize) + 1;
        }

        $attachments = AttachmentAdmin::alloc('pageSize=' . $pageSize, $input, false);
        $attachmentsStruct = [];

        while ($attachments->next()) {
            $attachmentsStruct[] = $this->buildMediaStruct($attachments);
        }

        return $attachmentsStruct;
    }

    public function wpGetMediaItem(int $blogId, string $userName, string $password, int $attachmentId): array
    {
        $attachment = AttachmentEdit::alloc(null, ['cid' => $attachmentId]);
        if (!$attachment->have()) {
            throw new Exception('attachment not found', -32602);
        }

        return $this->buildMediaStruct($attachment);
    }

    public function mwNewMediaObject(int $blogId, string $userName, string $password, array $data): array
    {
        $result = null;
        $insertId = 0;

        try {
            $payload = $this->normalizeUploadPayload($data);
            $result = Upload::uploadHandle($payload);

            if (false === $result) {
                throw new Exception('upload failed', -32001);
            }

            $insertId = $this->xmlRpc->insert([
                'title' => $result['name'],
                'slug' => $result['name'],
                'type' => 'attachment',
                'status' => 'publish',
                'text' => Common::jsonEncode($result, 0, '{}'),
                'allowComment' => 1,
                'allowPing' => 0,
                'allowFeed' => 1
            ]);

            $this->xmlRpc->db()->fetchRow(
                $this->xmlRpc->select()->where('table.contents.cid = ?', $insertId)
                    ->where('table.contents.type = ?', 'attachment'),
                [$this->xmlRpc, 'push']
            );

            XmlRpcWidget::pluginHandle()->call('upload', $this->xmlRpc);

            return [
                'id' => $insertId,
                'file' => $this->xmlRpc->attachment->name,
                'url' => $this->xmlRpc->attachment->url,
                'type' => $this->xmlRpc->attachment->mime ?? $this->xmlRpc->attachment->type,
            ];
        } catch (\Throwable $e) {
            if ($insertId > 0) {
                $this->xmlRpc->delete($this->xmlRpc->db()->sql()->where('cid = ?', $insertId));
            }

            if (is_array($result)) {
                Upload::cleanupUploadResult($result);
            }

            if ($e instanceof Exception) {
                throw $e;
            }

            throw new Exception($e->getMessage(), -32001);
        }
    }

    private function normalizeUploadPayload(array $data): array
    {
        $name = $data['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            throw new Exception('upload payload is invalid', -32602);
        }

        $bytes = $data['bytes'] ?? ($data['bits'] ?? null);
        if (!is_string($bytes)) {
            throw new Exception('upload payload is invalid', -32602);
        }

        return [
            'name' => $name,
            'size' => strlen($bytes),
            isset($data['bytes']) ? 'bytes' : 'bits' => $bytes,
        ];
    }

    private function buildMediaStruct($attachment): array
    {
        return [
            'attachment_id' => $attachment->cid,
            'date_created_gmt' => $this->xmlRpc->toGmtRpcDate((int) $attachment->created),
            'parent' => $attachment->parent,
            'link' => $attachment->attachment->url,
            'title' => $attachment->title,
            'caption' => $attachment->slug,
            'description' => $attachment->attachment->description,
            'metadata' => [
                'file' => $attachment->attachment->path,
                'size' => $attachment->attachment->size,
            ],
            'thumbnail' => $attachment->attachment->url,
        ];
    }
}
