<?php

namespace Widget;

use IXR\Date;
use IXR\Exception;
use IXR\Hook;
use IXR\Pingback;
use IXR\Server;
use ReflectionMethod;
use Typecho\Common;
use Typecho\Router;
use Typecho\Widget;
use Typecho\Widget\Exception as WidgetException;
use Widget\Base\Comments;
use Widget\Base\Contents;
use Widget\Contents\Attachment\Unattached;
use Widget\Contents\Page\Admin as PageAdmin;
use Widget\Contents\Post\Admin as PostAdmin;
use Widget\Contents\Attachment\Admin as AttachmentAdmin;
use Widget\Contents\Post\Edit as PostEdit;
use Widget\Contents\Page\Edit as PageEdit;
use Widget\Contents\Attachment\Edit as AttachmentEdit;
use Widget\Metas\Category\Edit as CategoryEdit;
use Widget\Metas\Category\Rows as CategoryRows;
use Widget\Metas\From as MetasFrom;
use Widget\Metas\Tag\Cloud;
use Widget\Comments\Edit as CommentsEdit;
use Widget\Comments\Admin as CommentsAdmin;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class XmlRpc extends Contents implements ActionInterface, Hook
{
    private array $wpOptions;

    public function execute(bool $run = false)
    {
        if ($run) {
            parent::execute();
        }

        // XML-RPC 请求不会走常规表单令牌校验。
        $this->security->enable(false);

        $this->wpOptions = [
            'software_name'    => [
                'desc'     => _t('软件名称'),
                'readonly' => true,
                'value'    => $this->options->software
            ],
            'software_version' => [
                'desc'     => _t('软件版本'),
                'readonly' => true,
                'value'    => $this->options->version
            ],
            'blog_url'         => [
                'desc'     => _t('博客地址'),
                'readonly' => true,
                'option'   => 'siteUrl'
            ],
            'home_url'         => [
                'desc'     => _t('博客首页地址'),
                'readonly' => true,
                'option'   => 'siteUrl'
            ],
            'login_url'        => [
                'desc'     => _t('登录地址'),
                'readonly' => true,
                'value'    => $this->options->loginUrl
            ],
            'admin_url'        => [
                'desc'     => _t('管理区域的地址'),
                'readonly' => true,
                'value'    => $this->options->adminUrl
            ],

            'post_thumbnail'     => [
                'desc'     => _t('文章缩略图'),
                'readonly' => true,
                'value'    => false
            ],

            'time_zone'          => [
                'desc'     => _t('时区'),
                'readonly' => false,
                'option'   => 'timezone'
            ],
            'blog_title'         => [
                'desc'     => _t('博客标题'),
                'readonly' => false,
                'option'   => 'title'
            ],
            'blog_tagline'       => [
                'desc'     => _t('博客关键字'),
                'readonly' => false,
                'option'   => 'description'
            ],
            'date_format'        => [
                'desc'     => _t('日期格式'),
                'readonly' => false,
                'option'   => 'postDateFormat'
            ],
            'time_format'        => [
                'desc'     => _t('时间格式'),
                'readonly' => false,
                'option'   => 'postDateFormat'
            ],
            'users_can_register' => [
                'desc'     => _t('是否允许注册'),
                'readonly' => false,
                'option'   => 'allowRegister'
            ]
        ];
    }

    public function wpGetPage(int $blogId, int $pageId, string $userName, string $password): array
    {
        $page = PageEdit::alloc(null, ['cid' => $pageId], false);

        [$excerpt, $more] = $this->getPostExtended($page);

        return [
            'dateCreated'            => $this->xmlRpcDate($page->created),
            'userid'                 => $page->authorId,
            'page_id'                => $page->cid,
            'page_status'            => $this->typechoToWordpressStatus($page->status, 'page'),
            'description'            => $excerpt,
            'title'                  => $page->title,
            'link'                   => $page->permalink,
            'permaLink'              => $page->permalink,
            'categories'             => $page->categories,
            'excerpt'                => $page->plainExcerpt,
            'text_more'              => $more,
            'mt_allow_comments'      => intval($page->allowComment),
            'mt_allow_pings'         => intval($page->allowPing),
            'wp_slug'                => $page->slug,
            'wp_password'            => $page->password,
            'wp_author'              => $page->author->name,
            'wp_page_parent_id'      => '0',
            'wp_page_parent_title'   => '',
            'wp_page_order'          => $page->order,
            'wp_author_id'           => $page->authorId,
            'wp_author_display_name' => $page->author->screenName,
            'date_created_gmt'       => $this->xmlRpcGmtDate($page->created),
            'custom_fields'          => [],
            'wp_page_template'       => $page->template
        ];
    }

    public function beforeRpcCall(string $methodName, ReflectionMethod $reflectionMethod, array $parameters)
    {
        $valid = 2;
        $auth = [];

        $accesses = [
            'wp.newPage'           => 'editor',
            'wp.deletePage'        => 'editor',
            'wp.getPageList'       => 'editor',
            'wp.getAuthors'        => 'editor',
            'wp.deleteCategory'    => 'editor',
            'wp.getPageStatusList' => 'editor',
            'wp.getPageTemplates'  => 'editor',
            'wp.getOptions'        => 'administrator',
            'wp.setOptions'        => 'administrator',
            'mt.setPostCategories' => 'editor',
        ];

        foreach ($reflectionMethod->getParameters() as $key => $parameter) {
            $name = $parameter->getName();
            if (($name == 'userName' || $name == 'password') && array_key_exists($key, $parameters)) {
                $auth[$name] = (string) $parameters[$key];
                $valid--;
            }
        }

        if ($valid == 0) {
            if ($this->user->login($auth['userName'], $auth['password'], true)) {
                if ($this->user->pass($accesses[$methodName] ?? 'contributor', true)) {
                    $this->user->execute();
                } else {
                    throw new Exception(_t('权限不足'), 403);
                }
            } else {
                throw new Exception(_t('无法登录, 密码错误'), 403);
            }
        }
    }

    public function afterRpcCall(string $methodName, &$result): void
    {
        Widget::destroy();
    }

    public function wpGetPages(int $blogId, string $userName, string $password): array
    {
        $pages = PageAdmin::alloc(null, 'status=all');

        $pageStructs = [];

        while ($pages->next()) {
            [$excerpt, $more] = $this->getPostExtended($pages);
            $pageStructs[] = [
                'dateCreated'            => $this->xmlRpcDate($pages->created),
                'userid'                 => $pages->authorId,
                'page_id'                => intval($pages->cid),
                'page_status'            => $this->typechoToWordpressStatus(
                    ($pages->hasSaved || 'page_draft' == $pages->type) ? 'draft' : $pages->status,
                    'page'
                ),
                'description'            => $excerpt,
                'title'                  => $pages->title,
                'link'                   => $pages->permalink,
                'permaLink'              => $pages->permalink,
                'categories'             => $pages->categories,
                'excerpt'                => $pages->plainExcerpt,
                'text_more'              => $more,
                'mt_allow_comments'      => intval($pages->allowComment),
                'mt_allow_pings'         => intval($pages->allowPing),
                'wp_slug'                => $pages->slug,
                'wp_password'            => $pages->password,
                'wp_author'              => $pages->author->name,
                'wp_page_parent_id'      => 0,
                'wp_page_parent_title'   => '',
                'wp_page_order'          => intval($pages->order),
                'wp_author_id'           => $pages->authorId,
                'wp_author_display_name' => $pages->author->screenName,
                'date_created_gmt'       => $this->xmlRpcGmtDate($pages->created),
                'custom_fields'          => [],
                'wp_page_template'       => $pages->template
            ];
        }

        return $pageStructs;
    }

    public function wpNewPage(int $blogId, string $userName, string $password, array $content, bool $publish): int
    {
        $content['post_type'] = 'page';
        return $this->mwNewPost($blogId, $userName, $password, $content, $publish);
    }

    public function mwNewPost(int $blogId, string $userName, string $password, array $content, bool $publish): int
    {
        $input = [];
        $type = isset($content['post_type']) && 'page' == $content['post_type'] ? 'page' : 'post';
        $title = $this->arrayString($content, 'title');
        $description = $this->arrayString($content, 'description');
        $more = $this->arrayString($content, 'mt_text_more');

        $input['title'] = trim($title) === '' ? _t('未命名文档') : $title;

        if (isset($content['slug'])) {
            $input['slug'] = $content['slug'];
        } elseif (isset($content['wp_slug'])) {
            $input['slug'] = $content['wp_slug'];
        }

        $input['text'] = $more !== '' ? $description . "\n<!--more-->\n" . $more : $description;
        $input['text'] = self::pluginHandle()->filter('textFilter', $input['text'], $this);

        $input['password'] = $content["wp_password"] ?? null;
        $input['order'] = $content["wp_page_order"] ?? null;

        $input['tags'] = $content['mt_keywords'] ?? null;
        $input['category'] = [];

        if (isset($content['postId'])) {
            $input['cid'] = $content['postId'];
        }

        if ('page' == $type && isset($content['wp_page_template'])) {
            $input['template'] = $content['wp_page_template'];
        }

        if (isset($content['dateCreated'])) {
            $timestamp = $this->parseXmlRpcTimestamp($content['dateCreated'], $this->options->getTimezoneZone());
            if ($timestamp !== null) {
                $input['created'] = $timestamp;
            }
        }

        if (!empty($content['categories']) && is_array($content['categories'])) {
            foreach ($content['categories'] as $category) {
                if (
                    !$this->db->fetchRow($this->db->select('mid')
                        ->from('table.metas')->where('type = ? AND name = ?', 'category', $category))
                ) {
                    $this->wpNewCategory($blogId, $userName, $password, ['name' => $category]);
                }

                $input['category'][] = $this->db->fetchObject($this->db->select('mid')
                    ->from('table.metas')->where('type = ? AND name = ?', 'category', $category)
                    ->limit(1))->mid;
            }
        }

        $input['allowComment'] = (isset($content['mt_allow_comments']) && (1 == $content['mt_allow_comments']
                || 'open' == $content['mt_allow_comments']))
            ? 1 : ((isset($content['mt_allow_comments']) && (0 == $content['mt_allow_comments']
                    || 'closed' == $content['mt_allow_comments']))
                ? 0 : $this->options->defaultAllowComment);

        $input['allowPing'] = (isset($content['mt_allow_pings']) && (1 == $content['mt_allow_pings']
                || 'open' == $content['mt_allow_pings']))
            ? 1 : ((isset($content['mt_allow_pings']) && (0 == $content['mt_allow_pings']
                    || 'closed' == $content['mt_allow_pings'])) ? 0 : $this->options->defaultAllowPing);

        $input['allowFeed'] = $this->options->defaultAllowFeed;
        $input['do'] = $publish ? 'publish' : 'save';
        $input['markdown'] = $this->options->xmlrpcMarkdown;

        if (isset($content["{$type}_status"])) {
            $status = $this->wordpressToTypechoStatus($content["{$type}_status"], $type);
            $input['visibility'] = $content["visibility"] ?? $status;
            if ('publish' == $status || 'waiting' == $status || 'private' == $status) {
                $input['do'] = 'publish';

                if ('private' == $status) {
                    $input['private'] = 1;
                }
            } else {
                $input['do'] = 'save';
            }
        }

        $unattached = Unattached::alloc();

        if ($unattached->have()) {
            while ($unattached->next()) {
                if (false !== strpos($input['text'], $unattached->attachment->url)) {
                    if (!isset($input['attachment'])) {
                        $input['attachment'] = [];
                    }

                    $input['attachment'][] = $unattached->cid;
                }
            }
        }

        if ('page' == $type) {
            $widget = PageEdit::alloc(null, $input, function (PageEdit $page) {
                $page->writePage();
            });
        } else {
            $widget = PostEdit::alloc(null, $input, function (PostEdit $post) {
                $post->writePost();
            });
        }

        return $widget->cid;
    }

    public function wpNewCategory(int $blogId, string $userName, string $password, array $category): int
    {
        $input['name'] = $category['name'];
        $input['slug'] = Common::slugName(Common::strBy($category['slug'] ?? null, $category['name']));
        $input['parent'] = $category['parent_id'] ?? ($category['parent'] ?? 0);
        $input['description'] = Common::strBy($category['description'] ?? null, $category['name']);

        $categoryWidget = CategoryEdit::alloc(null, $input, function (CategoryEdit $category) {
            $category->insertCategory();
        });

        if (!$categoryWidget->have()) {
            throw new Exception(_t('分类不存在'), 404);
        }

        return $categoryWidget->mid;
    }

    public function wpDeletePage(int $blogId, string $userName, string $password, int $pageId): bool
    {
        PageEdit::alloc(null, ['cid' => $pageId], function (PageEdit $page) {
            $page->deletePage();
        });
        return true;
    }

    public function wpEditPage(
        int $blogId,
        int $pageId,
        string $userName,
        string $password,
        array $content,
        bool $publish
    ): bool {
        $content['post_type'] = 'page';
        $this->mwEditPost($pageId, $userName, $password, $content, $publish);
        return true;
    }

    public function mwEditPost(
        int $postId,
        string $userName,
        string $password,
        array $content,
        bool $publish = true
    ): int {
        $content['postId'] = $postId;
        return $this->mwNewPost(1, $userName, $password, $content, $publish);
    }

    public function wpEditPost(int $blogId, string $userName, string $password, int $postId, array $content): bool
    {
        $post = Archive::alloc('type=single', ['cid' => $postId], false);
        if ($post->type == 'attachment') {
            $attachment['title'] = $this->arrayString($content, 'post_title', $post->title ?? '');
            $attachment['slug'] = $this->arrayString($content, 'post_excerpt', $post->slug ?? '');

            $text = $this->attachmentPayload((string) $post->text);
            $text['description'] = $this->arrayString($content, 'description', (string) ($text['description'] ?? ''));

            $attachment['text'] = Common::jsonEncode($text, 0, '{}');

            $updateRows = $this->update($attachment, $this->db->sql()->where('cid = ?', $postId));
            return $updateRows > 0;
        }

        return $this->mwEditPost($postId, $userName, $password, $content) > 0;
    }

    public function wpGetPageList(int $blogId, string $userName, string $password): array
    {
        $pages = PageAdmin::alloc(null, 'status=all');
        $pageStructs = [];

        while ($pages->next()) {
            $pageStructs[] = [
                'dateCreated'      => $this->xmlRpcDate($pages->created),
                'date_created_gmt' => $this->xmlRpcGmtDate($pages->created),
                'page_id'          => $pages->cid,
                'page_title'       => $pages->title,
                'page_parent_id'   => '0',
            ];
        }

        return $pageStructs;
    }

    public function wpGetAuthors(int $blogId, string $userName, string $password): array
    {
        $select = $this->db->select('table.users.uid', 'table.users.name', 'table.users.screenName')
            ->from('table.users');
        $authors = $this->db->fetchAll($select);

        $authorStructs = [];
        foreach ($authors as $author) {
            $authorStructs[] = [
                'user_id'      => $author['uid'],
                'user_login'   => $author['name'],
                'display_name' => $author['screenName']
            ];
        }

        return $authorStructs;
    }

    public function wpSuggestCategories(
        int $blogId,
        string $userName,
        string $password,
        string $category,
        int $maxResults = 0
    ): array {
        $key = Common::filterSearchQuery($category);
        $key = '%' . $key . '%';
        $select = $this->db->select()
            ->from('table.metas')
            ->where(
                'table.metas.type = ? AND (table.metas.name LIKE ? OR slug LIKE ?)',
                'category',
                $key,
                $key
            );

        if ($maxResults > 0) {
            $select->limit($maxResults);
        }

        $categories = MetasFrom::alloc(['query' => $select]);

        $categoryStructs = [];
        while ($categories->next()) {
            $categoryStructs[] = [
                'category_id'   => $categories->mid,
                'category_name' => $categories->name,
            ];
        }

        return $categoryStructs;
    }

    public function wpGetUsersBlogs(string $userName, string $password): array
    {
        return [
            [
                'isAdmin'  => $this->user->pass('administrator', true),
                'url'      => $this->options->siteUrl,
                'blogid'   => '1',
                'blogName' => $this->options->title,
                'xmlrpc'   => $this->options->xmlRpcUrl
            ]
        ];
    }

    public function wpGetProfile(int $blogId, string $userName, string $password): array
    {
        return [
            'user_id'      => $this->user->uid,
            'username'     => $this->user->name,
            'first_name'   => '',
            'last_name'    => '',
            'registered'   => $this->xmlRpcDate($this->user->created),
            'bio'          => '',
            'email'        => $this->user->mail,
            'nickname'     => $this->user->screenName,
            'url'          => $this->user->url,
            'display_name' => $this->user->screenName,
            'roles'        => $this->user->group
        ];
    }

    public function wpGetTags(int $blogId, string $userName, string $password): array
    {
        $struct = [];
        $tags = Cloud::alloc();

        while ($tags->next()) {
            $struct[] = [
                'tag_id'   => $tags->mid,
                'name'     => $tags->name,
                'count'    => $tags->count,
                'slug'     => $tags->slug,
                'html_url' => $tags->permalink,
                'rss_url'  => $tags->feedUrl
            ];
        }

        return $struct;
    }

    public function wpDeleteCategory(int $blogId, string $userName, string $password, int $categoryId): bool
    {
        CategoryEdit::alloc(null, ['mid' => $categoryId], function (CategoryEdit $category) {
            $category->deleteCategory();
        });

        return true;
    }

    public function wpGetCommentCount(int $blogId, string $userName, string $password, int $postId): array
    {
        $stat = Stat::alloc(null, ['cid' => $postId]);

        return [
            'approved'            => $stat->currentPublishedCommentsNum,
            'awaiting_moderation' => $stat->currentWaitingCommentsNum,
            'spam'                => $stat->currentSpamCommentsNum,
            'total_comments'      => $stat->currentCommentsNum
        ];
    }

    public function wpGetPostFormats(int $blogId, string $userName, string $password): array
    {
        return [
            'standard' => _t('标准')
        ];
    }

    public function wpGetPostStatusList(int $blogId, string $userName, string $password): array
    {
        return [
            'draft'   => _t('草稿'),
            'pending' => _t('待审核'),
            'publish' => _t('已发布')
        ];
    }

    public function wpGetPageStatusList(int $blogId, string $userName, string $password): array
    {
        return [
            'draft'   => _t('草稿'),
            'publish' => _t('已发布')
        ];
    }

    public function wpGetCommentStatusList(int $blogId, string $userName, string $password): array
    {
        return [
            'hold'    => _t('待审核'),
            'approve' => _t('显示'),
            'spam'    => _t('垃圾')
        ];
    }

    public function wpGetPageTemplates(int $blogId, string $userName, string $password): array
    {
        $templates = array_flip($this->getTemplates());
        $templates['Default'] = '';

        return $templates;
    }

    public function wpGetOptions(int $blogId, string $userName, string $password, array $options = []): array
    {
        $struct = [];
        if (empty($options)) {
            $options = array_keys($this->wpOptions);
        }

        foreach ($options as $option) {
            if (isset($this->wpOptions[$option])) {
                $struct[$option] = $this->wpOptions[$option];
                if (isset($struct[$option]['option'])) {
                    $struct[$option]['value'] = $this->options->{$struct[$option]['option']};
                    unset($struct[$option]['option']);
                }
            }
        }

        return $struct;
    }

    public function wpSetOptions(int $blogId, string $userName, string $password, array $options = []): array
    {
        $struct = [];
        foreach ($options as $option => $value) {
            if (isset($this->wpOptions[$option])) {
                $struct[$option] = $this->wpOptions[$option];
                if (isset($struct[$option]['option'])) {
                    $struct[$option]['value'] = $this->options->{$struct[$option]['option']};
                    unset($struct[$option]['option']);
                }

                if (!$this->wpOptions[$option]['readonly'] && isset($this->wpOptions[$option]['option'])) {
                    if ('time_zone' === $option) {
                        if (!is_scalar($value)) {
                            continue;
                        }

                        $timezone = (int) $value;
                        if ($timezone < -50400 || $timezone > 50400 || $timezone % 60 !== 0) {
                            continue;
                        }

                        $value = (string) $timezone;
                        $this->db->query(
                            $this->db->update('table.options')
                                ->rows(['value' => \Utils\Zone::legacyId($timezone)])
                                ->where('name = ?', 'timezoneId'),
                            \Typecho\Db::WRITE
                        );
                    }

                    if (
                        $this->db->query($this->db->update('table.options')
                            ->rows(['value' => $value])
                            ->where('name = ?', $this->wpOptions[$option]['option'])) > 0
                    ) {
                        $struct[$option]['value'] = $value;
                    }
                }
            }
        }

        return $struct;
    }

    public function wpGetComment(int $blogId, string $userName, string $password, int $commentId): array
    {
        $comment = CommentsEdit::alloc(null, ['coid' => $commentId], function (CommentsEdit $comment) {
            $comment->getComment();
        });

        if (!$comment->have()) {
            throw new Exception(_t('评论不存在'), 404);
        }

        if (!$comment->commentIsWriteable()) {
            throw new Exception(_t('没有获取评论的权限'), 403);
        }

        return [
            'date_created_gmt' => $this->xmlRpcGmtDate($comment->created),
            'user_id'          => $comment->authorId,
            'comment_id'       => $comment->coid,
            'parent'           => $comment->parent,
            'status'           => $this->typechoToWordpressStatus($comment->status, 'comment'),
            'content'          => $comment->text,
            'link'             => $comment->permalink,
            'post_id'          => $comment->cid,
            'post_title'       => $comment->title,
            'author'           => $comment->author,
            'author_url'       => $comment->url,
            'author_email'     => $comment->mail,
            'author_ip'        => $comment->ip,
            'type'             => $comment->type
        ];
    }

    public function wpGetComments(int $blogId, string $userName, string $password, array $struct): array
    {
        $input = [];
        if (!empty($struct['status'])) {
            $input['status'] = $this->wordpressToTypechoStatus($struct['status'], 'comment');
        } else {
            $input['__typecho_all_comments'] = 'on';
        }

        if (!empty($struct['post_id'])) {
            $input['cid'] = $struct['post_id'];
        }

        $pageSize = 10;
        if (!empty($struct['number'])) {
            $pageSize = max(1, abs((int) $struct['number']));
        }

        if (!empty($struct['offset'])) {
            $offset = abs((int) $struct['offset']);
            $input['page'] = intdiv($offset, $pageSize) + 1;
        }

        $comments = CommentsAdmin::alloc('pageSize=' . $pageSize, $input, false);
        $commentsStruct = [];

        while ($comments->next()) {
            $commentsStruct[] = [
                'date_created_gmt' => $this->xmlRpcGmtDate($comments->created),
                'user_id'          => $comments->authorId,
                'comment_id'       => $comments->coid,
                'parent'           => $comments->parent,
                'status'           => $this->typechoToWordpressStatus($comments->status, 'comment'),
                'content'          => $comments->text,
                'link'             => $comments->permalink,
                'post_id'          => $comments->cid,
                'post_title'       => $comments->title,
                'author'           => $comments->author,
                'author_url'       => $comments->url,
                'author_email'     => $comments->mail,
                'author_ip'        => $comments->ip,
                'type'             => $comments->type
            ];
        }

        return $commentsStruct;
    }

    public function wpDeleteComment(int $blogId, string $userName, string $password, int $commentId): bool
    {
        CommentsEdit::alloc(null, ['coid' => $commentId], function (CommentsEdit $comment) {
            $comment->deleteComment();
        });
        return true;
    }

    public function wpEditComment(int $blogId, string $userName, string $password, int $commentId, array $struct): bool
    {
        $commentRow = $this->db->fetchRow(
            $this->db->select()->from('table.comments')->where('coid = ?', $commentId)->limit(1)
        );

        if (!$commentRow) {
            return false;
        }

        $input = [
            'text' => (string) ($commentRow['text'] ?? ''),
            'author' => (string) ($commentRow['author'] ?? ''),
            'mail' => (string) ($commentRow['mail'] ?? ''),
            'url' => (string) ($commentRow['url'] ?? ''),
        ];

        if (isset($struct['date_created_gmt']) && $struct['date_created_gmt'] instanceof Date) {
            $timestamp = $this->parseXmlRpcTimestamp($struct['date_created_gmt'], new \DateTimeZone('UTC'));
            if ($timestamp !== null) {
                $input['created'] = $timestamp;
            }
        }

        if (isset($struct['status'])) {
            $input['status'] = $this->wordpressToTypechoStatus($struct['status'], 'comment');
        }

        if (isset($struct['content'])) {
            $input['text'] = $struct['content'];
        }

        if (isset($struct['author'])) {
            $input['author'] = trim(strip_tags(Common::removeXSS((string) $struct['author'])));
        }

        if (isset($struct['author_url'])) {
            $input['url'] = Common::safeUrl((string) $struct['author_url']);
        }

        if (isset($struct['author_email'])) {
            $input['mail'] = trim(strip_tags(Common::removeXSS((string) $struct['author_email'])));
        }

        $updatedComment = null;
        CommentsEdit::alloc(null, ['coid' => $commentId], function (CommentsEdit $comment) use ($commentId, $input, &$updatedComment) {
            $updatedComment = $comment->editCommentData($commentId, $input);
        });

        return is_array($updatedComment);
    }

    public function wpNewComment(int $blogId, string $userName, string $password, $path, array $struct): int
    {
        if (is_numeric($path)) {
            $post = Archive::alloc('type=single', ['cid' => $path], false);

            if ($post->have()) {
                $path = $post->permalink;
            }
        } else {
            $path = Common::url(substr($path, strlen((string) $this->options->index)), '/');
        }

        $input = [
            'permalink' => $path,
            'type'      => 'comment'
        ];

        if (isset($struct['comment_author'])) {
            $input['author'] = $this->arrayString($struct, 'comment_author');
        }

        if (isset($struct['comment_author_email'])) {
            $input['mail'] = $this->arrayString($struct, 'comment_author_email');
        }

        if (isset($struct['comment_author_url'])) {
            $input['url'] = $this->arrayString($struct, 'comment_author_url');
        }

        if (isset($struct['comment_parent'])) {
            $input['parent'] = $struct['comment_parent'];
        }

        if (isset($struct['content'])) {
            $input['text'] = $struct['content'];
        }

        $comment = Feedback::alloc(['checkReferer' => false], $input, function (Feedback $comment) {
            $comment->action();
        });
        return $comment->have() ? $comment->coid : 0;
    }

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
            $pageSize = max(1, abs((int) $struct['number']));
        }

        if (!empty($struct['offset'])) {
            $offset = abs((int) $struct['offset']);
            $input['page'] = intdiv($offset, $pageSize) + 1;
        }

        $attachments = AttachmentAdmin::alloc('pageSize=' . $pageSize, $input, false);
        $attachmentsStruct = [];

        while ($attachments->next()) {
            $attachmentsStruct[] = [
                'attachment_id'    => $attachments->cid,
                'date_created_gmt' => $this->xmlRpcGmtDate($attachments->created),
                'parent'           => $attachments->parent,
                'link'             => $attachments->attachment->url,
                'title'            => $attachments->title,
                'caption'          => $attachments->slug,
                'description'      => $attachments->attachment->description,
                'metadata'         => [
                    'file' => $attachments->attachment->path,
                    'size' => $attachments->attachment->size,
                ],
                'thumbnail'        => $attachments->attachment->url,
            ];
        }
        return $attachmentsStruct;
    }

    public function wpGetMediaItem(int $blogId, string $userName, string $password, int $attachmentId): array
    {
        $attachment = AttachmentEdit::alloc(null, ['cid' => $attachmentId]);

        return [
            'attachment_id'    => $attachment->cid,
            'date_created_gmt' => $this->xmlRpcGmtDate($attachment->created),
            'parent'           => $attachment->parent,
            'link'             => $attachment->attachment->url,
            'title'            => $attachment->title,
            'caption'          => $attachment->slug,
            'description'      => $attachment->attachment->description,
            'metadata'         => [
                'file' => $attachment->attachment->path,
                'size' => $attachment->attachment->size,
            ],
            'thumbnail'        => $attachment->attachment->url,
        ];
    }

    public function mwGetPost(int $postId, string $userName, string $password): array
    {
        $post = PostEdit::alloc(null, ['cid' => $postId], false);

        [$excerpt, $more] = $this->getPostExtended($post);
        $categories = array_column($post->categories, 'name');
        $tags = array_column($post->tags, 'name');

        return [
            'dateCreated'            => $this->xmlRpcDate($post->created),
            'userid'                 => $post->authorId,
            'postid'                 => $post->cid,
            'description'            => $excerpt,
            'title'                  => $post->title,
            'link'                   => $post->permalink,
            'permaLink'              => $post->permalink,
            'categories'             => $categories,
            'mt_excerpt'             => $post->plainExcerpt,
            'mt_text_more'           => $more,
            'mt_allow_comments'      => intval($post->allowComment),
            'mt_allow_pings'         => intval($post->allowPing),
            'mt_keywords'            => implode(', ', $tags),
            'wp_slug'                => $post->slug,
            'wp_password'            => $post->password,
            'wp_author'              => $post->author->name,
            'wp_author_id'           => $post->authorId,
            'wp_author_display_name' => $post->author->screenName,
            'date_created_gmt'       => $this->xmlRpcGmtDate($post->created),
            'post_status'            => $this->typechoToWordpressStatus($post->status, 'post'),
            'custom_fields'          => [],
            'sticky'                 => 0
        ];
    }

    public function mwGetRecentPosts(int $blogId, string $userName, string $password, int $postsNum): array
    {
        $posts = PostAdmin::alloc('pageSize=' . $postsNum, 'status=all');

        $postStructs = [];
        while ($posts->next()) {
            [$excerpt, $more] = $this->getPostExtended($posts);
            $categories = array_column($posts->categories, 'name');
            $tags = array_column($posts->tags, 'name');

            $postStructs[] = [
                'dateCreated'            => $this->xmlRpcDate($posts->created),
                'userid'                 => $posts->authorId,
                'postid'                 => $posts->cid,
                'description'            => $excerpt,
                'title'                  => $posts->title,
                'link'                   => $posts->permalink,
                'permaLink'              => $posts->permalink,
                'categories'             => $categories,
                'mt_excerpt'             => $posts->plainExcerpt,
                'mt_text_more'           => $more,
                'wp_more_text'           => $more,
                'mt_allow_comments'      => intval($posts->allowComment),
                'mt_allow_pings'         => intval($posts->allowPing),
                'mt_keywords'            => implode(', ', $tags),
                'wp_slug'                => $posts->slug,
                'wp_password'            => $posts->password,
                'wp_author'              => $posts->author->name,
                'wp_author_id'           => $posts->authorId,
                'wp_author_display_name' => $posts->author->screenName,
                'date_created_gmt'       => $this->xmlRpcGmtDate($posts->created),
                'post_status'            => $this->typechoToWordpressStatus(
                    ($posts->hasSaved || 'post_draft' == $posts->type) ? 'draft' : $posts->status,
                    'post'
                ),
                'custom_fields'          => [],
                'wp_post_format'         => 'standard',
                'date_modified'          => $this->xmlRpcDate($posts->modified),
                'date_modified_gmt'      => $this->xmlRpcGmtDate($posts->modified),
                'wp_post_thumbnail'      => '',
                'sticky'                 => 0
            ];
        }

        return $postStructs;
    }

    public function mwGetCategories(int $blogId, string $userName, string $password): array
    {
        $categories = CategoryRows::alloc();

        $categoryStructs = [];
        while ($categories->next()) {
            $categoryStructs[] = [
                'categoryId'          => $categories->mid,
                'parentId'            => $categories->parent,
                'categoryName'        => $categories->name,
                'categoryDescription' => $categories->description,
                'description'         => $categories->name,
                'htmlUrl'             => $categories->permalink,
                'rssUrl'              => $categories->feedUrl,
            ];
        }

        return $categoryStructs;
    }

    public function mwNewMediaObject(int $blogId, string $userName, string $password, array $data): array
    {
        $result = Upload::uploadHandle($data);

        if (false === $result) {
            throw new Exception('upload failed', -32001);
        } else {
            $insertId = $this->insert([
                'title'        => $result['name'],
                'slug'         => $result['name'],
                'type'         => 'attachment',
                'status'       => 'publish',
                'text'         => Common::jsonEncode($result, 0, '{}'),
                'allowComment' => 1,
                'allowPing'    => 0,
                'allowFeed'    => 1
            ]);

            $this->db->fetchRow($this->select()->where('table.contents.cid = ?', $insertId)
                ->where('table.contents.type = ?', 'attachment'), [$this, 'push']);

            self::pluginHandle()->call('upload', $this);

            return [
                'file' => $this->attachment->name,
                'url'  => $this->attachment->url
            ];
        }
    }

    public function mtGetRecentPostTitles(int $blogId, string $userName, string $password, int $postsNum): array
    {
        $posts = PostAdmin::alloc('pageSize=' . $postsNum, 'status=all');
        $postTitleStructs = [];
        while ($posts->next()) {
            $postTitleStructs[] = [
                'dateCreated'      => $this->xmlRpcDate($posts->created),
                'userid'           => $posts->authorId,
                'postid'           => $posts->cid,
                'title'            => $posts->title,
                'date_created_gmt' => $this->xmlRpcGmtDate($posts->created)
            ];
        }

        return $postTitleStructs;
    }

    public function mtGetCategoryList(int $blogId, string $userName, string $password): array
    {
        $categories = CategoryRows::alloc();

        $categoryStructs = [];
        while ($categories->next()) {
            $categoryStructs[] = [
                'categoryId'   => $categories->mid,
                'categoryName' => $categories->name,
            ];
        }
        return $categoryStructs;
    }

    public function mtGetPostCategories(int $postId, string $userName, string $password): array
    {
        $post = PostEdit::alloc(null, ['cid' => $postId], false);

        $categories = [];
        foreach ($post->categories as $category) {
            $categories[] = [
                'categoryName' => $category['name'],
                'categoryId'   => $category['mid'],
                'isPrimary'    => true
            ];
        }

        return $categories;
    }

    public function mtSetPostCategories(int $postId, string $userName, string $password, array $categories): bool
    {
        PostEdit::alloc(null, ['cid' => $postId], function (PostEdit $post) use ($postId, $categories) {
            $post->setCategories($postId, array_column($categories, 'categoryId'), 'publish' == $post->status);
        });

        return true;
    }

    public function mtPublishPost(int $postId, string $userName, string $password): bool
    {
        PostEdit::alloc(null, ['cid' => $postId, 'status' => 'publish'], function (PostEdit $post) {
            $post->markPost();
        });

        return true;
    }

    public function bloggerGetUsersBlogs(int $blogId, string $userName, string $password): array
    {
        return [
            [
                'isAdmin'  => $this->user->pass('administrator', true),
                'url'      => $this->options->siteUrl,
                'blogid'   => 1,
                'blogName' => $this->options->title,
                'xmlrpc'   => $this->options->xmlRpcUrl
            ]
        ];
    }

    public function bloggerGetUserInfo(int $blogId, string $userName, string $password): array
    {
        return [
            'nickname'  => $this->user->screenName,
            'userid'    => $this->user->uid,
            'url'       => $this->user->url,
            'email'     => $this->user->mail,
            'lastname'  => '',
            'firstname' => ''
        ];
    }

    public function bloggerGetPost(int $blogId, int $postId, string $userName, string $password): array
    {
        $post = PostEdit::alloc(null, ['cid' => $postId]);
        $categories = array_column($post->categories, 'name');

        $content = '<title>' . $post->title . '</title>';
        $content .= '<category>' . implode(',', $categories) . '</category>';
        $content .= stripslashes($post->text);

        return [
            'userid'      => $post->authorId,
            'dateCreated' => $this->xmlRpcDate($post->created),
            'content'     => $content,
            'postid'      => $post->cid
        ];
    }

    public function bloggerDeletePost(int $blogId, int $postId, string $userName, string $password, $publish): bool
    {
        PostEdit::alloc(null, ['cid' => $postId], function (PostEdit $post) {
            $post->deletePost();
        });
        return true;
    }

    public function bloggerGetRecentPosts(int $blogId, string $userName, string $password, int $postsNum): array
    {
        $posts = PostAdmin::alloc('pageSize=' . $postsNum, 'status=all');

        $postStructs = [];
        while ($posts->next()) {
            $categories = array_column($posts->categories, 'name');

            $content = '<title>' . $posts->title . '</title>';
            $content .= '<category>' . implode(',', $categories) . '</category>';
            $content .= stripslashes($posts->text);

            $struct = [
                'userid'      => $posts->authorId,
                'dateCreated' => $this->xmlRpcDate($posts->created),
                'content'     => $content,
                'postid'      => $posts->cid,
            ];
            $postStructs[] = $struct;
        }

        return $postStructs;
    }

    public function bloggerGetTemplate(int $blogId, string $userName, string $password, $template): bool
    {
        return true;
    }

    public function bloggerSetTemplate(int $blogId, string $userName, string $password, $content, $template): bool
    {
        return true;
    }

    public function pingbackPing(string $source, string $target): int
    {
        if ((int) $this->options->allowXmlRpc !== 2) {
            throw new Exception(_t('Pingback 接口已关闭'), 49);
        }

        $pathInfo = Common::url(substr($target, strlen((string) $this->options->index)), '/');
        $post = Router::match($pathInfo);

        $params = Common::parseUrl($source);
        if (!isset($params['host']) || !isset($params['scheme']) || !in_array($params['scheme'], ['http', 'https'])) {
            throw new Exception(_t('源地址服务器错误'), 16);
        }

        if (!$this->isSafePingbackHost((string) $params['host'])) {
            throw new Exception(_t('源地址服务器错误'), 16);
        }

        if (!($post instanceof Archive) || !$post->have() || !$post->is('single')) {
            throw new Exception(_t('这个目标地址不存在'), 33);
        }

        if (!$post->allowPing) {
            throw new Exception(_t('目标地址禁止Ping'), 49);
        }

        $pingNum = $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where(
                'table.comments.cid = ? AND table.comments.url = ? AND table.comments.type <> ?',
                $post->cid,
                $source,
                'comment'
            ))->num;

        if ($pingNum > 0) {
            throw new Exception(_t('PingBack已经存在'), 48);
        }

        try {
            $pingbackRequest = new Pingback($source, $target);

            $pingback = [
                'cid'     => $post->cid,
                'created' => $this->options->time,
                'agent'   => $this->request->getAgent(),
                'ip'      => $this->request->getIp(),
                'author'  => $pingbackRequest->getTitle(),
                'url'     => Common::safeUrl($source),
                'text'    => $pingbackRequest->getContent(),
                'ownerId' => $post->author->uid,
                'type'    => 'pingback',
                'status'  => $this->options->commentsRequireModeration ? 'waiting' : 'approved'
            ];

            $pingback = self::pluginHandle()->filter('pingback', $pingback, $post);
            $insertId = Comments::alloc()->insert($pingback);
            self::pluginHandle()->call('finishPingback', $this);

            return $insertId;
        } catch (WidgetException $e) {
            throw new Exception(_t('源地址服务器错误'), 16);
        }
    }

    private function isSafePingbackHost(string $host): bool
    {
        if (!Common::checkSafeHost($host)) {
            return false;
        }

        $ipv4s = gethostbynamel($host);
        if (is_array($ipv4s) && !empty($ipv4s)) {
            foreach ($ipv4s as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return false;
                }
            }
            return true;
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (!is_array($records) || empty($records)) {
            return false;
        }

        foreach ($records as $record) {
            $ipv6 = (string) ($record['ipv6'] ?? '');
            if (
                $ipv6 === ''
                || filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                return false;
            }
        }

        return true;
    }

    private function arrayString(array $data, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return $default;
        }

        $value = $data[$key];
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    private function xmlRpcDate(int $timestamp): Date
    {
        return new Date($this->options->getDateTime($timestamp));
    }

    private function xmlRpcGmtDate(int $timestamp): Date
    {
        return new Date($this->options->getUtcDateTime($timestamp)->format('Ymd\TH:i:s\Z'));
    }

    private function parseXmlRpcTimestamp(Date $date, \DateTimeZone $defaultZone): ?int
    {
        $iso = preg_replace('/\.[0-9]{1,6}(?=Z|[+-]\d{2}:?\d{2}$)/', '', $date->getIso()) ?? $date->getIso();
        $dateTime = \DateTimeImmutable::createFromFormat('!Ymd\TH:i:s', substr($iso, 0, 17), $defaultZone);

        if (!$dateTime instanceof \DateTimeImmutable && strlen($iso) > 17) {
            $formats = str_ends_with($iso, 'Z')
                ? ['!Ymd\TH:i:s\Z']
                : ['!Ymd\TH:i:sP', '!Ymd\TH:i:sO'];

            foreach ($formats as $format) {
                $dateTime = \DateTimeImmutable::createFromFormat($format, $iso, $defaultZone);
                if ($dateTime instanceof \DateTimeImmutable) {
                    break;
                }
            }
        }

        return $dateTime instanceof \DateTimeImmutable ? $dateTime->getTimestamp() : null;
    }

    private function attachmentPayload(string $text): array
    {
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function action()
    {
        if (0 == $this->options->allowXmlRpc) {
            throw new Exception(_t('请求的地址不存在'), 404);
        }

        if (isset($this->request->rsd)) {
            $xml =
            <<<EOF
<?xml version="1.0" encoding="{$this->options->charset}"?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
    <service>
        <engineName>Typecho</engineName>
        <engineLink>https://typecho.org/</engineLink>
        <homePageLink>{$this->options->siteUrl}</homePageLink>
        <apis>
            <api name="WordPress" blogID="1" preferred="true" apiLink="{$this->options->xmlRpcUrl}" />
            <api name="Movable Type" blogID="1" preferred="false" apiLink="{$this->options->xmlRpcUrl}" />
            <api name="MetaWeblog" blogID="1" preferred="false" apiLink="{$this->options->xmlRpcUrl}" />
            <api name="Blogger" blogID="1" preferred="false" apiLink="{$this->options->xmlRpcUrl}" />
        </apis>
    </service>
</rsd>
EOF;
            $this->response->throwCallback(static function () use ($xml) {
                echo $xml;
            }, 'text/xml');
        } elseif (isset($this->request->wlw)) {
            $xml =
            <<<EOF
<?xml version="1.0" encoding="{$this->options->charset}"?>
<manifest xmlns="http://schemas.microsoft.com/wlw/manifest/weblog">
    <options>
        <supportsKeywords>Yes</supportsKeywords>
        <supportsFileUpload>Yes</supportsFileUpload>
        <supportsExtendedEntries>Yes</supportsExtendedEntries>
        <supportsCustomDate>Yes</supportsCustomDate>
        <supportsCategories>Yes</supportsCategories>

        <supportsCategoriesInline>Yes</supportsCategoriesInline>
        <supportsMultipleCategories>Yes</supportsMultipleCategories>
        <supportsHierarchicalCategories>Yes</supportsHierarchicalCategories>
        <supportsNewCategories>Yes</supportsNewCategories>
        <supportsNewCategoriesInline>Yes</supportsNewCategoriesInline>
        <supportsCommentPolicy>Yes</supportsCommentPolicy>

        <supportsPingPolicy>Yes</supportsPingPolicy>
        <supportsAuthor>Yes</supportsAuthor>
        <supportsSlug>Yes</supportsSlug>
        <supportsPassword>Yes</supportsPassword>
        <supportsExcerpt>Yes</supportsExcerpt>
        <supportsTrackbacks>Yes</supportsTrackbacks>

        <supportsPostAsDraft>Yes</supportsPostAsDraft>

        <supportsPages>Yes</supportsPages>
        <supportsPageParent>No</supportsPageParent>
        <supportsPageOrder>Yes</supportsPageOrder>
        <requiresXHTML>True</requiresXHTML>
        <supportsAutoUpdate>No</supportsAutoUpdate>

    </options>
</manifest>
EOF;
            $this->response->throwCallback(static function () use ($xml) {
                echo $xml;
            }, 'text/xml');
        } else {
            $api = [
                'wp.getPage'                => [$this, 'wpGetPage'],
                'wp.getPages'               => [$this, 'wpGetPages'],
                'wp.newPage'                => [$this, 'wpNewPage'],
                'wp.deletePage'             => [$this, 'wpDeletePage'],
                'wp.editPage'               => [$this, 'wpEditPage'],
                'wp.getPageList'            => [$this, 'wpGetPageList'],
                'wp.getAuthors'             => [$this, 'wpGetAuthors'],
                'wp.getCategories'          => [$this, 'mwGetCategories'],
                'wp.newCategory'            => [$this, 'wpNewCategory'],
                'wp.suggestCategories'      => [$this, 'wpSuggestCategories'],
                'wp.uploadFile'             => [$this, 'mwNewMediaObject'],

                'wp.getUsersBlogs'          => [$this, 'wpGetUsersBlogs'],
                'wp.getTags'                => [$this, 'wpGetTags'],
                'wp.deleteCategory'         => [$this, 'wpDeleteCategory'],
                'wp.getCommentCount'        => [$this, 'wpGetCommentCount'],
                'wp.getPostStatusList'      => [$this, 'wpGetPostStatusList'],
                'wp.getPageStatusList'      => [$this, 'wpGetPageStatusList'],
                'wp.getPageTemplates'       => [$this, 'wpGetPageTemplates'],
                'wp.getOptions'             => [$this, 'wpGetOptions'],
                'wp.setOptions'             => [$this, 'wpSetOptions'],
                'wp.getComment'             => [$this, 'wpGetComment'],
                'wp.getComments'            => [$this, 'wpGetComments'],
                'wp.deleteComment'          => [$this, 'wpDeleteComment'],
                'wp.editComment'            => [$this, 'wpEditComment'],
                'wp.newComment'             => [$this, 'wpNewComment'],
                'wp.getCommentStatusList'   => [$this, 'wpGetCommentStatusList'],

                'wp.getProfile'             => [$this, 'wpGetProfile'],
                'wp.getPostFormats'         => [$this, 'wpGetPostFormats'],
                'wp.getMediaLibrary'        => [$this, 'wpGetMediaLibrary'],
                'wp.getMediaItem'           => [$this, 'wpGetMediaItem'],
                'wp.editPost'               => [$this, 'wpEditPost'],

                'blogger.getUsersBlogs'     => [$this, 'bloggerGetUsersBlogs'],
                'blogger.getUserInfo'       => [$this, 'bloggerGetUserInfo'],
                'blogger.getPost'           => [$this, 'bloggerGetPost'],
                'blogger.getRecentPosts'    => [$this, 'bloggerGetRecentPosts'],
                'blogger.getTemplate'       => [$this, 'bloggerGetTemplate'],
                'blogger.setTemplate'       => [$this, 'bloggerSetTemplate'],
                'blogger.deletePost'        => [$this, 'bloggerDeletePost'],

                'metaWeblog.newPost'        => [$this, 'mwNewPost'],
                'metaWeblog.editPost'       => [$this, 'mwEditPost'],
                'metaWeblog.getPost'        => [$this, 'mwGetPost'],
                'metaWeblog.getRecentPosts' => [$this, 'mwGetRecentPosts'],
                'metaWeblog.getCategories'  => [$this, 'mwGetCategories'],
                'metaWeblog.newMediaObject' => [$this, 'mwNewMediaObject'],

                'metaWeblog.deletePost'     => [$this, 'bloggerDeletePost'],
                'metaWeblog.getTemplate'    => [$this, 'bloggerGetTemplate'],
                'metaWeblog.setTemplate'    => [$this, 'bloggerSetTemplate'],
                'metaWeblog.getUsersBlogs'  => [$this, 'bloggerGetUsersBlogs'],

                'mt.getCategoryList'        => [$this, 'mtGetCategoryList'],
                'mt.getRecentPostTitles'    => [$this, 'mtGetRecentPostTitles'],
                'mt.getPostCategories'      => [$this, 'mtGetPostCategories'],
                'mt.setPostCategories'      => [$this, 'mtSetPostCategories'],
                'mt.publishPost'            => [$this, 'mtPublishPost'],

                'pingback.ping'             => [$this, 'pingbackPing'],
            ];

            if (1 == $this->options->allowXmlRpc) {
                unset($api['pingback.ping']);
            }

            $server = new Server($api);
            $server->setHook($this);
            $server->serve();
        }
    }

    private function getPostExtended(Contents $content): array
    {
        $agent = $this->request->getAgent();

        if (
            false !== strpos($agent, 'wp-iphone')
            || false !== strpos($agent, 'wp-blackberry')
            || false !== strpos($agent, 'wp-android')
            || false !== strpos($agent, 'plain-text')
            || $this->options->xmlrpcMarkdown
        ) {
            $text = $content->text;
        } else {
            $text = $content->content;
        }

        $post = explode('<!--more-->', $text, 2);
        return [
            $this->options->xmlrpcMarkdown ? $post[0] : Common::fixHtml($post[0]),
            isset($post[1]) ? Common::fixHtml($post[1]) : null
        ];
    }

    private function typechoToWordpressStatus(string $status, string $type = 'post'): string
    {
        if ('post' == $type) {
            switch ($status) {
                case 'waiting':
                    return 'pending';
                case 'publish':
                case 'draft':
                case 'private':
                    return $status;
                default:
                    return 'publish';
            }
        } elseif ('page' == $type) {
            switch ($status) {
                case 'publish':
                case 'draft':
                case 'private':
                    return $status;
                default:
                    return 'publish';
            }
        } elseif ('comment' == $type) {
            switch ($status) {
                case 'waiting':
                    return 'hold';
                case 'spam':
                    return $status;
                case 'publish':
                case 'approved':
                default:
                    return 'approve';
            }
        }

        return '';
    }

    private function wordpressToTypechoStatus(string $status, string $type = 'post'): string
    {
        if ('post' == $type) {
            switch ($status) {
                case 'pending':
                    return 'waiting';
                case 'publish':
                case 'draft':
                case 'private':
                case 'waiting':
                    return $status;
                default:
                    return 'publish';
            }
        } elseif ('page' == $type) {
            switch ($status) {
                case 'publish':
                case 'draft':
                case 'private':
                    return $status;
                default:
                    return 'publish';
            }
        } elseif ('comment' == $type) {
            switch ($status) {
                case 'hold':
                case 'waiting':
                    return 'waiting';
                case 'spam':
                    return $status;
                case 'approve':
                case 'publish':
                case 'approved':
                default:
                    return 'approved';
            }
        }

        return '';
    }
}
