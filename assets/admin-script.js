/**
 * WP Product Blog Generator - Admin Scripts
 * Version: 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Form elements
    const $form = $('#wpbg-product-form');
    const $productName = $('#product_name');
    const $generateBtn = $('#generate_blog');
    const $spinner = $('.spinner');
    const $resultSection = $('#wpbg-result-section');
    const $generatedContent = $('#wpbg-generated-content');
    const $copyBtn = $('#copy-content');
    const $saveBtn = $('#save-as-post');
    
    // Form submission handler
    if ($form.length) {
        $form.on('submit', function(e) {
            e.preventDefault();
            
            const productName = $productName.val().trim();
            
            if (!productName) {
                showMessage('Lütfen bir ürün adı girin.', 'error');
                $productName.focus();
                return;
            }
            
            generateBlogContent(productName);
        });
    }
    
    // Generate blog content via AJAX
    function generateBlogContent(productName) {
        // Show loading state
        setLoadingState(true);
        
        // AJAX request
        $.ajax({
            url: wpbg_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_product_blog',
                product_name: productName,
                nonce: wpbg_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayGeneratedContent(response.data.content);
                    showMessage(response.data.message, 'success');
                    $resultSection.slideDown();
                } else {
                    showMessage(response.data || 'Bir hata oluştu.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showMessage(wpbg_ajax.error_text, 'error');
            },
            complete: function() {
                setLoadingState(false);
            }
        });
    }
    
    // Display generated content
    function displayGeneratedContent(content) {
        $generatedContent.html(content);
        
        // Scroll to result section
        $('html, body').animate({
            scrollTop: $resultSection.offset().top - 50
        }, 500);
    }
    
    // Set loading state
    function setLoadingState(loading) {
        if (loading) {
            $generateBtn.prop('disabled', true).val(wpbg_ajax.generating_text);
            $spinner.addClass('is-active');
            $form.addClass('wpbg-loading');
        } else {
            $generateBtn.prop('disabled', false).val('Blog Yazısı Oluştur');
            $spinner.removeClass('is-active');
            $form.removeClass('wpbg-loading');
        }
    }
    
    // Show message
    function showMessage(message, type) {
        // Remove existing messages
        $('.wpbg-message').remove();
        
        // Create new message
        const $message = $('<div class="wpbg-message ' + type + '">' + message + '</div>');
        
        // Insert after form
        $form.after($message);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 50
        }, 300);
    }
    
    // Copy content to clipboard
    $copyBtn.on('click', function() {
        const content = $generatedContent.text();
        
        if (navigator.clipboard && window.isSecureContext) {
            // Modern clipboard API
            navigator.clipboard.writeText(content).then(function() {
                showMessage('İçerik panoya kopyalandı!', 'success');
                $copyBtn.text('Kopyalandı!').prop('disabled', true);
                
                setTimeout(function() {
                    $copyBtn.text('İçeriği Kopyala').prop('disabled', false);
                }, 2000);
            }).catch(function(err) {
                console.error('Clipboard error:', err);
                fallbackCopyToClipboard(content);
            });
        } else {
            // Fallback for older browsers
            fallbackCopyToClipboard(content);
        }
    });
    
    // Fallback copy method
    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showMessage('İçerik panoya kopyalandı!', 'success');
                $copyBtn.text('Kopyalandı!').prop('disabled', true);
                
                setTimeout(function() {
                    $copyBtn.text('İçeriği Kopyala').prop('disabled', false);
                }, 2000);
            } else {
                showMessage('Kopyalama başarısız. Lütfen manuel olarak kopyalayın.', 'error');
            }
        } catch (err) {
            console.error('Fallback copy error:', err);
            showMessage('Kopyalama desteklenmiyor. Lütfen manuel olarak kopyalayın.', 'error');
        }
        
        document.body.removeChild(textArea);
    }
    
    // Save as WordPress post
    $saveBtn.on('click', function() {
        const content = $generatedContent.html();
        const productName = $productName.val().trim();
        
        if (!content || !productName) {
            showMessage('Kaydedilecek içerik bulunamadı.', 'error');
            return;
        }
        
        // Show loading state for save button
        const originalText = $saveBtn.text();
        $saveBtn.text('Kaydediliyor...').prop('disabled', true);
        
        // AJAX request to save as post
        $.ajax({
            url: wpbg_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_as_wordpress_post',
                product_name: productName,
                content: content,
                nonce: wpbg_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Blog yazısı başarıyla kaydedildi! <a href="' + response.data.edit_url + '" target="_blank">Düzenlemek için tıklayın</a>', 'success');
                } else {
                    showMessage(response.data || 'Kaydetme sırasında bir hata oluştu.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Save AJAX Error:', error);
                showMessage('Kaydetme sırasında bir hata oluştu.', 'error');
            },
            complete: function() {
                $saveBtn.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Form validation
    if ($productName.length) {
        $productName.on('input', function() {
            const value = $(this).val().trim();
            $generateBtn.prop('disabled', value.length === 0);
        });
        
        // Enter key handler
        $productName.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $form.submit();
            }
        });

        // Character counter
        const maxLength = 100;
        const $charCounter = $('<div class="char-counter" style="font-size: 12px; color: #666; margin-top: 5px;"></div>');
        $productName.after($charCounter);
        
        const updateCharCounter = function() {
            const currentLength = $productName.val().length;
            $charCounter.text(currentLength + '/' + maxLength + ' karakter');
        };
        
        $productName.on('input', updateCharCounter);
        $productName.attr('maxlength', maxLength);
        updateCharCounter();
        $productName.focus();
    }

    // --- Content Improver logic ---
    const $improveBtn = $('#wpbg-improve-content');
    const $analyzeBtn = $('#wpbg-analyze-seo');
    const $improverSpinner = $('.wpbg-actions .spinner');
    const $seoResults = $('#wpbg-seo-results');
    const $focusKeyword = $('#wpbg-focus-keyword');

    // Get content from TinyMCE or textarea
    function getEditorContent() {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wpbg_content_editor')) {
            return tinyMCE.get('wpbg_content_editor').getContent();
        }
        return $('#wpbg_content_editor').val();
    }

    // Set content to TinyMCE or textarea
    function setEditorContent(content) {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wpbg_content_editor')) {
            tinyMCE.get('wpbg_content_editor').setContent(content);
        } else {
            $('#wpbg_content_editor').val(content);
        }
    }

    // SEO Analysis Handler
    if ($analyzeBtn.length) {
        $analyzeBtn.on('click', function() {
            const content = getEditorContent();
            const keyword = $focusKeyword.val();

            if (!content) {
                alert('Lütfen önce bir içerik girin veya oluşturun.');
                return;
            }

            $analyzeBtn.prop('disabled', true).text('Analiz ediliyor...');
            $improverSpinner.addClass('is-active');

            $.ajax({
                url: wpbg_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'analyze_seo',
                    content: content,
                    keyword: keyword,
                    nonce: wpbg_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displaySeoResults(response.data);
                    } else {
                        alert('Hata: ' + response.data);
                    }
                },
                error: function() {
                    alert('Analiz sırasında bir hata oluştu.');
                },
                complete: function() {
                    $analyzeBtn.prop('disabled', false).text('SEO Analizi Yap');
                    $improverSpinner.removeClass('is-active');
                }
            });
        });
    }

    // Content Improvement Handler
    if ($improveBtn.length) {
        $improveBtn.on('click', function() {
            const content = getEditorContent();
            const keyword = $focusKeyword.val();

            if (!content) {
                alert('Lütfen düzenlemek istediğiniz içeriği girin.');
                return;
            }

            if (!confirm('Bu işlem mevcut içeriğinizi tamamen değiştirecektir. Devam etmek istiyor musunuz?')) {
                return;
            }

            $improveBtn.prop('disabled', true).text('Yazı düzenleniyor...');
            $improverSpinner.addClass('is-active');

            $.ajax({
                url: wpbg_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'improve_content',
                    content: content,
                    keyword: keyword,
                    nonce: wpbg_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        setEditorContent(response.data);
                        alert('Yazınız başarıyla düzenlendi! Lütfen SEO analizini tekrar yapın.');
                    } else {
                        alert('Hata: ' + response.data);
                    }
                },
                error: function() {
                    alert('Düzenleme sırasında bir hata oluştu.');
                },
                complete: function() {
                    $improveBtn.prop('disabled', false).text('Yazıyı Baştan Aşağı Düzenle');
                    $improverSpinner.removeClass('is-active');
                }
            });
        });
    }

    function displaySeoResults(data) {
        $seoResults.slideDown();
        
        // Update scores
        updateScoreBar('seo', data.seo_score);
        updateScoreBar('readability', data.readability_score);

        // Update lists
        updateList('wpbg-errors-list', data.errors);
        updateList('wpbg-suggestions-list', data.suggestions);

        // Scroll to results
        $('html, body').animate({
            scrollTop: $seoResults.offset().top - 50
        }, 500);
    }

    function updateScoreBar(type, score) {
        const $bar = $('#' + type + '-score-bar');
        const $text = $('#' + type + '-score-text');
        
        $bar.css('width', score + '%');
        $text.text(score + '/100');

        // Color based on score
        if (score >= 80) {
            $bar.css('background-color', '#4caf50'); // Green
        } else if (score >= 50) {
            $bar.css('background-color', '#ffc107'); // Amber
        } else {
            $bar.css('background-color', '#f44336'); // Red
        }
    }

    function updateList(elementId, items) {
        const $list = $('#' + elementId);
        $list.empty();
        
        if (items && items.length > 0) {
            items.forEach(function(item) {
                $list.append('<li>' + item + '</li>');
            });
        } else {
            $list.append('<li>Harika! Her şey yolunda görünüyor.</li>');
        }
    }

    // Debug mode (if enabled)
    if (window.wpbg_debug) {
        console.log('WP Product Blog Generator - Debug mode enabled');
        console.log('AJAX URL:', wpbg_ajax.ajax_url);
        console.log('Nonce:', wpbg_ajax.nonce);
    }
});