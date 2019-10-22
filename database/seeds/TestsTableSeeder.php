<?php

use App\Models\Test;
use Illuminate\Database\Seeder;

class TestsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // for ($i=1; $i<=10; $i++) {
        //     Test::create([
        //         'num' => $i,
        //         'hoge' => 'テスト' . $i,
        //     ]);
        // }
        factory(Test::class, 10)->create();
    }
}
