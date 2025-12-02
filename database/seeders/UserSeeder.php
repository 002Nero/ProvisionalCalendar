<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['username' => 'admin', 'first_name' => 'admin', 'last_name' => 'admin', 'email' => 'admin@example.com', 'password' => bcrypt('admin123'), 'role_id' => 1, 'acronym' => 'A'],
            ['username' => 'tmonediere', 'first_name' => 'Thierry', 'last_name' => 'Monediere', 'email' => 'thierry.monediere@unilim.fr', 'password' => bcrypt('monediere123'), 'role_id' => 2, 'acronym' => 'TM'],
            ['username' => 'thugel', 'first_name' => 'Thomas', 'last_name' => 'Hugel', 'email' => 'thomas.hugel@unilim.fr', 'password' => bcrypt('hugel123'), 'role_id' => 2, 'acronym' => 'TH'],
            ['username' => 'mconete', 'first_name' => 'Maria-Cristina', 'last_name' => 'Onete', 'email' => 'maria-cristina.onete@unilim.fr', 'password' => bcrypt('onete123'), 'role_id' => 2, 'acronym' => 'CO'],
            ['username' => 'ldubreuil', 'first_name' => 'Laurent', 'last_name' => 'Dubreuil', 'email' => 'laurent.dubreuil@unilim.fr', 'password' => bcrypt('dubreuil123'), 'role_id' => 2, 'acronym' => 'LD'],
            ['username' => 'nmerillou', 'first_name' => 'Nicolas', 'last_name' => 'Merillou', 'email' => 'nicolas.merillou@unilim.fr', 'password' => bcrypt('merillou123'), 'role_id' => 2, 'acronym' => 'NM'],
            ['username' => 'smerillou', 'first_name' => 'Stephane', 'last_name' => 'Merillou', 'email' => 'stephane.merillou@unilim.fr', 'password' => bcrypt('merillou123'), 'role_id' => 2, 'acronym' => 'SM'],
            ['username' => 'iblasquez', 'first_name' => 'Isabelle', 'last_name' => 'Blasquez', 'email' => 'isabelle.blasquez@unilim.fr', 'password' => bcrypt('blasquez123'), 'role_id' => 2, 'acronym' => 'IB'],
            ['username' => 'vbaulant', 'first_name' => 'Veronique', 'last_name' => 'Baulant', 'email' => 'veronique.baulant@ac-limoges.fr', 'password' => bcrypt('baulant123'), 'role_id' => 2, 'acronym' => 'VB'],
            ['username' => 'djmingo', 'first_name' => 'David-Jean', 'last_name' => 'Mingo', 'email' => 'davidjean.mingo@gmail.com', 'password' => bcrypt('mingo123'), 'role_id' => 2, 'acronym' => 'DM'],
            ['username' => 'alaurent', 'first_name' => 'Amaury', 'last_name' => 'Laurent', 'email' => 'amaury.laurent89@gmail.com', 'password' => bcrypt('laurent123'), 'role_id' => 2, 'acronym' => 'AL'],
            ['username' => 'apoursat', 'first_name' => 'Anais', 'last_name' => 'Poursat', 'email' => 'anais.poursat@unilim.fr', 'password' => bcrypt('poursat123'), 'role_id' => 2, 'acronym' => 'AP'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['email' => $u['email']], $u);
        }
    }
}
