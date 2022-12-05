<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeedbackElementsTable extends Migration
{
    public function up()
    {
        Schema::create('feedback_elements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('feedback_group_id');
            $table->string('title');

            $table
                ->foreign('feedback_group_id')
                ->references('id')
                ->on('feedback_groups');
        });
    }

    public function down()
    {
        Schema::dropIfExists('feedback_elements');
    }
}
