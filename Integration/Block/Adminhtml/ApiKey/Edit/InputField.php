<?php
namespace ViaAds\Integration\Block\Adminhtml\ApiKey\Edit;

/**
 * Class SaveButton
 */
class InputField
{
    /**
     * @return array
     */
    public function getInputData()
    {
        return [
            "label"     => __("Dependent Field"),
            "class"     => "required-entry",
            "required"  => true,
            "name"      => "dependent_field"
        ];
    }
}
