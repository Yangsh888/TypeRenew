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
            $pageSize = abs(intval($struct['number']));
        }

        if (!empty($struct['offset'])) {
            $input['page'] = abs(intval($struct['offset'])) + 1;
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

        return $this->buildMediaStruct($attachment);
    }

    public function mwNewMediaObject(int $blogId, string $userName, string $password, array $data): array
    {
        $result = Upload::uploadHandle($data);

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
            'file' => $this->xmlRpc->attachment->name,
            'url' => $this->xmlRpc->attachment->url
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
