<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeedbacksForOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('feedbacks_for_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
        });
    }

    public function down()
    {
        Schema::dropIfExists('feedbacks_for_orders');
    }
}
