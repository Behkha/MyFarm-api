<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductFeedbacksTable extends Migration
{
    public function up()
    {
        Schema::create('product_feedbacks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('feedback_element_value_id');
            $table->unsignedInteger('feedback_element_id');
            $table->unsignedBigInteger('user_id');
            $table
                ->timestamp('created_at')
                ->useCurrent();

            $table
                ->foreign('product_id')
                ->references('id')
                ->on('products');

            $table
                ->foreign('feedback_element_value_id')
                ->references('id')
                ->on('feedback_element_values');

            $table
                ->foreign('feedback_element_id')
                ->references('id')
                ->on('feedback_elements');

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_feedbacks');
    }
}
