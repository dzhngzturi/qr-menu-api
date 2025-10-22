<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    protected $fillable = ['name','slug','settings'];
    protected $casts = ['settings'=>'array'];

    public function users(){ return $this->belongsToMany(User::class)->withPivot('role'); }
    public function categories(){ return $this->hasMany(Category::class); }
    public function dishes(){ return $this->hasMany(Dish::class); }

    public function getRouteKeyName(){ return 'id'; }
}
