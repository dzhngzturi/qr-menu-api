<?php
// app/Models/Allergen.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Allergen extends Model
{
    protected $fillable = ['restaurant_id','code','name','is_active', 'position'];

    public function restaurant() { return $this->belongsTo(Restaurant::class); }
    public function dishes()     { return $this->belongsToMany(Dish::class, 'allergen_dish'); }
}

