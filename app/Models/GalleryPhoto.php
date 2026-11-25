<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto hasil instalasi yang ditampilkan di galeri landing page. File disimpan di disk privat
 * dan hanya disajikan lewat GalleryImageController (hanya yang berstatus tayang).
 */
#[Fillable(['title', 'caption', 'path', 'thumb_path', 'sort_order', 'is_published'])]
class GalleryPhoto extends Model
{
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }
}
