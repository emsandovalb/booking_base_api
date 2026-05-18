<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    /**
     * Return all translation strings for the requested language.
     * Falls back to the default language if the requested code is missing.
     */
    public function index(Request $request): JsonResponse
    {
        $code = $request->query('lang');

        $language = null;
        if ($code) {
            $language = Language::where('code', $code)->first();
        }

        if (!$language) {
            $language = Language::where('is_default', true)->first()
                ?? Language::orderBy('id')->first();
        }

        if (!$language) {
            return response()->json([
                'lang' => $code ?? 'unknown',
                'version' => 0,
                'strings' => new \stdClass(),
            ]);
        }

        $translations = Translation::with('key')
            ->where('language_id', $language->id)
            ->get();

        $strings = [];
        foreach ($translations as $translation) {
            if ($translation->key) {
                $strings[$translation->key->name] = $translation->value;
            }
        }

        return response()->json([
            'lang' => $language->code,
            'version' => $language->version,
            'strings' => $strings,
        ]);
    }
}
