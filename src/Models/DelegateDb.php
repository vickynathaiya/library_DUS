<?php

namespace Systruss\SchedTransactions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DelegateDb extends Model
{
  	use HasFactory;
	protected $fillable = ['address','passphrase','network','sched_feq','sched_active'];
}
