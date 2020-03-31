<?php
/**
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Salesfire extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'salesfire';
        $this->tab = 'smart_shopping';
        $this->version = '0.1.0';
        $this->author = 'Salesfire';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Salesfire');
        $this->description = $this->l('Salesfire is a service that provides a number of tools that help to increase sales using various on site methods.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Salesfire');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('SALESFIRE_ACTIVE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('orderConfirmation');
    }

    public function uninstall()
    {
        Configuration::deleteByName('SALESFIRE_ACTIVE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitSalesfireModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSalesfireModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Active'),
                        'name' => 'SALESFIRE_ACTIVE',
                        'is_bool' => true,
                        'desc' => $this->l('Activate Salesfire'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Enter your Site ID (This can be found within your Salesfire Dashboard)'),
                        'name' => 'SALESFIRE_SITE_ID',
                        'label' => $this->l('Site ID'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'SALESFIRE_ACTIVE' => Configuration::get('SALESFIRE_ACTIVE', true),
            'SALESFIRE_SITE_ID' => Configuration::get('SALESFIRE_SITE_ID', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $smarty_variables = array(
            'sfSiteId' => Tools::safeOutput(Configuration::get('SALESFIRE_SITE_ID')),
        );

        $this->smarty->assign($smarty_variables);

        $display = $this->display(__FILE__, 'salesfire.tpl');

        if (method_exists($this->context->controller, 'getProduct')) {
            $product = $this->context->controller->getProduct();

            $smarty_variables['sfProduct'] = array(
                'sku' => $product->reference,
                'name' => $product->name,
                'price' => $product->price
            );

            $this->smarty->assign($smarty_variables);

            $display .= $this->display(__FILE__, 'product-views.tpl');
        }

        return $display;
    }

    public function hookOrderConfirmation($params)
    {
        $order = $params['order'];
        $currency = Currency::getCurrencyInstance((int) $order->id_currency);

        $sfOrder = array(
            'ecommerce' => array(
                'purchase' => array(
                    'id' => $order->id,
                    'revenue' => $order->total_paid_tax_excl,
                    'shipping' => $order->total_shipping,
                    'tax' => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
                    'currency' => $currency->iso_code,
                    'products' => array()
                )
            )
        );

        foreach ($order->getProducts() as $product) {
            $sfOrder['ecommerce']['purchase']['products'][] = array(
                'sku' => $product['reference'],
                'parent_sku' => $product['reference'],
                'name' => $product['product_name'],
                'price' => $product['total_price'],
                'currency' => $currency->iso_code
            );
        }

        $this->smarty->assign(array(
            'sfOrder' => $sfOrder
        ));

        return $this->display(__FILE__, 'order-success.tpl');
    }
}
