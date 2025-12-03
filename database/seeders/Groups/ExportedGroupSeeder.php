<?php

namespace Database\Seeders\Groups;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExportedGroupSeeder extends Seeder
{
    public function run()
    {
        $rows = [
            ['id'=>1,'name'=>'G1','promotion_id'=>14,'student_amount'=>28,'created_at'=>'2025-12-03 08:06:52','updated_at'=>'2025-12-03 08:06:52'],
            ['id'=>2,'name'=>'G2','promotion_id'=>14,'student_amount'=>28,'created_at'=>'2025-12-03 08:06:52','updated_at'=>'2025-12-03 08:06:52'],
            ['id'=>3,'name'=>'G3','promotion_id'=>14,'student_amount'=>28,'created_at'=>'2025-12-03 08:06:52','updated_at'=>'2025-12-03 08:06:52'],
            ['id'=>4,'name'=>'G4','promotion_id'=>15,'student_amount'=>28,'created_at'=>'2025-12-03 08:06:52','updated_at'=>'2025-12-03 08:06:52'],
            ['id'=>5,'name'=>'G5','promotion_id'=>15,'student_amount'=>28,'created_at'=>'2025-12-03 08:06:52','updated_at'=>'2025-12-03 08:06:52'],
            ['id'=>6,'name'=>'G6','promotion_id'=>15,'student_amount'=>28,'created_at'=>'2025-12-03 08:06:52','updated_at'=>'2025-12-03 08:06:52'],
            ['id'=>7,'name'=>'G7','promotion_id'=>16,'student_amount'=>28,'created_at'=>'2025-12-03 08:06:52','updated_at'=>'2025-12-03 08:06:52'],
            ['id'=>8,'name'=>'G8','promotion_id'=>16,'student_amount'=>28,'created_at'=>'2025-12-03 08:06:52','updated_at'=>'2025-12-03 08:06:52'],
            ['id'=>10,'name'=>'G1','promotion_id'=>1,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:01','updated_at'=>'2025-12-03 09:25:01'],
            ['id'=>11,'name'=>'G2','promotion_id'=>1,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:06','updated_at'=>'2025-12-03 09:25:06'],
            ['id'=>12,'name'=>'G3','promotion_id'=>1,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:10','updated_at'=>'2025-12-03 09:25:10'],
            ['id'=>13,'name'=>'G4','promotion_id'=>2,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:16','updated_at'=>'2025-12-03 09:25:16'],
            ['id'=>14,'name'=>'G5','promotion_id'=>2,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:20','updated_at'=>'2025-12-03 09:25:20'],
            ['id'=>15,'name'=>'G6','promotion_id'=>2,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:28','updated_at'=>'2025-12-03 09:25:28'],
            ['id'=>16,'name'=>'G1','promotion_id'=>3,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:39','updated_at'=>'2025-12-03 09:25:39'],
            ['id'=>17,'name'=>'G2','promotion_id'=>3,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:42','updated_at'=>'2025-12-03 09:25:42'],
            ['id'=>18,'name'=>'G3','promotion_id'=>3,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:46','updated_at'=>'2025-12-03 09:25:46'],
            ['id'=>19,'name'=>'G4','promotion_id'=>4,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:53','updated_at'=>'2025-12-03 09:25:53'],
            ['id'=>20,'name'=>'G5','promotion_id'=>4,'student_amount'=>28,'created_at'=>'2025-12-03 09:25:58','updated_at'=>'2025-12-03 09:25:58'],
            ['id'=>21,'name'=>'G6','promotion_id'=>4,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:01','updated_at'=>'2025-12-03 09:26:01'],
            ['id'=>22,'name'=>'G1','promotion_id'=>5,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:10','updated_at'=>'2025-12-03 09:26:10'],
            ['id'=>23,'name'=>'G2','promotion_id'=>5,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:14','updated_at'=>'2025-12-03 09:26:14'],
            ['id'=>24,'name'=>'G3','promotion_id'=>5,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:17','updated_at'=>'2025-12-03 09:26:17'],
            ['id'=>25,'name'=>'G4','promotion_id'=>6,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:23','updated_at'=>'2025-12-03 09:26:23'],
            ['id'=>26,'name'=>'G5','promotion_id'=>6,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:26','updated_at'=>'2025-12-03 09:26:26'],
            ['id'=>27,'name'=>'G6','promotion_id'=>6,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:30','updated_at'=>'2025-12-03 09:26:30'],
            ['id'=>28,'name'=>'G7','promotion_id'=>7,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:34','updated_at'=>'2025-12-03 09:26:34'],
            ['id'=>29,'name'=>'G8','promotion_id'=>7,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:37','updated_at'=>'2025-12-03 09:26:37'],
            ['id'=>30,'name'=>'G1','promotion_id'=>8,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:45','updated_at'=>'2025-12-03 09:26:45'],
            ['id'=>31,'name'=>'G2','promotion_id'=>8,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:48','updated_at'=>'2025-12-03 09:26:48'],
            ['id'=>32,'name'=>'G3','promotion_id'=>8,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:51','updated_at'=>'2025-12-03 09:26:51'],
            ['id'=>33,'name'=>'G4','promotion_id'=>9,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:55','updated_at'=>'2025-12-03 09:26:55'],
            ['id'=>34,'name'=>'G5','promotion_id'=>9,'student_amount'=>28,'created_at'=>'2025-12-03 09:26:57','updated_at'=>'2025-12-03 09:26:57'],
            ['id'=>35,'name'=>'G6','promotion_id'=>9,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:00','updated_at'=>'2025-12-03 09:27:00'],
            ['id'=>36,'name'=>'G7','promotion_id'=>10,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:05','updated_at'=>'2025-12-03 09:27:05'],
            ['id'=>37,'name'=>'G8','promotion_id'=>10,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:08','updated_at'=>'2025-12-03 09:27:08'],
            ['id'=>38,'name'=>'G1','promotion_id'=>11,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:14','updated_at'=>'2025-12-03 09:27:14'],
            ['id'=>39,'name'=>'G2','promotion_id'=>11,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:17','updated_at'=>'2025-12-03 09:27:17'],
            ['id'=>40,'name'=>'G3','promotion_id'=>11,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:20','updated_at'=>'2025-12-03 09:27:20'],
            ['id'=>41,'name'=>'G4','promotion_id'=>12,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:24','updated_at'=>'2025-12-03 09:27:24'],
            ['id'=>42,'name'=>'G5','promotion_id'=>12,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:28','updated_at'=>'2025-12-03 09:27:28'],
            ['id'=>43,'name'=>'G6','promotion_id'=>12,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:31','updated_at'=>'2025-12-03 09:27:31'],
            ['id'=>44,'name'=>'G7','promotion_id'=>13,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:35','updated_at'=>'2025-12-03 09:27:35'],
            ['id'=>45,'name'=>'G8','promotion_id'=>13,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:39','updated_at'=>'2025-12-03 09:27:39'],
            ['id'=>46,'name'=>'G1','promotion_id'=>17,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:48','updated_at'=>'2025-12-03 09:27:48'],
            ['id'=>47,'name'=>'G2','promotion_id'=>17,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:51','updated_at'=>'2025-12-03 09:27:51'],
            ['id'=>48,'name'=>'G3','promotion_id'=>17,'student_amount'=>28,'created_at'=>'2025-12-03 09:27:55','updated_at'=>'2025-12-03 09:27:55'],
            ['id'=>49,'name'=>'G4','promotion_id'=>18,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:01','updated_at'=>'2025-12-03 09:28:01'],
            ['id'=>50,'name'=>'G5','promotion_id'=>18,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:04','updated_at'=>'2025-12-03 09:28:04'],
            ['id'=>51,'name'=>'G6','promotion_id'=>18,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:08','updated_at'=>'2025-12-03 09:28:08'],
            ['id'=>52,'name'=>'G7','promotion_id'=>19,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:12','updated_at'=>'2025-12-03 09:28:12'],
            ['id'=>53,'name'=>'G8','promotion_id'=>19,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:15','updated_at'=>'2025-12-03 09:28:15'],
            ['id'=>54,'name'=>'G1','promotion_id'=>20,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:23','updated_at'=>'2025-12-03 09:28:23'],
            ['id'=>55,'name'=>'G2','promotion_id'=>20,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:26','updated_at'=>'2025-12-03 09:28:26'],
            ['id'=>56,'name'=>'G3','promotion_id'=>20,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:29','updated_at'=>'2025-12-03 09:28:29'],
            ['id'=>57,'name'=>'G4','promotion_id'=>21,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:33','updated_at'=>'2025-12-03 09:28:33'],
            ['id'=>58,'name'=>'G5','promotion_id'=>21,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:36','updated_at'=>'2025-12-03 09:28:36'],
            ['id'=>59,'name'=>'G6','promotion_id'=>21,'student_amount'=>28,'created_at'=>'2025-12-03 09:28:42','updated_at'=>'2025-12-03 09:28:42'],
            ['id'=>60,'name'=>'G7','promotion_id'=>22,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:06','updated_at'=>'2025-12-03 09:30:06'],
            ['id'=>61,'name'=>'G8','promotion_id'=>22,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:09','updated_at'=>'2025-12-03 09:30:09'],
            ['id'=>62,'name'=>'G1','promotion_id'=>23,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:14','updated_at'=>'2025-12-03 09:30:14'],
            ['id'=>63,'name'=>'G2','promotion_id'=>23,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:17','updated_at'=>'2025-12-03 09:30:17'],
            ['id'=>64,'name'=>'G3','promotion_id'=>23,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:20','updated_at'=>'2025-12-03 09:30:20'],
            ['id'=>65,'name'=>'G4','promotion_id'=>24,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:25','updated_at'=>'2025-12-03 09:30:25'],
            ['id'=>66,'name'=>'G5','promotion_id'=>24,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:29','updated_at'=>'2025-12-03 09:30:29'],
            ['id'=>67,'name'=>'G6','promotion_id'=>24,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:32','updated_at'=>'2025-12-03 09:30:32'],
            ['id'=>68,'name'=>'G7','promotion_id'=>25,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:36','updated_at'=>'2025-12-03 09:30:36'],
            ['id'=>69,'name'=>'G8','promotion_id'=>25,'student_amount'=>28,'created_at'=>'2025-12-03 09:30:40','updated_at'=>'2025-12-03 09:30:40'],
            ['id'=>70,'name'=>'G1','promotion_id'=>26,'student_amount'=>28,'created_at'=>'2025-12-03 09:31:02','updated_at'=>'2025-12-03 09:31:02'],
            ['id'=>71,'name'=>'G2','promotion_id'=>26,'student_amount'=>28,'created_at'=>'2025-12-03 09:31:06','updated_at'=>'2025-12-03 09:31:06'],
            ['id'=>72,'name'=>'G3','promotion_id'=>26,'student_amount'=>28,'created_at'=>'2025-12-03 09:31:09','updated_at'=>'2025-12-03 09:31:09'],
            ['id'=>73,'name'=>'G4','promotion_id'=>27,'student_amount'=>28,'created_at'=>'2025-12-03 09:31:14','updated_at'=>'2025-12-03 09:31:14'],
            ['id'=>74,'name'=>'G5','promotion_id'=>27,'student_amount'=>28,'created_at'=>'2025-12-03 09:31:17','updated_at'=>'2025-12-03 09:31:17'],
            ['id'=>75,'name'=>'G6','promotion_id'=>27,'student_amount'=>28,'created_at'=>'2025-12-03 09:31:20','updated_at'=>'2025-12-03 09:31:20'],
            ['id'=>76,'name'=>'G7','promotion_id'=>28,'student_amount'=>28,'created_at'=>'2025-12-03 09:31:23','updated_at'=>'2025-12-03 09:31:23'],
            ['id'=>77,'name'=>'G8','promotion_id'=>28,'student_amount'=>28,'created_at'=>'2025-12-03 09:31:27','updated_at'=>'2025-12-03 09:31:27'],
        ];

        // Insert preserving provided IDs/timestamps. If you prefer a fresh insert, truncate first.
        DB::table('groups')->insert($rows);
    }
}
