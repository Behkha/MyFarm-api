<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductDisadvantageFeedbackTable extends Migration
{
    public function up()
    {
        Schema::create('product_disadvantage_feedback', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('disadvantage_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_disadvantage_feedback');
    }
}
