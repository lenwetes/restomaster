<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuraciones';

    protected $fillable = ['grupo', 'clave', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'array'];
    }
}
