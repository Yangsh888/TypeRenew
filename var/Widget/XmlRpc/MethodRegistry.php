<?php

namespace Widget\XmlRpc;

use Widget\XmlRpc as XmlRpcWidget;

class MethodRegistry
{
    /** @var array<string, string> */
    private const ACCESS_MAP = [
        'wp.newPage' => 'editor',
        'wp.deletePage' => 'editor',
        'wp.getPageList' => 'editor',
        'wp.getAuthors' => 'editor',
        'wp.deleteCategory' => 'editor',
        'wp.getPageStatusList' => 'editor',
        'wp.getPageTemplates' => 'editor',
        'wp.getOptions' => 'administrator',
        'wp.setOptions' => 'administrator',
        'mt.setPostCategories' => 'editor',
    ];

    private XmlRpcWidget $xmlRpc;

    public function __construct(XmlRpcWidget $xmlRpc)
    {
        $this->xmlRpc = $xmlRpc;
    }

    public function accessLevel(string $methodName): string
    {
        return self::ACCESS_MAP[$methodName] ?? 'contributor';
    }

    public function callbacks(bool $allowPingback): array
    {
        $commentHandler = new CommentHandler($this->xmlRpc);
        $mediaHandler = new MediaHandler($this->xmlRpc);
        $optionsHandler = new OptionsHandler($this->xmlRpc);
        $api = [
            /** WordPress API */
            'wp.getPage' => [$this->xmlRpc, 'wpGetPage'],
            'wp.getPages' => [$this->xmlRpc, 'wpGetPages'],
            'wp.newPage' => [$this->xmlRpc, 'wpNewPage'],
            'wp.deletePage' => [$this->xmlRpc, 'wpDeletePage'],
            'wp.editPage' => [$this->xmlRpc, 'wpEditPage'],
            'wp.getPageList' => [$this->xmlRpc, 'wpGetPageList'],
            'wp.getAuthors' => [$this->xmlRpc, 'wpGetAuthors'],
            'wp.getCategories' => [$this->xmlRpc, 'mwGetCategories'],
            'wp.newCategory' => [$this->xmlRpc, 'wpNewCategory'],
            'wp.suggestCategories' => [$this->xmlRpc, 'wpSuggestCategories'],
            'wp.uploadFile' => [$mediaHandler, 'mwNewMediaObject'],

            /** New WordPress API since 2.9.2 */
            'wp.getUsersBlogs' => [$this->xmlRpc, 'wpGetUsersBlogs'],
            'wp.getTags' => [$this->xmlRpc, 'wpGetTags'],
            'wp.deleteCategory' => [$this->xmlRpc, 'wpDeleteCategory'],
            'wp.getCommentCount' => [$commentHandler, 'wpGetCommentCount'],
            'wp.getPostStatusList' => [$this->xmlRpc, 'wpGetPostStatusList'],
            'wp.getPageStatusList' => [$this->xmlRpc, 'wpGetPageStatusList'],
            'wp.getPageTemplates' => [$this->xmlRpc, 'wpGetPageTemplates'],
            'wp.getOptions' => [$optionsHandler, 'wpGetOptions'],
            'wp.setOptions' => [$optionsHandler, 'wpSetOptions'],
            'wp.getComment' => [$commentHandler, 'wpGetComment'],
            'wp.getComments' => [$commentHandler, 'wpGetComments'],
            'wp.deleteComment' => [$commentHandler, 'wpDeleteComment'],
            'wp.editComment' => [$commentHandler, 'wpEditComment'],
            'wp.newComment' => [$commentHandler, 'wpNewComment'],
            'wp.getCommentStatusList' => [$commentHandler, 'wpGetCommentStatusList'],

            /** New Wordpress API after 2.9.2 */
            'wp.getProfile' => [$this->xmlRpc, 'wpGetProfile'],
            'wp.getPostFormats' => [$this->xmlRpc, 'wpGetPostFormats'],
            'wp.getMediaLibrary' => [$mediaHandler, 'wpGetMediaLibrary'],
            'wp.getMediaItem' => [$mediaHandler, 'wpGetMediaItem'],
            'wp.editPost' => [$this->xmlRpc, 'wpEditPost'],

            /** Blogger API */
            'blogger.getUsersBlogs' => [$this->xmlRpc, 'bloggerGetUsersBlogs'],
            'blogger.getUserInfo' => [$this->xmlRpc, 'bloggerGetUserInfo'],
            'blogger.getPost' => [$this->xmlRpc, 'bloggerGetPost'],
            'blogger.getRecentPosts' => [$this->xmlRpc, 'bloggerGetRecentPosts'],
            'blogger.getTemplate' => [$this->xmlRpc, 'bloggerGetTemplate'],
            'blogger.setTemplate' => [$this->xmlRpc, 'bloggerSetTemplate'],
            'blogger.deletePost' => [$this->xmlRpc, 'bloggerDeletePost'],

            'metaWeblog.newPost' => [$this->xmlRpc, 'mwNewPost'],
            'metaWeblog.editPost' => [$this->xmlRpc, 'mwEditPost'],
            'metaWeblog.getPost' => [$this->xmlRpc, 'mwGetPost'],
            'metaWeblog.getRecentPosts' => [$this->xmlRpc, 'mwGetRecentPosts'],
            'metaWeblog.getCategories' => [$this->xmlRpc, 'mwGetCategories'],
            'metaWeblog.newMediaObject' => [$mediaHandler, 'mwNewMediaObject'],

            'metaWeblog.deletePost' => [$this->xmlRpc, 'bloggerDeletePost'],
            'metaWeblog.getTemplate' => [$this->xmlRpc, 'bloggerGetTemplate'],
            'metaWeblog.setTemplate' => [$this->xmlRpc, 'bloggerSetTemplate'],
            'metaWeblog.getUsersBlogs' => [$this->xmlRpc, 'bloggerGetUsersBlogs'],

            'mt.getCategoryList' => [$this->xmlRpc, 'mtGetCategoryList'],
            'mt.getRecentPostTitles' => [$this->xmlRpc, 'mtGetRecentPostTitles'],
            'mt.getPostCategories' => [$this->xmlRpc, 'mtGetPostCategories'],
            'mt.setPostCategories' => [$this->xmlRpc, 'mtSetPostCategories'],
            'mt.publishPost' => [$this->xmlRpc, 'mtPublishPost'],
        ];

        if ($allowPingback) {
            $api['pingback.ping'] = [new PingbackHandler($this->xmlRpc), 'pingbackPing'];
        }

        return $api;
    }
}
