<?php
declare(strict_types=1);

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use Quarksol\SmartChatbot\Types\ProviderSettings;

/**
 * OpenAI Native Provider
 * 
 * Uses the official OpenAI SDK or direct API approach.
 */
class OpenAINative extends OpenAI {
    public function __construct(ProviderSettings $settings) {
        parent::__construct($settings);
        $this->name = 'OpenAI Native SDK';
    }
}
