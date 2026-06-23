# Migrations Laravel - Volet Apprenant (Learn&Quiz)

**Date:** 23 juin 2026  
**Usage:** Référence et code source des migrations pour le Volet Apprenant.

---

## 1. Migration: quiz_attempts

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 5, 2)->nullable()->comment('Score en pourcentage 0-100');
            $table->integer('points_earned')->nullable();
            $table->integer('points_total')->nullable();
            $table->boolean('passed')->nullable();
            $table->integer('time_spent')->nullable()->comment('Temps en secondes');
            $table->json('answers')->nullable()->comment('Réponses par question');
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status', 20)->default('in_progress'); // 'in_progress', 'completed', 'abandoned'
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['learner_id', 'quiz_id']);
            $table->index('status');
            $table->index('completed_at');
            $table->index(['learner_id', 'quiz_id', 'completed_at'], 'idx_attempts_composite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
```

---

## 2. Migration: quiz_answers

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->text('answer_given')->nullable();
            $table->text('correct_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->integer('points_earned')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('attempt_id');
            $table->index('question_id');
            $table->index('is_correct');
            
            // Contrainte: une seule réponse par question par tentative
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
```

---

## 3. Migration: learner_progress

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->string('content_type', 50); // 'article', 'quiz'
            $table->unsignedBigInteger('content_id');
            $table->string('status', 20)->default('not_started'); // 'not_started', 'in_progress', 'completed'
            $table->unsignedTinyInteger('progress_percentage')->default(0); // 0-100
            $table->integer('time_spent')->default(0)->comment('Temps en secondes');
            $table->unsignedTinyInteger('rating')->nullable()->comment('Note de 1 à 5 étoiles');
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('learner_id');
            $table->index(['content_type', 'content_id']);
            $table->index('status');
            $table->index('completed_at');
            $table->index(['learner_id', 'status'], 'idx_learner_status');
            
            // Contrainte: un seul enregistrement par contenu par apprenant
            $table->unique(['learner_id', 'content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_progress');
    }
};
```

---

## 4. Migration: notifications

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 50); // 'new_quiz', 'new_article', 'streak_reminder', 'flashcard_due', 'exam_deadline'
            $table->string('title');
            $table->text('message');
            $table->text('action_url')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('priority', 20)->default('normal'); // 'low', 'normal', 'high', 'urgent'
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('type');
            $table->index(['is_read', 'created_at']);
            $table->index('priority');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

---

## 5. Migration: Ajouter champs aux quizzes

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_attempts')->default(3)->after('is_active');
            $table->boolean('show_correct_answers')->default(true)->after('max_attempts');
            $table->boolean('allow_partial_score')->default(false)->after('show_correct_answers');
            $table->timestamp('available_from')->nullable()->after('allow_partial_score');
            $table->timestamp('available_until')->nullable()->after('available_from');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['max_attempts', 'show_correct_answers', 'allow_partial_score', 'available_from', 'available_until']);
        });
    }
};
```

---

## 6. Migration: Ajouter champs aux articles

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedSmallInteger('estimated_reading_time')->nullable()->after('content');
            $table->timestamp('available_from')->nullable()->after('is_active');
            $table->timestamp('available_until')->nullable()->after('available_from');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['estimated_reading_time', 'available_from', 'available_until']);
        });
    }
};
```

---

## 7. Migration: flashcards

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->decimal('difficulty_factor', 3, 2)->default(2.50)->comment('EF (SuperMemo-2 Easiness Factor)');
            $table->integer('interval_days')->default(1);
            $table->integer('repetitions')->default(0);
            $table->date('next_review_date');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->string('ease_rating', 20)->nullable(); // 'easy', 'medium', 'hard', 'again'
            $table->timestamps();

            $table->unique(['learner_id', 'question_id']);
            $table->index('next_review_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcards');
    }
};
```

---

## 8. Migration: badges

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description');
            $table->string('icon', 100);
            $table->string('condition_type', 50); // e.g. quiz_completed, quiz_perfect, articles_read, streak, etc.
            $table->json('condition_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
```

---

## 9. Migration: learner_badges

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->foreignId('badge_id')->constrained('badges')->onDelete('cascade');
            $table->timestamp('earned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['learner_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_badges');
    }
};
```

---

## 10. Migration: learner_xp

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_xp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->unique()->constrained('learners')->onDelete('cascade');
            $table->integer('total_xp')->default(0);
            $table->integer('current_level')->default(1);
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_xp');
    }
};
```

---

## 11. Migration: error_reports

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->string('content_type', 50); // 'quiz' ou 'article' (polymorphique)
            $table->unsignedBigInteger('content_id');
            $table->string('error_type', 50); // 'content', 'spelling', 'technical'
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('pending'); // 'pending', 'resolved', 'ignored'
            $table->timestamps();

            // Indexes
            $table->index(['content_type', 'content_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
```

---

## 12. Migration: screenshot_attempts

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screenshot_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->onDelete('cascade');
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->onDelete('cascade');
            $table->timestamp('detected_at')->useCurrent();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screenshot_attempts');
    }
};
```

---

## 13. Migration: learner_preferences

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->unique()->constrained('learners')->onDelete('cascade');
            $table->string('locale', 10)->default('fr');
            $table->string('theme', 20)->default('light'); // 'light', 'dark', 'auto'
            $table->string('font_size', 20)->default('medium'); // 'small', 'medium', 'large'
            $table->boolean('sound_enabled')->default(true);
            $table->json('notifications_enabled')->nullable(); // configuration fine
            $table->time('streak_reminder_time')->default('20:00:00');
            $table->time('dnd_start')->default('22:00:00');
            $table->time('dnd_end')->default('08:00:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_preferences');
    }
};
```
