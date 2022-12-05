<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderFeedbackTable extends Migration
{
    public function up()
    {
        Schema::create('order_feedback', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('feedback_id');
            $table->unsignedInteger('value_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_feedback');
    }
}
