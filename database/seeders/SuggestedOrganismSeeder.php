<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SuggestedOrganism;

class SuggestedOrganismSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Truncate the table first
        SuggestedOrganism::truncate();
        
        $organisms = [
            'Escherichia coli',
            'Staphylococcus aureus',
            'Streptococcus pneumoniae',
            'Pseudomonas aeruginosa',
            'Klebsiella pneumoniae',
            'Enterococcus faecalis',
            'Proteus mirabilis',
            'Candida albicans',
            'Enterobacter cloacae',
            'Acinetobacter baumannii',
            'Serratia marcescens',
            'Morganella morganii',
            'Citrobacter freundii',
            'Providencia stuartii',
            'Burkholderia cepacia',
            'Stenotrophomonas maltophilia',
            'Enterococcus faecium',
            'Candida glabrata',
            'Candida tropicalis',
            'Candida krusei'
        ];

        foreach ($organisms as $organism) {
            SuggestedOrganism::updateOrCreate(
                ['name' => $organism],
                ['name' => $organism]
            );
        }
    }
}