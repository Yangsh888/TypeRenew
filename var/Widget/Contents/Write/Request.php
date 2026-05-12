<?php

namespace Widget\Contents\Write;

use Typecho\Widget\Request as WidgetRequest;

final class Request
{
    public static function normalize(array $contents, WidgetRequest $request, int $cid): array
    {
        $attachmentCids = trim((string) $request->get('attachment_cids', ''));
        if ($attachmentCids !== '') {
            $cids = array_filter(array_map('intval', explode(',', $attachmentCids)));

            if (!empty($cids)) {
                $existing = !empty($contents['attachment']) && is_array($contents['attachment'])
                    ? array_map('intval', $contents['attachment'])
                    : [];
                $contents['attachment'] = array_values(array_unique(array_merge($existing, $cids)));
            }
        }

        if ($cid > 0) {
            $contents['oldCid'] = $cid;
        }

        if ($request->is('markdown=1')) {
            $contents['text'] = '<!--markdown-->' . (string) ($contents['text'] ?? '');
        }

        return $contents;
    }
}
