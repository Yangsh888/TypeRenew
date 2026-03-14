<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$stat = \Widget\Stat::alloc();
?>

<main class="main">
    <div class="body container">
        <div class="row typecho-page-main" role="form">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2 tr-panel tr-profile-panel">
                <section class="tr-card tr-profile-summary">
                    <div class="tr-card-b">
                        <div class="tr-profile-head">
                            <div class="tr-profile-head-left">
                                <a class="tr-profile-avatar-link" href="https://gravatar.com/" title="<?php _e('在 Gravatar 上修改头像'); ?>">
                                    <img class="tr-profile-avatar" src="<?php echo htmlspecialchars(\Typecho\Common::gravatarUrl($user->mail, 96, 'X', 'mm', $request->isSecure()), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($user->screenName, ENT_QUOTES, 'UTF-8'); ?>">
                                </a>
                                <div class="tr-profile-meta">
                                    <div class="tr-profile-name"><?php $user->screenName(); ?></div>
                                    <div class="tr-profile-sub">
                                        <?php _e('文章 %s · 评论 %s · 分类 %s', $stat->myPublishedPostsNum, $stat->myPublishedCommentsNum, $stat->categoriesNum); ?>
                                    </div>
                                    <?php if ($user->logged > 0): ?>
                                        <?php $logged = new \Typecho\Date($user->logged); ?>
                                        <div class="tr-profile-sub"><?php _e('最后登录 %s', $logged->word()); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="tr-profile-head-right" aria-label="<?php _e('统计'); ?>">
                                <div class="tr-profile-kpis">
                                    <div class="tr-profile-kpi">
                                        <div class="tr-profile-kpi-v"><?php echo (int) $stat->myPublishedPostsNum; ?></div>
                                        <div class="tr-profile-kpi-k"><?php _e('文章'); ?></div>
                                    </div>
                                    <div class="tr-profile-kpi">
                                        <div class="tr-profile-kpi-v"><?php echo (int) $stat->myPublishedCommentsNum; ?></div>
                                        <div class="tr-profile-kpi-k"><?php _e('评论'); ?></div>
                                    </div>
                                    <div class="tr-profile-kpi">
                                        <div class="tr-profile-kpi-v"><?php echo (int) $stat->categoriesNum; ?></div>
                                        <div class="tr-profile-kpi-k"><?php _e('分类'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="tr-profile-main">
                    <section class="tr-card tr-form-card" data-tr-form-card>
                        <div class="tr-card-h">
                            <div class="tr-card-h-inner">
                                <h3><?php _e('个人资料'); ?></h3>
                                <div class="tr-card-actions" data-tr-form-actions></div>
                            </div>
                        </div>
                        <div class="tr-card-b">
                            <?php \Widget\Users\Profile::alloc()->profileForm()->render(); ?>
                        </div>
                    </section>

                    <?php if ($user->pass('contributor', true)): ?>
                        <section class="tr-card tr-form-card" id="writing-option" data-tr-form-card>
                            <div class="tr-card-h">
                                <div class="tr-card-h-inner">
                                    <h3><?php _e('撰写设置'); ?></h3>
                                    <div class="tr-card-actions" data-tr-form-actions></div>
                                </div>
                            </div>
                            <div class="tr-card-b">
                                <?php \Widget\Users\Profile::alloc()->optionsForm()->render(); ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="tr-card tr-form-card" id="change-password" data-tr-form-card>
                        <div class="tr-card-h">
                            <div class="tr-card-h-inner">
                                <h3><?php _e('密码修改'); ?></h3>
                                <div class="tr-card-actions" data-tr-form-actions></div>
                            </div>
                        </div>
                        <div class="tr-card-b">
                            <?php \Widget\Users\Profile::alloc()->passwordForm()->render(); ?>
                        </div>
                    </section>

                    <?php \Widget\Users\Profile::alloc()->personalFormList(); ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
\Typecho\Plugin::factory('admin/profile.php')->call('bottom');
include 'footer.php';
?>
