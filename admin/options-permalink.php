<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$permalinkWidget = \Widget\Options\Permalink::alloc();
?>

<main class="main">
    <div class="body container">
        <div class="row typecho-page-main" role="form">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2 tr-panel">
                <?php include 'options-tabs.php'; ?>
                <div class="tr-settings-body">
                <?php $permalinkWidget->form()->render(); ?>
                </div>
                <div class="tr-card tr-mt-16">
                    <div class="tr-card-b">
                        <div class="tr-section-title"><?php _e('伪静态规则示例'); ?></div>
                        <?php foreach ($permalinkWidget->rewriteNotes() as $note): ?>
                            <div class="tr-help"><?php echo $note; ?></div>
                        <?php endforeach; ?>

                        <?php foreach ($permalinkWidget->rewriteExamples() as $example): ?>
                            <div class="tr-mt-12">
                                <div class="tr-section-title"><?php echo htmlspecialchars((string) $example['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="tr-help"><?php echo htmlspecialchars((string) $example['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <pre class="tr-mt-12"><code class="mono"><?php echo htmlspecialchars((string) $example['code'], ENT_QUOTES, 'UTF-8'); ?></code></pre>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
?>

<?php include 'footer.php'; ?>
