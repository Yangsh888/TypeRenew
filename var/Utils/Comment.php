<?php

namespace Utils;

use Typecho\Cache;
use Typecho\Common;
use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Comment
{
    public static function syncUserAuthor(
        Db $db,
        int $uid,
        ?string $screenName,
        ?string $fallbackName = null,
        int $cacheCommentFlush = 1,
        ?callable $beforePurge = null
    ): int {
        $updatedRows = self::updateUserAuthor($db, $uid, $screenName, $fallbackName);
        if ($updatedRows > 0) {
            self::purgeCache($cacheCommentFlush, $beforePurge);
        }

        return $updatedRows;
    }

    public static function syncAllAuthors(
        Db $db,
        int $cacheCommentFlush = 1,
        ?callable $beforePurge = null
    ): int {
        try {
            $users = $db->fetchAll($db->select('uid', 'name', 'screenName')->from('table.users'));
        } catch (\Throwable) {
            return 0;
        }

        $updatedRows = 0;
        foreach ($users as $user) {
            $updatedRows += self::updateUserAuthor(
                $db,
                (int) ($user['uid'] ?? 0),
                isset($user['screenName']) ? (string) $user['screenName'] : null,
                isset($user['name']) ? (string) $user['name'] : null
            );
        }

        if ($updatedRows > 0) {
            self::purgeCache($cacheCommentFlush, $beforePurge);
        }

        return $updatedRows;
    }

    public static function purgeCache(int $cacheCommentFlush = 1, ?callable $beforePurge = null): void
    {
        if ($cacheCommentFlush !== 1) {
            return;
        }

        if ($beforePurge !== null) {
            try {
                $beforePurge();
            } catch (\Throwable $e) {
                error_log('Utils.Comment.beforePurge: ' . $e->getMessage());
            }
        }

        try {
            $cache = Cache::getInstance();
            $cache->invalidate('comments');
            $cache->invalidate('contents');
            $cache->invalidate('metas');
        } catch (\Throwable $e) {
            error_log('Utils.Comment.invalidate: ' . $e->getMessage());
            try {
                Cache::getInstance()->flush();
            } catch (\Throwable $flushError) {
                error_log('Utils.Comment.flush: ' . $flushError->getMessage());
            }
        }
    }

    private static function updateUserAuthor(Db $db, int $uid, ?string $screenName, ?string $fallbackName = null): int
    {
        if ($uid <= 0) {
            return 0;
        }

        $author = Common::strBy($screenName, $fallbackName);
        if ($author === '') {
            return 0;
        }

        return (int) $db->query(
            $db->update('table.comments')
                ->rows(['author' => $author])
                ->where('authorId = ?', $uid)
                ->where('(author IS NULL OR author <> ?)', $author)
        );
    }
}
