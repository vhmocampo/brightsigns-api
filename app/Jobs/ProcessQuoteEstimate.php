<?php

namespace App\Jobs;

use App\Models\QuoteEstimate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Client;
use MongoDB\Client as MongoClient;
use Exception;

class ProcessQuoteEstimate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Client $openAiClient;
    private MongoClient $mongoClient;
    private string $uuid;
    private QuoteEstimate $quoteEstimate;

    public function __construct(
        $uuid = null,
    ) {
        $this->uuid = $uuid;
    }

    public function handle(): void
    {
        $this->quoteEstimate = QuoteEstimate::where('uuid', $this->uuid)->firstOrFail();
        $this->mongoClient = new MongoClient(config('services.mongodb.connection_string'));
        $this->openAiClient = \OpenAI::client(config('services.openai.api_key'));

        try {
            $this->quoteEstimate->update(['status' => 'processing']);

            $quoteEstimator = new \App\Services\QuoteEstimator();
            $quoteEstimator->estimateQuote($this->quoteEstimate);

            $this->quoteEstimate->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        } catch (Exception $e) {
            $this->quoteEstimate->update(['status' => 'failed']);
            throw $e;
        }
    }
}
