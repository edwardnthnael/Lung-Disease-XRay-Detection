<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\XRayDiagnosis;
use Carbon\Carbon;

class XRayController extends Controller
{
    public function index()
    {
        $diagnoses = XRayDiagnosis::orderBy('created_at', 'desc')->get();
        return view('dashboard', compact('diagnoses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'xray_image' => 'required|image|mimes:jpeg,png,jpg|max:10240'
        ]);

        try {
            $imagePath = $request->file('xray_image')->store('xray_images', 'public');
            $fullImagePath = storage_path('app/public/' . $imagePath);

            $aiResult = $this->analyzeXRayWithVertexAI($fullImagePath);

            if (isset($aiResult['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $aiResult['error'],
                    'error_type' => $aiResult['error_type'] ?? 'AI_ERROR'
                ], 503);
            }

            $diagnosis = XRayDiagnosis::create([
                'nama' => $request->nama,
                'image_path' => $imagePath,
                'ai_result' => json_encode($aiResult),
                'diagnosis' => $aiResult['predicted_disease'],
                'confidence' => $aiResult['confidence'],
                'explanation' => $aiResult['explanation'] ?? null,
                'created_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Diagnosa berhasil disimpan',
                'data' => $diagnosis,
                'ai_result' => $aiResult
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    private function analyzeXRayWithVertexAI($imagePath)
    {
        try {
            $imageContent = file_get_contents($imagePath);
            $base64Image = base64_encode($imageContent);

            $projectId = config('services.vertex_ai.project_id');
            $location = config('services.vertex_ai.location');
            $modelId = config('services.vertex_ai.model_id');
            $accessToken = config('services.vertex_ai.access_token');

            $url = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$projectId}/locations/{$location}/publishers/google/models/{$modelId}:streamGenerateContent";

            // Sebaiknya gunakan sistem otentikasi yang lebih aman seperti Application Default Credentials (ADC) daripada access token statis.

            $payload = [
                "contents" => [
                    [
                        "role" => "user",
                        "parts" => [
                            [
                                "inlineData" => [
                                    "mimeType" => "image/jpeg",
                                    "data" => $base64Image
                                ]
                            ],
                            [
                                "text" => "Analisis X-Ray dada ini dan berikan diagnosa untuk kemungkinan penyakit paru-paru. " .
                                    "Berikan penilaian untuk: Normal, Pneumonia, COVID-19, Tuberculosis, dan Fibrosis. " .
                                    "Format response:\n" .
                                    "Diagnosa Utama: [nama penyakit dengan confidence tertinggi]\n" .
                                    "Confidence: [persentase confidence 0-100]\n" .
                                    "Detail Analisis:\n" .
                                    "- Normal: [persentase]%\n" .
                                    "- Pneumonia: [persentase]%\n" .
                                    "- COVID-19: [persentase]%\n" .
                                    "- Tuberculosis: [persentase]%\n" .
                                    "- Fibrosis: [persentase]%\n" .
                                    "Penjelasan: [jelaskan temuan pada X-Ray yang mendukung diagnosa]"
                            ]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "temperature" => 0.1,
                    "maxOutputTokens" => 2000,
                    "topP" => 0.8
                ],
                "safetySettings" => [
                    ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "OFF"],
                    ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "OFF"],
                    ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "OFF"],
                    ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "OFF"]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->withOptions(['stream' => true])
                ->timeout(30)
                ->post($url, $payload);

            if (!$response->successful()) {
                $statusCode = $response->status();
                $errorBody = $response->body();

                // Log::error("Vertex AI HTTP Error - Status: {$statusCode}, Body: {$errorBody}");

                $errorData = json_decode($errorBody, true);

                if ($statusCode == 401) {
                    return [
                        'error' => 'Sistem AI sedang mengalami masalah otentikasi. Silakan hubungi administrator untuk memperbarui kredensial.',
                        'error_type' => 'AUTHENTICATION_ERROR',
                        'technical_details' => 'Access token expired atau tidak valid'
                    ];
                } elseif ($statusCode == 403) {
                    return [
                        'error' => 'Akses ke layanan AI ditolak. Periksa izin atau quota API.',
                        'error_type' => 'PERMISSION_ERROR'
                    ];
                } elseif ($statusCode == 429) {
                    return [
                        'error' => 'Terlalu banyak permintaan. Silakan coba lagi dalam beberapa saat.',
                        'error_type' => 'RATE_LIMIT_ERROR'
                    ];
                } elseif ($statusCode >= 500) {
                    return [
                        'error' => 'Server AI sedang mengalami gangguan. Silakan coba lagi nanti.',
                        'error_type' => 'SERVER_ERROR'
                    ];
                } else {
                    return [
                        'error' => 'Terjadi kesalahan saat menghubungi layanan AI. Kode error: ' . $statusCode,
                        'error_type' => 'API_ERROR'
                    ];
                }
            }

            $body = $response->getBody();
            $fullContent = '';

            while (!$body->eof()) {
                $fullContent .= $body->read(1024);
            }

            // Log::info("Gemini Raw Stream:\n" . $fullContent);

            if (strpos($fullContent, '"error":') !== false) {
                $errorData = json_decode($fullContent, true);
                if (isset($errorData['error'])) {
                    $errorCode = $errorData['error']['code'] ?? 'unknown';
                    $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
                    $errorStatus = $errorData['error']['status'] ?? 'UNKNOWN';

                    // Log::error("Vertex AI API Error: {$errorCode} - {$errorMessage}");

                    if ($errorStatus === 'UNAUTHENTICATED' || $errorCode == 401) {
                        return [
                            'error' => 'Token autentikasi AI telah kedaluwarsa. Silakan hubungi administrator untuk memperbarui token.',
                            'error_type' => 'TOKEN_EXPIRED',
                            'technical_details' => $errorMessage
                        ];
                    } else {
                        return [
                            'error' => 'Layanan AI mengalami masalah: ' . $errorMessage,
                            'error_type' => 'API_ERROR',
                            'technical_details' => "Code: {$errorCode}, Status: {$errorStatus}"
                        ];
                    }
                }
            }

            $responseText = $this->parseStreamingResponse($fullContent);

            if (empty($responseText)) {
                return [
                    'error' => 'AI tidak memberikan respons yang valid. Silakan coba lagi.',
                    'error_type' => 'EMPTY_RESPONSE'
                ];
            }

            // Log::info("Parsed Response Text: " . $responseText);

            return $this->parseGeminiResponse($responseText);
        } catch (\Exception $e) {
            // Log::error('Vertex AI Exception: ' . $e->getMessage());

            if (strpos($e->getMessage(), 'timeout') !== false) {
                return [
                    'error' => 'Layanan AI membutuhkan waktu terlalu lama untuk merespons. Silakan coba lagi.',
                    'error_type' => 'TIMEOUT_ERROR'
                ];
            } elseif (strpos($e->getMessage(), 'Connection') !== false) {
                return [
                    'error' => 'Tidak dapat terhubung ke layanan AI. Periksa koneksi internet Anda.',
                    'error_type' => 'CONNECTION_ERROR'
                ];
            } else {
                return [
                    'error' => 'Terjadi kesalahan teknis saat menganalisis X-Ray: ' . $e->getMessage(),
                    'error_type' => 'TECHNICAL_ERROR'
                ];
            }
        }
    }

    private function parseStreamingResponse($fullContent)
    {
        $responseText = '';

        $lines = explode("\n", $fullContent);

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (strpos($line, 'data: ') === 0) {
                $line = substr($line, 6);
            }

            $json = json_decode($line, true);

            if ($json && isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $responseText .= $json['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        if (empty($responseText)) {
            $jsonObjects = explode('},{', $fullContent);
            foreach ($jsonObjects as $index => $jsonStr) {
                if ($index > 0) {
                    $jsonStr = '{' . $jsonStr;
                }
                if ($index < count($jsonObjects) - 1) {
                    $jsonStr = $jsonStr . '}';
                }

                $json = json_decode($jsonStr, true);

                if ($json && isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                    $responseText .= $json['candidates'][0]['content']['parts'][0]['text'];
                }
            }
        }

        return $responseText;
    }

    private function parseGeminiResponse($responseText)
    {
        // Log::info('Raw Gemini Response: ' . $responseText);

        if (empty($responseText)) {
            // Log::warning('Empty response text received');
            return [
                'error' => 'AI tidak memberikan hasil analisis. Silakan coba upload gambar X-Ray yang berbeda.',
                'error_type' => 'EMPTY_AI_RESPONSE'
            ];
        }

        $predictedDisease = 'Unknown';
        if (preg_match('/Diagnosa Utama[:\s]*(.+?)(?:\n|$)/i', $responseText, $matches)) {
            $predictedDisease = trim($matches[1]);
        } elseif (preg_match('/\*\*Diagnosa Utama\*\*[:\s]*(.+?)(?:\n|$)/i', $responseText, $matches)) {
            $predictedDisease = trim($matches[1]);
        }

        $confidence = 0;
        if (preg_match('/Confidence[:\s]*(\d+)%?/i', $responseText, $matches)) {
            $confidence = (float)$matches[1];
        } elseif (preg_match('/\*\*Confidence\*\*[:\s]*(\d+)%?/i', $responseText, $matches)) {
            $confidence = (float)$matches[1];
        }

        $allPredictions = [];

        if (preg_match_all('/[-*]\s*(Normal|Pneumonia|COVID-19|Tuberculosis|Fibrosis)[:\s]*(\d+)%/i', $responseText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $allPredictions[$match[1]] = (float)$match[2];
            }
        }

        if (empty($allPredictions) && preg_match_all('/\*\s*(Normal|Pneumonia|COVID-19|Tuberculosis|Fibrosis)[:\s]*(\d+)%/i', $responseText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $allPredictions[$match[1]] = (float)$match[2];
            }
        }

        if (empty($allPredictions)) {
            $allPredictions = [
                'Normal' => 20,
                'Pneumonia' => 20,
                'COVID-19' => 20,
                'Tuberculosis' => 20,
                'Fibrosis' => 20
            ];
        }

        $explanation = 'Tidak ada penjelasan tersedia';
        if (preg_match('/Penjelasan[:\s]*(.+)/si', $responseText, $matches)) {
            $explanation = trim($matches[1]);
        } elseif (preg_match('/\*\*Penjelasan\*\*[:\s]*(.+)/si', $responseText, $matches)) {
            $explanation = trim($matches[1]);
        }

        // Log::info('Diagnosa parsed: ' . $predictedDisease);
        // Log::info('Confidence parsed: ' . $confidence);
        // Log::info('All predictions: ' . json_encode($allPredictions));

        return [
            'predicted_disease' => $predictedDisease,
            'confidence' => $confidence,
            'all_predictions' => $allPredictions,
            'explanation' => $explanation,
            'analysis_timestamp' => Carbon::now()->toISOString(),
            'raw_response' => $responseText
        ];
    }
}
