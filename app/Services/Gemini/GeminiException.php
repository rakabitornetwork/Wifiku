<?php

namespace App\Services\Gemini;

use RuntimeException;

/**
 * Kegagalan memanggil Gemini; pesannya aman ditampilkan ke admin (tidak memuat API key).
 */
class GeminiException extends RuntimeException {}
