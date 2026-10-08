<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Setting extends Model
{
    use LogsActivity;

    protected $fillable = ['key', 'value', 'type', 'group', 'description'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['key', 'value'])->logOnlyDirty()->useLogName('setting');
    }
}