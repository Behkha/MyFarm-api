<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductAdvantageFeedbackTable extends Migration
{
    public function up()
    {
        Schema::create('product_advantage_feedback', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('advantage_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_advantage_feedback');
    }
}
