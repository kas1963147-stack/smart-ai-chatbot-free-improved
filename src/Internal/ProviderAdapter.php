<?php
declare(strict_types=1);

/**
 * Provider Adapter for Neuron AI
 * 
 * Bridges our 40+ providers to Neuron's AIProviderInterface.
 * This enables using all our providers with Neuron's agentic features.
 * 
 * @package Quarksol\SmartChatbot\Internal
 */

namespace Quarksol\SmartChatbot\Internal;

if (!defined('ABSPATH')) { exit; }

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\MessageMapperInterface;
use NeuronAI\Providers\ToolMapperInterface;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\Tools\ToolInterface;
use Quarksol\SmartChatbot\Api\Providers\BaseProvider;
use Quarksol\SmartChatbot\Types\ProviderSettings;
use Quarksol\SmartChatbot\Services\Logger;
use Generator;

use function Quarksol\SmartChatbot\Api\buildApiHandler;

/**
 * Adapts our BaseProvider to Neuron's AIProviderInterface
 */
class ProviderAdapter implements AIProviderInterface
{
    protected BaseProvider $provider;
    protected ?string $systemPrompt = null;
    protected array $tools = [];
    protected ?HttpClientInterface $client = null;

    public function __construct(BaseProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Create from ProviderSettings
     */
    public static function fromSettings(ProviderSettings $settings): self
    {
        return new self(buildApiHandler($settings));
    }

    /**
     * Set the system prompt
     */
    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        $this->systemPrompt = $prompt;

        Logger::debug('ProviderAdapter system prompt set', [
            'prompt_length' => strlen($prompt ?? ''),
        ]);

        return $this;
    }

    /**
     * Set available tools
     * 
     * @param ToolInterface[] $tools
     */
    public function setTools(array $tools): AIProviderInterface
    {
        $this->tools = $tools;

        Logger::debug('ProviderAdapter tools set', [
            'tools_count' => count($tools),
        ]);

        return $this;
    }

    /**
     * Message mapper for converting Neuron messages to provider format
     */
    public function messageMapper(): MessageMapperInterface
    {
        return new class implements MessageMapperInterface {
            public function map(array $messages): array
            {
                $mapped = [];
                foreach ($messages as $message) {
                    // Handle ToolCallResultMessage specially
                    if ($message instanceof \NeuronAI\Chat\Messages\ToolCallResultMessage) {
                        foreach ($message->getTools() as $tool) {
                            $mapped[] = [
                                'role' => 'tool',
                                'tool_call_id' => $tool->getCallId(),
                                'content' => $tool->getResult() ?? json_encode(['result' => 'Tool execution completed']),
                            ];
                        }
                        continue;
                    }

                    // Handle ToolCallMessage (assistant requesting tool calls)
                    if ($message instanceof \NeuronAI\Chat\Messages\ToolCallMessage) {
                        $toolCalls = [];
                        foreach ($message->getTools() as $tool) {
                            $toolCalls[] = [
                                'id' => $tool->getCallId(),
                                'type' => 'function',
                                'function' => [
                                    'name' => $tool->getName(),
                                    'arguments' => json_encode($tool->getInputs()),
                                ],
                            ];
                        }
                        $mapped[] = [
                            'role' => 'assistant',
                            'content' => $message->getContent() ?? null,
                            'tool_calls' => $toolCalls,
                        ];
                        continue;
                    }

                    // Standard messages
                    $mapped[] = [
                        'role' => $message->getRole() ?? 'user',
                        'content' => $message->getContent(),
                    ];
                }
                return $mapped;
            }

            public function mapAll(array $messages): array
            {
                return $this->map($messages);
            }
        };
    }

    /**
     * Tool payload mapper for converting Neuron tools to provider format
     */
    public function toolPayloadMapper(): ToolMapperInterface
    {
        return new class implements ToolMapperInterface {
            public function map(array $tools): array
            {
                $mapped = [];
                foreach ($tools as $tool) {
                    $properties = [];
                    $required = [];
                    $toolName = $tool->getName();

                    foreach ($tool->getProperties() as $prop) {
                        $propSchema = method_exists($prop, 'getJsonSchema') ? $prop->getJsonSchema() : [
                            'type' => $prop->getType(),
                            'description' => $prop->getDescription(),
                        ];
                        $properties[$prop->getName()] = $propSchema;
                        if ($prop->isRequired()) {
                            $required[] = $prop->getName();
                        }
                    }

                    $parametersObj = [
                        'type' => 'object',
                        // Cast to (object) so empty arrays encode as {} not []
                        'properties' => (object) ($properties ?? []),
                    ];
                    
                    // Only add required field if there are required properties
                    if (!empty($required)) {
                        $parametersObj['required'] = $required;
                    }

                    $mapped[] = [
                        'type' => 'function',
                        'function' => [
                            'name' => $tool->getName(),
                            'description' => $tool->getDescription(),
                            'parameters' => $parametersObj,
                        ],
                    ];
                }
                return $mapped;
            }
        };
    }

    /**
     * Synchronous chat
     * 
     * @param Message ...$messages
     */
    public function chat(Message ...$messages): Message
    {
        return $this->chatAsync($messages)->wait();
    }

    /**
     * Async chat with promise
     * 
     * @param Message[] $messages
     */
    public function chatAsync(array $messages): PromiseInterface
    {
        $promise = new Promise(function () use (&$promise, $messages) {
            try {
                $formattedMessages = $this->messageMapper()->mapAll($messages);

                // Limit tools to API maximum (Azure/OpenAI have 128 tool limit)
                // NOTE: Some providers have stricter limits due to TPM constraints
                $toolsToSend = $this->tools;
                $maxTools = 128;
                
                // Provider-specific tool limits based on token constraints
                $providerName = $this->provider->providerName ?? '';
                if (strtolower($providerName) === 'groq') {
                    // Groq free tier has 8000 TPM limit
                    // Each tool definition uses ~50-100 tokens
                    // Limit to 20 tools to stay under budget
                    $maxTools = 20;
                    Logger::debug('ProviderAdapter: Using reduced tool limit for Groq', [
                        'max_tools' => $maxTools,
                        'original_count' => count($toolsToSend),
                    ]);
                }
                
                if (count($toolsToSend) > $maxTools) {
                    // Prioritize system tools first, then take first N of remaining
                    $toolsToSend = array_slice($toolsToSend, 0, $maxTools);
                }

                $toolPayload = !empty($toolsToSend)
                    ? $this->toolPayloadMapper()->map($toolsToSend)
                    : null;

                $fullResponse = '';
                $toolCalls = [];

                // Debug: Track chunk types for diagnostics
                $chunkTypeCounts = [];

                $metadata = null;
                if ($toolPayload) {
                    $metadata = new \Quarksol\SmartChatbot\Api\Providers\CreateMessageMetadata();
                    $metadata->tools = $toolPayload;
                    $metadata->toolChoice = 'auto'; // Encourage tool usage

                    Logger::debug('ProviderAdapter sending tools to provider', [
                        'tools_count' => count($toolPayload),
                        'tool_names' => array_slice(array_map(fn($t) => $t['function']['name'] ?? 'unknown', $toolPayload), 0, 10),
                    ]);
                } else {
                    Logger::warning('ProviderAdapter has no tools to send', [
                        'tools_count' => count($this->tools),
                    ]);
                }

                // Track partial tool calls for streaming aggregation
                $partialToolCalls = [];

                foreach ($this->provider->createMessage(
                    $this->systemPrompt ?? 'You are a helpful assistant.',
                    $formattedMessages,
                    $metadata
                ) as $chunk) {
                    $chunkType = $chunk['type'] ?? 'unknown';
                    $chunkTypeCounts[$chunkType] = ($chunkTypeCounts[$chunkType] ?? 0) + 1;

                    Logger::debug('ProviderAdapter chunk received', [
                        'type' => $chunkType,
                        'keys' => array_keys($chunk),
                    ]);

                    $type = $chunk['type'] ?? null;

                    if ($type === 'text') {
                        $fullResponse .= $chunk['text'] ?? '';
                    } elseif ($type === 'error') {
                        // Capture error message from provider
                        $errorMessage = $chunk['error'] ?? 'Unknown API error';
                        Logger::error('ProviderAdapter received error from provider', [
                            'error' => $errorMessage,
                            'chunk' => $chunk,
                        ]);
                        
                        // If it's a tool schema error, give user a friendly message
                        if (stripos($errorMessage, 'Invalid schema for function') !== false) {
                            error_log("[ProviderAdapter] Tool schema error detected - a MCP tool has invalid schema: {$errorMessage}");
                            $fullResponse = "I'm sorry, I encountered a configuration issue with one of my tools. Please try again or contact support.";
                        } else {
                            // Set as response so user sees the actual error
                            $fullResponse = "API Error: " . $errorMessage;
                        }
                    } elseif ($type === 'tool_call_partial') {
                        // Aggregate partial tool call chunks
                        $index = $chunk['index'] ?? 0;
                        if (!isset($partialToolCalls[$index])) {
                            $partialToolCalls[$index] = [
                                'id' => null,
                                'name' => '',
                                'arguments' => '',
                            ];
                        }
                        if (!empty($chunk['id'])) {
                            $partialToolCalls[$index]['id'] = $chunk['id'];
                        }
                        if (!empty($chunk['name'])) {
                            $partialToolCalls[$index]['name'] .= $chunk['name'];
                        }
                        if (!empty($chunk['arguments'])) {
                            $partialToolCalls[$index]['arguments'] .= $chunk['arguments'];
                        }
                    } elseif ($type === 'tool_call_end') {
                        // Finalize partial tool calls when streaming ends
                        foreach ($partialToolCalls as $index => $partial) {
                            if (!empty($partial['name'])) {
                                $toolCalls[] = [
                                    'id' => $partial['id'] ?? uniqid('call_'),
                                    'function' => [
                                        'name' => $partial['name'],
                                        'arguments' => $partial['arguments'] ?: '{}',
                                    ],
                                ];
                            }
                        }
                        $partialToolCalls = [];
                    } elseif ($type === 'tool_call') {
                        // Non-streaming tool call (complete)
                        if (!empty($chunk['name'])) {
                            $toolCalls[] = [
                                'id' => $chunk['id'] ?? uniqid('call_'),
                                'function' => [
                                    'name' => $chunk['name'],
                                    'arguments' => $chunk['arguments'] ?? '{}',
                                ],
                            ];
                        }
                    }
                }

                // Finalize any remaining partial tool calls (in case tool_call_end wasn't sent)
                foreach ($partialToolCalls as $partial) {
                    if (!empty($partial['name'])) {
                        $toolCalls[] = [
                            'id' => $partial['id'] ?? uniqid('call_'),
                            'function' => [
                                'name' => $partial['name'],
                                'arguments' => $partial['arguments'] ?: '{}',
                            ],
                        ];
                    }
                }

                // Debug summary: What did we receive from the provider?
                Logger::info('ProviderAdapter streaming complete', [
                    'chunk_types' => $chunkTypeCounts,
                    'tool_calls_found' => count($toolCalls),
                    'response_length' => strlen($fullResponse),
                    'partial_tool_calls_remaining' => count($partialToolCalls),
                ]);

                // Return tool call message if tools were called
                if (!empty($toolCalls)) {
                    $resolvedTools = [];
                    foreach ($toolCalls as $call) {
                        $toolName = $call['function']['name'] ?? '';
                        if (empty($toolName)) {
                            continue; // Skip empty tool names
                        }
                        try {
                            $tool = $this->findTool($toolName);
                            $tool->setInputs(json_decode($call['function']['arguments'], true) ?? []);
                            $tool->setCallId($call['id']);
                            $resolvedTools[] = $tool;
                        } catch (\Exception $e) {
                            Logger::warning('ProviderAdapter tool not found', [
                                'tool' => $toolName,
                            ]);
                        }
                    }

                    if (!empty($resolvedTools)) {
                        $result = new ToolCallMessage('', $resolvedTools);
                        $result->addMetadata('tool_calls', $toolCalls);
                        $promise->resolve($result);
                    } else {
                        // All tool calls failed - return as text message
                        $promise->resolve(new AssistantMessage($fullResponse));
                    }
                } else {
                    // Fallback: If no tool calls were detected but the provider signalled a tool use (e.g. via finish_reason),
                    // we return a standard message to prevent "hanging" or hallucinations.
                    if (empty($fullResponse)) {
                        // Log detailed diagnostics to help debug cloud issues
                        Logger::error('ProviderAdapter: No response or tools parsed - provider may have failed', [
                            'chunk_types_received' => $chunkTypeCounts,
                            'tools_count_in_adapter' => count($this->tools),
                            'tools_sent_to_provider' => $toolPayload ? count($toolPayload) : 0,
                            'system_prompt_set' => !empty($this->systemPrompt),
                            'message_count' => count($formattedMessages),
                        ]);
                        $fullResponse = "The AI provider returned an empty response (no text or tool calls). This usually means the model failed to generate a response due to high load or content filtering. Please try again.";
                    }
                    $promise->resolve(new AssistantMessage($fullResponse));
                }
            } catch (\Exception $e) {
                $promise->reject($e);
            }
        });

        return $promise;
    }

    /**
     * Stream response from the LLM.
     * 
     * @param Message ...$messages
     */
    public function stream(Message ...$messages): Generator
    {
        $formattedMessages = $this->messageMapper()->mapAll($messages);

        // Limit tools (same logic as chatAsync)
        $toolsToSend = $this->tools;
        $maxTools = 128;
        $providerName = $this->provider->providerName ?? '';
        if (strtolower($providerName) === 'groq') {
            $maxTools = 20;
        }
        if (count($toolsToSend) > $maxTools) {
            $toolsToSend = array_slice($toolsToSend, 0, $maxTools);
        }

        // Build metadata with tools
        $metadata = null;
        if (!empty($toolsToSend)) {
            $toolPayload = $this->toolPayloadMapper()->map($toolsToSend);
            $metadata = new \Quarksol\SmartChatbot\Api\Providers\CreateMessageMetadata();
            $metadata->tools = $toolPayload;
            $metadata->toolChoice = 'auto';
        }

        $text = '';
        $partialToolCalls = [];

        // Use real-time streaming if the provider supports it
        $useRealtime = method_exists($this->provider, 'createMessageRealtime');
        $generator = $useRealtime
            ? $this->provider->createMessageRealtime(
                $this->systemPrompt ?? 'You are a helpful assistant.',
                $formattedMessages,
                $metadata
            )
            : $this->provider->createMessage(
                $this->systemPrompt ?? 'You are a helpful assistant.',
                $formattedMessages,
                $metadata
            );

        foreach ($generator as $chunk) {
            $type = $chunk['type'] ?? null;

            if ($type === 'text') {
                $content = $chunk['text'] ?? '';
                $text .= $content;
                yield $content;

            } elseif ($type === 'tool_call_partial') {
                // Aggregate partial tool call chunks
                $index = $chunk['index'] ?? 0;
                if (!isset($partialToolCalls[$index])) {
                    $partialToolCalls[$index] = [
                        'id' => null,
                        'name' => '',
                        'arguments' => '',
                    ];
                }
                if (!empty($chunk['id'])) {
                    $partialToolCalls[$index]['id'] = $chunk['id'];
                }
                if (!empty($chunk['name'])) {
                    $partialToolCalls[$index]['name'] .= $chunk['name'];
                }
                if (!empty($chunk['arguments'])) {
                    $partialToolCalls[$index]['arguments'] .= $chunk['arguments'];
                }

            } elseif ($type === 'tool_call') {
                // Non-streaming tool call
                if (!empty($chunk['name'])) {
                    $partialToolCalls[] = [
                        'id' => $chunk['id'] ?? uniqid('call_'),
                        'name' => $chunk['name'],
                        'arguments' => $chunk['arguments'] ?? '{}',
                    ];
                }

            } elseif ($type === 'error') {
                $errorMsg = $chunk['error'] ?? 'Unknown API error';
                Logger::error('ProviderAdapter stream error', ['error' => $errorMsg]);
                yield "Error: " . $errorMsg;

            } elseif ($type === 'usage') {
                // Forward usage info for NeuronAI to capture
                yield json_encode(['usage' => [
                    'input_tokens' => $chunk['input_tokens'] ?? 0,
                    'output_tokens' => $chunk['output_tokens'] ?? 0,
                ]]);
            }
        }

        // Build resolved tools from accumulated partial tool calls
        $resolvedTools = [];
        foreach ($partialToolCalls as $partial) {
            if (empty($partial['name'])) continue;
            try {
                $tool = $this->findTool($partial['name']);
                $tool->setInputs(json_decode($partial['arguments'] ?: '{}', true) ?? []);
                $tool->setCallId($partial['id'] ?? uniqid('call_'));
                $resolvedTools[] = $tool;
            } catch (\Exception $e) {
                Logger::warning('Stream: tool not found', ['tool' => $partial['name']]);
            }
        }

        if (!empty($resolvedTools)) {
            $message = new ToolCallMessage($text, $resolvedTools);
            $message->addMetadata('tool_calls', array_map(fn($t) => [
                'id' => $t->getCallId(),
                'type' => 'function',
                'function' => [
                    'name' => $t->getName(),
                    'arguments' => json_encode($t->getInputs()),
                ]
            ], $resolvedTools));
            return $message;
        }

        return new AssistantMessage($text);
    }

    /**
     * Structured output (JSON mode)
     * 
     * @param array|Message $messages
     * @param string $class
     * @param array $response_schema
     */
    public function structured(array|Message $messages, string $class, array $response_schema): Message
    {
        $messages = is_array($messages) ? $messages : [$messages];
        // For now, delegate to regular chat - structured output would need more implementation
        return $this->chat(...$messages);
    }

    /**
     * Set HTTP client
     */
    public function setHttpClient(HttpClientInterface $client): AIProviderInterface
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Find tool by name
     */
    protected function findTool(string $name): ToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }
        throw new \Exception("Tool not found: {$name}");
    }
}

