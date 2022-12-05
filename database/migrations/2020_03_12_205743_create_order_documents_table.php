<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('order_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('cheque_id');
            $table->date('paid_date');
            $table->string('payer_name');
            $table
                ->text('description')
                ->nullable();
            $table
                ->string('file_path')
                ->nullable();

            $table
                ->foreign('order_id')
                ->references('id')
                ->on('orders');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_documents');
    }
}
