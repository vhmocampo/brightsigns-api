<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\QuoteEstimate;
use App\Jobs\ProcessQuoteEstimate;
use App\Jobs\SendQuoteEstimateEmailJob;
use App\Models\QuoteRequest;
use Illuminate\Support\Str;
use Exception;

class QuoteRequestController extends Controller
{

    /**
     * Get quote request by UUID
     */
    public function getQuoteRequest($uuid): JsonResponse
    {
        try {
            $quoteEstimate = QuoteEstimate::where('uuid', $uuid)->firstOrFail();

            return response()->json([
                'uuid' => $quoteEstimate->uuid,
                'status' => $quoteEstimate->status,
                'line_items' => $quoteEstimate->lineItems->map(function ($item) {
                    return [
                        'name' => $item->name,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                        'similarity_score' => $item->similarity_score,
                    ];
                }),
                'created_at' => $quoteEstimate->created_at,
                'completed_at' => $quoteEstimate->completed_at,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve quote request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process quote request and queue estimate processing
     */
    public function processQuoteRequest(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'quote_request' => 'required|string|max:10000'
            ]);

            // Create a new QuoteEstimate (status: queued)
            $quoteRequest = new QuoteRequest([
                'name' => $request->input('name', 'Anonymous'),
                'email' => $request->input('email', 'anonymous@example.com'),
                'original_request' => $request->input('quote_request')
            ]);
            $quoteRequest->save();

            $quoteEstimate = QuoteEstimate::create([
                'uuid' => Str::uuid(),
                'status' => 'queued',
                'quote_request_id' => $quoteRequest->id
            ]);

            // Dispatch the job to process the estimate
            ProcessQuoteEstimate::dispatch($quoteEstimate->uuid);

            return response()->json([
                'accepted' => true,
                'uuid' => $quoteEstimate->uuid,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to process quote request: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submitQuoteRequest(Request $request, $uuid): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'full_name' => 'string|max:255',
                'email' => 'required|email|max:255',
                'files' => 'nullable|array'
            ]);

            // Get the validated data
            $fullName = $request->input('full_name');
            $email = $request->input('email');
            $files = $request->input('files', []);

            // Update final quote request, queue an e-mail job with the quote estimate
            $quoteEstimate = QuoteEstimate::where('uuid', $uuid)->firstOrFail();
            $quoteRequest = $quoteEstimate->quoteRequest;
            $quoteRequest->update([
                'name' => $fullName ?? 'Anonymous',
                'email' => $email,
            ]);

            dispatch(new SendQuoteEstimateEmailJob($quoteEstimate->uuid));

            return response()->json([
                'success' => true,
                'message' => 'Quote request submitted successfully.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to submit final quote request: ' . $e->getMessage()
            ], 500);
        }
    }
}