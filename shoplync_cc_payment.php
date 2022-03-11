<?php
/**
* 2007-2022 PrestaShop
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
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Shoplync_cc_payment extends PaymentModule
{
    private $_html = '';
    private $_postErrors = [];

    public function __construct()
    {
        $this->name = 'shoplync_cc_payment';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Shopylync';
        $this->controllers = ['payment', 'validation'];

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';


        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Use Credit Card On File', [], 'Modules.Checkpayment.Admin');
        $this->description = $this->trans('This module allows you to accept payments by credit cards saved on file.', [], 'Modules.Checkpayment.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to delete these details?', [], 'Modules.Checkpayment.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];


        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans('No currency has been set for this module.', [], 'Modules.Checkpayment.Admin');
        }
    }

    public function install()
    {
        $state_id = $this->addOrderState('Awaiting CC On File Payment');
        Configuration::updateValue('SHOPLYNC_OS_DEFAULT', $state_id);
        
        return parent::install() 
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
        ;
    }

    public function uninstall()
    {
        return Configuration::deleteByName('SHOPLYNC_OS_DEFAULT')
            && parent::uninstall()
        ;
    }
    
    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('SHOPLYNC_OS_DEFAULT', Tools::getValue('SHOPLYNC_OS_DEFAULT'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', [], 'Admin.Notifications.Success'));
    }

    private function _displayCheck()
    {
        return $this->display(__FILE__, './views/templates/hook/infos.tpl');
    }

    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $this->_html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        $this->_html .= $this->_displayCheck();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function addOrderState($name)
    {
        $states = OrderState::getOrderStates((int)$this->context->language->id);
        $order_state = null;
        // check if order state exist
        foreach ($states as $state) {
            if (in_array($name, $state)) {
                $order_state = $state;
                break;
            }
        }
 
        // If the state does not exist, we create it.
        if ($order_state == null) {
            // create new order state
            $order_state = new OrderState();
            $order_state->color = '#0094d4';
            $order_state->send_email = true;
            $order_state->invoice = true;
            $order_state->module_name = $this->name;
            $order_state->template = 'cheque';
            $order_state->name = array();
            $languages = Language::getLanguages(false);
            foreach ($languages as $language)
                $order_state->name[ $language['id_lang'] ] = $name;
 
            // Update object
            $order_state->add();
        }
 
        return (is_array($order_state) ? $order_state['id_order_state'] : $order_state->id);
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            //$this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(
            $this->getTemplateVars()
        );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->trans('Use Credit Card On File', [], 'Modules.Checkpayment.Admin'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
                ->setAdditionalInformation($this->fetch('module:shoplync_cc_payment/views/templates/front/payment_infos.tpl'));

        return [$newOption];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();
        $rest_to_paid = $params['order']->getOrdersTotalPaid() - $params['order']->getTotalPaid();
        if (in_array($state, [Configuration::get('SHOPLYNC_OS_DEFAULT'), Configuration::get('PS_OS_CHEQUE'), Configuration::get('PS_OS_OUTOFSTOCK'), Configuration::get('PS_OS_OUTOFSTOCK_UNPAID')])) {
            $this->smarty->assign([
                'total_to_pay' => Tools::displayPrice(
                    $rest_to_paid,
                    new Currency($params['order']->id_currency),
                    false
                ),
                'shop_name' => $this->context->shop->name,
                'status' => 'ok',
                'id_order' => $params['order']->id,
            ]);
            if (isset($params['order']->reference) && !empty($params['order']->reference)) {
                $this->smarty->assign('reference', $params['order']->reference);
            }
        } else {
            $this->smarty->assign('status', 'failed');
        }

        return $this->fetch('module:shoplync_cc_payment/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int) ($cart->id_currency));
        $currencies_module = $this->getCurrency((int) $cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                'title' => $this->l(''),
                //'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => 'Select A Payment Status',
                        'name' => 'SHOPLYNC_OS_DEFAULT',
                        'class' => 'chosen',
                        'options' => array(
                            'optiongroup'=>array(
                                'label'=>'label',
                                'query'=>array(
                                    array(
                                        'label'=>'-',
                                        'options'=> OrderState::getOrderStates((int)$this->context->language->id),
                                    )
                                ),
                            ),
                            'options'=>array(
                                 'query'=>'options',
                                 'id'=>'id_order_state',
                                 'name'=>'name'
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
        

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigFieldsValues()
    {
        return [
            'SHOPLYNC_OS_DEFAULT' => Tools::getValue('SHOPLYNC_OS_DEFAULT', Configuration::get('SHOPLYNC_OS_DEFAULT')),
        ];
    }

    public function getTemplateVars()
    {
        $cart = $this->context->cart;
        $total = $this->trans(
            '%amount% (tax incl.)',
            [
                '%amount%' => Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH)),
            ],
            'Modules.Checkpayment.Admin'
        );

        return [
            'checkTotal' => $total,
        ];
    }
}
