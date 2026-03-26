<?php
namespace hollisho\translatepress\translate\deepseek\inc\Helpers;

/**
 * 翻译重试限制器
 *
 * 使用 WordPress transient 记录每条字符串的每日翻译失败次数。
 * 同一字符串每天最多重试 3 次（翻译检查失败后），超过则跳过不再翻译。
 * 计数器每天自动重置（transient 过期时间 24h）。
 */
class TranslationRetryLimiter
{
    /**
     * 每日最大重试次数
     */
    const MAX_DAILY_RETRIES = 3;

    /**
     * transient 前缀
     */
    const TRANSIENT_PREFIX = 'trp_ds_retry_';

    /**
     * transient 过期时间（秒）= 24 小时
     */
    const EXPIRATION = 86400;

    /**
     * 检查某条字符串是否已超过每日重试上限
     *
     * @param string $original     原文字符串
     * @param string $targetLang   目标语言代码
     * @return bool true = 可以翻译, false = 已达上限，跳过
     */
    public static function canRetry($original, $targetLang)
    {
        if (!function_exists('get_transient')) {
            return true;
        }

        $key = self::getTransientKey($original, $targetLang);
        $count = get_transient($key);

        if ($count === false) {
            return true; // 没有记录，可以翻译
        }

        return (int) $count < self::MAX_DAILY_RETRIES;
    }

    /**
     * 记录一次翻译失败
     *
     * @param string $original     原文字符串
     * @param string $targetLang   目标语言代码
     */
    public static function recordFailure($original, $targetLang)
    {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return;
        }

        $key = self::getTransientKey($original, $targetLang);
        $count = get_transient($key);

        if ($count === false) {
            set_transient($key, 1, self::EXPIRATION);
        } else {
            set_transient($key, (int) $count + 1, self::EXPIRATION);
        }
    }

    /**
     * 从待翻译列表中过滤掉已达上限的字符串
     *
     * @param array  $strings      待翻译字符串 [key => original_text]
     * @param string $targetLang   目标语言代码
     * @return array ['allowed' => [可以翻译的], 'blocked' => [被限制的]]
     */
    public static function filterStrings(array $strings, $targetLang)
    {
        $allowed = [];
        $blocked = [];

        foreach ($strings as $key => $original) {
            if (self::canRetry($original, $targetLang)) {
                $allowed[$key] = $original;
            } else {
                $blocked[$key] = $original;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[DeepSeek Retry Limiter] Skipped (daily limit reached): "%s" -> %s',
                        mb_substr($original, 0, 60),
                        $targetLang
                    ));
                }
            }
        }

        return ['allowed' => $allowed, 'blocked' => $blocked];
    }

    /**
     * 批量记录翻译失败
     *
     * @param array  $failedOriginals 失败的原文 [key => original_text]
     * @param string $targetLang      目标语言代码
     */
    public static function recordBatchFailure(array $failedOriginals, $targetLang)
    {
        foreach ($failedOriginals as $original) {
            self::recordFailure($original, $targetLang);
        }
    }

    /**
     * 生成 transient key
     * 使用原文的 md5 hash 避免 key 过长
     */
    private static function getTransientKey($original, $targetLang)
    {
        $hash = md5($original . '|' . $targetLang);
        return self::TRANSIENT_PREFIX . $hash;
    }
}
