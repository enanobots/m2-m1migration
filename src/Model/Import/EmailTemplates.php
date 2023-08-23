<?php
/**
 * Copyright © Q-Solutions Studio: eCommerce Nanobots. All rights reserved.
 *
 * @category    Nanobots
 * @package     Nanobots_MigrationTool
 * @author      Jakub Winkler <jwinkler@qsolutionsstudio.com
 * @author      Sebastian Strojwas <sebastian@qsolutionsstudio.com>
 * @author      Wojtek Wnuk <wojtek@qsolutionsstudio.com>
 * @author      Lukasz Owczarczuk <lukasz@qsolutionsstudio.com>
 */

namespace Nanobots\MigrationTool\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class EmailTemplates extends TableImportAbstract
{
    protected const TEXT_COLUMN = 'template_text';

    /**
     * @param $m1Entity
     * @param array $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns = []) : array
    {
        $m2Entity = $m1Entity;
        foreach ($m2Entity as $columnName => $value) {
            if ($columnName == self::TEXT_COLUMN) {
                $value = str_replace(
                    "var order.getCreatedAtFormated('long')",
                    'var created_at_formatted',
                    $value
                );
                $value = str_replace(
                    "var order.getShippingAddress().format('html')",
                    'var formattedShippingAddress|raw',
                    $value
                );
                $value = str_replace(
                    "depend order.getIsNotVirtual()",
                    'depend order_data.is_not_virtual',
                    $value
                );
                $value = str_replace(
                    'var payment_html',
                    'var payment_html|raw',
                    $value
                );
                $value = str_replace(
                    'layout handle="sales_email_order_myshipment" order=$order',
                    'var order.shipping_description',
                    $value
                );
                $value = str_replace(
                    'layout handle="sales_email_order_items" order=$order',
                    'layout handle="sales_email_order_items" order_id=$order_id area="frontend"',
                    $value
                );
                $value = str_replace(
                    "depend order.getFirecheckoutCustomerComment()",
                    'depend firecheckout_comment',
                    $value
                );
                $value = str_replace(
                    'htmlescape var=$order.getFirecheckoutCustomerComment()',
                    'var firecheckout_comment',
                    $value
                );
                $value = str_replace(
                    'store url="customer/account/resetpassword/" _query_id=$customer.id _query_token=$customer.rp_token',
                    'var this.getUrl($store,"customer/account/createPassword",[_query:[id:$customer.id,token:$customer.rp_token],_nosid:1])',
                    $value
                );
                $m2Entity[$columnName] = $value;
            }
        }
        return $m2Entity;
    }

    /**
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function syncEmailTemplates() : void
    {
        $this->syncData('core_email_template', 'email_template');
    }
}
