/**
 * Agent Admin JavaScript
 */
(function ($) {
    'use strict';

    $(document).ready(function () {

        // Tab switching
        $('.swc-tab-btn').on('click', function () {
            var tab = $(this).data('tab');

            $('.swc-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.swc-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });

        // Tool toggle
        $('.swc-tool-toggle').on('change', function () {
            var $card = $(this).closest('.swc-tool-card');
            var $config = $card.find('.swc-tool-config');

            if ($(this).is(':checked')) {
                $card.addClass('enabled');
                $config.slideDown();
            } else {
                $card.removeClass('enabled');
                $config.slideUp();
            }
        });

        // Skill toggle
        $('.swc-skill-toggle').on('change', function () {
            var $card = $(this).closest('.swc-skill-card');

            if ($(this).is(':checked')) {
                $card.addClass('active');
            } else {
                $card.removeClass('active');
            }
        });

        // Save Tools
        $('.swc-save-tools').on('click', function () {
            var $btn = $(this);
            var tools = {};
            var config = {};

            $('.swc-tool-toggle').each(function () {
                var slug = $(this).data('tool');
                tools[slug] = $(this).is(':checked') ? 'true' : 'false';
            });

            // Collect config values
            $('input[name^="config["]').each(function () {
                var name = $(this).attr('name');
                var match = name.match(/config\[(\w+)\]\[(\w+)\]/);
                if (match) {
                    var toolSlug = match[1];
                    var fieldKey = match[2];

                    if (!config[toolSlug]) config[toolSlug] = {};

                    if ($(this).is(':checkbox')) {
                        if (!config[toolSlug][fieldKey]) config[toolSlug][fieldKey] = [];
                        if ($(this).is(':checked')) {
                            config[toolSlug][fieldKey].push($(this).val());
                        }
                    } else {
                        config[toolSlug][fieldKey] = $(this).val();
                    }
                }
            });

            $btn.prop('disabled', true).text('Saving...');

            $.post(swcAgentAdmin.ajaxurl, {
                action: 'swc_save_tool_settings',
                nonce: swcAgentAdmin.nonce,
                tools: tools,
                config: config
            }, function (response) {
                $btn.prop('disabled', false).text('Save Tool Settings');
                if (response.success) {
                    showNotice('success', 'Tools saved successfully!');
                } else {
                    showNotice('error', 'Failed to save tools');
                }
            });
        });

        // Save Prompts
        $('.swc-save-prompts').on('click', function () {
            var $btn = $(this);
            var prompts = {};

            $('.swc-prompt-item').each(function () {
                var key = $(this).data('prompt-key');
                prompts[key] = {
                    name: $(this).find('input[name$="[name]"]').val(),
                    content: $(this).find('.swc-prompt-content').val(),
                    priority: $(this).find('.swc-prompt-priority-input').val()
                };
            });

            $btn.prop('disabled', true).text('Saving...');

            $.post(swcAgentAdmin.ajaxurl, {
                action: 'swc_save_prompts',
                nonce: swcAgentAdmin.nonce,
                prompts: prompts
            }, function (response) {
                $btn.prop('disabled', false).text('Save Prompts');
                if (response.success) {
                    showNotice('success', 'Prompts saved successfully!');
                } else {
                    showNotice('error', 'Failed to save prompts');
                }
            });
        });

        // Add new prompt
        $('.swc-add-prompt-btn').on('click', function () {
            var name = $('#new-prompt-name').val();
            var content = $('#new-prompt-content').val();

            if (!name || !content) {
                alert('Please enter both name and content');
                return;
            }

            var key = name.toLowerCase().replace(/\s+/g, '_');

            var html = `
                <div class="swc-prompt-item" data-prompt-key="${key}">
                    <div class="swc-prompt-header">
                        <h3>${name}</h3>
                        <span class="swc-prompt-priority">Priority: 50</span>
                    </div>
                    <textarea class="swc-prompt-content" 
                              name="prompts[${key}][content]"
                              rows="4">${content}</textarea>
                    <input type="hidden" name="prompts[${key}][name]" value="${name}">
                    <input type="number" class="swc-prompt-priority-input"
                           name="prompts[${key}][priority]" value="50" min="1" max="100">
                </div>
            `;

            $('.swc-prompts-list').append(html);
            $('#new-prompt-name').val('');
            $('#new-prompt-content').val('');
        });

        // Save Skills
        $('.swc-save-skills').on('click', function () {
            var $btn = $(this);
            var skills = [];

            $('.swc-skill-toggle:checked').each(function () {
                skills.push($(this).val());
            });

            $btn.prop('disabled', true).text('Saving...');

            $.post(swcAgentAdmin.ajaxurl, {
                action: 'swc_save_skills',
                nonce: swcAgentAdmin.nonce,
                skills: skills
            }, function (response) {
                $btn.prop('disabled', false).text('Save Skills');
                if (response.success) {
                    showNotice('success', 'Skills saved successfully!');
                } else {
                    showNotice('error', 'Failed to save skills');
                }
            });
        });

        // Upload custom skill
        $('.swc-upload-skill-btn').on('click', function () {
            var content = $('#custom-skill-content').val();

            if (!content) {
                alert('Please enter skill content');
                return;
            }

            // For now, just show a message. Full implementation would save to server.
            alert('Custom skill upload coming in next update!');
        });

        // Show notice
        function showNotice(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.smart-ai-chatbot-admin h1').after($notice);

            setTimeout(function () {
                $notice.fadeOut(function () {
                    $(this).remove();
                });
            }, 3000);
        }
    });

})(jQuery);
