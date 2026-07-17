<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = ['name_ar', 'name_en', 'slug', 'logo_url', 'config', 'is_active'];

    protected $casts = ['config' => 'array', 'is_active' => 'boolean'];

    public function users(): HasMany        { return $this->hasMany(User::class); }
    public function services(): HasMany     { return $this->hasMany(ServiceDefinition::class); }
    public function applications(): HasMany { return $this->hasMany(Application::class); }
}
