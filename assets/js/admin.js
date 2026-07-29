/**
 * Smart Woo Chatbot Admin JavaScript
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // Tab switching
    $(".swc-tab").on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const tabId = $(this).data("tab");

      // Update tab buttons
      $(".swc-tab").removeClass("active");
      $(this).addClass("active");

      // Update tab content - hide all first, then show selected
      $(".swc-tab-content").removeClass("active").hide();
      $("#tab-" + tabId)
        .addClass("active")
        .show();
    });

    // Initialize tabs - ensure first tab is visible on page load
    $(".swc-tab-content").hide();
    $("#tab-general").addClass("active").show();
    $(".swc-tab").first().addClass("active");

    // Color picker
    if ($.fn.wpColorPicker) {
      $(".swc-color-picker").wpColorPicker();
    }

    // Load FAQs
    loadFAQs();

    // Add FAQ
    $("#add-faq").on("click", function () {
      const question = $("#faq-question").val().trim();
      const answer = $("#faq-answer").val().trim();
      const keywords = $("#faq-keywords").val().trim();

      if (!question || !answer) {
        alert("Please enter both question and answer");
        return;
      }

      $.ajax({
        url: swcAdmin.ajaxurl,
        method: "POST",
        data: {
          action: "swc_save_faq",
          nonce: swcAdmin.nonce,
          question: question,
          answer: answer,
          keywords: keywords,
        },
        success: function (response) {
          if (response.success) {
            $("#faq-question").val("");
            $("#faq-answer").val("");
            $("#faq-keywords").val("");
            loadFAQs();
          } else {
            alert("Failed to save FAQ");
          }
        },
      });
    });

    // Delete FAQ
    $(document).on("click", ".swc-faq-delete", function () {
      if (!confirm("Delete this FAQ?")) return;

      const id = $(this).data("id");

      $.ajax({
        url: swcAdmin.ajaxurl,
        method: "POST",
        data: {
          action: "swc_delete_faq",
          nonce: swcAdmin.nonce,
          id: id,
        },
        success: function (response) {
          if (response.success) {
            loadFAQs();
          }
        },
      });
    });

    // ========== AI Provider Settings ==========

    // Toggle API key visibility
    $("#swc-toggle-api-key").on("click", function () {
      const $input = $("#swc-ai-api-key");
      const $btn = $(this);

      if ($input.attr("type") === "password") {
        $input.attr("type", "text");
        $btn.text("Hide");
      } else {
        $input.attr("type", "password");
        $btn.text("Show");
      }
    });

    // Provider change - load models dynamically
    $("#swc-ai-provider").on("change", function () {
      const provider = $(this).val();
      const $modelSelect = $("#swc-ai-model");
      const $apiKeyRow = $("#swc-ai-api-key").closest("tr");
      const $apiKeyDesc = $apiKeyRow.find(".description");

      // Show/hide API key hint based on provider
      const localProviders = ["ollama", "lmstudio"];
      const needsBaseUrl = ["azure", "ollama", "lmstudio", "cloudflare"];

      // Show/hide Base URL field
      if (needsBaseUrl.includes(provider)) {
        $("#swc-base-url-row").show();
        // Update hint based on provider
        let hint = "Custom API endpoint URL (leave empty for default)";
        let placeholder = "";
        if (provider === "azure") {
          hint = '<strong style="color: #d63638;">Required for Azure!</strong> Enter your Azure resource endpoint (e.g., https://your-resource.openai.azure.com)';
          placeholder = "https://your-resource.openai.azure.com";
        } else if (provider === "ollama") {
          hint = "Ollama server URL (default: http://localhost:11434)";
          placeholder = "http://localhost:11434";
        } else if (provider === "lmstudio") {
          hint = "LM Studio server URL (default: http://localhost:1234)";
          placeholder = "http://localhost:1234";
        } else if (provider === "cloudflare") {
          hint = "Your Cloudflare Account ID";
          placeholder = "your-account-id";
        }
        $("#swc-base-url-hint").html(hint);
        $("#swc-ai-base-url").attr("placeholder", placeholder);
      } else {
        $("#swc-base-url-row").hide();
      }

      if (localProviders.includes(provider)) {
        const hint =
          provider === "ollama"
            ? "<strong>Not required!</strong> Ollama runs locally. Make sure <code>ollama serve</code> is running."
            : "<strong>Not required!</strong> LM Studio runs locally. Enable Local Server in LM Studio.";
        $apiKeyDesc.html(hint);
        $("#swc-ai-api-key").attr(
          "placeholder",
          "Not required for local providers",
        );
      } else {
        $apiKeyDesc.html("Enter your API key from the selected provider");
        $("#swc-ai-api-key").attr("placeholder", "");
      }

      $modelSelect.prop("disabled", true).html("<option>Loading...</option>");

      $.ajax({
        url: swcAdmin.ajaxurl,
        method: "POST",
        data: {
          action: "swc_get_provider_models",
          nonce: swcAdmin.nonce,
          provider: provider,
        },
        success: function (response) {
          $modelSelect.prop("disabled", false).empty();

          if (response.success && response.data) {
            $.each(response.data, function (modelId, model) {
              $modelSelect.append(
                $("<option></option>").val(modelId).text(model.name),
              );
            });
          }
        },
        error: function () {
          $modelSelect
            .prop("disabled", false)
            .html("<option>Error loading models</option>");
        },
      });
    });

    // Test AI Connection
    $("#swc-test-connection").on("click", function () {
      const $btn = $(this);
      const $status = $("#swc-connection-status");
      const provider = $("#swc-ai-provider").val();
      const apiKey = $("#swc-ai-api-key").val();
      const model = $("#swc-ai-model").val();

      // Ollama and LM Studio don't need API key
      const localProviders = ["ollama", "lmstudio"];
      if (!apiKey && !localProviders.includes(provider)) {
        $status.html(
          '<span style="color: #dc2626;">  Please enter an API key</span>',
        );
        return;
      }

      $btn.prop("disabled", true).text("Testing...");
      $status.html('<span style="color: #2563eb;"> ⏳ Connecting...</span>');

      $.ajax({
        url: swcAdmin.ajaxurl,
        method: "POST",
        data: {
          action: "swc_test_ai_connection",
          nonce: swcAdmin.nonce,
          provider: provider,
          api_key: apiKey,
          model: model,
          base_url: $("#swc-ai-base-url").val() || "",
        },
        success: function (response) {
          if (response.success) {
            $status.html(
              '<span style="color: #16a34a;">  ' +
              response.data.message +
              "</span>",
            );
          } else {
            $status.html(
              '<span style="color: #dc2626;">  ' + response.data + "</span>",
            );
          }
        },
        error: function () {
          $status.html(
            '<span style="color: #dc2626;">  Connection failed</span>',
          );
        },
        complete: function () {
          $btn.prop("disabled", false).text("Test Connection");
        },
      });
    });
  });

  function loadFAQs() {
    $.ajax({
      url: swcAdmin.ajaxurl,
      method: "POST",
      data: {
        action: "swc_get_faqs",
        nonce: swcAdmin.nonce,
      },
      success: function (response) {
        if (response.success) {
          renderFAQs(response.data);
        }
      },
    });
  }

  function renderFAQs(faqs) {
    const $list = $("#faq-list");
    $list.empty();

    if (!faqs || faqs.length === 0) {
      $list.html('<p style="color: #64748b;">No FAQs added yet.</p>');
      return;
    }

    faqs.forEach(function (faq) {
      const html = `
                <div class="swc-faq-item">
                    <div class="swc-faq-content">
                        <div class="swc-faq-question">${escapeHtml(faq.question)}</div>
                        <div class="swc-faq-answer">${escapeHtml(faq.answer)}</div>
                        ${faq.keywords ? `<div class="swc-faq-keywords">Keywords: ${escapeHtml(faq.keywords)}</div>` : ""}
                    </div>
                    <button class="swc-faq-delete" data-id="${faq.id}">️ Delete</button>
                </div>
            `;
      $list.append(html);
    });
  }

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }
})(jQuery);
