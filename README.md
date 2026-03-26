# Fuckseo.io Deepseek for TranslatePress

A WordPress plugin that integrates DeepSeek AI with TranslatePress for high-quality machine translation, featuring **two-layer translation quality checking** and a **translation management admin panel**.

## Features

### 🌐 AI Translation
- Leverages DeepSeek Chat API for accurate, contextual translations
- Batch translation support (64 strings per request)
- 30+ languages supported

### 🔍 Two-Layer Quality Checking
Translations are automatically verified before being saved:

**Layer 1 — Code-level Detection**
| Text Type | Rule |
|-----------|------|
| Short text (<50 chars) | Any source language character → reject |
| Long text (≥50 chars) | Source language ratio > 10% → reject |

- Uses Unicode ranges to detect Chinese, Japanese, Korean, Arabic, Cyrillic, Greek, etc.
- Latin-script languages (en/fr/de/es) skip this layer automatically

**Layer 2 — AI Quality Validation**
- Source language residue (untranslated fragments)
- Content loss (translation significantly shorter than original)
- HTML tag damage (broken/mismatched tags)

Rejected translations fall back to original text; TranslatePress will auto-retry on next page visit.

### ⚙️ Translation Management Panel
A standalone admin page in WordPress sidebar with two sections:

**Delete Translations by Language**
- Checkbox table showing all configured languages with entry counts
- One-click deletion of dictionary & gettext tables per language

**Untranslated URL Crawler**
- Tab-based UI showing all published pages per language
- "Start Crawling" button visits each page's translated URL server-side
- Triggers TranslatePress automatic translation with progress bar

## Requirements

- WordPress 6.0+
- PHP 7.2+
- [TranslatePress](https://wordpress.org/plugins/translatepress-multilingual/) (free or premium)
- [DeepSeek API Key](https://platform.deepseek.com/)

## Installation

1. Download `deepseek-for-translatepress.zip` from this repository
2. WordPress Admin → **Plugins → Add New → Upload Plugin**
3. Select the ZIP file → **Install Now → Activate**

## Configuration

1. Go to **Settings → TranslatePress → Automatic Translation**
2. Enable **Automatic Translation**
3. Select **DeepSeek** as the translation engine
4. Enter your DeepSeek API key
5. Save settings

## Cost Estimate

DeepSeek pricing: ¥2/1M input tokens, ¥3/1M output tokens

| Content | Translation | QC Check | Total |
|---------|------------|----------|-------|
| 1 article (1000 words EN) | ¥0.009 | ¥0.004 | **≈ ¥0.013** |
| 100 articles | | | **≈ ¥1.3** |
| 1000 articles × 5 languages | | | **≈ ¥65** |

## Supported Languages

Arabic, Bulgarian, Chinese (Simplified/Traditional), Czech, Danish, Dutch, English, Estonian, Finnish, French, German, Greek, Hungarian, Indonesian, Italian, Japanese, Korean, Latvian, Lithuanian, Norwegian, Polish, Portuguese, Romanian, Russian, Slovak, Slovenian, Spanish, Swedish, Turkish, Ukrainian

## License

GPLv2 or later

## Credits

- **Author**: [fuckseo.io](https://fuckseo.io)
- **Based on**: [TranslatePress](https://translatepress.com/) plugin architecture
- **Translation Service**: [DeepSeek AI](https://deepseek.com/)