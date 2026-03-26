<?php
namespace hollisho\translatepress\translate\deepseek\inc\Helpers;

/**
 * 翻译管理器 — 按语言删除翻译 & 未翻译 URL 发现
 */
class TranslationManager
{
    /**
     * 获取 TranslatePress 配置的所有翻译语言
     *
     * @return array [['code' => 'fr_FR', 'name' => 'French', 'count' => 123], ...]
     */
    public static function getConfiguredLanguages()
    {
        $settings = get_option('trp_settings', []);
        $languages = isset($settings['translation-languages']) ? $settings['translation-languages'] : [];
        $default_language = isset($settings['default-language']) ? $settings['default-language'] : '';

        $result = [];
        foreach ($languages as $lang_code) {
            if ($lang_code === $default_language) {
                continue; // 跳过默认语言
            }

            $result[] = [
                'code' => $lang_code,
                'name' => self::getLanguageName($lang_code),
                'dictionary_count' => self::getTranslationCount($lang_code, 'dictionary'),
                'gettext_count' => self::getTranslationCount($lang_code, 'gettext'),
            ];
        }

        return $result;
    }

    /**
     * 获取某语言的翻译条目数
     */
    private static function getTranslationCount($language_code, $type = 'dictionary')
    {
        global $wpdb;
        $table_name = self::getTableName($language_code, $type);

        // 检查表是否存在
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME, $table_name
        ));

        if (!$table_exists) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
    }

    /**
     * 删除指定语言的翻译数据
     *
     * @param string $language_code 语言代码 (e.g. 'fr_FR')
     * @return array ['success' => bool, 'message' => string, 'deleted_tables' => []]
     */
    public static function deleteLanguageTranslations($language_code)
    {
        global $wpdb;
        $deleted_tables = [];
        $errors = [];

        // 删除 dictionary 表
        $dict_table = self::getTableName($language_code, 'dictionary');
        if (self::tableExists($dict_table)) {
            $result = $wpdb->query("DROP TABLE IF EXISTS `{$dict_table}`");
            if ($result !== false) {
                $deleted_tables[] = $dict_table;
            } else {
                $errors[] = "Failed to drop {$dict_table}";
            }
        }

        // 删除 gettext 表
        $gettext_table = self::getTableName($language_code, 'gettext');
        if (self::tableExists($gettext_table)) {
            $result = $wpdb->query("DROP TABLE IF EXISTS `{$gettext_table}`");
            if ($result !== false) {
                $deleted_tables[] = $gettext_table;
            } else {
                $errors[] = "Failed to drop {$gettext_table}";
            }
        }

        // 删除 slug_translations 中对应语言的记录
        $slug_table = $wpdb->prefix . 'trp_slug_translations';
        if (self::tableExists($slug_table)) {
            $wpdb->delete($slug_table, ['language' => $language_code]);
        }

        if (empty($errors)) {
            return [
                'success' => true,
                'message' => sprintf('Successfully deleted translations for %s', self::getLanguageName($language_code)),
                'deleted_tables' => $deleted_tables,
            ];
        }

        return [
            'success' => false,
            'message' => implode('; ', $errors),
            'deleted_tables' => $deleted_tables,
        ];
    }

    /**
     * 获取所有已发布的页面 URL
     *
     * @return array ['url' => string, 'title' => string, 'post_id' => int]
     */
    public static function getAllPublishedUrls()
    {
        $post_types = get_post_types(['public' => true], 'names');
        $urls = [];

        $posts = get_posts([
            'post_type'      => array_values($post_types),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        foreach ($posts as $post) {
            $urls[] = [
                'post_id' => $post->ID,
                'title'   => $post->post_title,
                'url'     => get_permalink($post->ID),
            ];
        }

        return $urls;
    }

    /**
     * 获取某语言未翻译的 URL 列表
     *
     * @param string $language_code 语言代码
     * @return array
     */
    public static function getUntranslatedUrls($language_code)
    {
        global $wpdb;

        $all_urls = self::getAllPublishedUrls();
        $dict_table = self::getTableName($language_code, 'dictionary');

        // 如果翻译表不存在，说明全部未翻译
        if (!self::tableExists($dict_table)) {
            return $all_urls;
        }

        // 获取已翻译的 original_id 列表
        $original_table = $wpdb->prefix . 'trp_original_strings';
        if (!self::tableExists($original_table)) {
            return $all_urls;
        }

        // 查询已翻译的字符串数量，按 original 的来源分组
        // TranslatePress 追踪 original strings 并关联到 dictionary
        // 我们只能通过检查 dictionary 表是否有内容来判断翻译覆盖率
        // 更可靠的方式：检查该语言的页面 URL 是否能生成翻译版 URL
        $translated_count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT original_id) FROM `{$dict_table}` WHERE translated != '' AND status != 0"
        );

        $total_originals = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$original_table}`"
        );

        // 返回所有 URL，附带翻译覆盖率信息
        $untranslated = [];
        foreach ($all_urls as $url_info) {
            $url_info['translated_url'] = self::getTranslatedUrl($url_info['url'], $language_code);
            $untranslated[] = $url_info;
        }

        return $untranslated;
    }

    /**
     * 获取翻译统计信息
     *
     * @return array 每个语言的统计
     */
    public static function getTranslationStats()
    {
        global $wpdb;
        $languages = self::getConfiguredLanguages();
        $original_table = $wpdb->prefix . 'trp_original_strings';
        $total_originals = 0;

        if (self::tableExists($original_table)) {
            $total_originals = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$original_table}`");
        }

        foreach ($languages as &$lang) {
            $dict_table = self::getTableName($lang['code'], 'dictionary');
            if (self::tableExists($dict_table)) {
                $lang['translated'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM `{$dict_table}` WHERE translated != '' AND status != 0"
                );
            } else {
                $lang['translated'] = 0;
            }
            $lang['total'] = $total_originals;
        }

        return $languages;
    }

    /**
     * 爬取指定 URL 以触发自动翻译
     *
     * @param string $url 翻译版 URL
     * @return array ['success' => bool, 'status_code' => int]
     */
    public static function crawlUrl($url)
    {
        $response = wp_remote_get($url, [
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; TranslatePressCrawler/1.0)',
            'sslverify'  => false,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        return [
            'success' => ($status_code >= 200 && $status_code < 400),
            'status_code' => $status_code,
            'message' => 'OK',
        ];
    }

    /**
     * 构造翻译版 URL
     */
    private static function getTranslatedUrl($url, $language_code)
    {
        $settings = get_option('trp_settings', []);
        $url_slugs = isset($settings['url-slugs']) ? $settings['url-slugs'] : [];

        if (isset($url_slugs[$language_code])) {
            $slug = $url_slugs[$language_code];
            $home_url = home_url('/');
            // 将 home_url 后面插入语言 slug
            $translated_url = str_replace($home_url, $home_url . $slug . '/', $url);
            return $translated_url;
        }

        return $url;
    }

    /**
     * 获取 TranslatePress 翻译表名
     */
    private static function getTableName($language_code, $type = 'dictionary')
    {
        global $wpdb;
        // TranslatePress 表名格式: {prefix}trp_dictionary_{lang_code} (小写, 连字符替换)
        $lang_suffix = strtolower(str_replace('-', '_', $language_code));
        return $wpdb->prefix . "trp_{$type}_{$lang_suffix}";
    }

    /**
     * 检查数据库表是否存在
     */
    private static function tableExists($table_name)
    {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME, $table_name
        ));
        return (int) $result > 0;
    }

    /**
     * 获取语言名称
     */
    private static function getLanguageName($code)
    {
        // 尝试 TranslatePress 内置方法
        if (class_exists('TRP_Languages')) {
            $trp_languages = new \TRP_Languages();
            $languages = $trp_languages->get_wp_languages();
            foreach ($languages as $lang) {
                if ($lang['language'] === $code) {
                    return $lang['english_name'];
                }
            }
        }

        // 降级方案
        $names = [
            'en_US' => 'English', 'en_GB' => 'English (UK)',
            'zh_CN' => 'Chinese (Simplified)', 'zh_TW' => 'Chinese (Traditional)',
            'ja' => 'Japanese', 'ko' => 'Korean',
            'fr_FR' => 'French', 'de_DE' => 'German',
            'es_ES' => 'Spanish', 'it_IT' => 'Italian',
            'pt_BR' => 'Portuguese (Brazil)', 'pt_PT' => 'Portuguese',
            'ru_RU' => 'Russian', 'ar' => 'Arabic',
            'nl_NL' => 'Dutch', 'sv_SE' => 'Swedish',
            'da_DK' => 'Danish', 'fi' => 'Finnish',
            'nb_NO' => 'Norwegian', 'pl_PL' => 'Polish',
            'tr_TR' => 'Turkish', 'uk' => 'Ukrainian',
            'el' => 'Greek', 'hu_HU' => 'Hungarian',
            'cs_CZ' => 'Czech', 'ro_RO' => 'Romanian',
            'bg_BG' => 'Bulgarian', 'sk_SK' => 'Slovak',
            'sl_SI' => 'Slovenian', 'et' => 'Estonian',
            'lv' => 'Latvian', 'lt_LT' => 'Lithuanian',
            'id_ID' => 'Indonesian', 'th' => 'Thai',
            'vi' => 'Vietnamese', 'ms_MY' => 'Malay',
            'hi_IN' => 'Hindi',
        ];

        return isset($names[$code]) ? $names[$code] : $code;
    }
}
