<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['grupo', 'clave', 'valor'])]
#[Table(name: 'configuraciones')]
class Configuracion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['valor' => 'array'];
    }
}
