{**
 * @author    АО Райффайзенбанк <ecom@raiffeisen.ru>
 * @copyright 2007 АО Райффайзенбанк
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt The GNU General Public License version 2 (GPLv2)
 *}
{extends "$layout"}
{block name='content'}
  <section>
    <p>{l mod='raifpay' s='You have successfully submitted your payment form.'}</p>
    <p>{l mod='raifpay' s='Here are the params:'}</p>
    <ul>
      {foreach from=$params key=name item=value}
        <li>{$name}: {$value}</li>
      {/foreach}
    </ul>
    <p>{l mod='raifpay' s="Now, you just need to proceed the payment and do what you need to do."}</p>
  </section>
{/block}
