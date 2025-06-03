<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;
    protected $table = 'comments';
    protected $primaryKey = 'comment_id';
    protected $fillable = [
        'article_id',
        'user_id',
        'comment_text',
        'parent_id'
    ];

    /**
     * Get the user that owns the comment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the article that owns the comment
     */
    public function article() 
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Get the parent comment
     */
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the replies for the comment
     */
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
