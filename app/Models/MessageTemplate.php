<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Teks template pesan pelanggan hasil suntingan Owner (lihat MessageTemplates).
 */
#[Fillable(['key', 'body', 'updated_by'])]
class MessageTemplate extends Model {}
