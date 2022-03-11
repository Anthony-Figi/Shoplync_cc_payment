{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}

{if $status == 'ok'}
<h3>{l s='Credit Card Payment:' d='Modules.Checkpayment.Shop'}</h3>


<p>We will charge your credit card on file a total of <span class="price"><strong>{$total_to_pay}</strong></span> for the balance of your order. If we do not have a credit card on file for you we will contact you for card details.</p>
<p>Please contact us at <a id="debitEmail" href="tel:1.604.325.2252"><strong>604-325-2252</strong></a> if your card number has changed or not on file. </p>

<br>		
<p><strong>{l s='Your order will be processed as soon as we confirm your credit card payment.' d='Modules.Checkpayment.Shop'}</strong></p>
		
		<p>{l s='For any questions or for further information, please contact our' d='Modules.Checkpayment.Shop'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' d='Modules.Checkpayment.Shop'}</a></p>
{else}
	<p class="warning">
		{l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' d='Modules.Checkpayment.Shop'}
		<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' d='Modules.Checkpayment.Shop'}</a>.
	</p>
{/if}