<?php

namespace App\Jobs;

use App\Models\QuoteEstimate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\QuoteRequest;

class SendQuoteEstimateEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $quoteEstimateUuid;

    /**
     * Create a new job instance.
     */
    public function __construct(string $quoteEstimateUuid)
    {
        $this->quoteEstimateUuid = $quoteEstimateUuid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Load the quote request with its estimate and line items
            $quoteEstimate = QuoteEstimate::where('uuid', $this->quoteEstimateUuid)
                ->with(['quoteRequest', 'lineItems'])
                ->first();
            $quoteRequest = $quoteEstimate->quoteRequest;

            if (!$quoteRequest) {
                Log::error("Quote request not found: {$this->quoteEstimateUuid}");
                return;
            }

            if (!$quoteRequest->quoteEstimate) {
                Log::error("Quote estimate not found for quote request: {$this->quoteEstimateUuid}");
                return;
            }

            $supportEmail = config('mail.quote_support_email');
            $ccEmail = config('mail.quote_cc_email');

            if (!$supportEmail) {
                Log::error('QUOTE_SUPPORT_EMAIL not configured');
                return;
            }

            // Send the email
            Mail::send('emails.quote-estimate', [
                'quoteRequest' => $quoteRequest,
                'quoteEstimate' => $quoteEstimate,
                'lineItems' => $quoteEstimate->lineItems
            ], function ($message) use ($supportEmail, $ccEmail, $quoteRequest) {
                $message->to($supportEmail)
                        ->cc($ccEmail)
                        ->subject('Quote Estimate - ' . $quoteRequest->uuid)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info("Quote estimate email sent successfully for: {$this->quoteEstimateUuid}");

        } catch (\Exception $e) {
            Log::error("Failed to send quote estimate email for {$this->quoteEstimateUuid}: " . $e->getMessage());
            throw $e;
        }
    }
}
