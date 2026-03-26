# Fuckseo.io Deepseek for TranslatePress

一款集成 DeepSeek AI 与 TranslatePress 的 WordPress 翻译插件，内置**两层翻译质量检查**和**翻译管理面板**。

## 功能特点

### 🌐 AI 翻译
- 使用 DeepSeek Chat API 进行高质量翻译
- 批量翻译（每次请求最多 64 条字符串）
- 支持 30+ 种语言

### 🔍 两层翻译质量检查
翻译结果在存入数据库前自动验证：

**第一层 — 代码级检测**
| 文本类型 | 规则 |
|---------|------|
| 短文本（<50 字符） | 有任何源语言字符 → 拒绝 |
| 长文本（≥50 字符） | 源语言字符占比 > 10% → 拒绝 |

- 通过 Unicode 范围检测中文、日文、韩文、阿拉伯文、西里尔字母、希腊文等
- 拉丁字母语言（英/法/德/西等）自动跳过此层

**第二层 — AI 质量审核**
- 源语言文字残留（未翻译片段）
- 内容丢失（翻译比原文短太多）
- HTML 标签破损（标签不完整/不匹配）

不合格翻译回退为原文，TranslatePress 会在下次页面访问时自动重试。

### ⚙️ 翻译管理面板
WordPress 后台左侧边栏独立菜单页面，包含两个板块：

**按语言删除翻译**
- Checkbox 表格显示所有翻译语言及条目数
- 一键删除对应语言的 dictionary 和 gettext 表

**未翻译 URL 爬取**
- 按语言 Tab 切换，列出所有已发布页面
- 点击"开始爬取"，服务器端逐个访问翻译版 URL
- 触发 TranslatePress 自动翻译，带进度条显示

## 环境要求

- WordPress 6.0+
- PHP 7.2+
- [TranslatePress](https://wordpress.org/plugins/translatepress-multilingual/)（免费版或付费版）
- [DeepSeek API Key](https://platform.deepseek.com/)

## 安装方法

1. 下载本仓库中的 `deepseek-for-translatepress.zip`
2. WordPress 后台 → **插件 → 安装插件 → 上传插件**
3. 选择 ZIP 文件 → **现在安装 → 启用**

## 配置步骤

1. 进入 **设置 → TranslatePress → 自动翻译**
2. 开启 **自动翻译**
3. 翻译引擎选择 **DeepSeek**
4. 输入 DeepSeek API Key
5. 保存设置

## 成本估算

DeepSeek 定价：输入 ¥2/百万 token，输出 ¥3/百万 token

| 内容 | 翻译费用 | 质量检查 | 总计 |
|------|---------|---------|------|
| 1 篇文章（1000 词英文） | ¥0.009 | ¥0.004 | **≈ ¥0.013** |
| 100 篇文章 | | | **≈ ¥1.3** |
| 1000 篇 × 5 种语言 | | | **≈ ¥65** |

## 支持的语言

阿拉伯语、保加利亚语、中文（简体/繁体）、捷克语、丹麦语、荷兰语、英语、爱沙尼亚语、芬兰语、法语、德语、希腊语、匈牙利语、印尼语、意大利语、日语、韩语、拉脱维亚语、立陶宛语、挪威语、波兰语、葡萄牙语、罗马尼亚语、俄语、斯洛伐克语、斯洛文尼亚语、西班牙语、瑞典语、土耳其语、乌克兰语

## 许可证

GPLv2 or later

## 致谢

- **作者**：[fuckseo.io](https://fuckseo.io)
- **基于**：[TranslatePress](https://translatepress.com/) 插件架构
- **翻译服务**：[DeepSeek AI](https://deepseek.com/)