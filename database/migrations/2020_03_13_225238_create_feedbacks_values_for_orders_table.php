<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeedbacksValuesForOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('feedbacks_values_for_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('feedback_id');
            $table->string('value');
        });
    }

    public function down()
    {
        Schema::dropIfExists('feedbacks_values_for_orders');
    }
}
