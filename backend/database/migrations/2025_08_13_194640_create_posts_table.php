<?php

use App\Enums\PostStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->text('content')
                ->comment('Content of the post');
            $table->text('moderation_reason')
                ->nullable()
                ->comment('Reason for moderation, if applicable');
            $table->enum('status', Arr::pluck(PostStatus::cases(), 'value'))
                ->default(PostStatus::Pending)
                ->comment('Status of the post, e.g., Pending, Approved, Rejected');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
