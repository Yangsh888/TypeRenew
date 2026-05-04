<?php

namespace Widget;

use DateTimeImmutable;
use DateTimeZone;
use IXR\Date;
use IXR\Exception;
use IXR\Server;
use Typecho\Common;
use Typecho\Timezone as SiteTimezone;
use Widget\Base\Contents;
use Widget\Contents\Attachment\Unattached;
use Widget\Contents\Page\Admin as PageAdmin;
use Widget\Contents\Post\Admin as PostAdmin;
use Widget\Contents\Post\Edit as PostEdit;
use Widget\Contents\Page\Edit as PageEdit;
use Widget\Metas\Category\Edit as CategoryEdit;
use Widget\Metas\Category\Rows as CategoryRows;
use Widget\Metas\From as MetasFrom;
use Widget\Metas\Tag\Cloud;
use Widget\XmlRpc\HookHandler as XmlRpcHookHandler;
use Widget\XmlRpc\MethodRegistry as XmlRpcMethodRegistry;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class XmlRpc extends Contents implements ActionInterface
{
    public function toSiteRpcDate(int $timestamp): Date
    {
        return new Date($timestamp + SiteTimezone::offsetAt($timestamp));
    }

    public function toGmtRpcDate(int $timestamp): Date
    {
        return new Date($timestamp);
    }

    public function fromSiteRpcDate(Date $date): int
    {
        return $this->parseRpcDate($date, false);
    }

    public function fromUtcRpcDate(Date $date): int
    {
        return $this->parseRpcDate($date, true);
    }

    private function parseRpcDate(Date $date, bool $utc): int
    {
        $iso = trim($date->getIso());
        if ($iso === '') {
            return 0;
        }

        $normalized = $iso;
        if (preg_match('/^[0-9]{8}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}[0-9]{2}$/', $normalized) === 1) {
            $normalized = substr($normalized, 0, -5) . substr($normalized, -5, 3) . ':' . substr($normalized, -2);
        }

        if (preg_match('/^[0-9]{8}T[0-9]{2}:[0-9]{2}:[0-9]{2}(Z|[+-][0-9]{2}:[0-9]{2})$/', $normalized) === 1) {
            $dateTime = $this->createStrictDateTime('!Ymd\TH:i:sP', $normalized);
            if ($dateTime instanceof DateTimeImmutable) {
                return $dateTime->getTimestamp();
            }
        }

        if (preg_match('/^([0-9]{4})([0-9]{2})([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})$/', $normalized, $matches) !== 1) {
            return 0;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $hour = (int) $matches[4];
        $minute = (int) $matches[5];
        $second = (int) $matches[6];

        if ($utc) {
            $dateTime = $this->createStrictDateTime(
                '!Y-m-d H:i:s',
                sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
                new DateTimeZone('UTC')
            );

            return $dateTime instanceof DateTimeImmutable ? $dateTime->getTimestamp() : 0;
        }

        return SiteTimezone::fromLocalParts($year, $month, $day, $hour, $minute, $second) ?? 0;
    }

    private function createStrictDateTime(
        string $format,
        string $value,
        ?DateTimeZone $zone = null
    ): ?DateTimeImmutable {
        $dateTime = DateTimeImmutable::createFromFormat($format, $value, $zone);
        if (!$dateTime instanceof DateTimeImmutable) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();
        if (
            is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)
        ) {
            return null;
        }

        return $dateTime;
    }

    /**
     * 如果这里没有重载, 每次都会被默认执行
     *
     * @param bool $run 是否执行
     */
    public function execute(bool $run = false)
    {
        if ($run) {
            parent::execute();
        }

        // XML-RPC 请求不会走常规表单令牌校验。
        $this->security->enable(false);
    }

    /**
     * 获取pageId指定的page
     * about wp xmlrpc api, you can see http://codex.wordpress.org/XML-RPC
     *
     * @param int $blogId
     * @param int $pageId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPage(int $blogId, int $pageId, string $userName, string $password): array
    {
        $page = PageEdit::alloc(null, ['cid' => $pageId], false);
        return $this->buildPageStruct($page, $this->typechoToWordpressStatus($page->status, 'page'));
    }

    /**
     * 获取所有的page
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPages(int $blogId, string $userName, string $password): array
    {
        $pages = PageAdmin::alloc(null, 'status=all');

        $pageStructs = [];

        while ($pages->next()) {
            $pageStructs[] = $this->buildPageStruct($pages, $this->pageStatus($pages), true);
        }

        return $pageStructs;
    }

    /**
     * 撰写一个新page
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param array $content
     * @param bool $publish
     * @return int
     */
    public function wpNewPage(int $blogId, string $userName, string $password, array $content, bool $publish): int
    {
        $content['post_type'] = 'page';
        return $this->mwNewPost($blogId, $userName, $password, $content, $publish);
    }

    /**
     * MetaWeblog API
     * about MetaWeblog API, you can see http://www.xmlrpc.com/metaWeblogApi
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param array $content
     * @param bool $publish
     * @return int
     */
    public function mwNewPost(int $blogId, string $userName, string $password, array $content, bool $publish): int
    {
        $input = [];
        $type = $this->resolveContentType($content);
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
        $input['category'] = $this->resolveCategoryIds($blogId, $userName, $password, $content['categories'] ?? []);

        if (isset($content['postId'])) {
            $input['cid'] = $content['postId'];
        }

        if ('page' == $type && isset($content['wp_page_template'])) {
            $input['template'] = $content['wp_page_template'];
        }

        if (isset($content['dateCreated'])) {
            $input['created'] = $this->fromSiteRpcDate($content['dateCreated']);
        }

        $input['allowComment'] = $this->resolveDiscussionSetting(
            $content,
            'mt_allow_comments',
            $this->options->defaultAllowComment
        );
        $input['allowPing'] = $this->resolveDiscussionSetting(
            $content,
            'mt_allow_pings',
            $this->options->defaultAllowPing
        );

        $input['allowFeed'] = $this->options->defaultAllowFeed;
        $input['markdown'] = $this->options->xmlrpcMarkdown;
        $this->applyContentStatus($input, $content, $type, $publish);

        /** 对未归档附件进行归档 */
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

        $widget = $this->writeContentWidget($type, $input);
        return $widget->cid;
    }

    /**
     * 添加一个新的分类
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param array $category
     * @return int
     */
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

    /**
     * 删除pageId指定的page
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $pageId
     * @return bool
     */
    public function wpDeletePage(int $blogId, string $userName, string $password, int $pageId): bool
    {
        PageEdit::alloc(null, ['cid' => $pageId], function (PageEdit $page) {
            $page->deletePage();
        });
        return true;
    }

    /**
     * 编辑pageId指定的page
     *
     * @param int $blogId
     * @param int $pageId
     * @param string $userName
     * @param string $password
     * @param array $content
     * @param bool $publish
     * @return bool
     */
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

    /**
     * 编辑post
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @param array $content
     * @param bool $publish
     * @return int
     */
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

    /**
     * 编辑postId指定的post
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $postId
     * @param array $content
     * @return bool
     */
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

    /**
     * 获取page列表，没有wpGetPages获得的详细
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPageList(int $blogId, string $userName, string $password): array
    {
        $pages = PageAdmin::alloc(null, 'status=all');
        $pageStructs = [];

        while ($pages->next()) {
            $pageStructs[] = [
                'dateCreated'      => $this->toSiteRpcDate((int) $pages->created),
                'date_created_gmt' => $this->toGmtRpcDate((int) $pages->created),
                'page_id'          => $pages->cid,
                'page_title'       => $pages->title,
                'page_parent_id'   => '0',
            ];
        }

        return $pageStructs;
    }

    /**
     * 获得一个由blog所有作者的信息组成的数组
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
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

    /**
     * 获取由给定的string开头的链接组成的数组
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param string $category
     * @param int $maxResults
     * @return array
     */
    public function wpSuggestCategories(
        int $blogId,
        string $userName,
        string $password,
        string $category,
        int $maxResults = 0
    ): array {
        /** 构造出查询语句并且查询*/
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

    /**
     * 获取用户博客列表
     *
     * @param string $userName 用户名
     * @param string $password 密码
     * @return array
     */
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

    /**
     * 获取用户资料
     *
     * @param int $blogId
     * @param string $userName 用户名
     * @param string $password 密码
     * @return array
     */
    public function wpGetProfile(int $blogId, string $userName, string $password): array
    {
        return [
            'user_id'      => $this->user->uid,
            'username'     => $this->user->name,
            'first_name'   => '',
            'last_name'    => '',
            'registered'   => $this->toSiteRpcDate((int) $this->user->created),
            'bio'          => '',
            'email'        => $this->user->mail,
            'nickname'     => $this->user->screenName,
            'url'          => $this->user->url,
            'display_name' => $this->user->screenName,
            'roles'        => $this->user->group
        ];
    }

    /**
     * 获取标签列表
     *
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
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

    /**
     * 删除分类
     *
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $categoryId
     * @return bool
     */
    public function wpDeleteCategory(int $blogId, string $userName, string $password, int $categoryId): bool
    {
        CategoryEdit::alloc(null, ['mid' => $categoryId], function (CategoryEdit $category) {
            $category->deleteCategory();
        });

        return true;
    }

    /**
     * 获取文章类型列表
     *
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPostFormats(int $blogId, string $userName, string $password): array
    {
        return [
            'standard' => _t('标准')
        ];
    }

    /**
     * 获取文章状态列表
     *
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPostStatusList(int $blogId, string $userName, string $password): array
    {
        return [
            'draft'   => _t('草稿'),
            'pending' => _t('待审核'),
            'publish' => _t('已发布')
        ];
    }

    /**
     * 获取页面状态列表
     *
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPageStatusList(int $blogId, string $userName, string $password): array
    {
        return [
            'draft'   => _t('草稿'),
            'publish' => _t('已发布')
        ];
    }

    /**
     * 获取页面模板
     *
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPageTemplates(int $blogId, string $userName, string $password): array
    {
        $templates = array_flip($this->getTemplates());
        $templates['Default'] = '';

        return $templates;
    }

    /**
     * 获取指定id的post
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function mwGetPost(int $postId, string $userName, string $password): array
    {
        $post = PostEdit::alloc(null, ['cid' => $postId], false);
        return $this->buildPostStruct($post, $this->typechoToWordpressStatus($post->status, 'post'));
    }

    /**
     * 获取前$postsNum个post
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $postsNum
     * @return array
     */
    public function mwGetRecentPosts(int $blogId, string $userName, string $password, int $postsNum): array
    {
        $posts = PostAdmin::alloc('pageSize=' . $postsNum, 'status=all');

        $postStructs = [];
        while ($posts->next()) {
            $postStructs[] = $this->buildPostStruct($posts, $this->postStatus($posts), true);
        }

        return $postStructs;
    }

    /**
     * 获取所有的分类
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
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

    /**
     * 获取 $postNum个post title
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $postsNum
     * @return array
     */
    public function mtGetRecentPostTitles(int $blogId, string $userName, string $password, int $postsNum): array
    {
        $posts = PostAdmin::alloc('pageSize=' . $postsNum, 'status=all');
        $postTitleStructs = [];
        while ($posts->next()) {
            $postTitleStructs[] = [
                'dateCreated'      => $this->toSiteRpcDate((int) $posts->created),
                'userid'           => $posts->authorId,
                'postid'           => $posts->cid,
                'title'            => $posts->title,
                'date_created_gmt' => $this->toGmtRpcDate((int) $posts->created)
            ];
        }

        return $postTitleStructs;
    }

    /**
     * 获取分类列表
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
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

    /**
     * 获取指定post的分类
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function mtGetPostCategories(int $postId, string $userName, string $password): array
    {
        $post = PostEdit::alloc(null, ['cid' => $postId], false);

        /** 格式化categories*/
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

    /**
     * 修改post的分类
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @param array $categories
     * @return bool
     * @throws \Typecho\Db\Exception
     */
    public function mtSetPostCategories(int $postId, string $userName, string $password, array $categories): bool
    {
        PostEdit::alloc(null, ['cid' => $postId], function (PostEdit $post) use ($postId, $categories) {
            $post->setCategories($postId, array_column($categories, 'categoryId'), 'publish' == $post->status);
        });

        return true;
    }

    /**
     * 发布(重建)数据
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @return bool
     */
    public function mtPublishPost(int $postId, string $userName, string $password): bool
    {
        PostEdit::alloc(null, ['cid' => $postId, 'status' => 'publish'], function (PostEdit $post) {
            $post->markPost();
        });

        return true;
    }

    /**
     * 取得当前用户的所有blog
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
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

    /**
     * 返回当前用户的信息
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
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

    /**
     * 获取当前作者的一个指定id的post的详细信息
     *
     * @param int $blogId
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function bloggerGetPost(int $blogId, int $postId, string $userName, string $password): array
    {
        $post = PostEdit::alloc(null, ['cid' => $postId]);
        $categories = array_column($post->categories, 'name');

        $content = '<title>' . $post->title . '</title>';
        $content .= '<category>' . implode(',', $categories) . '</category>';
        $content .= stripslashes($post->text);

        return [
            'userid'      => $post->authorId,
            'dateCreated' => $this->toSiteRpcDate((int) $post->created),
            'content'     => $content,
            'postid'      => $post->cid
        ];
    }

    /**
     * bloggerDeletePost
     * 删除文章
     *
     * @param int $blogId
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @param mixed $publish
     * @return bool
     */
    public function bloggerDeletePost(int $blogId, int $postId, string $userName, string $password, $publish): bool
    {
        PostEdit::alloc(null, ['cid' => $postId], function (PostEdit $post) {
            $post->deletePost();
        });
        return true;
    }

    /**
     * 获取当前作者前postsNum个post
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $postsNum
     * @return array
     */
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
                'dateCreated' => $this->toSiteRpcDate((int) $posts->created),
                'content'     => $content,
                'postid'      => $posts->cid,
            ];
            $postStructs[] = $struct;
        }

        return $postStructs;
    }

    /**
     * bloggerGetTemplate
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $template
     * @return bool
     */
    public function bloggerGetTemplate(int $blogId, string $userName, string $password, $template): bool
    {
        return true;
    }

    /**
     * bloggerSetTemplate
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $content
     * @param mixed $template
     * @return bool
     */
    public function bloggerSetTemplate(int $blogId, string $userName, string $password, $content, $template): bool
    {
        return true;
    }

    public function arrayString(array $data, string $key, string $default = ''): string
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

    private function attachmentPayload(string $text): array
    {
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function resolveContentType(array $content): string
    {
        return isset($content['post_type']) && $content['post_type'] == 'page' ? 'page' : 'post';
    }

    private function resolveCategoryIds(int $blogId, string $userName, string $password, $categories): array
    {
        if (empty($categories) || !is_array($categories)) {
            return [];
        }

        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryId = $this->resolveCategoryIdByName((string) $category);
            if ($categoryId === null) {
                $this->wpNewCategory($blogId, $userName, $password, ['name' => $category]);
                $categoryId = $this->resolveCategoryIdByName((string) $category);
            }

            if ($categoryId !== null) {
                $categoryIds[] = $categoryId;
            }
        }

        return $categoryIds;
    }

    private function resolveCategoryIdByName(string $name): ?int
    {
        $category = $this->db->fetchObject(
            $this->db->select('mid')
                ->from('table.metas')
                ->where('type = ? AND name = ?', 'category', $name)
                ->limit(1)
        );

        return isset($category->mid) ? (int) $category->mid : null;
    }

    private function resolveDiscussionSetting(array $content, string $key, int $default): int
    {
        if (!isset($content[$key])) {
            return $default;
        }

        if ($content[$key] == 1 || $content[$key] == 'open') {
            return 1;
        }

        if ($content[$key] == 0 || $content[$key] == 'closed') {
            return 0;
        }

        return $default;
    }

    private function applyContentStatus(array &$input, array $content, string $type, bool $publish): void
    {
        $input['do'] = $publish ? 'publish' : 'save';

        if (!isset($content["{$type}_status"])) {
            return;
        }

        $status = $this->wordpressToTypechoStatus($content["{$type}_status"], $type);
        $input['visibility'] = $content['visibility'] ?? $status;
        $input['do'] = in_array($status, ['publish', 'waiting', 'private'], true) ? 'publish' : 'save';

        if ($status == 'private') {
            $input['private'] = 1;
        }
    }

    private function writeContentWidget(string $type, array $input): Contents
    {
        if ($type == 'page') {
            return PageEdit::alloc(null, $input, function (PageEdit $page) {
                $page->writePage();
            });
        }

        return PostEdit::alloc(null, $input, function (PostEdit $post) {
            $post->writePost();
        });
    }

    private function buildPageStruct(Contents $page, string $status, bool $list = false): array
    {
        [$excerpt, $more] = $this->getPostExtended($page);

        return [
            'dateCreated' => $this->toSiteRpcDate((int) $page->created),
            'date_modified' => $this->toSiteRpcDate((int) $page->modified),
            'userid' => $page->authorId,
            'page_id' => $list ? intval($page->cid) : $page->cid,
            'page_status' => $status,
            'description' => $excerpt,
            'title' => $page->title,
            'link' => $page->permalink,
            'permaLink' => $page->permalink,
            'categories' => $page->categories,
            'excerpt' => $page->plainExcerpt,
            'text_more' => $more,
            'mt_allow_comments' => intval($page->allowComment),
            'mt_allow_pings' => intval($page->allowPing),
            'wp_slug' => $page->slug,
            'wp_password' => $page->password,
            'wp_author' => $page->author->name,
            'wp_page_parent_id' => $list ? 0 : '0',
            'wp_page_parent_title' => '',
            'wp_page_order' => $list ? intval($page->order) : $page->order,
            'wp_author_id' => $page->authorId,
            'wp_author_display_name' => $page->author->screenName,
            'date_created_gmt' => $this->toGmtRpcDate((int) $page->created),
            'date_modified_gmt' => $this->toGmtRpcDate((int) $page->modified),
            'custom_fields' => [],
            'wp_page_template' => $page->template
        ];
    }

    private function buildPostStruct(Contents $post, string $status, bool $recent = false): array
    {
        [$excerpt, $more] = $this->getPostExtended($post);
        $struct = [
            'dateCreated' => $this->toSiteRpcDate((int) $post->created),
            'userid' => $post->authorId,
            'postid' => $post->cid,
            'description' => $excerpt,
            'title' => $post->title,
            'link' => $post->permalink,
            'permaLink' => $post->permalink,
            'categories' => array_column($post->categories, 'name'),
            'mt_excerpt' => $post->plainExcerpt,
            'mt_text_more' => $more,
            'mt_allow_comments' => intval($post->allowComment),
            'mt_allow_pings' => intval($post->allowPing),
            'mt_keywords' => implode(', ', array_column($post->tags, 'name')),
            'wp_slug' => $post->slug,
            'wp_password' => $post->password,
            'wp_author' => $post->author->name,
            'wp_author_id' => $post->authorId,
            'wp_author_display_name' => $post->author->screenName,
            'date_created_gmt' => $this->toGmtRpcDate((int) $post->created),
            'date_modified' => $this->toSiteRpcDate((int) $post->modified),
            'date_modified_gmt' => $this->toGmtRpcDate((int) $post->modified),
            'post_status' => $status,
            'custom_fields' => [],
            'sticky' => 0
        ];

        if ($recent) {
            $struct['wp_more_text'] = $more;
            $struct['wp_post_format'] = 'standard';
            $struct['wp_post_thumbnail'] = '';
        }

        return $struct;
    }

    private function pageStatus(Contents $page): string
    {
        return $this->typechoToWordpressStatus(
            ($page->hasSaved || $page->type == 'page_draft') ? 'draft' : $page->status,
            'page'
        );
    }

    private function postStatus(Contents $post): string
    {
        return $this->typechoToWordpressStatus(
            ($post->hasSaved || $post->type == 'post_draft') ? 'draft' : $post->status,
            'post'
        );
    }

    public function db(): \Typecho\Db
    {
        return $this->db;
    }

    public function optionsWidget(): \Widget\Options
    {
        return $this->options;
    }

    public function userWidget(): \Widget\User
    {
        return $this->user;
    }

    public function requestWidget(): \Typecho\Widget\Request
    {
        return $this->request;
    }

    /**
     * 入口执行方法
     *
     * @throws Exception
     */
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
            $registry = new XmlRpcMethodRegistry($this);
            $server = new Server($registry->callbacks(1 != $this->options->allowXmlRpc));
            $server->setHook(new XmlRpcHookHandler($this, $registry));
            $server->serve();
        }
    }

    /**
     * 获取扩展字段
     *
     * @param Contents $content
     * @return array
     */
    public function getPostExtended(Contents $content): array
    {
        //根据客户端显示来判断是否显示html代码
        $agent = $this->request->getAgent();

        switch (true) {
            case false !== strpos($agent, 'wp-iphone'):   // wordpress iphone客户端
            case false !== strpos($agent, 'wp-blackberry'):  // 黑莓
            case false !== strpos($agent, 'wp-andriod'):  // andriod
            case false !== strpos($agent, 'plain-text'):  // 这是预留给第三方开发者的接口, 用于强行调用非所见即所得数据
            case $this->options->xmlrpcMarkdown:
                $text = $content->text;
                break;
            default:
                $text = $content->content;
                break;
        }

        $post = explode('<!--more-->', $text, 2);
        return [
            $this->options->xmlrpcMarkdown ? $post[0] : Common::fixHtml($post[0]),
            isset($post[1]) ? Common::fixHtml($post[1]) : null
        ];
    }

    /**
     * 将typecho的状态类型转换为wordperss的风格
     *
     * @param string $status typecho的状态
     * @param string $type 内容类型
     * @return string
     */
    public function typechoToWordpressStatus(string $status, string $type = 'post'): string
    {
        if ('post' == $type) {
            /** 文章状态 */
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

    /**
     * 将wordpress的状态类型转换为typecho的风格
     * @param string $status wordpress的状态
     * @param string $type 内容类型
     * @return string
     */
    public function wordpressToTypechoStatus(string $status, string $type = 'post'): string
    {
        if ('post' == $type) {
            /** 文章状态 */
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
