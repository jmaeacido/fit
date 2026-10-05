<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Submission extends Model
{
    protected $fillable = ['request_key', 'name', 'display_name', 'display_number', 'design_1_display_name', 'design_1_display_number', 'size', 'designs', 'paid_at'];
    protected $hidden = ['request_key'];
    protected function casts(): array { return ['designs' => 'array', 'paid_at' => 'datetime']; }
}
