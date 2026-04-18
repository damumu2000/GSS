<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $fillable = [
        'name',
        'site_key',
        'status',
        'template_limit',
        'active_site_template_id',
        'logo',
        'favicon',
        'contact_phone',
        'contact_email',
        'address',
        'seo_title',
        'seo_keywords',
        'seo_description',
        'remark',
        'opened_at',
        'expires_at',
    ];
}
