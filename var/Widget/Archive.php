<?php

namespace Widget;

use Typecho\Common;
use Typecho\Config;
use Typecho\Cookie;
use Typecho\Db;
use Typecho\Db\Query;
use Typecho\Router;
use Typecho\Widget\Exception as WidgetException;
use Typecho\Widget\Helper\PageNavigator\Classic;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Base\Contents;
use Widget\Comments\Ping;
use Widget\Contents\Attachment\Related as AttachmentRelated;
use Widget\Contents\Related\Author as AuthorRelated;
use Widget\Contents\From as ContentsFrom;
use Widget\Contents\Related as ContentsRelated;
use Widget\Metas\From as MetasFrom;
use Widget\Contents\Page\Rows as PageRows;
use Widget\Users\Author;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 内容归档组件
 *
 * @package Widget
 */
class Archive extends Contents
{
    private string $themeFile;

    private string $themeDir;

    private ?Query $countSql = null;

    private ?int $total = null;

    private bool $invokeFromOutside = false;

    private bool $invokeByFeed = false;

    private int $currentPage;

    private Router\ParamsDelegateInterface $pageRow;

    private string $archiveFeedUrl;

    private string $archiveFeedRssUrl;

    private string $archiveFeedAtomUrl;

    private ?string $archiveKeywords = null;

    private ?string $archiveDescription = null;

    private ?string $archiveTitle = null;

    private ?string $archiveUrl = null;

    private string $archiveType = 'index';

    private bool $archiveSingle = false;

    private bool $makeSinglePageAsFrontPage = false;

    private string $archiveSlug;

    /**
     * @param Config $parameter
     * @throws \Exception
     */
    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault([
            'pageSize'       => $this->options->pageSize,
            'type'           => null,
            'checkPermalink' => true,
            'preview'        => false,
            'commentPage'    => 0
        ]);

        if (null == $parameter->type) {
            if (!isset(Router::$current)) {
                throw new WidgetException('Archive type is not set', 500);
            }

            $parameter->type = Router::$current;
        } else {
            $this->invokeFromOutside = true;
        }

        if ($parameter->isFeed) {
            $this->invokeByFeed = true;
        }

        $this->themeDir = rtrim($this->options->themeFile($this->options->theme), '/') . '/';
    }

    public function addArchiveTitle(string $archiveTitle)
    {
        $current = $this->getArchiveTitle();
        $this->setArchiveTitle(
            $current === null || $current === ''
                ? $archiveTitle
                : $current . ' ' . $archiveTitle
        );
    }

    public function getArchiveTitle(): ?string
    {
        return $this->archiveTitle;
    }

    public function setArchiveTitle(string $archiveTitle)
    {
        $this->archiveTitle = $archiveTitle;
    }

    public function getArchiveSlug(): ?string
    {
        return $this->archiveSlug;
    }

    public function setArchiveSlug(string $archiveSlug)
    {
        $this->archiveSlug = $archiveSlug;
    }

    public function getArchiveType(): ?string
    {
        return $this->archiveType;
    }

    public function setArchiveType(string $archiveType)
    {
        $this->archiveType = $archiveType;
    }

    public function getArchiveUrl(): ?string
    {
        return $this->archiveUrl;
    }

    public function setArchiveUrl(?string $archiveUrl): void
    {
        $this->archiveUrl = $archiveUrl;
    }

    public function getArchiveDescription(): ?string
    {
        return $this->archiveDescription;
    }

    public function setArchiveDescription(string $archiveDescription)
    {
        $this->archiveDescription = $archiveDescription;
    }

    public function getArchiveKeywords(): ?string
    {
        return $this->archiveKeywords;
    }

    public function setArchiveKeywords(string $archiveKeywords)
    {
        $this->archiveKeywords = $archiveKeywords;
    }

    public function getArchiveFeedAtomUrl(): string
    {
        return $this->archiveFeedAtomUrl;
    }

    public function setArchiveFeedAtomUrl(string $archiveFeedAtomUrl)
    {
        $this->archiveFeedAtomUrl = $archiveFeedAtomUrl;
    }

    public function getArchiveFeedRssUrl(): string
    {
        return $this->archiveFeedRssUrl;
    }

    public function setArchiveFeedRssUrl(string $archiveFeedRssUrl)
    {
        $this->archiveFeedRssUrl = $archiveFeedRssUrl;
    }

    public function getArchiveFeedUrl(): string
    {
        return $this->archiveFeedUrl;
    }

    public function setArchiveFeedUrl(string $archiveFeedUrl)
    {
        $this->archiveFeedUrl = $archiveFeedUrl;
    }

    public function getCountSql(): ?Query
    {
        return $this->countSql;
    }

    public function setCountSql(Query $countSql): void
    {
        $this->countSql = $countSql;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function ___currentPage(): int
    {
        return $this->getCurrentPage();
    }

    public function getTotalPage(): int
    {
        $pageSize = max(1, (int) $this->parameter->pageSize);
        return (int) ceil($this->getTotal() / $pageSize);
    }

    public function getTotal(): int
    {
        if (!isset($this->total)) {
            $this->total = $this->countSql instanceof Query ? $this->size($this->countSql) : $this->length;
        }

        return $this->total;
    }

    public function setTotal(int $total)
    {
        $this->total = $total;
    }

    public function getThemeFile(): ?string
    {
        return $this->themeFile;
    }

    public function setThemeFile(string $themeFile)
    {
        $this->themeFile = $themeFile;
    }

    public function getThemeDir(): ?string
    {
        return $this->themeDir;
    }

    public function setThemeDir(string $themeDir)
    {
        $this->themeDir = $themeDir;
    }

    private function resolveThemeFilePath(?string $themeFile): ?string
    {
        if ($themeFile === null || $themeFile === '' || str_contains($themeFile, "\0")) {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', $themeFile), '/');
        if ($relativePath === '') {
            return null;
        }

        $path = realpath($this->themeDir . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($path === false || !is_file($path)) {
            return null;
        }

        $themeRoot = realpath($this->themeDir);
        if ($themeRoot === false) {
            return null;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $themeRoot), '/') . '/';
        $normalizedPath = str_replace('\\', '/', $path);
        return str_starts_with($normalizedPath, $normalizedRoot) ? $path : null;
    }

    private function requireThemeFile(string $themeFile, bool $once = false): void
    {
        $path = $this->resolveThemeFilePath($themeFile);
        if ($path === null) {
            throw new WidgetException(_t('文件不存在'), 500);
        }

        if ($once) {
            require_once $path;
        } else {
            require $path;
        }
    }

    public function execute()
    {
        if ($this->have()) {
            return;
        }

        $handles = [
            'index'              => 'indexHandle',
            'index_page'         => 'indexHandle',
            'archive'            => 'archiveEmptyHandle',
            'archive_page'       => 'archiveEmptyHandle',
            404                  => 'error404Handle',
            'single'             => 'singleHandle',
            'page'               => 'singleHandle',
            'post'               => 'singleHandle',
            'attachment'         => 'singleHandle',
            'category'           => 'categoryHandle',
            'category_page'      => 'categoryHandle',
            'tag'                => 'tagHandle',
            'tag_page'           => 'tagHandle',
            'author'             => 'authorHandle',
            'author_page'        => 'authorHandle',
            'archive_year'       => 'dateHandle',
            'archive_year_page'  => 'dateHandle',
            'archive_month'      => 'dateHandle',
            'archive_month_page' => 'dateHandle',
            'archive_day'        => 'dateHandle',
            'archive_day_page'   => 'dateHandle',
            'search'             => 'searchHandle',
            'search_page'        => 'searchHandle'
        ];

        if ($this->request->is('s')) {
            $filterKeywords = $this->request->filter('search')->get('s');

            if (null != $filterKeywords) {
                $this->response->redirect(
                    Router::url('search', ['keywords' => urlencode($filterKeywords)], $this->options->index)
                );
            }
        }

        $this->archiveFeedUrl = $this->options->feedUrl;
        $this->archiveFeedRssUrl = $this->options->feedRssUrl;
        $this->archiveFeedAtomUrl = $this->options->feedAtomUrl;
        $this->archiveKeywords = $this->options->keywords;
        $this->archiveDescription = $this->options->description;
        $this->archiveUrl = $this->options->siteUrl;

        $frontPage = $this->options->frontPage;
        if (!$this->invokeByFeed && ('index' == $this->parameter->type || 'index_page' == $this->parameter->type)) {
            if (0 === strpos($frontPage, 'page:')) {
                $this->request->setParam('cid', intval(substr($frontPage, 5)));
                $this->parameter->type = 'page';
                $this->makeSinglePageAsFrontPage = true;
            } elseif (0 === strpos($frontPage, 'file:')) {
                $this->setThemeFile(substr($frontPage, 5));
                return;
            }
        }

        if ('recent' != $frontPage && $this->options->frontArchive) {
            $handles['archive'] = 'indexHandle';
            $handles['archive_page'] = 'indexHandle';
            $this->archiveType = 'front';
        }

        $this->currentPage = $this->request->filter('int')->get('page', 1);
        $hasPushed = false;
        $this->pageRow = new class implements Router\ParamsDelegateInterface
        {
            public function getRouterParam(string $key): string
            {
                return '{' . $key . '}';
            }
        };

        $select = self::pluginHandle()->trigger($selectPlugged)->call('select', $this);

        if (!$selectPlugged) {
            $select = $this->select('table.contents.*');

            if (!$this->parameter->preview) {
                if ('post' == $this->parameter->type || 'page' == $this->parameter->type) {
                    if ($this->user->hasLogin()) {
                        $select->where(
                            'table.contents.status = ? OR table.contents.status = ? 
                                OR (table.contents.status = ? AND table.contents.authorId = ?)',
                            'publish',
                            'hidden',
                            'private',
                            $this->user->uid
                        );
                    } else {
                        $select->where(
                            'table.contents.status = ? OR table.contents.status = ?',
                            'publish',
                            'hidden'
                        );
                    }
                } else {
                    if ($this->user->hasLogin()) {
                        $select->where(
                            'table.contents.status = ? OR (table.contents.status = ? AND table.contents.authorId = ?)',
                            'publish',
                            'private',
                            $this->user->uid
                        );
                    } else {
                        $select->where('table.contents.status = ?', 'publish');
                    }
                }
                $select->where('table.contents.created < ?', $this->options->time);
            }
        }

        self::pluginHandle()->call('handleInit', $this, $select);

        if (isset($handles[$this->parameter->type])) {
            $handle = $handles[$this->parameter->type];
            $this->{$handle}($select, $hasPushed);
        } else {
            $hasPushed = self::pluginHandle()->call('handle', $this->parameter->type, $this, $select);
        }

        $functionsFile = $this->themeDir . 'functions.php';
        if (
            (!$this->invokeFromOutside || $this->parameter->type == 404 || $this->parameter->preview)
            && file_exists($functionsFile)
        ) {
            require_once $functionsFile;
            if (function_exists('themeInit')) {
                themeInit($this);
            }
        }

        if ($hasPushed) {
            return;
        }

        $this->countSql = clone $select;

        $select->order('table.contents.created', Db::SORT_DESC)
            ->page($this->currentPage, $this->parameter->pageSize);
        $this->query($select);

        if ($this->currentPage > 1 && !$this->have()) {
            throw new WidgetException(_t('请求的地址不存在'), 404);
        }
    }

    public function select(...$fields): Query
    {
        if ($this->invokeByFeed) {
            return parent::select(...$fields)->where('table.contents.allowFeed = ?', 1)
                ->where("table.contents.password IS NULL OR table.contents.password = ''");
        } else {
            return parent::select(...$fields);
        }
    }

    public function content($more = null)
    {
        parent::content($this->is('single') ? false : $more);
    }

    public function pageNav(
        string $prev = '&laquo;',
        string $next = '&raquo;',
        int $splitPage = 3,
        string $splitWord = '...',
        $template = ''
    ) {
        if ($this->have()) {
            $hasNav = false;
            $default = [
                'wrapTag'   => 'ol',
                'wrapClass' => 'page-navigator'
            ];

            if (is_string($template)) {
                parse_str($template, $config);
            } else {
                $config = $template ?: [];
            }

            $template = array_merge($default, $config);
            $total = $this->getTotal();
            $query = Router::url(
                $this->parameter->type .
                (false === strpos((string) $this->parameter->type, '_page') ? '_page' : null),
                $this->pageRow,
                $this->options->index
            );

            self::pluginHandle()->trigger($hasNav)->call(
                'pageNav',
                $this->currentPage,
                $total,
                $this->parameter->pageSize,
                $prev,
                $next,
                $splitPage,
                $splitWord,
                $template,
                $query
            );

            if (!$hasNav && $total > $this->parameter->pageSize) {
                $nav = new Box(
                    $total,
                    $this->currentPage,
                    $this->parameter->pageSize,
                    $query
                );

                echo '<' . $template['wrapTag'] . (empty($template['wrapClass'])
                        ? '' : ' class="' . $template['wrapClass'] . '"') . '>';
                $nav->render($prev, $next, $splitPage, $splitWord, $template);
                echo '</' . $template['wrapTag'] . '>';
            }
        }
    }

    public function pageLink(string $word = '&laquo; Previous Entries', string $page = 'prev')
    {
        static $nav;

        if ($this->have()) {
            if (!isset($nav)) {
                $query = Router::url(
                    $this->parameter->type .
                    (false === strpos((string) $this->parameter->type, '_page') ? '_page' : null),
                    $this->pageRow,
                    $this->options->index
                );
                $nav = new Classic(
                    $this->getTotal(),
                    $this->currentPage,
                    $this->parameter->pageSize,
                    $query
                );
            }

            $nav->{$page}($word);
        }
    }

    public function comments(): \Widget\Comments\Archive
    {
        $parameter = [
            'parentId'      => $this->hidden ? 0 : $this->cid,
            'parentContent' => $this,
            'respondId'     => $this->respondId,
            'commentPage'   => $this->parameter->commentPage,
            'allowComment'  => $this->allow('comment')
        ];

        return \Widget\Comments\Archive::alloc($parameter);
    }

    public function pings(): Ping
    {
        return Ping::alloc([
            'parentId'      => $this->hidden ? 0 : $this->cid,
            'parentContent' => $this->row,
            'allowPing'     => $this->allow('ping')
        ]);
    }

    public function attachments(int $limit = 0, int $offset = 0): AttachmentRelated
    {
        return AttachmentRelated::allocWithAlias($this->cid . '-' . uniqid(), [
            'parentId' => $this->cid,
            'limit'    => $limit,
            'offset'   => $offset
        ]);
    }

    public function theNext(string $format = '%s', ?string $default = null, array $custom = [])
    {
        $query = $this->select()->where(
            'table.contents.created > ? AND table.contents.created < ?',
            $this->created,
            $this->options->time
        )
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', $this->type)
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->order('table.contents.created', Db::SORT_ASC)
            ->limit(1);

        $this->theLink(
            ContentsFrom::allocWithAlias('next:' . $this->cid, ['query' => $query]),
            $format,
            $default,
            $custom
        );
    }

    public function thePrev(string $format = '%s', ?string $default = null, array $custom = [])
    {
        $query = $this->select()->where('table.contents.created < ?', $this->created)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', $this->type)
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->order('table.contents.created', Db::SORT_DESC)
            ->limit(1);

        $this->theLink(
            ContentsFrom::allocWithAlias('prev:' . $this->cid, ['query' => $query]),
            $format,
            $default,
            $custom
        );
    }

    public function theLink(Contents $content, string $format = '%s', ?string $default = null, array $custom = [])
    {
        if ($content->have()) {
            $default = [
                'title'    => null,
                'tagClass' => null
            ];
            $custom = array_merge($default, $custom);

            $linkText = $custom['title'] ?? $content->title;
            $linkClass = empty($custom['tagClass']) ? '' : 'class="' . $custom['tagClass'] . '" ';
            $link = '<a ' . $linkClass . 'href="' . $content->permalink
                . '" title="' . $content->title . '">' . $linkText . '</a>';

            printf($format, $link);
        } else {
            echo $default;
        }
    }

    public function related(int $limit = 5, ?string $type = null): Contents
    {
        $type = strtolower($type ?? '');

        switch ($type) {
            case 'author':
                return AuthorRelated::alloc(
                    ['cid' => $this->cid, 'type' => $this->type, 'author' => $this->author->uid, 'limit' => $limit]
                );
            default:
                return ContentsRelated::alloc(
                    ['cid' => $this->cid, 'type' => $this->type, 'tags' => $this->tags, 'limit' => $limit]
                );
        }
    }

    public function header(?string $rule = null)
    {
        $rules = [];
        $allows = [
            'description'  => htmlspecialchars($this->archiveDescription ?? ''),
            'keywords'     => htmlspecialchars($this->archiveKeywords ?? ''),
            'generator'    => $this->options->generator,
            'template'     => $this->options->theme,
            'pingback'     => $this->options->xmlRpcUrl,
            'xmlrpc'       => $this->options->xmlRpcUrl . '?rsd',
            'wlw'          => $this->options->xmlRpcUrl . '?wlw',
            'rss2'         => $this->archiveFeedUrl,
            'rss1'         => $this->archiveFeedRssUrl,
            'commentReply' => 1,
            'antiSpam'     => 1,
            'social'       => 1,
            'atom'         => $this->archiveFeedAtomUrl
        ];

        $allowFeed = !$this->is('single') || $this->allow('feed') || $this->makeSinglePageAsFrontPage;

        if (!empty($rule)) {
            parse_str($rule, $rules);
            $allows = array_merge($allows, $rules);
        }

        $allows = self::pluginHandle()->filter('headerOptions', $allows, $this);
        $title = (empty($this->archiveTitle) ? '' : $this->archiveTitle . ' &raquo; ') . $this->options->title;

        $header = ($this->is('single') && !$this->is('index')) ? '<link rel="canonical" href="' . $this->archiveUrl . '" />' . "\n" : '';

        if (!empty($allows['pingback']) && 2 == $this->options->allowXmlRpc) {
            $header .= '<link rel="pingback" href="' . $allows['pingback'] . '" />' . "\n";
        }

        if (!empty($allows['xmlrpc']) && 0 < $this->options->allowXmlRpc) {
            $header .= '<link rel="EditURI" type="application/rsd+xml" title="RSD" href="'
                . $allows['xmlrpc'] . '" />' . "\n";
        }

        if (!empty($allows['wlw']) && 0 < $this->options->allowXmlRpc) {
            $header .= '<link rel="wlwmanifest" type="application/wlwmanifest+xml" href="'
                . $allows['wlw'] . '" />' . "\n";
        }

        if (!empty($allows['rss2']) && $allowFeed) {
            $header .= '<link rel="alternate" type="application/rss+xml" title="'
                . $title . ' &raquo; RSS 2.0" href="' . $allows['rss2'] . '" />' . "\n";
        }

        if (!empty($allows['rss1']) && $allowFeed) {
            $header .= '<link rel="alternate" type="application/rdf+xml" title="'
                . $title . ' &raquo; RSS 1.0" href="' . $allows['rss1'] . '" />' . "\n";
        }

        if (!empty($allows['atom']) && $allowFeed) {
            $header .= '<link rel="alternate" type="application/atom+xml" title="'
                . $title . ' &raquo; ATOM 1.0" href="' . $allows['atom'] . '" />' . "\n";
        }

        if (!empty($allows['description'])) {
            $header .= '<meta name="description" content="' . $allows['description'] . '" />' . "\n";
        }

        if (!empty($allows['keywords'])) {
            $header .= '<meta name="keywords" content="' . $allows['keywords'] . '" />' . "\n";
        }

        if (!empty($allows['generator'])) {
            $header .= '<meta name="generator" content="' . $allows['generator'] . '" />' . "\n";
        }

        if (!empty($allows['template'])) {
            $header .= '<meta name="template" content="' . $allows['template'] . '" />' . "\n";
        }

        if (!empty($allows['social'])) {
            $header .= '<meta property="og:type" content="' . ($this->is('single') ? 'article' : 'website') . '" />' . "\n";
            $header .= '<meta property="og:url" content="' . $this->archiveUrl . '" />' . "\n";
            $header .= '<meta name="twitter:title" property="og:title" itemprop="name" content="'
                . htmlspecialchars($this->archiveTitle ?? $this->options->title) . '" />' . "\n";
            $header .= '<meta name="twitter:description" property="og:description" itemprop="description" content="'
                . htmlspecialchars($this->archiveDescription ?? ($this->options->description ?? '')) . '" />' . "\n";
            $header .= '<meta property="og:site_name" content="' . htmlspecialchars($this->options->title) . '" />' . "\n";
            $header .= '<meta name="twitter:card" content="summary" />' . "\n";
            $header .= '<meta name="twitter:domain" content="' . $this->options->siteDomain . '" />' . "\n";
        }

        if ($this->options->commentsThreaded && $this->is('single')) {
            if ('' != $allows['commentReply']) {
                if (1 == $allows['commentReply']) {
                    $header .= <<<EOF
<script type="text/javascript">
(function () {
    window.TypechoComment = {
        dom : function (sel) {
            return document.querySelector(sel);
        },
        
        visiable: function (el, show) {
            el.style.display = show ? '' : 'none';
        },
    
        create : function (tag, attr) {
            const el = document.createElement(tag);
        
            for (const key in attr) {
                el.setAttribute(key, attr[key]);
            }
        
            return el;
        },
        
        inputParent: function (response, coid) {
            const form = 'form' === response.tagName ? response : response.querySelector('form');
            let input = form.querySelector('input[name=parent]');
            
            if (null == input && coid) {
                input = this.create('input', {
                    'type' : 'hidden',
                    'name' : 'parent'
                });

                form.appendChild(input);
            }
            
            if (coid) {
                input.setAttribute('value', coid);
            } else if (input) {
                input.parentNode.removeChild(input);
            }
        },
        
        getChild: function (root, node) {
            const parentNode = node.parentNode;
            
            if (parentNode === null) {
                return null;
            } else if (parentNode === root) {
                return node;
            } else {
                return this.getChild(root, parentNode);
            }
        },

        reply : function (htmlId, coid, btn) {
            const response = this.dom('#{$this->respondId}'),
                textarea = response.querySelector('textarea[name=text]'),
                comment = this.dom('#' + htmlId),
                child = this.getChild(comment, btn);

            this.inputParent(response, coid);

            if (this.dom('#{$this->respondId}-holder') === null) {
                const holder = this.create('div', {
                    'id' : '{$this->respondId}-holder'
                });

                response.parentNode.insertBefore(holder, response);
            }
            
            if (child) {
                comment.insertBefore(response, child.nextSibling);
            } else {
                comment.appendChild(response);
            }

            this.visiable(this.dom('#cancel-comment-reply-link'), true);

            if (null != textarea) {
                textarea.focus();
            }

            return false;
        },

        cancelReply : function () {
            const response = this.dom('#{$this->respondId}'),
                holder = this.dom('#{$this->respondId}-holder');

            this.inputParent(response, false);

            if (null === holder) {
                return true;
            }

            this.visiable(this.dom('#cancel-comment-reply-link'), false);
            holder.parentNode.insertBefore(response, holder);
            return false;
        }
    };
})();
</script>
EOF;
                } else {
                    $header .= '<script src="' . $allows['commentReply'] . '" type="text/javascript"></script>';
                }
            }
        }

        /** 反垃圾设置 */
        if ($this->options->commentsAntiSpam && $this->is('single')) {
            if ('' != $allows['antiSpam']) {
                if (1 == $allows['antiSpam']) {
                    $shuffled = Common::shuffleScriptVar($this->security->getToken($this->request->getRequestUrl()));
                    $header .= <<<EOF
<script type="text/javascript">
(function () {
    const events = ['scroll', 'mousemove', 'keyup', 'touchstart'];
    let added = false;

    document.addEventListener('DOMContentLoaded', function () {
        const response = document.querySelector('#{$this->respondId}');

        if (null != response) {
            const form = 'form' === response.tagName ? response : response.querySelector('form');
            const input = document.createElement('input');
            
            input.type = 'hidden';
            input.name = '_';
            input.value = {$shuffled};
 
            if (form) {
                function append() {
                    if (!added) {
                        form.appendChild(input);
                        added = true;
                    }
                }
            
                for (const event of events) {
                    window.addEventListener(event, append);
                }
            }
        }
    });
})();
</script>
EOF;
                } else {
                    $header .= '<script src="' . $allows['antiSpam'] . '" type="text/javascript"></script>';
                }
            }
        }

        echo $header;

        self::pluginHandle()->call('header', $header, $this);
    }

    public function footer()
    {
        self::pluginHandle()->call('footer', $this);
    }

    public function remember(string $cookieName, bool $return = false)
    {
        $cookieName = strtolower($cookieName);
        if (!in_array($cookieName, ['author', 'mail', 'url'])) {
            return '';
        }

        $value = Cookie::get('__typecho_remember_' . $cookieName);
        if ($return) {
            return $value;
        } else {
            echo htmlspecialchars($value ?? '');
        }
    }

    public function archiveTitle($defines = null, string $before = ' &raquo; ', string $end = '')
    {
        if ($this->archiveTitle) {
            $define = '%s';
            if (is_array($defines) && !empty($defines[$this->archiveType])) {
                $define = $defines[$this->archiveType];
            }

            echo $before . sprintf($define, $this->archiveTitle) . $end;
        }
    }

    public function keywords(string $split = ',', string $default = '')
    {
        echo empty($this->archiveKeywords) ? $default : str_replace(',', $split, htmlspecialchars($this->archiveKeywords ?? ''));
    }

    public function need(string $fileName)
    {
        $this->requireThemeFile($fileName);
    }

    public function render()
    {
        $this->checkPermalink();

        if (2 == $this->options->allowXmlRpc) {
            $this->response->setHeader('X-Pingback', $this->options->xmlRpcUrl);
        }
        $valid = false;

        if (!empty($this->themeFile)) {
            if (null !== $this->resolveThemeFilePath($this->themeFile)) {
                $valid = true;
            }
        }

        if (!$valid && !empty($this->archiveType)) {
            if (!empty($this->archiveSlug)) {
                $themeFile = $this->archiveType . '/' . $this->archiveSlug . '.php';
                if (file_exists($this->themeDir . $themeFile)) {
                    $this->themeFile = $themeFile;
                    $valid = true;
                }
            }

            if (!$valid) {
                $themeFile = $this->archiveType . '.php';
                if (file_exists($this->themeDir . $themeFile)) {
                    $this->themeFile = $themeFile;
                    $valid = true;
                }
            }

            if (!$valid && 'attachment' == $this->archiveType) {
                if (file_exists($this->themeDir . 'page.php')) {
                    $this->themeFile = 'page.php';
                    $valid = true;
                } elseif (file_exists($this->themeDir . 'post.php')) {
                    $this->themeFile = 'post.php';
                    $valid = true;
                }
            }

            if (!$valid && 'index' != $this->archiveType && 'front' != $this->archiveType) {
                $themeFile = $this->archiveSingle ? 'single.php' : 'archive.php';
                if (file_exists($this->themeDir . $themeFile)) {
                    $this->themeFile = $themeFile;
                    $valid = true;
                }
            }

            if (!$valid) {
                $themeFile = 'index.php';
                if (file_exists($this->themeDir . $themeFile)) {
                    $this->themeFile = $themeFile;
                    $valid = true;
                }
            }
        }

        if (!$valid) {
            throw new WidgetException(_t('文件不存在'), 500);
        }

        self::pluginHandle()->call('beforeRender', $this);

        $this->requireThemeFile($this->themeFile, true);

        self::pluginHandle()->call('afterRender', $this);
    }

    public function is(string $archiveType, ?string $archiveSlug = null): bool
    {
        return ($archiveType == $this->archiveType ||
                (($this->archiveSingle ? 'single' : 'archive') == $archiveType && 'index' != $this->archiveType) ||
                ('index' == $archiveType && $this->makeSinglePageAsFrontPage) ||
                ('feed' == $archiveType && $this->invokeByFeed))
            && (empty($archiveSlug) || $archiveSlug == $this->archiveSlug);
    }

    public function query($select)
    {
        self::pluginHandle()->trigger($queryPlugged)->call('query', $this, $select);
        if (!$queryPlugged) {
            $this->db->fetchAll($select, [$this, 'push']);
        }
    }

    protected function ___directory(): array
    {
        if ('page' == $this->type) {
            $page = PageRows::alloc('current=' . $this->cid);
            $directory = $page->getAllParentsSlug($this->cid);
            $directory[] = $this->slug;

            return $directory;
        }

        return parent::___directory();
    }

    protected function ___commentUrl(): string
    {
        $commentUrl = parent::___commentUrl();

        $reply = $this->request->filter('int')->get('replyTo');
        if ($reply && $this->is('single')) {
            $commentUrl .= '?parent=' . $reply;
        }

        return $commentUrl;
    }

    private function checkPermalink()
    {
        $type = $this->parameter->type;

        if (
            in_array($type, ['index', 404])
            || $this->makeSinglePageAsFrontPage
            || !$this->parameter->checkPermalink
        ) {
            return;
        }

        if ($this->archiveSingle) {
            $permalink = $this->permalink;
        } else {
            $path = Router::url(
                $type,
                new class ($this->currentPage, $this->pageRow) implements Router\ParamsDelegateInterface {
                    private Router\ParamsDelegateInterface $pageRow;
                    private int $currentPage;

                    public function __construct(int $currentPage, Router\ParamsDelegateInterface $pageRow)
                    {
                        $this->pageRow = $pageRow;
                        $this->currentPage = $currentPage;
                    }

                    public function getRouterParam(string $key): string
                    {
                        switch ($key) {
                            case 'page':
                                return $this->currentPage;
                            default:
                                return $this->pageRow->getRouterParam($key);
                        }
                    }
                }
            );

            $permalink = Common::url($path, $this->options->index);
        }

        $requestUrl = $this->request->getRequestUrl();

        $src = Common::parseUrl($permalink);
        $target = Common::parseUrl($requestUrl);

        if (
            ($src['host'] ?? null) != ($target['host'] ?? null)
            || urldecode((string) ($src['path'] ?? '')) != urldecode((string) ($target['path'] ?? ''))
        ) {
            $this->response->redirect($permalink, true);
        }
    }

    private function indexHandle(Query $select, bool &$hasPushed)
    {
        $select->where('table.contents.type = ?', 'post');

        self::pluginHandle()->call('indexHandle', $this, $select);
    }

    private function archiveEmptyHandle(Query $select, bool &$hasPushed)
    {
        throw new WidgetException(_t('请求的地址不存在'), 404);
    }

    private function error404Handle(Query $select, bool &$hasPushed)
    {
        $this->response->setStatus(404);
        $this->archiveTitle = _t('页面没找到');
        $this->archiveType = 'archive';
        $this->archiveSlug = 404;
        $this->themeFile = '404.php';
        $this->archiveSingle = false;

        $hasPushed = true;

        self::pluginHandle()->call('error404Handle', $this, $select);
    }

    private function singleHandle(Query $select, bool &$hasPushed)
    {
        $this->archiveSingle = true;
        $this->archiveType = 'single';

        if ('single' != $this->parameter->type) {
            $select->where('table.contents.type = ?', $this->parameter->type);
        }

        if ($this->request->is('cid')) {
            $select->where('table.contents.cid = ?', $this->request->filter('int')->get('cid'));
        }

        if ($this->request->is('slug')) {
            $select->where('table.contents.slug = ?', $this->request->get('slug'));
        }

        if ($this->request->is('directory') && 'page' == $this->parameter->type) {
            $directory = explode('/', (string) $this->request->get('directory', ''));
            $select->where('slug = ?', $directory[count($directory) - 1]);
        }

        if ($this->request->is('year')) {
            $year = $this->request->filter('int')->get('year');

            $fromMonth = 1;
            $toMonth = 12;

            $fromDay = 1;
            $toDay = 31;

            if ($this->request->is('month')) {
                $fromMonth = $this->request->filter('int')->get('month');
                $toMonth = $fromMonth;

                $toDay = date('t', mktime(0, 0, 0, $toMonth, 1, $year));

                if ($this->request->is('day')) {
                    $fromDay = $this->request->filter('int')->get('day');
                    $toDay = $fromDay;
                }
            }

            $from = mktime(0, 0, 0, $fromMonth, $fromDay, $year)
                - $this->options->timezone + $this->options->serverTimezone;
            $to = mktime(23, 59, 59, $toMonth, $toDay, $year)
                - $this->options->timezone + $this->options->serverTimezone;
            $select->where('table.contents.created >= ? AND table.contents.created < ?', $from, $to);
        }

        $isPasswordPosted = false;

        if (
            $this->request->isPost()
            && $this->request->is('protectPassword')
            && !$this->parameter->preview
        ) {
            $this->security->protect();
            Cookie::set(
                'protectPassword_' . $this->request->filter('int')->get('protectCID'),
                $this->request->get('protectPassword')
            );

            $isPasswordPosted = true;
        }

        $select->limit(1);
        $this->query($select);

        if (!$this->have()) {
            if ('page' == $this->parameter->type && $this->request->is('slug')) {
                $fallbackSelect = $this->select('table.contents.*');
                $fallbackSelect->where('table.contents.type = ?', 'post');
                $fallbackSelect->where('table.contents.slug = ?', $this->request->get('slug'));
                
                if (!$this->parameter->preview) {
                    if ($this->user->hasLogin()) {
                        $fallbackSelect->where(
                            'table.contents.status = ? OR table.contents.status = ? 
                                OR (table.contents.status = ? AND table.contents.authorId = ?)',
                            'publish',
                            'hidden',
                            'private',
                            $this->user->uid
                        );
                    } else {
                        $fallbackSelect->where(
                            'table.contents.status = ? OR table.contents.status = ?',
                            'publish',
                            'hidden'
                        );
                    }
                    $fallbackSelect->where('table.contents.created < ?', $this->options->time);
                }
                
                $fallbackSelect->limit(1);
                $this->query($fallbackSelect);
                
                if ($this->have()) {
                    $this->parameter->type = 'post';
                }
            }
            
            if (!$this->have()) {
                if (!$this->invokeFromOutside) {
                    throw new WidgetException(_t('请求的地址不存在'), 404);
                } else {
                    $hasPushed = true;
                    return;
                }
            }
        }

        if ($isPasswordPosted && $this->hidden) {
            throw new WidgetException(_t('对不起,您输入的密码错误'), 403);
        }

        if ($this->template) {
            $this->themeFile = $this->template;
        }

        if (!$this->makeSinglePageAsFrontPage) {
            $this->archiveFeedUrl = $this->feedUrl;
            $this->archiveFeedRssUrl = $this->feedRssUrl;
            $this->archiveFeedAtomUrl = $this->feedAtomUrl;
            $this->archiveTitle = $this->title;
            $this->archiveKeywords = implode(',', array_column($this->tags, 'name'));
            $this->archiveDescription = $this->plainExcerpt;
        }

        if ($this->parameter->preview && $this->type === 'revision') {
            $parent = ContentsFrom::allocWithAlias($this->parent, ['cid' => $this->parent]);
            $this->archiveType = $parent->type;
        } else {
            [$this->archiveType] = explode('_', $this->type);
        }

        $this->archiveSlug = ('post' == $this->archiveType || 'attachment' == $this->archiveType)
            ? $this->cid : $this->slug;

        $this->archiveUrl = $this->permalink;

        if ($this->hidden) {
            $this->response->setStatus(403);
        }

        $hasPushed = true;

        self::pluginHandle()->call('singleHandle', $this, $select);
    }

    private function categoryHandle(Query $select)
    {
        $categorySelect = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->limit(1);

        $alias = 'category';

        if ($this->request->is('mid')) {
            $mid = $this->request->filter('int')->get('mid');
            $categorySelect->where('mid = ?', $mid);
            $alias .= ':' . $mid;
        }

        if ($this->request->is('slug')) {
            $slug = $this->request->get('slug');
            $categorySelect->where('slug = ?', $slug);
            $alias .= ':' . $slug;
        }

        if ($this->request->is('directory')) {
            $directory = explode('/', (string) $this->request->get('directory', ''));
            $slug = $directory[count($directory) - 1];
            $categorySelect->where('slug = ?', $slug);
            $alias .= ':' . $slug;
        }

        $category = MetasFrom::allocWithAlias($alias, [
            'query' => $categorySelect
        ]);

        if (!$category->have()) {
            throw new WidgetException(_t('分类不存在'), 404);
        }

        if (isset($directory) && (implode('/', $directory) != implode('/', $category->directory))) {
            throw new WidgetException(_t('父级分类不存在'), 404);
        }

        $children = $category->getAllChildIds($category->mid);
        $children[] = $category->mid;

        $select->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid IN ?', $children)
            ->where('table.contents.type = ?', 'post')
            ->distinct();

        $this->pageRow = $category;
        $this->archiveKeywords = $category->name;
        $this->archiveDescription = $category->description;
        $this->archiveFeedUrl = $category->feedUrl;
        $this->archiveFeedRssUrl = $category->feedRssUrl;
        $this->archiveFeedAtomUrl = $category->feedAtomUrl;
        $this->archiveTitle = $category->name;
        $this->archiveType = 'category';
        $this->archiveSlug = $category->slug;
        $this->archiveUrl = $category->permalink;

        self::pluginHandle()->call('categoryHandle', $this, $select);
    }

    private function tagHandle(Query $select)
    {
        $tagSelect = $this->db->select()->from('table.metas')
            ->where('type = ?', 'tag')->limit(1);

        $alias = 'tag';

        if ($this->request->is('mid')) {
            $mid = $this->request->filter('int')->get('mid');
            $tagSelect->where('mid = ?', $mid);
            $alias .= ':' . $mid;
        }

        if ($this->request->is('slug')) {
            $slug = $this->request->get('slug');
            $tagSelect->where('slug = ?', $slug);
            $alias .= ':' . $slug;
        }

        $tag = MetasFrom::allocWithAlias($alias, [
            'query' => $tagSelect
        ]);

        if (!$tag->have()) {
            throw new WidgetException(_t('标签不存在'), 404);
        }

        $select->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $tag->mid)
            ->where('table.contents.type = ?', 'post');

        $this->pageRow = $tag;
        $this->archiveKeywords = $tag->name;
        $this->archiveDescription = $tag->description;
        $this->archiveFeedUrl = $tag->feedUrl;
        $this->archiveFeedRssUrl = $tag->feedRssUrl;
        $this->archiveFeedAtomUrl = $tag->feedAtomUrl;
        $this->archiveTitle = $tag->name;
        $this->archiveType = 'tag';
        $this->archiveSlug = $tag->slug;
        $this->archiveUrl = $tag->permalink;

        self::pluginHandle()->call('tagHandle', $this, $select);
    }

    private function authorHandle(Query $select)
    {
        $uid = $this->request->filter('int')->get('uid');

        $author = Author::allocWithAlias('user:' . $uid, [
            'uid' => $uid
        ]);

        if (!$author->have()) {
            throw new WidgetException(_t('作者不存在'), 404);
        }

        $select->where('table.contents.authorId = ?', $uid)
            ->where('table.contents.type = ?', 'post');

        $this->pageRow = $author;
        $this->archiveKeywords = $author->screenName;
        $this->archiveDescription = $author->screenName;
        $this->archiveFeedUrl = $author->feedUrl;
        $this->archiveFeedRssUrl = $author->feedRssUrl;
        $this->archiveFeedAtomUrl = $author->feedAtomUrl;
        $this->archiveTitle = $author->screenName;
        $this->archiveType = 'author';
        $this->archiveSlug = $author->uid;
        $this->archiveUrl = $author->permalink;

        self::pluginHandle()->call('authorHandle', $this, $select);
    }

    private function dateHandle(Query $select)
    {
        $year = $this->request->filter('int')->get('year');
        $month = $this->request->filter('int')->get('month');
        $day = $this->request->filter('int')->get('day');

        if (!empty($year) && !empty($month) && !empty($day)) {
            $from = mktime(0, 0, 0, $month, $day, $year);
            $to = mktime(23, 59, 59, $month, $day, $year);
            $this->archiveSlug = 'day';
            $this->archiveTitle = _t('%d年%d月%d日', $year, $month, $day);
        } elseif (!empty($year) && !empty($month)) {
            $from = mktime(0, 0, 0, $month, 1, $year);
            $to = mktime(23, 59, 59, $month, date('t', $from), $year);
            $this->archiveSlug = 'month';
            $this->archiveTitle = _t('%d年%d月', $year, $month);
        } elseif (!empty($year)) {
            $from = mktime(0, 0, 0, 1, 1, $year);
            $to = mktime(23, 59, 59, 12, 31, $year);
            $this->archiveSlug = 'year';
            $this->archiveTitle = _t('%d年', $year);
        }

        $select->where('table.contents.created >= ?', $from - $this->options->timezone + $this->options->serverTimezone)
            ->where('table.contents.created <= ?', $to - $this->options->timezone + $this->options->serverTimezone)
            ->where('table.contents.type = ?', 'post');

        $this->archiveType = 'date';

        $this->pageRow = new class ($year, $month, $day) implements Router\ParamsDelegateInterface {
            private int $year;
            private int $month;
            private int $day;

            public function __construct(int $year, int $month, int $day)
            {
                $this->year = $year;
                $this->month = $month;
                $this->day = $day;
            }

            public function getRouterParam(string $key): string
            {
                switch ($key) {
                    case 'year':
                        return $this->year;
                    case 'month':
                        return str_pad($this->month, 2, '0', STR_PAD_LEFT);
                    case 'day':
                        return str_pad($this->day, 2, '0', STR_PAD_LEFT);
                    default:
                        return '{' . $key . '}';
                }
            }
        };

        $currentRoute = str_replace('_page', '', $this->parameter->type);
        $this->archiveFeedUrl = Router::url($currentRoute, $this->pageRow, $this->options->feedUrl);
        $this->archiveFeedRssUrl = Router::url($currentRoute, $this->pageRow, $this->options->feedRssUrl);
        $this->archiveFeedAtomUrl = Router::url($currentRoute, $this->pageRow, $this->options->feedAtomUrl);
        $this->archiveUrl = Router::url($currentRoute, $this->pageRow, $this->options->index);

        self::pluginHandle()->call('dateHandle', $this, $select);
    }

    private function searchHandle(Query $select, bool &$hasPushed)
    {
        // Route params here are plain search terms, not full URLs. Applying `url`
        // filtering would strip spaces and break multi-word searches.
        $keywords = $this->request->filter('search')->get('keywords');
        self::pluginHandle()->trigger($hasPushed)->call('search', $keywords, $this);

        if (!$hasPushed) {
            $searchQuery = '%' . str_replace(' ', '%', $keywords) . '%';

            if ($this->user->hasLogin()) {
                $select->where("table.contents.password IS NULL
                 OR table.contents.password = '' OR table.contents.authorId = ?", $this->user->uid);
            } else {
                $select->where("table.contents.password IS NULL OR table.contents.password = ''");
            }

            $op = $this->db->getAdapter()->getDriver() == 'pgsql' ? 'ILIKE' : 'LIKE';

            $select->where("table.contents.title {$op} ? OR table.contents.text {$op} ?", $searchQuery, $searchQuery)
                ->where('table.contents.type = ?', 'post');
        }

        $this->archiveKeywords = $keywords;

        $this->pageRow = new class ($keywords) implements Router\ParamsDelegateInterface {
            private string $keywords;

            public function __construct(string $keywords)
            {
                $this->keywords = $keywords;
            }

            public function getRouterParam(string $key): string
            {
                switch ($key) {
                    case 'keywords':
                        return urlencode($this->keywords);
                    default:
                        return '{' . $key . '}';
                }
            }
        };

        $this->archiveFeedUrl = Router::url('search', $this->pageRow, $this->options->feedUrl);
        $this->archiveFeedRssUrl = Router::url('search', $this->pageRow, $this->options->feedRssUrl);
        $this->archiveFeedAtomUrl = Router::url('search', $this->pageRow, $this->options->feedAtomUrl);
        $this->archiveTitle = $keywords;
        $this->archiveType = 'search';
        $this->archiveSlug = $keywords;
        $this->archiveUrl = Router::url('search', $this->pageRow, $this->options->index);

        self::pluginHandle()->call('searchHandle', $this, $select);
    }
}
