<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MoroccoGeographySeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__.'/data/hcp-rgph-2024.json'), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($data) {
            $this->import('regions', $data['regions']);
            $this->import('cities', $data['cities'], 'region_id', DB::table('regions')->pluck('id', 'code')->all());
            $this->import('localities', $data['localities'], 'city_id', DB::table('cities')->pluck('id', 'code')->all());
        });
    }

    private function import(string $table, array $records, ?string $parentKey = null, array $parents = []): void
    {
        $time = now();
        foreach (array_chunk($records, 100) as $batch) {
            $rows = [];
            foreach ($batch as $record) {
                $attributes = $parentKey ? [$parentKey => $parents[$record[1]]] : [];
                $rows[] = $attributes + [
                    'id' => (string) Str::uuid7(),
                    'code' => $record[0],
                    'name_fr' => $record[count($record) - 2],
                    'name_ar' => $record[count($record) - 1],
                    'source_url' => 'https://www.hcp.ma/file/242341/',
                    'source_date' => '2024-09-01',
                    'active' => true,
                    'created_at' => $time,
                    'updated_at' => $time,
                ];
            }
            DB::table($table)->insertOrIgnore($rows);
        }
    }
}
