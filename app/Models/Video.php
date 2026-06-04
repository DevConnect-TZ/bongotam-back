<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    /** @use HasFactory\<\Database\Factories\VideoFactory\> */
    use HasFactory;

    protected $fillable = [
        'title',
        'thumbnail',
        'video_link',
        'price',
        'rating',
        'zone',
        'views',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'views' => 'integer',
        ];
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => MediaPath::toPublicUrl($value),
            set: fn (?string $value) => MediaPath::normalizeForStorage($value),
        );
    }

    protected function videoLink(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => MediaPath::toPublicUrl($value),
            set: fn (?string $value) => MediaPath::normalizeForStorage($value),
        );
    }
}
