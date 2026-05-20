<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'email',
    'phone',
    'company',
    'engagement_interest',
    'message',
    'source',
    'ip_address',
    'user_agent',
])]
class ProspectLead extends Model
{
    protected $table = 'prospect_leads';
}
