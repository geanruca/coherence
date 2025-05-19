<?php

namespace Geanruca\LaravelCoherence\Models;

use Illuminate\Database\Eloquent\Model;

class CoherenceCheck extends Model
{
    protected $guarded = [];

    public function model()
    {
        return $this->morphMany(\Geanruca\LaravelCoherence\Models\CoherenceCheck::class, 'model');
    }
}