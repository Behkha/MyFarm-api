<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDisadvantagesTable extends Migration
{
    public function up()
    {
        Schema::create('disadvantages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
        });
    }

    public function down()
    {
        Schema::dropIfExists('disadvantages');
    }
}
