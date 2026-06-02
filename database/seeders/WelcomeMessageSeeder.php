<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WelcomeMessage;
use Illuminate\Database\Seeder;

class WelcomeMessageSeeder extends Seeder
{
    public function run(): void
    {
        if (WelcomeMessage::query()->exists()) {
            return;
        }

        WelcomeMessage::query()->create([
            'version' => 1,
            'body' => $this->baseMessage(),
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }

    private function baseMessage(): string
    {
        return <<<'MD'
        **Nau mai, haere mai — welcome to {{practice_name}}.**

        Kia ora {{contact_first_name}},

        We're really glad you're here. Choosing to bring in advice for {{business_name}} says a lot, and we don't take that trust lightly.

        Future Shift works alongside New Zealand business owners with practical, straight advice — built around your numbers and your goals, not a generic template. As we work together you can expect honest answers: the encouraging ones and the hard ones. That honesty is the part that actually helps you make good decisions.

        Before we begin, there's a short guided setup — about 15 minutes. We'll confirm a few details, capture a snapshot of the business, ask what you're hoping to achieve, and gather a handful of documents. You can save and come back whenever suits. The fuller the picture, the sharper the advice we can give you.

        Everything you share stays private to your advisory team, is encrypted, and is never used for anything beyond your engagement.

        Whenever you're ready, let's get started.

        Ngā mihi,

        The {{practice_name}} team
        MD;
    }
}
