# Quote Request API Documentation

## Overview
This API endpoint processes quote requests by extracting individual items, generating embeddings using OpenAI, and finding similar products in the MongoDB database.

## Endpoint
```
POST /api/quote-request
```

## Request Body
```json
{
    "quote_request": "LED display panels, acrylic signage, vinyl banners"
}
```

## Parameters
- **quote_request** (required, string, max: 10,000 characters): The quote request text containing product descriptions

## Text Processing
The API processes the input text in the following order:
1. **Line separation**: First splits by line breaks (`\n`, `\r\n`, `\r`)
2. **Comma separation**: If no line breaks are found, splits by commas (`,`)
3. **Filtering**: Removes empty lines and trims whitespace

## Response Format
```json
{
    "success": true,
    "extracted_lines": [
        "LED display panels",
        "acrylic signage", 
        "vinyl banners"
    ],
    "results": [
        {
            "query_text": "LED display panels",
            "embedding_dimensions": 1536,
            "similar_products": [
                {
                    "id": "64f8b123456789abcdef1234",
                    "name": "Premium LED Panel 24x36",
                    "description": "High-resolution LED display panel for indoor use",
                    "price": 299.99,
                    "category": "LED Displays",
                    "similarity_score": 0.89
                }
            ]
        }
    ],
    "total_matches": 3
}
```

## Error Response
```json
{
    "error": "Failed to process quote request: [error details]"
}
```

## Setup Requirements

### 1. OpenAI API Key
Add your OpenAI API key to the `.env` file:
```
OPENAI_API_KEY=sk-your-openai-api-key-here
```

### 2. MongoDB Atlas Vector Search Index
You need to create a vector search index in your MongoDB Atlas cluster:

1. Go to your MongoDB Atlas dashboard
2. Navigate to your `BrightSigns` database → `products` collection
3. Go to "Search" → "Create Search Index"
4. Choose "Atlas Vector Search"
5. Use this configuration:

```json
{
  "fields": [
    {
      "numDimensions": 1536,
      "path": "embedded",
      "similarity": "cosine",
      "type": "vector"
    }
  ]
}
```

### 3. Product Data Structure
Ensure your MongoDB `products` collection documents have this structure:
```json
{
  "_id": "ObjectId",
  "name": "Product Name",
  "description": "Product description",
  "price": 99.99,
  "category": "Category Name",
  "embedded": [0.1234, -0.5678, ...] // 1536-dimensional vector
}
```

## Usage Examples

### Example 1: Line-separated input
```bash
curl -X POST http://localhost/api/quote-request \
  -H "Content-Type: application/json" \
  -d '{
    "quote_request": "LED display panels\nacrylic signage\nvinyl banners"
  }'
```

### Example 2: Comma-separated input
```bash
curl -X POST http://localhost/api/quote-request \
  -H "Content-Type: application/json" \
  -d '{
    "quote_request": "LED display panels, acrylic signage, vinyl banners"
  }'
```

### Example 3: Mixed input
```bash
curl -X POST http://localhost/api/quote-request \
  -H "Content-Type: application/json" \
  -d '{
    "quote_request": "Large format printing services\nDigital signage solutions, LED displays\nCustom vehicle wraps"
  }'
```

## Technical Details

- **Embedding Model**: `text-embedding-3-small` (1536 dimensions)
- **Vector Similarity**: Cosine similarity
- **Result Limit**: 5 similar products per query line
- **MongoDB Driver**: `mongodb/laravel-mongodb`
- **OpenAI Client**: `openai-php/client`

## Notes
- The API requires both OpenAI and MongoDB to be properly configured
- Vector search requires a properly configured Atlas Search index
- Large quote requests are limited to 10,000 characters
- Empty lines and items are automatically filtered out
