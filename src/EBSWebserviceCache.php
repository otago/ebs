<?php

namespace OP;

use SilverStripe\ORM\DataObject;

/**
 * Every X minutes the token gets regenerated. Speeds up consecutive requests.
 */
class EBSWebserviceCache extends DataObject
{

    private static $table_name = 'EBSWebserviceCache';
    private static $db = [
        'Name' => 'Text',
        'Token' => 'Text'
    ];
    
    /**
     * Deletes all EBSWebserviceCache tokens on dev build
     * @return void
     */
    public function requireDefaultRecords()
    {
        foreach (EBSWebserviceCache::get() as $EBSCache) {
            DB::alteration_message("Deleted Current token: " . $EBSCache->Name, "deleted");
            $EBSCache->delete();
        }
    }
}
