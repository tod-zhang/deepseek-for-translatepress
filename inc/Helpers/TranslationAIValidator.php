<?php
namespace hollisho\translatepress\translate\deepseek\inc\Helpers;

/**
 * 第二层翻译质量检查：AI 驱动的质量审核
 *
 * 将通过第一层的翻译批量发给 DeepSeek API，从三个维度检查：
 * 1. 源语言文字残留（未翻译片段）
 * 2. 翻译比原文短太多（内容丢失）
 * 3. HTML 标签破损
 */
class TranslationAIValidator
{
    /**
     * 批量 AI 质量检查
     *
     * @param array  $translations 通过第一层检查的翻译 [key => translated_string]
     * @param array  $originals    原文 [key => original_string]
     * @param string $sourceLang   源语言名称
     * @param string $targetLang   目标语言名称
     * @param string $apiKey       DeepSeek API Key
     * @param string $apiUrl       DeepSeek API URL
     * @return array ['passed' => [key => translation], 'failed' => [key => original]]
     */
    public static function validateBatch(
        array $translations,
        array $originals,
        $sourceLang,
        $targetLang,
        $apiKey,
        $apiUrl
    ) {
        if (empty($translations)) {
            return ['passed' => [], 'failed' => []];
        }

        $prompt = self::buildPrompt($translations, $originals, $sourceLang, $targetLang);

        $response = self::callApi($prompt, $apiKey, $apiUrl);

        if ($response === null) {
            // API 调用失败时，保守处理：全部通过（避免因检查服务故障丢失所有翻译）
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DeepSeek QC Layer2] API call failed, letting all translations pass');
            }
            return ['passed' => $translations, 'failed' => []];
        }

        return self::parseResults($response, $translations, $originals);
    }

    /**
     * 构建 AI 质量检查 prompt
     */
    private static function buildPrompt(array $translations, array $originals, $sourceLang, $targetLang)
    {
        $sourceLangName = isset(DeepSeekApiHelper::supportedLanguages[$sourceLang])
            ? DeepSeekApiHelper::supportedLanguages[$sourceLang]
            : $sourceLang;
        $targetLangName = isset(DeepSeekApiHelper::supportedLanguages[$targetLang])
            ? DeepSeekApiHelper::supportedLanguages[$targetLang]
            : $targetLang;

        $prompt  = "You are a translation quality checker. Check the following translations from {$sourceLangName} to {$targetLangName}.\n\n";
        $prompt .= "For each pair, evaluate THREE dimensions:\n";
        $prompt .= "1. Source language residue — any untranslated {$sourceLangName} text remaining in the translation\n";
        $prompt .= "2. Content loss — translation is significantly shorter than the original (missing content)\n";
        $prompt .= "3. HTML tag damage — HTML tags are broken, mismatched, or missing\n\n";
        $prompt .= "Return ONLY a JSON array, each element: {\"index\": <number>, \"result\": \"PASS\" or \"FAIL\"}\n";
        $prompt .= "Do NOT include any other text, explanation, or markdown formatting. Return raw JSON only.\n\n";
        $prompt .= "Pairs to check:\n";

        $index = 1;
        $keyMap = []; // index → original key
        foreach ($translations as $key => $translation) {
            $original = isset($originals[$key]) ? $originals[$key] : '';
            $prompt .= "[{$index}] Original: " . self::truncate($original, 500)
                     . " | Translation: " . self::truncate($translation, 500) . "\n";
            $keyMap[$index] = $key;
            $index++;
        }

        return ['prompt' => $prompt, 'keyMap' => $keyMap];
    }

    /**
     * 调用 DeepSeek API 进行质量检查
     *
     * @return string|null API 返回内容，失败返回 null
     */
    private static function callApi($promptData, $apiKey, $apiUrl)
    {
        $data = [
            'model'       => 'deepseek-chat',
            'temperature' => 0.1, // 低温度确保一致性
            'messages'    => [
                ['role' => 'user', 'content' => $promptData['prompt']]
            ],
            'max_tokens'  => 2000,
        ];

        $response = wp_remote_post($apiUrl, [
            'method'  => 'POST',
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($data),
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DeepSeek QC Layer2] WP_Error: ' . $response->get_error_message());
            }
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DeepSeek QC Layer2] HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
            }
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        if (empty($body) || empty($body->choices[0]->message->content)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DeepSeek QC Layer2] Empty or invalid response body');
            }
            return null;
        }

        return $body->choices[0]->message->content;
    }

    /**
     * 解析 AI 返回的检查结果
     *
     * @param string $content      AI 返回的原始内容
     * @param array  $translations 翻译结果
     * @param array  $originals    原文
     * @return array ['passed' => [...], 'failed' => [...]]
     */
    private static function parseResults($content, array $translations, array $originals)
    {
        $passed = [];
        $failed = [];

        // 提取 JSON（可能被 markdown 代码块包裹）
        $jsonContent = $content;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $matches)) {
            $jsonContent = $matches[1];
        }
        $jsonContent = trim($jsonContent);

        $results = json_decode($jsonContent, true);

        // 如果 JSON 解析失败，保守处理：全部通过
        if (!is_array($results)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DeepSeek QC Layer2] JSON parse failed, content: ' . mb_substr($content, 0, 200));
            }
            return ['passed' => $translations, 'failed' => []];
        }

        // 建立 index → result 映射
        $resultMap = [];
        foreach ($results as $item) {
            if (isset($item['index']) && isset($item['result'])) {
                $resultMap[(int)$item['index']] = strtoupper($item['result']);
            }
        }

        // 将翻译按顺序与结果对应
        $index = 1;
        foreach ($translations as $key => $translation) {
            $original = isset($originals[$key]) ? $originals[$key] : '';
            $result   = isset($resultMap[$index]) ? $resultMap[$index] : 'PASS'; // 默认通过

            if ($result === 'PASS') {
                $passed[$key] = $translation;
            } else {
                $failed[$key] = $original;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[DeepSeek QC Layer2 FAIL] key=%s | original="%s" | translation="%s"',
                        $key,
                        mb_substr($original, 0, 80),
                        mb_substr($translation, 0, 80)
                    ));
                }
            }
            $index++;
        }

        return ['passed' => $passed, 'failed' => $failed];
    }

    /**
     * 截断文本以避免 prompt 过长
     */
    private static function truncate($text, $maxLen = 500)
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        return mb_substr($text, 0, $maxLen) . '...';
    }
}
