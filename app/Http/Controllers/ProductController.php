<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class ProductController extends Controller
{
    /**
     * Display a listing of products for the authenticated user.
     */
    public function index()
    {
        $products = Product::where('user_id', Auth::user()->uuid)->get();
        return response()->json($products);
    }

    /**
     * Store a newly created product (Manual Entry).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'category' => 'nullable|string',
            'status' => 'required|in:active,draft',
            'sku' => 'nullable|string',
            'initial_stock' => 'nullable|integer|min:0',
            'media' => 'nullable|array',
            'media.*' => 'nullable|string', // Assuming image URLs or paths
        ]);

        $validated['user_id'] = Auth::user()->uuid;

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    /**
     * Generate inventory from AI Text.
     */
    public function generateFromAiText(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
        ]);

        $prompt = "Extract product information from the following text and return it as a JSON array of objects. 
        Each object should have these fields: 'name', 'price', 'initial_stock', 'description', 'category', 'sku'. 
        If a field is missing, use null.
        
        Text: \"{$request->text}\"
        
        Return ONLY valid JSON.";

        $result = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant that extracts product data into structured JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        $productsData = json_decode($result->choices[0]->message->content, true);

        return response()->json([
            'message' => 'AI text processing successful',
            'suggestion' => $productsData['products'] ?? $productsData
        ]);
    }

    /**
     * Scan product list from files/images using AI.
     */
    public function scanFromAiFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:png,jpg,jpeg,pdf|max:5120',
        ]);

        $path = $request->file('file')->getRealPath();
        $base64Image = base64_encode(file_get_contents($path));
        $mimeType = $request->file('file')->getMimeType();

        $result = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Analyze this image/document and extract all products listed. 
                            Return them as a JSON array of objects with fields: 'name', 'price', 'initial_stock', 'description', 'category', 'sku'.
                            Return ONLY valid JSON."
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$base64Image}",
                            ],
                        ],
                    ],
                ],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        $productsData = json_decode($result->choices[0]->message->content, true);

        return response()->json([
            'message' => 'File scanning successful',
            'suggestion' => $productsData['products'] ?? $productsData
        ]);
    }
}
