<?php

namespace Systruss\SchedTransactions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Senders extends Model
{
  	use HasFactory;
	protected $fillable = ['address','passphrase','network'];
}
