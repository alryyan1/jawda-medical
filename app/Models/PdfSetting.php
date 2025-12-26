<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'font_family',
        'font_size',
        'logo_path',
        'logo_width',
        'logo_height',
        'logo_position',
        'hospital_name',
        'header_image_path',
        'footer_phone',
        'footer_address',
        'footer_email',
    ];

    protected $casts = [
        'font_size' => 'integer',
        'logo_width' => 'decimal:2',
        'logo_height' => 'decimal:2',
    ];

    /**
     * Get the singleton PDF settings instance.
     * Creates default settings if none exist.
     */
    public static function getSettings(): self
    {
        $settings = self::first();
        
        if (!$settings) {
            $settings = self::create([
                'font_family' => 'Amiri',
                'font_size' => 10,
                'logo_position' => 'right',
            ]);
        }
        
        return $settings;
    }
}
