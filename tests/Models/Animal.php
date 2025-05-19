<?php

namespace Geanruca\LaravelCoherence\Tests\Models;

use Geanruca\LaravelCoherence\Traits\HasCoherence;
use Illuminate\Database\Eloquent\Model;

class Animal extends Model
{
    use HasCoherence;

    protected $guarded = [];
    public $timestamps = false;

    protected $attributes = [
        'name' => 'string',
        'mass_in_kilograms' => 'float'
    ];
}