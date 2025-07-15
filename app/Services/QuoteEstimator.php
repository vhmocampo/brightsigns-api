<?php

namespace App\Services;

use App\Models\QuoteEstimate;
use App\Models\QuoteEstimateLineItem;
use Exception;

class QuoteEstimator
{
    private $openAiClient;
    private $mongoClient;

    public function __construct()
    {
        $this->openAiClient = \OpenAI::client(config('services.openai.api_key'));
        $this->mongoClient = new \MongoDB\Client(config('services.mongodb.connection_string'));
    }

    public function estimateQuote(QuoteEstimate $quoteEstimate)
    {
        // Processing logic will go here
        $rawRequest = $quoteEstimate->quoteRequest->original_request;
        $lines = $this->extractLines($rawRequest);
        foreach ($lines as $line) {
            $result = $this->processLine($line);
            // Handle the result (e.g., save to database, update status, etc.)
            if (!empty($result)) {
                // For debugging, remove in production
                $estimateLineItem = new QuoteEstimateLineItem([
                    'quote_estimate_id' => $quoteEstimate->id,
                    'name' => $result['product_name'] ?? 'Unknown Product',
                    'description' => $result['description'] ?? '',
                    'quantity' => $result['quantity'] ?? 0,
                    'unit_price' => $result['price'] ?? 0.00,
                    'total_price' => $result['total_cost'] ?? 0.00,
                    'similarity_score' => ($result['ai_generated'] ?? false) ? 0.0 : 1.0
                ]);
                $estimateLineItem->save();
            }
        }
    }

    /**
     * Extract lines from quote request text using GPT-4 to parse possible products.
     * Passes the whole text to GPT-4 and expects an array of product queries.
     */
    private function extractLines(string $text): array
    {
        $systemPrompt = <<<PROMPT
You are an assistant that extracts product requests from customer quote queries, excluding any illegal or outrageous requests.
Given a user's request, return an array of strings, each string representing a distinct product or item the user is asking for, staying as close as possible to the user's original wording.
If the user is unsure about the product, you can infer the most likely product based on the context.
Return a JSON array of strings, where each string is a distinct product or item mentioned in the input, even if separated by phrases like "and", "also", or "as well as". Always capture every distinct product request in the input.

Examples:
Input: "I need 2 banners 24x36 and 1 yard sign 18x24"
Output: ["2 banners 24x36", "1 yard sign 18x24"]

Input: "One 4x8 sign, two 2x3 signs, and a 3x5 banner"
Output: ["One 4x8 sign", "two 2x3 signs", "a 3x5 banner"]

Input: "I need something for my storefront window"
Output: ["vinyl window graphic"]

Input: "something to hand out at events"
Output: ["flyers"]

PROMPT;

        try {
            $response = $this->openAiClient->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $text],
                ],
                'temperature' => 0.0,
                'max_tokens' => 5000,
            ]);

            $content = $response->choices[0]->message->content ?? '';
            $lines = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($lines) && !empty($lines)) {
                return array_map('trim', $lines);
            }

            // Fallback: try to extract JSON array from text
            if (preg_match('/\[[^\]]*\]/s', $content, $matches)) {
                $lines = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($lines) && !empty($lines)) {
                    return array_map('trim', $lines);
                }
            }

            // If all fails, return the whole text as a single line
            return [trim($text)];
        } catch (Exception $e) {
            // On error, return the whole text as a single line
            return [trim($text)];
        }
    }

    /**
     * Process a single line - create embedding and search MongoDB
     */
    private function processLine(string $line): ?array
    {
        if (empty($line)) {
            return null;
        }

        try {
            // Generate embedding using OpenAI
            $embedding = $this->generateEmbedding($line);

            if (!$embedding) {
                return null;
            }

            // Search MongoDB for similar products
            $similarProducts = $this->searchSimilarProducts($embedding);

            return $this->getGptAnswer($line, $similarProducts);

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get GPT answer for a requested product
     *
     * @param string $requestedProduct
     * @param array $possibleProducts
     * @return array
     */
    private function getGptAnswer($requestedProduct, $possibleProducts = []): array
    {
        $systemPrompt = <<<PROMPT
    You are a product matching assistant. 
    Given a requested product and a list of possible products (with their sizes and prices), select the closest match. 
    Return a JSON object with these fields only: quantity, width_inches, height_inches, price, total_cost, ai_generated (true/false), product_name, description (relevant to their query).

    - If possibleProducts is empty, infer the most likely product (or products if multiple) and set "ai_generated" to true, set price to 0.00
    - If possibleProducts is not empty, pick the closest match and set "ai_generated" to false.
    - If possibleProducts is not empty, but there are no obvious matches, fallback to the functionality for empty possible products
    - Ignore any non-relevant queries.
    - Only return the JSON object, no extra text.
    - If no exact quantity match, use the closest available quantity (for example, if user asks for 100, but closest available is 500, use 500).
    - If a match was found, include the size in inches in the description
    - If a match was found, include the 'per unit' price in the description (for example, if they order 1500, and the price is for 'per 250')

    Examples:
    Input: "I want a 24x36 sign, 2 pieces", possibleProducts: [...]
    Output: {"quantity":2,"width_inches":24,"height_inches":36,"price":99.99,"total_cost":199.98,"ai_generated":false,"product_name":"Standard Sign","description":"A standard 24x36 sign suitable for outdoor use."}

    PROMPT;

        $userPrompt = "Requested product: \"$requestedProduct\"\nPossible products: " . json_encode($possibleProducts);

        try {
            $response = $this->openAiClient->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 10000,
            ]);

            // Extract JSON from GPT response
            $content = $response->choices[0]->message->content ?? '';
            $json = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }

            // Fallback: try to extract JSON from text
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $json = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    return $json;
                }
            }

            return [
                'error' => 'Failed to parse GPT response',
                'raw_response' => $content,
            ];
        } catch (Exception $e) {
            return [
                'error' => 'Failed to get GPT answer: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate embedding for text using OpenAI
     */
    private function generateEmbedding(string $text): ?array
    {
        try {
            $response = $this->openAiClient->embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]);

            return $response->embeddings[0]->embedding;

        } catch (Exception $e) {
            throw new Exception('Failed to generate embedding: ' . $e->getMessage());
        }
    }

    /**
     * Search for similar products in MongoDB using vector similarity
     */
    private function searchSimilarProducts(array $embedding, int $limit = 3): array
    {
        try {
            $database = $this->mongoClient->selectDatabase('BrightSigns');
            $collection = $database->selectCollection('products');

            // Use MongoDB Atlas Vector Search
            $pipeline = [
                [
                    '$vectorSearch' => [
                        'index' => 'vector_index', // You'll need to create this index in Atlas
                        'path' => 'embedded',
                        'queryVector' => $embedding,
                        'numCandidates' => 100,
                        'limit' => $limit
                    ]
                ],
                [
                    '$project' => [
                        '_id' => 1,
                        'product_name' => 1,
                        'category_name' => 1,
                        'prices' => 1,
                        'score' => ['$meta' => 'vectorSearchScore']
                    ]
                ]
            ];

            $results = $collection->aggregate($pipeline)->toArray();

            $gptLines = [];
            foreach ($results as $result) {

                $prices = '';
                foreach ($result['prices'] as $price) {
                    $prices .= sprintf(
                        "each %s: $%s size inches (%s x %s) %s, ",
                        max(intval($price['quantity']), 1),
                        $price['price'],
                        $price['width'],
                        $price['height'],
                        $price['variant_id'] == 2 ? '(2-sided)' : ''
                    );
                }
                $prices = rtrim($prices, ', ');

                $gptLines[] = sprintf(
                    "%s - %s",
                    $result['product_name'],
                    $prices ? $prices : 'No prices available'
                );
            }

            return $gptLines;

        } catch (Exception $e) {
            return [];
        }
    }
}
