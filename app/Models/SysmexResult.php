<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @method static \Illuminate\Database\Eloquent\Builder|SysmexResult newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SysmexResult newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SysmexResult query()
 * @mixin \Eloquent
 */
class SysmexResult extends Model
{
    use HasFactory;
    protected $table = 'sysmex';
    public $timestamps = false;
    
    protected $fillable = [
        'doctorvisit_id',
        'wbc',
        'rbc',
        'hgb',
        'hct',
        'mcv',
        'mch',
        'mchc',
        'plt',
        'lym_p',
        'mxd_p',
        'neut_p',
        'lym_c',
        'mxd_c',
        'neut_c',
        'rdw_sd',
        'rdw_cv',
        'pdw',
        'mpv',
        'plcr',
        'flag',
        
        // BC6800 specific WBC differential parameters
        'bas_c',
        'bas_p',
        'eos_c',
        'eos_p',
        'mon_c',
        'mon_p',
        // Table columns actually present
        'mono_p',
        'baso_p',
        'mono_abs',
        'eso_abs',
        'baso_abs',
        'MICROR',
        
        // Additional platelet parameters
        'pct',
        'plcc',
        
        // Additional BC6800 specific parameters
        'hfc_c',
        'hfc_p',
        'plt_i',
        'wbc_d',
        'wbc_b',
        'pdw_sd',
        'inr_c',
        'inr_p',
    ];
}
