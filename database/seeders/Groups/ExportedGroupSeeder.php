<?php

namespace Database\Seeders\Groups;

use Illuminate\Database\Seeder;
use App\Models\Groups\Group;

class ExportedGroupSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name'=>'G1','promotion_id'=>14,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>14,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>14,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>15,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>15,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>15,'student_amount'=>28],
            ['name'=>'G7','promotion_id'=>16,'student_amount'=>28],
            ['name'=>'G8','promotion_id'=>16,'student_amount'=>28],
            ['name'=>'G1','promotion_id'=>1,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>1,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>1,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>2,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>2,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>2,'student_amount'=>28],
            ['name'=>'G1','promotion_id'=>3,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>3,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>3,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>4,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>4,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>4,'student_amount'=>28],
            ['name'=>'G1','promotion_id'=>5,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>5,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>5,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>6,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>6,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>6,'student_amount'=>28],
            ['name'=>'G7','promotion_id'=>7,'student_amount'=>28],
            ['name'=>'G8','promotion_id'=>7,'student_amount'=>28],
            ['name'=>'G1','promotion_id'=>8,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>8,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>8,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>9,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>9,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>9,'student_amount'=>28],
            ['name'=>'G7','promotion_id'=>10,'student_amount'=>28],
            ['name'=>'G8','promotion_id'=>10,'student_amount'=>28],
            ['name'=>'G1','promotion_id'=>11,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>11,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>11,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>12,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>12,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>12,'student_amount'=>28],
            ['name'=>'G7','promotion_id'=>13,'student_amount'=>28],
            ['name'=>'G8','promotion_id'=>13,'student_amount'=>28],
            ['name'=>'G1','promotion_id'=>17,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>17,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>17,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>18,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>18,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>18,'student_amount'=>28],
            ['name'=>'G7','promotion_id'=>19,'student_amount'=>28],
            ['name'=>'G8','promotion_id'=>19,'student_amount'=>28],
            ['name'=>'G1','promotion_id'=>20,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>20,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>20,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>21,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>21,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>21,'student_amount'=>28],
            ['name'=>'G7','promotion_id'=>22,'student_amount'=>28],
            ['name'=>'G8','promotion_id'=>22,'student_amount'=>28],
            ['name'=>'G1','promotion_id'=>23,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>23,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>23,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>24,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>24,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>24,'student_amount'=>28],
            ['name'=>'G7','promotion_id'=>25,'student_amount'=>28],
            ['name'=>'G8','promotion_id'=>25,'student_amount'=>28],
            ['name'=>'G1','promotion_id'=>26,'student_amount'=>28],
            ['name'=>'G2','promotion_id'=>26,'student_amount'=>28],
            ['name'=>'G3','promotion_id'=>26,'student_amount'=>28],
            ['name'=>'G4','promotion_id'=>27,'student_amount'=>28],
            ['name'=>'G5','promotion_id'=>27,'student_amount'=>28],
            ['name'=>'G6','promotion_id'=>27,'student_amount'=>28],
            ['name'=>'G7','promotion_id'=>28,'student_amount'=>28],
            ['name'=>'G8','promotion_id'=>28,'student_amount'=>28],
        ];

        // Remove any rows with ids greater than 77 (these are duplicates/old)
        // as you requested to keep only ids 1..77.
        try {
            Group::where('id', '>', 77)->delete();
        } catch (\Exception $e) {
            // ignore deletion errors; continue with seeding
        }

        // Normalize rows to the fields our model expects and use updateOrCreate
        foreach ($rows as $r) {
            $data = [
                'name' => $r['name'],
                'promotion_id' => $r['promotion_id'],
                'student_amount' => $r['student_amount'],
            ];

            Group::updateOrCreate(
                ['name' => $data['name'], 'promotion_id' => $data['promotion_id']],
                $data
            );
        }
    }
}
