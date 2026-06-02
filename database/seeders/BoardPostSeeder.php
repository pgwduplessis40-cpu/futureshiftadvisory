<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BoardPost;
use Illuminate\Database\Seeder;

class BoardPostSeeder extends Seeder
{
    public function run(): void
    {
        if (BoardPost::query()->exists()) {
            return;
        }

        $seeds = [
            [
                'type' => BoardPost::TYPE_QUOTE,
                'body' => 'The best way to predict the future is to create it.',
                'attribution' => 'Peter Drucker',
                'pinned' => true,
            ],
            [
                'type' => BoardPost::TYPE_QUOTE,
                'body' => 'Whāia te iti kahurangi ki te tūohu koe me he maunga teitei — seek the treasure you value most dearly; if you bow your head, let it be to a lofty mountain.',
                'attribution' => 'Whakataukī',
                'pinned' => false,
            ],
            [
                'type' => BoardPost::TYPE_MESSAGE,
                'title' => 'One step at a time',
                'body' => "Progress rarely looks dramatic up close. Steady, honest work on the things that matter compounds. Keep going — we're in this with you.",
                'attribution' => null,
                'pinned' => false,
            ],
        ];

        foreach ($seeds as $seed) {
            BoardPost::query()->create([
                'type' => $seed['type'],
                'title' => $seed['title'] ?? null,
                'body' => $seed['body'],
                'attribution' => $seed['attribution'],
                'status' => BoardPost::STATUS_PUBLISHED,
                'pinned' => $seed['pinned'],
                'published_at' => now(),
            ]);
        }
    }
}
