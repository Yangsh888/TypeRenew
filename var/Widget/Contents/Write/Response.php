<?php

namespace Widget\Contents\Write;

use Typecho\Timezone;
use Typecho\Widget\Request as WidgetRequest;
use Typecho\Widget\Response as WidgetResponse;
use Widget\Notice;

final class Response
{
    public static function finishSave(
        WidgetRequest $request,
        WidgetResponse $response,
        int $time,
        int $cid,
        string $title,
        int $draftId,
        string $redirect
    ): void {
        Notice::alloc()->highlight((string) $cid);

        if ($request->isAjax()) {
            $response->throwJson([
                'success' => 1,
                'time' => Timezone::format($time, 'H:i:s A'),
                'cid' => $cid,
                'draftId' => $draftId
            ]);
            return;
        }

        Notice::alloc()->set(
            _t('草稿 "%s" 已经被保存', htmlspecialchars($title, ENT_QUOTES, 'UTF-8')),
            'success'
        );
        $response->redirect($redirect);
    }
}
