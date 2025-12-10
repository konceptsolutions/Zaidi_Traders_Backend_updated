<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoaSubGroup extends Model
{
    use HasFactory;
    protected $fillable = [ 'name', 'code', 'coa_group_id', 'is_default', 'is_active'];

    public function coaGroup(){
        return $this->belongsTo(CoaGroup::class);
    }

    public function coaAccounts(){
        return $this->hasMany(CoaAccount::class);
    }
}
