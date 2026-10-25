<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Países e províncias. Os municípios vêm de database/seeders/data/municipalities.csv
 * (colunas: province,municipality), a preencher a partir da fonte oficial; não há
 * municípios por omissão para não inventar a divisão administrativa.
 */
class GeographySeeder extends Seeder
{
    private const COUNTRIES = [
        ['Angola', 'Angolana', 'AO'], ['Portugal', 'Portuguesa', 'PT'], ['Brasil', 'Brasileira', 'BR'],
        ['República Democrática do Congo', 'Congolesa', 'CD'], ['República do Congo', 'Congolesa', 'CG'],
        ['Namíbia', 'Namibiana', 'NA'], ['Zâmbia', 'Zambiana', 'ZM'], ['Moçambique', 'Moçambicana', 'MZ'],
        ['Cabo Verde', 'Cabo-verdiana', 'CV'], ['São Tomé e Príncipe', 'São-tomense', 'ST'], ['Guiné-Bissau', 'Guineense', 'GW'],
    ];

    private const PROVINCES = [
        'Bengo', 'Benguela', 'Bié', 'Cabinda', 'Cuando', 'Cubango', 'Cuanza Norte', 'Cuanza Sul', 'Cunene',
        'Huambo', 'Huíla', 'Icolo e Bengo', 'Luanda', 'Lunda Norte', 'Lunda Sul', 'Malanje', 'Moxico',
        'Moxico Leste', 'Namibe', 'Uíge', 'Zaire',
    ];

    public function run(): void
    {
        foreach (self::COUNTRIES as [$name, $nationality, $iso]) {
            DB::table('countries')->updateOrInsert(['iso2' => $iso], compact('name', 'nationality'));
        }

        $angola = DB::table('countries')->where('iso2', 'AO')->value('id');

        foreach (self::PROVINCES as $name) {
            DB::table('provinces')->updateOrInsert(['country_id' => $angola, 'name' => $name]);
        }

        $csv = database_path('seeders/data/municipalities.csv');
        if (! is_file($csv)) {
            return;
        }

        $provinces = DB::table('provinces')->where('country_id', $angola)->pluck('id', 'name');
        $rows = array_map('str_getcsv', file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        array_shift($rows);

        foreach ($rows as [$province, $municipality]) {
            if (isset($provinces[$province])) {
                DB::table('municipalities')->updateOrInsert(['province_id' => $provinces[$province], 'name' => $municipality]);
            }
        }
    }
}
