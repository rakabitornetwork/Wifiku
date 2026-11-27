<?php

namespace App\Services\Updater;

use RuntimeException;

/**
 * Pembaruan tidak bisa dimulai (sedang berjalan, tidak ada pembaruan, atau repositori tidak siap).
 */
class UpdateException extends RuntimeException {}
