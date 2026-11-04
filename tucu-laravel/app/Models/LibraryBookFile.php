<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LibraryBookFile extends Model
{
    protected $guarded = [];
    protected $casts = ['encrypted' => 'boolean', 'page_count' => 'integer', 'book_id' => 'integer'];
}
