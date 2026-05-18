<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Language;
use App\Models\Translation;
use App\Models\TranslationKey;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'es', 'name' => 'Español', 'is_default' => true],
            ['code' => 'en', 'name' => 'English', 'is_default' => false],
        ];

        $languageModels = [];
        foreach ($languages as $lang) {
            $languageModels[$lang['code']] = Language::updateOrCreate(
                ['code' => $lang['code']],
                [
                    'name' => $lang['name'],
                    'is_default' => $lang['is_default'],
                    'version' => 1,
                ]
            );
        }

        $entries = require __DIR__ . '/translations.php';

        foreach ($entries as $entry) {
            $key = TranslationKey::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'module' => $entry['module'] ?? null,
                    'description' => $entry['description'] ?? null,
                ]
            );

            foreach (($entry['translations'] ?? []) as $code => $value) {
                $language = $languageModels[$code] ?? null;
                if (!$language) {
                    continue;
                }
                Translation::updateOrCreate(
                    [
                        'language_id' => $language->id,
                        'translation_key_id' => $key->id,
                    ],
                    ['value' => $value]
                );
            }
        }
    }
}
