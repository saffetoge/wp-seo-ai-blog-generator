/**
 * WPBG Post Editor Integration
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        var $regenerateBtn = $('#wpbg-regenerate-btn');
        var $formatBtn = $('#wpbg-format-btn');
        var $formatCustomBtn = $('#wpbg-format-custom-btn');
        var $customText = $('#wpbg-custom-text');
        var $keywordInput = $('#wpbg-focus-keyword-meta');
        var $status = $('#wpbg-meta-status');

        /**
         * Get post title from Gutenberg or Classic Editor
         */
        function getPostTitle() {
            // Gutenberg
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
            }
            // Classic Editor
            return $('#title').val() || '';
        }

        /**
         * Get post content from Gutenberg or Classic Editor
         */
        function getPostContent() {
            // Gutenberg
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostContent() || '';
            }
            // Classic Editor - TinyMCE
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                return tinyMCE.get('content').getContent();
            }
            // Classic Editor - Text mode
            return $('#content').val() || '';
        }

        /**
         * Set post content in Gutenberg or Classic Editor
         */
        function setPostContent(content) {
            // Gutenberg
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
                wp.data.dispatch('core/editor').resetBlocks(
                    wp.blocks.parse(content)
                );
                return true;
            }
            // Classic Editor - TinyMCE
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                tinyMCE.get('content').setContent(content);
                return true;
            }
            // Classic Editor - Text mode
            $('#content').val(content);
            return true;
        }

        /**
         * Append content to post
         */
        function appendPostContent(content) {
            var currentContent = getPostContent();
            var newContent = currentContent + '\n\n' + content;
            setPostContent(newContent);
        }

        /**
         * Show status message
         */
        function showStatus(message, type) {
            var bgColor = type === 'error' ? '#f8d7da' :
                          type === 'success' ? '#d4edda' : '#fff3cd';
            var textColor = type === 'error' ? '#721c24' :
                            type === 'success' ? '#155724' : '#856404';

            $status.html(message)
                   .css({
                       'background': bgColor,
                       'color': textColor,
                       'padding': '10px',
                       'border-radius': '4px'
                   })
                   .show();
        }

        /**
         * Hide status
         */
        function hideStatus() {
            $status.hide();
        }

        /**
         * Set button loading state
         */
        function setLoading($btn, loading, text) {
            if (loading) {
                $btn.prop('disabled', true).text(text || wpbg_post_editor.generating_text);
            } else {
                $btn.prop('disabled', false).text($btn.data('original-text'));
            }
        }

        // Store original button texts
        $regenerateBtn.data('original-text', $regenerateBtn.text());
        $formatBtn.data('original-text', $formatBtn.text());
        $formatCustomBtn.data('original-text', $formatCustomBtn.text());

        /**
         * Regenerate content based on title
         */
        $regenerateBtn.on('click', function() {
            var title = getPostTitle();
            var keyword = $keywordInput.val();

            if (!title) {
                showStatus('Lütfen önce bir başlık girin.', 'error');
                return;
            }

            if (!confirm('Mevcut içerik yeni içerikle DEĞİŞTİRİLECEK. Devam etmek istiyor musunuz?')) {
                return;
            }

            hideStatus();
            setLoading($regenerateBtn, true, wpbg_post_editor.generating_text);

            $.ajax({
                url: wpbg_post_editor.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpbg_regenerate_content',
                    nonce: wpbg_post_editor.nonce,
                    title: title,
                    keyword: keyword,
                    current_content: getPostContent()
                },
                success: function(response) {
                    if (response.success) {
                        setPostContent(response.data.content);
                        showStatus(response.data.message, 'success');
                    } else {
                        showStatus(response.data || wpbg_post_editor.error_text, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatus(wpbg_post_editor.error_text + ': ' + error, 'error');
                },
                complete: function() {
                    setLoading($regenerateBtn, false);
                }
            });
        });

        /**
         * Format existing content
         */
        $formatBtn.on('click', function() {
            var content = getPostContent();
            var title = getPostTitle();
            var keyword = $keywordInput.val();

            if (!content) {
                showStatus('Formatlanacak içerik bulunamadı.', 'error');
                return;
            }

            hideStatus();
            setLoading($formatBtn, true, wpbg_post_editor.formatting_text);

            $.ajax({
                url: wpbg_post_editor.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpbg_format_content',
                    nonce: wpbg_post_editor.nonce,
                    content: content,
                    title: title,
                    keyword: keyword,
                    mode: 'format'
                },
                success: function(response) {
                    if (response.success) {
                        setPostContent(response.data.content);
                        showStatus(response.data.message, 'success');
                    } else {
                        showStatus(response.data || wpbg_post_editor.error_text, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatus(wpbg_post_editor.error_text + ': ' + error, 'error');
                },
                complete: function() {
                    setLoading($formatBtn, false);
                }
            });
        });

        /**
         * Format custom text and append
         */
        $formatCustomBtn.on('click', function() {
            var customText = $customText.val();
            var keyword = $keywordInput.val();

            if (!customText) {
                showStatus('Lütfen formatlamak istediğiniz metni girin.', 'error');
                return;
            }

            hideStatus();
            setLoading($formatCustomBtn, true, wpbg_post_editor.formatting_text);

            $.ajax({
                url: wpbg_post_editor.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpbg_format_content',
                    nonce: wpbg_post_editor.nonce,
                    content: customText,
                    title: getPostTitle(),
                    keyword: keyword,
                    mode: 'append'
                },
                success: function(response) {
                    if (response.success) {
                        appendPostContent(response.data.content);
                        $customText.val(''); // Clear textarea
                        showStatus('Metin formatlandı ve eklendi!', 'success');
                    } else {
                        showStatus(response.data || wpbg_post_editor.error_text, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatus(wpbg_post_editor.error_text + ': ' + error, 'error');
                },
                complete: function() {
                    setLoading($formatCustomBtn, false);
                }
            });
        });
    });

})(jQuery);
