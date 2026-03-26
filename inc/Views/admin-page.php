<?php
defined('ABSPATH') || exit;

/**
 * Admin page template for Translation Management
 *
 * @var array $languages 配置的翻译语言
 * @var array $stats     翻译统计
 */
?>

<div class="wrap trp-deepseek-admin">
    <h1><?php esc_html_e('Translation Management', 'hollisho-integration-deepseek-for-translatepress'); ?></h1>

    <!-- 板块一：按语言删除翻译 -->
    <div class="trp-ds-card" id="trp-ds-delete-section">
        <h2>
            <span class="dashicons dashicons-trash"></span>
            <?php esc_html_e('Delete Translations by Language', 'hollisho-integration-deepseek-for-translatepress'); ?>
        </h2>
        <p class="description">
            <?php esc_html_e('Select the languages whose translations you want to delete. This will drop the corresponding dictionary and gettext tables from the database.', 'hollisho-integration-deepseek-for-translatepress'); ?>
        </p>

        <?php if (empty($languages)) : ?>
            <p class="trp-ds-notice"><?php esc_html_e('No translation languages configured.', 'hollisho-integration-deepseek-for-translatepress'); ?></p>
        <?php else : ?>
            <table class="trp-ds-table widefat">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="trp-ds-check-all" /></th>
                        <th><?php esc_html_e('Language', 'hollisho-integration-deepseek-for-translatepress'); ?></th>
                        <th><?php esc_html_e('Code', 'hollisho-integration-deepseek-for-translatepress'); ?></th>
                        <th><?php esc_html_e('Dictionary Entries', 'hollisho-integration-deepseek-for-translatepress'); ?></th>
                        <th><?php esc_html_e('Gettext Entries', 'hollisho-integration-deepseek-for-translatepress'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($languages as $lang) : ?>
                        <tr>
                            <td><input type="checkbox" class="trp-ds-lang-check" value="<?php echo esc_attr($lang['code']); ?>" /></td>
                            <td><strong><?php echo esc_html($lang['name']); ?></strong></td>
                            <td><code><?php echo esc_html($lang['code']); ?></code></td>
                            <td><?php echo esc_html(number_format_i18n($lang['dictionary_count'])); ?></td>
                            <td><?php echo esc_html(number_format_i18n($lang['gettext_count'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="trp-ds-actions">
                <button type="button" id="trp-ds-delete-btn" class="button button-primary button-hero trp-ds-danger-btn" disabled>
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Delete Selected Translations', 'hollisho-integration-deepseek-for-translatepress'); ?>
                </button>
            </p>
            <div id="trp-ds-delete-result" class="trp-ds-result hidden"></div>
        <?php endif; ?>
    </div>

    <!-- 板块二：未翻译 URL 列表 & 爬取 -->
    <div class="trp-ds-card" id="trp-ds-crawl-section">
        <h2>
            <span class="dashicons dashicons-admin-links"></span>
            <?php esc_html_e('Untranslated URLs & Crawl Trigger', 'hollisho-integration-deepseek-for-translatepress'); ?>
        </h2>
        <p class="description">
            <?php esc_html_e('Browse pages that haven\'t been translated yet. Click "Start Crawling" to visit each page and trigger automatic translation.', 'hollisho-integration-deepseek-for-translatepress'); ?>
        </p>

        <?php if (empty($languages)) : ?>
            <p class="trp-ds-notice"><?php esc_html_e('No translation languages configured.', 'hollisho-integration-deepseek-for-translatepress'); ?></p>
        <?php else : ?>
            <div class="trp-ds-lang-tabs">
                <?php foreach ($languages as $index => $lang) : ?>
                    <button type="button"
                        class="trp-ds-tab-btn <?php echo $index === 0 ? 'active' : ''; ?>"
                        data-lang="<?php echo esc_attr($lang['code']); ?>">
                        <?php echo esc_html($lang['name']); ?>
                        <?php if (isset($lang['translated']) && isset($lang['total']) && $lang['total'] > 0) : ?>
                            <span class="trp-ds-badge"><?php echo esc_html(round($lang['translated'] / $lang['total'] * 100)); ?>%</span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="trp-ds-urls-container">
                <div class="trp-ds-urls-loading">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading URLs...', 'hollisho-integration-deepseek-for-translatepress'); ?>
                </div>
                <div class="trp-ds-urls-content hidden">
                    <div class="trp-ds-urls-header">
                        <span id="trp-ds-url-count"></span>
                        <button type="button" id="trp-ds-crawl-btn" class="button button-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Start Crawling', 'hollisho-integration-deepseek-for-translatepress'); ?>
                        </button>
                        <button type="button" id="trp-ds-stop-btn" class="button hidden">
                            <?php esc_html_e('Stop', 'hollisho-integration-deepseek-for-translatepress'); ?>
                        </button>
                    </div>

                    <!-- 进度条 -->
                    <div id="trp-ds-progress" class="trp-ds-progress hidden">
                        <div class="trp-ds-progress-bar">
                            <div class="trp-ds-progress-fill" style="width: 0%"></div>
                        </div>
                        <span class="trp-ds-progress-text">0 / 0</span>
                    </div>

                    <!-- URL 列表 -->
                    <table class="trp-ds-url-table widefat">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php esc_html_e('Page Title', 'hollisho-integration-deepseek-for-translatepress'); ?></th>
                                <th><?php esc_html_e('URL', 'hollisho-integration-deepseek-for-translatepress'); ?></th>
                                <th><?php esc_html_e('Status', 'hollisho-integration-deepseek-for-translatepress'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="trp-ds-url-list"></tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
