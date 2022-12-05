<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeedbackElementValuesTable extends Migration
{
    public function up()
    {
        Schema::create('feedback_element_values', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('feedback_element_id');
            $table->string('value');

            $table
                ->foreign('feedback_element_id')
                ->references('id')
                ->on('feedback_elements');
        });
    }

    public function down()
    {
        Schema::dropIfExists('feedback_element_values');
    }
}
