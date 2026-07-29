<?php
declare(strict_types=1);


/**
 * Provider Constants
 * 
 * 
 * @package App
 */

namespace Quarksol\SmartChatbot\Api\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default headers for API requests
 */
const DEFAULT_HEADERS = [
    'User-Agent' => 'App-WordPress/1.0',
];

/**
 * Common timeout values
 */
const REQUEST_TIMEOUT = 60;
const STREAM_TIMEOUT = 300;

/**
 * Token pricing divisor (per million)
 */
const TOKEN_PRICE_DIVISOR = 1_000_000;
