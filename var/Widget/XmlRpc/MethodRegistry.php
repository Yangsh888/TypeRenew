<?php

namespace Widget\XmlRpc;

use Widget\XmlRpc as XmlRpcWidget;

class MethodRegistry
{
    private XmlRpcWidget $xmlRpc;

    public function __construct(XmlRpcWidget $xmlRpc)
    {
        $this->xmlRpc = $xmlRpc;
    }

    public function policy(string $methodName, bool $allowPingback = true): ?array
    {
        $definitions = $this->definitions($allowPingback);
        return $definitions[$methodName] ?? null;
    }

    public function requiresAuth(string $methodName, bool $allowPingback = true): bool
    {
        $policy = $this->policy($methodName, $allowPingback);
        return (bool) ($policy['auth'] ?? false);
    }

    public function accessLevel(string $methodName, bool $allowPingback = true): ?string
    {
        $policy = $this->policy($methodName, $allowPingback);
        return is_array($policy) ? ($policy['access'] ?? null) : null;
    }

    public function callbacks(bool $allowPingback): array
    {
        $callbacks = [];
        foreach ($this->definitions($allowPingback) as $name => $definition) {
            $callbacks[$name] = $definition['callback'];
        }

        return $callbacks;
    }

    /**
     * 所有 XML-RPC 业务方法都在这里集中声明访问策略，避免再依赖参数名推断鉴权。
     *
     * @return array<string, array{callback: array, access?: string, auth?: bool}>
     */
    private function definitions(bool $allowPingback): array
    {
        $commentHandler = new CommentHandler($this->xmlRpc);
        $mediaHandler = new MediaHandler($this->xmlRpc);
        $optionsHandler = new OptionsHandler($this->xmlRpc);
        $api = [
            /** WordPress API */
            'wp.getPage' => ['callback' => [$this->xmlRpc, 'wpGetPage'], 'access' => 'editor', 'auth' => true],
            'wp.getPages' => ['callback' => [$this->xmlRpc, 'wpGetPages'], 'access' => 'editor', 'auth' => true],
            'wp.newPage' => ['callback' => [$this->xmlRpc, 'wpNewPage'], 'access' => 'editor', 'auth' => true],
            'wp.deletePage' => ['callback' => [$this->xmlRpc, 'wpDeletePage'], 'access' => 'editor', 'auth' => true],
            'wp.editPage' => ['callback' => [$this->xmlRpc, 'wpEditPage'], 'access' => 'editor', 'auth' => true],
            'wp.getPageList' => ['callback' => [$this->xmlRpc, 'wpGetPageList'], 'access' => 'editor', 'auth' => true],
            'wp.getAuthors' => ['callback' => [$this->xmlRpc, 'wpGetAuthors'], 'access' => 'editor', 'auth' => true],
            'wp.getCategories' => ['callback' => [$this->xmlRpc, 'mwGetCategories'], 'access' => 'contributor', 'auth' => true],
            'wp.newCategory' => ['callback' => [$this->xmlRpc, 'wpNewCategory'], 'access' => 'editor', 'auth' => true],
            'wp.suggestCategories' => ['callback' => [$this->xmlRpc, 'wpSuggestCategories'], 'access' => 'contributor', 'auth' => true],
            'wp.uploadFile' => ['callback' => [$mediaHandler, 'mwNewMediaObject'], 'access' => 'editor', 'auth' => true],

            /** New WordPress API since 2.9.2 */
            'wp.getUsersBlogs' => ['callback' => [$this->xmlRpc, 'wpGetUsersBlogs'], 'access' => 'contributor', 'auth' => true],
            'wp.getTags' => ['callback' => [$this->xmlRpc, 'wpGetTags'], 'access' => 'contributor', 'auth' => true],
            'wp.deleteCategory' => ['callback' => [$this->xmlRpc, 'wpDeleteCategory'], 'access' => 'editor', 'auth' => true],
            'wp.getCommentCount' => ['callback' => [$commentHandler, 'wpGetCommentCount'], 'access' => 'editor', 'auth' => true],
            'wp.getPostStatusList' => ['callback' => [$this->xmlRpc, 'wpGetPostStatusList'], 'access' => 'contributor', 'auth' => true],
            'wp.getPageStatusList' => ['callback' => [$this->xmlRpc, 'wpGetPageStatusList'], 'access' => 'editor', 'auth' => true],
            'wp.getPageTemplates' => ['callback' => [$this->xmlRpc, 'wpGetPageTemplates'], 'access' => 'editor', 'auth' => true],
            'wp.getOptions' => ['callback' => [$optionsHandler, 'wpGetOptions'], 'access' => 'administrator', 'auth' => true],
            'wp.setOptions' => ['callback' => [$optionsHandler, 'wpSetOptions'], 'access' => 'administrator', 'auth' => true],
            'wp.getComment' => ['callback' => [$commentHandler, 'wpGetComment'], 'access' => 'editor', 'auth' => true],
            'wp.getComments' => ['callback' => [$commentHandler, 'wpGetComments'], 'access' => 'editor', 'auth' => true],
            'wp.deleteComment' => ['callback' => [$commentHandler, 'wpDeleteComment'], 'access' => 'editor', 'auth' => true],
            'wp.editComment' => ['callback' => [$commentHandler, 'wpEditComment'], 'access' => 'editor', 'auth' => true],
            'wp.newComment' => ['callback' => [$commentHandler, 'wpNewComment'], 'access' => 'editor', 'auth' => true],
            'wp.getCommentStatusList' => ['callback' => [$commentHandler, 'wpGetCommentStatusList'], 'access' => 'editor', 'auth' => true],

            /** New Wordpress API after 2.9.2 */
            'wp.getProfile' => ['callback' => [$this->xmlRpc, 'wpGetProfile'], 'access' => 'contributor', 'auth' => true],
            'wp.getPostFormats' => ['callback' => [$this->xmlRpc, 'wpGetPostFormats'], 'access' => 'contributor', 'auth' => true],
            'wp.getMediaLibrary' => ['callback' => [$mediaHandler, 'wpGetMediaLibrary'], 'access' => 'editor', 'auth' => true],
            'wp.getMediaItem' => ['callback' => [$mediaHandler, 'wpGetMediaItem'], 'access' => 'editor', 'auth' => true],
            'wp.editPost' => ['callback' => [$this->xmlRpc, 'wpEditPost'], 'access' => 'editor', 'auth' => true],

            /** Blogger API */
            'blogger.getUsersBlogs' => ['callback' => [$this->xmlRpc, 'bloggerGetUsersBlogs'], 'access' => 'contributor', 'auth' => true],
            'blogger.getUserInfo' => ['callback' => [$this->xmlRpc, 'bloggerGetUserInfo'], 'access' => 'contributor', 'auth' => true],
            'blogger.getPost' => ['callback' => [$this->xmlRpc, 'bloggerGetPost'], 'access' => 'contributor', 'auth' => true],
            'blogger.getRecentPosts' => ['callback' => [$this->xmlRpc, 'bloggerGetRecentPosts'], 'access' => 'contributor', 'auth' => true],
            'blogger.getTemplate' => ['callback' => [$this->xmlRpc, 'bloggerGetTemplate'], 'access' => 'administrator', 'auth' => true],
            'blogger.setTemplate' => ['callback' => [$this->xmlRpc, 'bloggerSetTemplate'], 'access' => 'administrator', 'auth' => true],
            'blogger.deletePost' => ['callback' => [$this->xmlRpc, 'bloggerDeletePost'], 'access' => 'editor', 'auth' => true],

            'metaWeblog.newPost' => ['callback' => [$this->xmlRpc, 'mwNewPost'], 'access' => 'editor', 'auth' => true],
            'metaWeblog.editPost' => ['callback' => [$this->xmlRpc, 'mwEditPost'], 'access' => 'editor', 'auth' => true],
            'metaWeblog.getPost' => ['callback' => [$this->xmlRpc, 'mwGetPost'], 'access' => 'contributor', 'auth' => true],
            'metaWeblog.getRecentPosts' => ['callback' => [$this->xmlRpc, 'mwGetRecentPosts'], 'access' => 'contributor', 'auth' => true],
            'metaWeblog.getCategories' => ['callback' => [$this->xmlRpc, 'mwGetCategories'], 'access' => 'contributor', 'auth' => true],
            'metaWeblog.newMediaObject' => ['callback' => [$mediaHandler, 'mwNewMediaObject'], 'access' => 'editor', 'auth' => true],

            'metaWeblog.deletePost' => ['callback' => [$this->xmlRpc, 'bloggerDeletePost'], 'access' => 'editor', 'auth' => true],
            'metaWeblog.getTemplate' => ['callback' => [$this->xmlRpc, 'bloggerGetTemplate'], 'access' => 'administrator', 'auth' => true],
            'metaWeblog.setTemplate' => ['callback' => [$this->xmlRpc, 'bloggerSetTemplate'], 'access' => 'administrator', 'auth' => true],
            'metaWeblog.getUsersBlogs' => ['callback' => [$this->xmlRpc, 'bloggerGetUsersBlogs'], 'access' => 'contributor', 'auth' => true],

            'mt.getCategoryList' => ['callback' => [$this->xmlRpc, 'mtGetCategoryList'], 'access' => 'contributor', 'auth' => true],
            'mt.getRecentPostTitles' => ['callback' => [$this->xmlRpc, 'mtGetRecentPostTitles'], 'access' => 'contributor', 'auth' => true],
            'mt.getPostCategories' => ['callback' => [$this->xmlRpc, 'mtGetPostCategories'], 'access' => 'contributor', 'auth' => true],
            'mt.setPostCategories' => ['callback' => [$this->xmlRpc, 'mtSetPostCategories'], 'access' => 'editor', 'auth' => true],
            'mt.publishPost' => ['callback' => [$this->xmlRpc, 'mtPublishPost'], 'access' => 'editor', 'auth' => true],
        ];

        if ($allowPingback) {
            $api['pingback.ping'] = [
                'callback' => [new PingbackHandler($this->xmlRpc), 'pingbackPing'],
                'auth' => false,
            ];
        }

        return $api;
    }
}
