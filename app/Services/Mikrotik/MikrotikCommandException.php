<?php

namespace App\Services\Mikrotik;

use RuntimeException;

/**
 * Router terhubung tetapi menolak perintah (respons !trap), mis. profile tidak ditemukan.
 */
class MikrotikCommandException extends RuntimeException {}
