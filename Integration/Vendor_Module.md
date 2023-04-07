# ViaAds_Integration

ViaAds_Integration is a Magento 2 extension that provides synchronization functionality for products and orders from Magento to ViaAds.

## Installation

Please follow these steps to install the extension:

1. Install the module using composer:

```bash
composer require ViaAds/Integration
```
2. Enable the module:

```bash
php bin/magento module:enable ViaAds_Integration
php bin/magento setup:upgrade
php bin/magento cache:clean
```
3. Configure the module:
- Access the Configuration Page: Once the extension is installed, go to the configuration page in your Magento admin panel by selecting "ViaAds" from the main menu, and then selecting "Data Synchronization" from the submenu.
- Enter API Key: Press "Enter New Key" on the configuration page, which will take you to a sub-page with an input field. Insert the API Key provided by ViaAds in the designated field. Please ensure that the API Key is entered correctly for synchronization to be activated.
- Save Configuration: Click the "Save" button to save the configuration settings.

## Cronjobs
The module includes cronjobs for automatic synchronization of products and orders. The cronjob schedules are as follows:

Products synchronization: Runs daily at 1:00 AM server time. Cron expression: 0 1 * * *
Orders synchronization: Runs daily at 2:00 AM server time. Cron expression: 0 2 * * *
Make sure that your Magento installation has the necessary cron setup to trigger these cronjobs.

## Documentation
Detailed documentation for the ViaAds_Integration extension can be found in the "docs" folder of the installed module. The documentation includes installation instructions, configuration guides, and usage documentation.

You can access the documentation files after installing the module by navigating to the following path within your Magento installation:

<magento_root>/vendor/ViaAds/Integration/docs/

## Support
For any issues or questions, please contact our support team at support@viaads.dk or visit our website at https://viaads.dk/support.

## Contributing
We welcome contributions from the community! If you have any bug reports, feature requests, or improvements, please create a pull request or open an issue on the GitHub repository at https://github.com/ViaAds/Magento-2-Module.

## License
ViaAds_Integration is released under the MIT License.