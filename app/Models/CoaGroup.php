<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoaGroup extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'parent', 'type', 'code'];

    public function coaSubGroups()
    {
        return $this->hasMany(CoaSubGroup::class)->select('id', 'coa_group_id', 'name', 'code', 'type');
    }

    public function depreciationSubGroups()
    {
        return $this->hasMany(CoaSubGroup::class)->where('type', 'depreciation')->select('id', 'coa_group_id', 'name', 'code', 'type');
    }

    public function nonDepreciationSubGroups()
    {
        return $this->hasMany(CoaSubGroup::class)->where('type', '!=', 'depreciation')->orWhere('type', '=', null)->select('id', 'coa_group_id', 'name', 'code', 'type');
    }
}
