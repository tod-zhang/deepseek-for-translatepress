/**
 * DeepSeek TranslatePress Admin Page JavaScript
 * 处理删除翻译和爬取未翻译 URL 的 AJAX 交互
 */
(function ($) {
    'use strict';

    let crawlAborted = false;

    // ========== 删除翻译功能 ==========

    // 全选 / 取消全选
    $('#trp-ds-check-all').on('change', function () {
        $('.trp-ds-lang-check').prop('checked', this.checked);
        toggleDeleteButton();
    });

    // 单个 checkbox 变化
    $(document).on('change', '.trp-ds-lang-check', function () {
        toggleDeleteButton();
        // 更新全选状态
        var total = $('.trp-ds-lang-check').length;
        var checked = $('.trp-ds-lang-check:checked').length;
        $('#trp-ds-check-all').prop('checked', total === checked);
    });

    function toggleDeleteButton() {
        var anyChecked = $('.trp-ds-lang-check:checked').length > 0;
        $('#trp-ds-delete-btn').prop('disabled', !anyChecked);
    }

    // 删除按钮点击
    $('#trp-ds-delete-btn').on('click', function () {
        var selectedLangs = [];
        $('.trp-ds-lang-check:checked').each(function () {
            selectedLangs.push($(this).val());
        });

        if (selectedLangs.length === 0) return;

        var langNames = [];
        $('.trp-ds-lang-check:checked').each(function () {
            langNames.push($(this).closest('tr').find('strong').text());
        });

        var confirmMsg = trpDeepseekAdmin.i18n.confirmDelete + '\n\n' + langNames.join(', ');
        if (!confirm(confirmMsg)) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text(trpDeepseekAdmin.i18n.deleting);

        $.ajax({
            url: trpDeepseekAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'trp_deepseek_delete_translations',
                nonce: trpDeepseekAdmin.nonce,
                languages: selectedLangs,
            },
            success: function (response) {
                var $result = $('#trp-ds-delete-result');
                $result.removeClass('hidden success error');

                if (response.success) {
                    $result.addClass('success').html(response.data.message);
                    // 刷新页面更新数据
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    $result.addClass('error').html(response.data.message);
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-warning"></span> ' +
                        trpDeepseekAdmin.i18n.deleteBtn
                    );
                }
            },
            error: function () {
                $('#trp-ds-delete-result')
                    .removeClass('hidden')
                    .addClass('error')
                    .text(trpDeepseekAdmin.i18n.ajaxError);
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-warning"></span> ' +
                    trpDeepseekAdmin.i18n.deleteBtn
                );
            },
        });
    });

    // ========== 未翻译 URL & 爬取功能 ==========

    var currentLang = $('.trp-ds-tab-btn.active').data('lang');

    // 页面加载时加载第一个语言的 URL
    if (currentLang) {
        loadUrls(currentLang);
    }

    // Tab 切换
    $(document).on('click', '.trp-ds-tab-btn', function () {
        $('.trp-ds-tab-btn').removeClass('active');
        $(this).addClass('active');
        currentLang = $(this).data('lang');
        loadUrls(currentLang);
    });

    function loadUrls(langCode) {
        var $container = $('#trp-ds-urls-container');
        $container.find('.trp-ds-urls-loading').removeClass('hidden');
        $container.find('.trp-ds-urls-content').addClass('hidden');

        $.ajax({
            url: trpDeepseekAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'trp_deepseek_get_urls',
                nonce: trpDeepseekAdmin.nonce,
                language: langCode,
            },
            success: function (response) {
                $container.find('.trp-ds-urls-loading').addClass('hidden');
                $container.find('.trp-ds-urls-content').removeClass('hidden');

                if (response.success) {
                    renderUrlList(response.data.urls);
                } else {
                    $('#trp-ds-url-list').html(
                        '<tr><td colspan="4">' + response.data.message + '</td></tr>'
                    );
                }
            },
            error: function () {
                $container.find('.trp-ds-urls-loading').addClass('hidden');
                $container.find('.trp-ds-urls-content').removeClass('hidden');
                $('#trp-ds-url-list').html(
                    '<tr><td colspan="4">' + trpDeepseekAdmin.i18n.ajaxError + '</td></tr>'
                );
            },
        });
    }

    function renderUrlList(urls) {
        var $list = $('#trp-ds-url-list');
        $list.empty();

        $('#trp-ds-url-count').text(urls.length + ' ' + trpDeepseekAdmin.i18n.pages);

        if (urls.length === 0) {
            $list.html(
                '<tr><td colspan="4" style="text-align:center;color:#46b450;font-weight:500;">' +
                trpDeepseekAdmin.i18n.allTranslated +
                '</td></tr>'
            );
            $('#trp-ds-crawl-btn').addClass('hidden');
            return;
        }

        $('#trp-ds-crawl-btn').removeClass('hidden');

        urls.forEach(function (url, index) {
            var row =
                '<tr data-url="' + encodeURIComponent(url.translated_url) + '" data-index="' + index + '">' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + escapeHtml(url.title) + '</td>' +
                '<td><a href="' + escapeHtml(url.translated_url) + '" target="_blank">' +
                escapeHtml(truncateUrl(url.url, 60)) + '</a></td>' +
                '<td class="trp-ds-status pending">' + trpDeepseekAdmin.i18n.pending + '</td>' +
                '</tr>';
            $list.append(row);
        });
    }

    // 开始爬取
    $('#trp-ds-crawl-btn').on('click', function () {
        var $rows = $('#trp-ds-url-list tr[data-url]');
        if ($rows.length === 0) return;

        crawlAborted = false;
        $(this).addClass('hidden');
        $('#trp-ds-stop-btn').removeClass('hidden');
        $('#trp-ds-progress').removeClass('hidden');

        var total = $rows.length;
        var current = 0;

        updateProgress(current, total);

        function crawlNext(index) {
            if (index >= total || crawlAborted) {
                $('#trp-ds-crawl-btn').removeClass('hidden');
                $('#trp-ds-stop-btn').addClass('hidden');
                if (crawlAborted) {
                    updateProgressText(trpDeepseekAdmin.i18n.stopped + ' (' + current + '/' + total + ')');
                }
                return;
            }

            var $row = $rows.eq(index);
            var url = decodeURIComponent($row.data('url'));
            $row.find('.trp-ds-status').removeClass('pending').addClass('crawling').text(trpDeepseekAdmin.i18n.crawling);

            $.ajax({
                url: trpDeepseekAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'trp_deepseek_crawl_url',
                    nonce: trpDeepseekAdmin.nonce,
                    url: url,
                },
                success: function (response) {
                    current++;
                    if (response.success) {
                        $row.find('.trp-ds-status').removeClass('crawling').addClass('done')
                            .text(trpDeepseekAdmin.i18n.done + ' (' + response.data.status_code + ')');
                    } else {
                        $row.find('.trp-ds-status').removeClass('crawling').addClass('failed')
                            .text(trpDeepseekAdmin.i18n.failed);
                    }
                    updateProgress(current, total);
                    crawlNext(index + 1);
                },
                error: function () {
                    current++;
                    $row.find('.trp-ds-status').removeClass('crawling').addClass('failed')
                        .text(trpDeepseekAdmin.i18n.failed);
                    updateProgress(current, total);
                    crawlNext(index + 1);
                },
            });
        }

        crawlNext(0);
    });

    // 停止爬取
    $('#trp-ds-stop-btn').on('click', function () {
        crawlAborted = true;
    });

    function updateProgress(current, total) {
        var pct = total > 0 ? Math.round((current / total) * 100) : 0;
        $('.trp-ds-progress-fill').css('width', pct + '%');
        updateProgressText(current + ' / ' + total);
    }

    function updateProgressText(text) {
        $('.trp-ds-progress-text').text(text);
    }

    // Utilities
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function truncateUrl(url, maxLen) {
        if (!url) return '';
        if (url.length <= maxLen) return url;
        return url.substring(0, maxLen) + '...';
    }
})(jQuery);
