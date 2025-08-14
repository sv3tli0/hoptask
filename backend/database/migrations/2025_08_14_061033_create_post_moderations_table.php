<?php

declare(strict_types=1);

use App\Enums\PostModerationSeverity;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_moderations', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Post::class)
                ->comment('ID of the post being moderated')
                ->constrained()
                ->onDelete('cascade');

            $table->boolean('approved')
                ->default(false)
                ->comment('Whether the post was approved by moderation');

            $table->json('categories')
                ->nullable()
                ->comment('Categories flagged by the moderation service');

            $table->enum('severity', Arr::pluck(PostModerationSeverity::cases(), 'value'))
                ->default(PostModerationSeverity::DEFAULT->value)
                ->comment('Severity level of moderation flags');

            $table->decimal('confidence', 3, 2)
                ->nullable()
                ->comment('Confidence score from the moderation service (0.00-1.00)');

            $table->text('reason')
                ->nullable()
                ->comment('Reason provided by the moderation service');

            $table->boolean('error')
                ->default(false)
                ->comment('Whether an error occurred during moderation');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_moderations');
    }
};
