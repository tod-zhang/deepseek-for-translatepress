<?php
namespace hollisho\translatepress\translate\deepseek\inc\Helpers;

/**
 * 第一层翻译质量检查：代码级源语言字符残留检测
 *
 * 检查翻译结果中源语言字符占比：
 * - 短文本（<50字符）：有任何源语言字符 → 拒绝
 * - 长文本（≥50字符）：源语言字符占比 > 10% → 拒绝
 */
class TranslationQualityChecker
{
    /**
     * Unicode 字符范围映射表（源语言代码 → regex 字符类）
     * 仅对拥有独特文字系统的语言有效
     */
    private static $unicodeRanges = [
        // 中文
        'zh-cn' => '\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}\x{f900}-\x{faff}',
        'zh-tw' => '\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}\x{f900}-\x{faff}',
        // 日文（平假名 + 片假名 + 汉字）
        'ja'    => '\x{3040}-\x{309f}\x{30a0}-\x{30ff}\x{4e00}-\x{9fff}',
        // 韩文
        'ko'    => '\x{ac00}-\x{d7af}\x{1100}-\x{11ff}\x{3130}-\x{318f}',
        // 阿拉伯文
        'ar'    => '\x{0600}-\x{06ff}\x{0750}-\x{077f}\x{08a0}-\x{08ff}',
        // 俄文 / 乌克兰文 / 保加利亚文（西里尔字母）
        'ru'    => '\x{0400}-\x{04ff}',
        'uk'    => '\x{0400}-\x{04ff}',
        'bg'    => '\x{0400}-\x{04ff}',
        // 希腊文
        'el'    => '\x{0370}-\x{03ff}\x{1f00}-\x{1fff}',
        // 泰文
        'th'    => '\x{0e00}-\x{0e7f}',
        // 希伯来文
        'he'    => '\x{0590}-\x{05ff}',
    ];

    /**
     * 短文本阈值（字符数）
     */
    const SHORT_TEXT_THRESHOLD = 50;

    /**
     * 长文本源语言字符占比上限
     */
    const LONG_TEXT_RATIO_LIMIT = 0.10;

    /**
     * 批量检查翻译质量
     *
     * @param array  $translations  翻译结果 [key => translated_string]
     * @param array  $originals     原文 [key => original_string]
     * @param string $sourceLang    源语言代码 (e.g. 'zh-cn', 'ja', 'en')
     * @return array ['passed' => [key => translation], 'failed' => [key => original]]
     */
    public static function checkBatch(array $translations, array $originals, $sourceLang)
    {
        $passed = [];
        $failed = [];

        // 获取源语言的 Unicode 范围
        $pattern = self::getSourceLanguagePattern($sourceLang);

        // 如果源语言无独特文字系统（西文→西文），代码级检查不适用，全部通过
        if ($pattern === null) {
            return [
                'passed' => $translations,
                'failed' => [],
            ];
        }

        foreach ($translations as $key => $translation) {
            $original = isset($originals[$key]) ? $originals[$key] : '';

            if (self::checkSingle($translation, $pattern)) {
                $passed[$key] = $translation;
            } else {
                $failed[$key] = $original;
                // 记录日志
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[DeepSeek QC Layer1 FAIL] key=%s | source_lang=%s | original="%s" | translation="%s"',
                        $key, $sourceLang,
                        mb_substr($original, 0, 80),
                        mb_substr($translation, 0, 80)
                    ));
                }
            }
        }

        return [
            'passed' => $passed,
            'failed' => $failed,
        ];
    }

    /**
     * 检查单条翻译
     *
     * @param string $translation 翻译后的文本
     * @param string $pattern     源语言字符的正则匹配模式
     * @return bool true = 通过, false = 拒绝
     */
    private static function checkSingle($translation, $pattern)
    {
        // 去除 HTML 标签，只检查纯文本内容
        $plainText = strip_tags($translation);
        $plainText = trim($plainText);

        // 空文本直接通过（交给 AI 层判断内容丢失）
        if (mb_strlen($plainText) === 0) {
            return true;
        }

        // 移除白名单词汇（品牌名、专有名词），避免误判
        $plainText = self::stripWhitelistedTerms($plainText);

        // 计算源语言字符数
        $sourceCharCount = preg_match_all($pattern, $plainText);
        $totalCharCount  = mb_strlen($plainText);

        // 移除白名单后文本为空，直接通过
        if ($totalCharCount === 0) {
            return true;
        }

        if ($totalCharCount < self::SHORT_TEXT_THRESHOLD) {
            // 短文本：有任何源语言字符 → 拒绝
            return $sourceCharCount === 0;
        } else {
            // 长文本：源语言字符占比 > 10% → 拒绝
            $ratio = $sourceCharCount / $totalCharCount;
            return $ratio <= self::LONG_TEXT_RATIO_LIMIT;
        }
    }

    /**
     * 根据源语言代码获取 Unicode 正则匹配模式
     *
     * @param string $sourceLang 源语言代码
     * @return string|null 正则模式，null 表示不适用代码级检查
     */
    private static function getSourceLanguagePattern($sourceLang)
    {
        // 标准化语言代码为小写
        $sourceLang = strtolower($sourceLang);

        if (isset(self::$unicodeRanges[$sourceLang])) {
            return '/[' . self::$unicodeRanges[$sourceLang] . ']/u';
        }

        // 不在列表中的语言（拉丁字母系语言等）不适用代码级检查
        return null;
    }

    /**
     * 获取白名单（品牌名/专有名词）
     * 从 TranslatePress「从自动转换中排除字符串」设置中读取
     *
     * @return array 白名单词汇列表
     */
    private static function getWhitelist()
    {
        static $whitelist = null;

        if ($whitelist !== null) {
            return $whitelist;
        }

        $whitelist = [];

        if (!function_exists('get_option')) {
            return $whitelist;
        }

        $settings = get_option('trp_advanced_settings', []);

        // TranslatePress 在 trp_advanced_settings 中存储排除字符串
        if (!empty($settings['exclude_strings_from_automatic_translation'])) {
            $excluded = $settings['exclude_strings_from_automatic_translation'];
            if (is_array($excluded)) {
                $whitelist = array_filter(array_map('trim', $excluded));
            } elseif (is_string($excluded)) {
                $whitelist = array_filter(array_map('trim', explode("\n", $excluded)));
            }
        }

        return $whitelist;
    }

    /**
     * 从文本中移除白名单词汇
     * 这样品牌名（如"华为"、"淘宝"）出现在翻译中不会被误判为源语言残留
     *
     * @param string $text 待检查的文本
     * @return string 移除白名单词汇后的文本
     */
    private static function stripWhitelistedTerms($text)
    {
        $whitelist = self::getWhitelist();

        if (empty($whitelist)) {
            return $text;
        }

        foreach ($whitelist as $term) {
            if (mb_strlen($term) > 0) {
                $text = str_ireplace($term, '', $text);
            }
        }

        return trim($text);
    }
}
