<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryBook extends Model
{
    protected $guarded = [];
    protected $casts = ['is_featured' => 'boolean', 'published_at' => 'datetime'];
    public function category() { return $this->belongsTo(LibraryCategory::class, 'category_id'); }
    public function currentFile() { return $this->belongsTo(LibraryBookFile::class, 'current_file_id'); }
    public function files() { return $this->hasMany(LibraryBookFile::class, 'book_id'); }
    public function scopePublished($query) { return $query->where('status', 'published'); }
    public function publicData(): array
    {
        $f = $this->currentFile;
        return ['id' => $this->id, 'slug' => $this->slug, 'title' => $this->title,
            'author' => $this->author, 'description' => $this->description,
            'category' => $this->category?->only(['id', 'name', 'slug']),
            'is_featured' => $this->is_featured, 'published_at' => $this->published_at,
            'cover_url' => $this->cover_path ? '/api/library/books/'.$this->id.'/cover' : null,
            'file' => $f && $f->book_id === $this->id ? $f->only(['id', 'version', 'page_count', 'byte_size']) : null];
    }
}
