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
    ];
}
