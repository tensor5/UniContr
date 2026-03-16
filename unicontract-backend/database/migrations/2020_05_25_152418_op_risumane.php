<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\MappingRuolo;
use App\Role;
use App\Personale;
use Illuminate\Support\Facades\DB;

class OpRisumane extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $role = Role::where('name','op_risumane')->first();
        if ($role==null){
            $role = Role::create(['name' => 'op_risumane']);
            $this->insertOffice(['005145'], 'op_risumane');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }

    private function insertOffice(Array $offices, $rolename){
        $role = Role::where('name', $rolename)->first();
        $useOracle = !app()->environment('testing') && $this->oracleConnected();

        foreach ($offices as $office) {
            $mp = new MappingRuolo();
            $mp->unitaorganizzativa_uo = $office;
            $mp->descrizione_uo = "Fake descr for $office";

            if ($useOracle) {
                try {
                    $uo = $mp->unitaorganizzativa()->first();
                    if ($uo && !empty($uo->descr)) {
                        $mp->descrizione_uo = $uo->descr;
                    }
                } catch (\Exception $e) {
                    // Keep the fake description when Oracle is unavailable.
                }
            }

            $mp->role_id = $role->id;
            $mp->save();
        }       
    }

    
    /**
     * Check if Oracle connection is alive
     */
    private function oracleConnected(): bool
    {
        try {
            DB::connection('oracle')->getPdo();
            return true;
        } catch (\Exception $e) {
            // Could not connect
            return false;
        }
    }
}
