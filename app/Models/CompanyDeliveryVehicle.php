<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDeliveryVehicle extends Model
{
    protected $fillable = [
        'plate',
        'label',
    ];
}
