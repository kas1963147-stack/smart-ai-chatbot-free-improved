<?php
/**
 * Provider Factory Class
 * 
 * Creates the appropriate AI provider based on configuration.
 * Supports 30 AI providers worldwide.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SWC_Chatbot_Provider_Factory {
    
    /**
     * Default models for each provider
     */
    private static $default_models = array(
        'openai' => 'gpt-4o-mini',
        'anthropic' => 'claude-3-5-sonnet-20241022',
        'gemini' => 'gemini-1.5-flash',
        'deepseek' => 'deepseek-chat',
        'mistral' => 'mistral-large-latest',
        'groq' => 'llama-3.3-70b-versatile',
        'together' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
        'ollama' => 'llama3.2',
        'azure' => 'gpt-5-mini',
        'cohere' => 'command-r-plus',
        'perplexity' => 'llama-3.1-sonar-large-128k-online',
        'xai' => 'grok-2-latest',
        'ai21' => 'jamba-1.5-large',
        'fireworks' => 'accounts/fireworks/models/llama-v3p3-70b-instruct',
        'openrouter' => 'openai/gpt-4o',
        'cerebras' => 'llama3.3-70b',
        'sambanova' => 'Meta-Llama-3.3-70B-Instruct',
        'huggingface' => 'meta-llama/Llama-3.3-70B-Instruct',
        'hyperbolic' => 'meta-llama/Llama-3.3-70B-Instruct',
        'lepton' => 'llama3.3-70b',
        'novita' => 'meta-llama/llama-3.3-70b-instruct',
        'cloudflare' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
        'replicate' => 'meta/meta-llama-3-70b-instruct',
        'lmstudio' => 'local-model',
        'zhipu' => 'glm-4-flash',
        'moonshot' => 'moonshot-v1-32k',
        'yi' => 'yi-lightning',
        'siliconflow' => 'deepseek-ai/DeepSeek-V3',
        'baichuan' => 'Baichuan4',
        'minimax' => 'abab6.5s-chat'
    );
    
    /**
     * Create a provider instance based on configuration
     */
    public static function create_provider($config) {
        $provider = isset($config['provider']) ? $config['provider'] : '';
        $api_key = isset($config['apiKey']) ? $config['apiKey'] : '';
        $model = isset($config['model']) ? $config['model'] : '';
        $base_url = isset($config['baseUrl']) ? $config['baseUrl'] : null;
        $temperature = isset($config['temperature']) ? floatval($config['temperature']) : 0.7;
        $max_tokens = isset($config['maxTokens']) ? intval($config['maxTokens']) : null;
        
        // Local providers don't require API key
        $local_providers = array('ollama', 'lmstudio');
        if (!in_array($provider, $local_providers) && empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key is required for ' . $provider . ' provider');
        }
        
        // Use default model if not specified
        if (empty($model) && isset(self::$default_models[$provider])) {
            $model = self::$default_models[$provider];
        }
        
        // BRIDGE: Try modern provider factory first
        // This bridges the gap between legacy code and the new system
        if (class_exists('\Quarksol\SmartChatbot\Api\Providers\ProviderFactory') && class_exists('\Quarksol\SmartChatbot\Api\Providers\LegacyAdapter')) {
            // Map legacy config to clean array
            $modernConfig = [
                'apiKey' => $api_key,
                'model' => $model,
                'baseUrl' => $base_url,
                'temperature' => $temperature,
                'maxTokens' => $max_tokens
            ];
            
            // Only try bridging for OpenAI initially to ensure stability
            if ($provider === 'openai') {
                $modernProvider = \Quarksol\SmartChatbot\Api\Providers\ProviderFactory::create($provider, $modernConfig);
                if ($modernProvider) {
                    return new \Quarksol\SmartChatbot\Api\Providers\LegacyAdapter($modernProvider);
                }
            }
        }
        
        switch ($provider) {
            case 'openai': return new SWC_Chatbot_OpenAI_Provider($api_key, $model, $base_url, $temperature, $max_tokens);
            case 'anthropic': return new SWC_Chatbot_Anthropic_Provider($api_key, $model, $temperature, $max_tokens);
            case 'gemini': return new SWC_Chatbot_Gemini_Provider($api_key, $model, $temperature, $max_tokens);
            case 'deepseek': return new SWC_Chatbot_DeepSeek_Provider($api_key, $model, $temperature, $max_tokens);
            case 'mistral': return new SWC_Chatbot_Mistral_Provider($api_key, $model, $temperature, $max_tokens);
            case 'groq': return new SWC_Chatbot_Groq_Provider($api_key, $model, $temperature, $max_tokens);
            case 'together': return new SWC_Chatbot_Together_Provider($api_key, $model, $temperature, $max_tokens);
            case 'ollama': return new SWC_Chatbot_Ollama_Provider($api_key, $model, $base_url, $temperature, $max_tokens);
            case 'azure': return new SWC_Chatbot_Azure_Provider($api_key, $model, $base_url, $temperature, $max_tokens);
            case 'cohere': return new SWC_Chatbot_Cohere_Provider($api_key, $model, $temperature, $max_tokens);
            case 'perplexity': return new SWC_Chatbot_Perplexity_Provider($api_key, $model, $temperature, $max_tokens);
            case 'xai': return new SWC_Chatbot_XAI_Provider($api_key, $model, $temperature, $max_tokens);
            case 'ai21': return new SWC_Chatbot_AI21_Provider($api_key, $model, $temperature, $max_tokens);
            case 'fireworks': return new SWC_Chatbot_Fireworks_Provider($api_key, $model, $temperature, $max_tokens);
            case 'openrouter': return new SWC_Chatbot_OpenRouter_Provider($api_key, $model, $temperature, $max_tokens);
            case 'cerebras': return new SWC_Chatbot_Cerebras_Provider($api_key, $model, $temperature, $max_tokens);
            case 'sambanova': return new SWC_Chatbot_SambaNova_Provider($api_key, $model, $temperature, $max_tokens);
            case 'huggingface': return new SWC_Chatbot_HuggingFace_Provider($api_key, $model, $temperature, $max_tokens);
            case 'hyperbolic': return new SWC_Chatbot_Hyperbolic_Provider($api_key, $model, $temperature, $max_tokens);
            case 'lepton': return new SWC_Chatbot_Lepton_Provider($api_key, $model, $temperature, $max_tokens);
            case 'novita': return new SWC_Chatbot_Novita_Provider($api_key, $model, $temperature, $max_tokens);
            case 'cloudflare': return new SWC_Chatbot_Cloudflare_Provider($api_key, $model, $base_url, $temperature, $max_tokens);
            case 'replicate': return new SWC_Chatbot_Replicate_Provider($api_key, $model, $temperature, $max_tokens);
            case 'lmstudio': return new SWC_Chatbot_LMStudio_Provider($api_key, $model, $base_url, $temperature, $max_tokens);
            case 'zhipu': return new SWC_Chatbot_Zhipu_Provider($api_key, $model, $temperature, $max_tokens);
            case 'moonshot': return new SWC_Chatbot_Moonshot_Provider($api_key, $model, $temperature, $max_tokens);
            case 'yi': return new SWC_Chatbot_Yi_Provider($api_key, $model, $temperature, $max_tokens);
            case 'siliconflow': return new SWC_Chatbot_SiliconFlow_Provider($api_key, $model, $temperature, $max_tokens);
            case 'baichuan': return new SWC_Chatbot_Baichuan_Provider($api_key, $model, $temperature, $max_tokens);
            case 'minimax': return new SWC_Chatbot_Minimax_Provider($api_key, $model, $temperature, $max_tokens);
            default: return new WP_Error('unknown_provider', 'Unknown provider: ' . $provider);
        }
    }
    
    /**
     * Validate an API key format
     */
    public static function validate_api_key($provider, $api_key) {
        $local_providers = array('ollama', 'lmstudio');
        if (in_array($provider, $local_providers)) return array('valid' => true);
        if (empty($api_key) || trim($api_key) === '') return array('valid' => false, 'message' => 'API key is required');
        
        switch ($provider) {
            case 'openai': if (strpos($api_key, 'sk-') !== 0) return array('valid' => false, 'message' => 'OpenAI key should start with "sk-"'); break;
            case 'anthropic': if (strpos($api_key, 'sk-ant-') !== 0) return array('valid' => false, 'message' => 'Anthropic key should start with "sk-ant-"'); break;
            case 'gemini': if (strpos($api_key, 'AIzaSy') !== 0) return array('valid' => false, 'message' => 'Gemini key should start with "AIzaSy"'); break;
            case 'groq': if (strpos($api_key, 'gsk_') !== 0) return array('valid' => false, 'message' => 'Groq key should start with "gsk_"'); break;
            case 'xai': if (strpos($api_key, 'xai-') !== 0) return array('valid' => false, 'message' => 'xAI key should start with "xai-"'); break;
        }
        return array('valid' => true);
    }
    
    /**
     * Get available providers
     */
    public static function get_available_providers() {
        return array(
            // Major Commercial Providers
            array('id' => 'openai', 'name' => 'OpenAI', 'defaultModel' => self::$default_models['openai'], 'models' => SWC_Chatbot_OpenAI_Provider::get_available_models()),
            array('id' => 'anthropic', 'name' => 'Anthropic (Claude)', 'defaultModel' => self::$default_models['anthropic'], 'models' => SWC_Chatbot_Anthropic_Provider::get_available_models()),
            array('id' => 'gemini', 'name' => 'Google Gemini', 'defaultModel' => self::$default_models['gemini'], 'models' => SWC_Chatbot_Gemini_Provider::get_available_models()),
            array('id' => 'deepseek', 'name' => 'DeepSeek', 'defaultModel' => self::$default_models['deepseek'], 'models' => SWC_Chatbot_DeepSeek_Provider::get_available_models()),
            array('id' => 'xai', 'name' => 'xAI (Grok)', 'defaultModel' => self::$default_models['xai'], 'models' => SWC_Chatbot_XAI_Provider::get_available_models()),
            array('id' => 'mistral', 'name' => 'Mistral AI', 'defaultModel' => self::$default_models['mistral'], 'models' => SWC_Chatbot_Mistral_Provider::get_available_models()),
            array('id' => 'cohere', 'name' => 'Cohere', 'defaultModel' => self::$default_models['cohere'], 'models' => SWC_Chatbot_Cohere_Provider::get_available_models()),
            array('id' => 'ai21', 'name' => 'AI21 Labs', 'defaultModel' => self::$default_models['ai21'], 'models' => SWC_Chatbot_AI21_Provider::get_available_models()),
            array('id' => 'perplexity', 'name' => 'Perplexity (Online)', 'defaultModel' => self::$default_models['perplexity'], 'models' => SWC_Chatbot_Perplexity_Provider::get_available_models()),
            
            // Fast Inference Providers
            array('id' => 'groq', 'name' => 'Groq (Ultra Fast)', 'defaultModel' => self::$default_models['groq'], 'models' => SWC_Chatbot_Groq_Provider::get_available_models()),
            array('id' => 'cerebras', 'name' => 'Cerebras (Fast)', 'defaultModel' => self::$default_models['cerebras'], 'models' => SWC_Chatbot_Cerebras_Provider::get_available_models()),
            array('id' => 'sambanova', 'name' => 'SambaNova', 'defaultModel' => self::$default_models['sambanova'], 'models' => SWC_Chatbot_SambaNova_Provider::get_available_models()),
            
            // Model Hosting Providers
            array('id' => 'fireworks', 'name' => 'Fireworks AI', 'defaultModel' => self::$default_models['fireworks'], 'models' => SWC_Chatbot_Fireworks_Provider::get_available_models()),
            array('id' => 'together', 'name' => 'Together AI', 'defaultModel' => self::$default_models['together'], 'models' => SWC_Chatbot_Together_Provider::get_available_models()),
            array('id' => 'replicate', 'name' => 'Replicate', 'defaultModel' => self::$default_models['replicate'], 'models' => SWC_Chatbot_Replicate_Provider::get_available_models()),
            array('id' => 'huggingface', 'name' => 'Hugging Face', 'defaultModel' => self::$default_models['huggingface'], 'models' => SWC_Chatbot_HuggingFace_Provider::get_available_models()),
            array('id' => 'hyperbolic', 'name' => 'Hyperbolic', 'defaultModel' => self::$default_models['hyperbolic'], 'models' => SWC_Chatbot_Hyperbolic_Provider::get_available_models()),
            array('id' => 'lepton', 'name' => 'Lepton AI', 'defaultModel' => self::$default_models['lepton'], 'models' => SWC_Chatbot_Lepton_Provider::get_available_models()),
            array('id' => 'novita', 'name' => 'Novita AI', 'defaultModel' => self::$default_models['novita'], 'models' => SWC_Chatbot_Novita_Provider::get_available_models()),
            array('id' => 'siliconflow', 'name' => 'SiliconFlow', 'defaultModel' => self::$default_models['siliconflow'], 'models' => SWC_Chatbot_SiliconFlow_Provider::get_available_models()),
            array('id' => 'cloudflare', 'name' => 'Cloudflare AI', 'defaultModel' => self::$default_models['cloudflare'], 'models' => SWC_Chatbot_Cloudflare_Provider::get_available_models()),
            
            // Chinese AI Providers
            array('id' => 'zhipu', 'name' => 'Zhipu (GLM)', 'defaultModel' => self::$default_models['zhipu'], 'models' => SWC_Chatbot_Zhipu_Provider::get_available_models()),
            array('id' => 'moonshot', 'name' => 'Moonshot (Kimi)', 'defaultModel' => self::$default_models['moonshot'], 'models' => SWC_Chatbot_Moonshot_Provider::get_available_models()),
            array('id' => 'yi', 'name' => '01.AI (Yi)', 'defaultModel' => self::$default_models['yi'], 'models' => SWC_Chatbot_Yi_Provider::get_available_models()),
            array('id' => 'baichuan', 'name' => 'Baichuan', 'defaultModel' => self::$default_models['baichuan'], 'models' => SWC_Chatbot_Baichuan_Provider::get_available_models()),
            array('id' => 'minimax', 'name' => 'Minimax', 'defaultModel' => self::$default_models['minimax'], 'models' => SWC_Chatbot_Minimax_Provider::get_available_models()),
            
            // Meta Providers & Enterprise
            array('id' => 'openrouter', 'name' => 'OpenRouter (200+ models)', 'defaultModel' => self::$default_models['openrouter'], 'models' => SWC_Chatbot_OpenRouter_Provider::get_available_models()),
            array('id' => 'azure', 'name' => 'Azure OpenAI', 'defaultModel' => self::$default_models['azure'], 'models' => SWC_Chatbot_Azure_Provider::get_available_models()),
            
            // Local Providers (No API Key)
            array('id' => 'ollama', 'name' => 'Ollama (Local)', 'defaultModel' => self::$default_models['ollama'], 'models' => SWC_Chatbot_Ollama_Provider::get_available_models()),
            array('id' => 'lmstudio', 'name' => 'LM Studio (Local)', 'defaultModel' => self::$default_models['lmstudio'], 'models' => SWC_Chatbot_LMStudio_Provider::get_available_models())
        );
    }
    
    /**
     * Get models for a specific provider
     */
    public static function get_provider_models($provider) {
        switch ($provider) {
            case 'openai': return SWC_Chatbot_OpenAI_Provider::get_available_models();
            case 'anthropic': return SWC_Chatbot_Anthropic_Provider::get_available_models();
            case 'gemini': return SWC_Chatbot_Gemini_Provider::get_available_models();
            case 'deepseek': return SWC_Chatbot_DeepSeek_Provider::get_available_models();
            case 'mistral': return SWC_Chatbot_Mistral_Provider::get_available_models();
            case 'groq': return SWC_Chatbot_Groq_Provider::get_available_models();
            case 'together': return SWC_Chatbot_Together_Provider::get_available_models();
            case 'ollama': return SWC_Chatbot_Ollama_Provider::get_available_models();
            case 'azure': return SWC_Chatbot_Azure_Provider::get_available_models();
            case 'cohere': return SWC_Chatbot_Cohere_Provider::get_available_models();
            case 'perplexity': return SWC_Chatbot_Perplexity_Provider::get_available_models();
            case 'xai': return SWC_Chatbot_XAI_Provider::get_available_models();
            case 'ai21': return SWC_Chatbot_AI21_Provider::get_available_models();
            case 'fireworks': return SWC_Chatbot_Fireworks_Provider::get_available_models();
            case 'openrouter': return SWC_Chatbot_OpenRouter_Provider::get_available_models();
            case 'cerebras': return SWC_Chatbot_Cerebras_Provider::get_available_models();
            case 'sambanova': return SWC_Chatbot_SambaNova_Provider::get_available_models();
            case 'huggingface': return SWC_Chatbot_HuggingFace_Provider::get_available_models();
            case 'hyperbolic': return SWC_Chatbot_Hyperbolic_Provider::get_available_models();
            case 'lepton': return SWC_Chatbot_Lepton_Provider::get_available_models();
            case 'novita': return SWC_Chatbot_Novita_Provider::get_available_models();
            case 'cloudflare': return SWC_Chatbot_Cloudflare_Provider::get_available_models();
            case 'replicate': return SWC_Chatbot_Replicate_Provider::get_available_models();
            case 'lmstudio': return SWC_Chatbot_LMStudio_Provider::get_available_models();
            case 'zhipu': return SWC_Chatbot_Zhipu_Provider::get_available_models();
            case 'moonshot': return SWC_Chatbot_Moonshot_Provider::get_available_models();
            case 'yi': return SWC_Chatbot_Yi_Provider::get_available_models();
            case 'siliconflow': return SWC_Chatbot_SiliconFlow_Provider::get_available_models();
            case 'baichuan': return SWC_Chatbot_Baichuan_Provider::get_available_models();
            case 'minimax': return SWC_Chatbot_Minimax_Provider::get_available_models();
            default: return array();
        }
    }
}
