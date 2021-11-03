<?php
/**
 * Class OPEBSSiteConfig
 * @package OP
 * @author Alastair Nicholl <alastair.nicholl@op.ac.nz>
 */

namespace OP;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataExtension;

class OPEBSSiteConfig extends DataExtension
{
    private static $db = [
        'DisableEBSConnectivity' => "Boolean",
    ];

    /**
     * Update CMS Fields
     * @param FieldList $fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab(
            'Root.Main',
            CheckboxField::create('DisableEBSConnectivity', 'Diable Connection to EBS')
                ->setDescription('Disable the ability to connect to EBS, for use in the case of upgrades or maintenance')
        );
    }
}
