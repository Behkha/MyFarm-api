<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryFeedbackTable extends Migration
{
    public function up()
    {
        Schema::create('category_feedback', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id');
            $table->unsignedInteger('feedback_group_id');

            $table
                ->foreign('category_id')
                ->references('id')
                ->on('categories');
            $table
                ->foreign('feedback_group_id')
                ->references('id')
                ->on('feedback_groups');
        });
    }

    public function down()
    {
        Schema::dropIfExists('category_feedback');
    }
}
