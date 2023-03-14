<?php
namespace ViaAds\Integration\Block\Adminhtml\ApiKey\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Class SaveButton
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData()
    {
        return [
            'label' => __('Save API Key'),
            'class' => 'save primary',
            'sort_order' => 80,
                'data_attribute' => [
                    'mage-init' => [
                        'Magento_Ui/js/form/button-adapter' => [
                            'actions' => [
                                [
                                    'targetName' => 'your_ui_component_name.your_ui_component_name',
                                    'actionName' => 'save',
                                    'params' => [
                                        true,
                                    ]
                                ]
                            ]
                        ]
                    ],
                ]
        ];
    }
}