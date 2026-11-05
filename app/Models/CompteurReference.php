<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Table « compteurs_references ». */
class CompteurReference extends Model
{
    protected $table = 'compteurs_references';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['prefixe', 'annee', 'dernier_numero'];

}
