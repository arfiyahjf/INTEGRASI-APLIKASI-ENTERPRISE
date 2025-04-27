<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $table = 'loans';

    protected $fillable = [
        'user_id', 'book_id', 'borrowed_at', 'due_date', 'returned_at', 'status'
    ];

    public $timestamps = true;
}
