<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDelegateDbsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delegate_dbs', function (Blueprint $table) {
            $table->id();
            $table->string('address');
            $table->string('passphrase');
            $table->boolean('sched_active')->nullable();
            $table->tinyinteger('sched_freq')->default('24');
            $table->enum('network',['infi','edge']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delegate_dbs');
    }
}
