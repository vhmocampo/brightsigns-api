<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendQuoteEstimateEmailJob;
use App\Models\QuoteRequest;

class TestQuoteEmailJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:quote-email {quote_request_uuid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the quote estimate email job';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $quoteRequestUuid = $this->argument('quote_request_uuid');
        
        $this->info("Testing quote email job for UUID: {$quoteRequestUuid}");
        
        // Check if quote request exists
        $quoteRequest = QuoteRequest::where('uuid', $quoteRequestUuid)->first();
        
        if (!$quoteRequest) {
            $this->error("Quote request with UUID {$quoteRequestUuid} not found.");
            return 1;
        }
        
        $this->info("Found quote request: {$quoteRequest->id}");
        
        // Dispatch the job
        try {
            SendQuoteEstimateEmailJob::dispatch($quoteRequestUuid);
            $this->info("Job dispatched successfully!");
            
            // If using sync queue, the job should execute immediately
            if (config('queue.default') === 'sync') {
                $this->info("Email should have been sent (using sync queue).");
            } else {
                $this->info("Job queued. Run 'sail artisan queue:work' to process it.");
            }
            
        } catch (\Exception $e) {
            $this->error("Error dispatching job: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
