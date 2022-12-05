<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeedbackGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('feedback_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
        });
    }

    public function down()
    {
        Schema::dropIfExists('feedback_groups');
    }
}
