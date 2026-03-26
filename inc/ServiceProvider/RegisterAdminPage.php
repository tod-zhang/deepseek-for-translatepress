<?php
namespace hollisho\translatepress\translate\deepseek\inc\ServiceProvider;

use hollisho\translatepress\translate\deepseek\inc\Base\Common;
use hollisho\translatepress\translate\deepseek\inc\Base\ServiceProviderInterface;
use hollisho\translatepress\translate\deepseek\inc\Helpers\TranslationManager;

/**
 * 注册翻译管理后台页面
 */
class RegisterAdminPage extends Common implements ServiceProviderInterface
{
    public function register()
    {
        add_action('admin_menu', [$this, 'addAdminMenu'], 99);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_trp_deepseek_delete_translations', [$this, 'ajaxDeleteTranslations']);
        add_action('wp_ajax_trp_deepseek_get_urls', [$this, 'ajaxGetUrls']);
        add_action('wp_ajax_trp_deepseek_crawl_url', [$this, 'ajaxCrawlUrl']);
    }

    /**
     * 添加管理菜单
     */
    public function addAdminMenu()
    {
        add_menu_page(
            __('Translation Management', 'hollisho-integration-deepseek-for-translatepress'),
            __('Translation Mgmt', 'hollisho-integration-deepseek-for-translatepress'),
            'manage_options',
            'trp-deepseek-management',
            [$this, 'renderAdminPage'],
            'dashicons-translation',
            81
        );
    }

    /**
     * 渲染管理页面
     */
    public function renderAdminPage()
    {
        $languages = TranslationManager::getConfiguredLanguages();
        $stats = TranslationManager::getTranslationStats();

        // 合并统计数据到语言数组
        $statsMap = [];
        foreach ($stats as $stat) {
            $statsMap[$stat['code']] = $stat;
        }
        foreach ($languages as &$lang) {
            if (isset($statsMap[$lang['code']])) {
                $lang['translated'] = $statsMap[$lang['code']]['translated'];
                $lang['total'] = $statsMap[$lang['code']]['total'];
            }
        }

        include dirname(__DIR__) . '/Views/admin-page.php';
    }

    /**
     * 加载 CSS/JS 资源
     */
    public function enqueueAssets($hook)
    {
        // 只在我们的管理页面加载
        if (strpos($hook, 'trp-deepseek-management') === false) {
            return;
        }

        wp_enqueue_style(
            'trp-deepseek-admin-css',
            $this->plugin_url . 'assets/css/admin-page.css',
            [],
            Common::HO_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'trp-deepseek-admin-js',
            $this->plugin_url . 'assets/js/admin-page.js',
            ['jquery'],
            Common::HO_PLUGIN_VERSION,
            true
        );

        wp_localize_script('trp-deepseek-admin-js', 'trpDeepseekAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('trp_deepseek_admin'),
            'i18n'    => [
                'confirmDelete' => __('Are you sure you want to delete translations for the following languages? This action cannot be undone!', 'hollisho-integration-deepseek-for-translatepress'),
                'deleting'      => __('Deleting...', 'hollisho-integration-deepseek-for-translatepress'),
                'deleteBtn'     => __('Delete Selected Translations', 'hollisho-integration-deepseek-for-translatepress'),
                'ajaxError'     => __('An error occurred. Please try again.', 'hollisho-integration-deepseek-for-translatepress'),
                'pages'         => __('pages', 'hollisho-integration-deepseek-for-translatepress'),
                'allTranslated' => __('All pages have been translated for this language!', 'hollisho-integration-deepseek-for-translatepress'),
                'pending'       => __('Pending', 'hollisho-integration-deepseek-for-translatepress'),
                'crawling'      => __('Crawling...', 'hollisho-integration-deepseek-for-translatepress'),
                'done'          => __('Done', 'hollisho-integration-deepseek-for-translatepress'),
                'failed'        => __('Failed', 'hollisho-integration-deepseek-for-translatepress'),
                'stopped'       => __('Stopped', 'hollisho-integration-deepseek-for-translatepress'),
            ],
        ]);
    }

    /**
     * AJAX: 删除翻译
     */
    public function ajaxDeleteTranslations()
    {
        check_ajax_referer('trp_deepseek_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $languages = isset($_POST['languages']) ? array_map('sanitize_text_field', $_POST['languages']) : [];

        if (empty($languages)) {
            wp_send_json_error(['message' => 'No languages selected']);
        }

        $results = [];
        $allSuccess = true;

        foreach ($languages as $lang_code) {
            $result = TranslationManager::deleteLanguageTranslations($lang_code);
            $results[] = $result;
            if (!$result['success']) {
                $allSuccess = false;
            }
        }

        // 构建消息
        $messages = [];
        foreach ($results as $r) {
            $messages[] = $r['message'];
        }

        if ($allSuccess) {
            wp_send_json_success(['message' => implode('<br>', $messages)]);
        } else {
            wp_send_json_error(['message' => implode('<br>', $messages)]);
        }
    }

    /**
     * AJAX: 获取未翻译 URL 列表
     */
    public function ajaxGetUrls()
    {
        check_ajax_referer('trp_deepseek_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';

        if (empty($language)) {
            wp_send_json_error(['message' => 'No language specified']);
        }

        $urls = TranslationManager::getUntranslatedUrls($language);

        wp_send_json_success(['urls' => $urls]);
    }

    /**
     * AJAX: 爬取单个 URL
     */
    public function ajaxCrawlUrl()
    {
        check_ajax_referer('trp_deepseek_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (empty($url)) {
            wp_send_json_error(['message' => 'No URL specified']);
        }

        $result = TranslationManager::crawlUrl($url);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
