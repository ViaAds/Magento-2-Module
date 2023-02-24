<?php

namespace ViaAds\Integration\Block\Adminhtml;

class Post extends \Magento\Backend\Block\Widget\Grid\Container
{
    /**
     * constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_controller = 'adminhtml_datasync';
        $this->_blockGroup = 'ViaAds_DataSync';
        $this->_headerText = __('Data Sync');
        $this->_addButtonLabel = __('Synchronize Data to ViaAds');
        parent::_construct();
    }
}
