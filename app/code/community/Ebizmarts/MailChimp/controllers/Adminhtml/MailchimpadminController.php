<?php

/**
 * mc-magento Magento Component
 *
 * @category  Ebizmarts
 * @package   mc-magento
 * @author    Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @date:     7/23/18 3:27 PM
 * @file:     GetvaluesController.php
 */
class Ebizmarts_MailChimp_Adminhtml_MailchimpadminController extends Mage_Adminhtml_Controller_Action
{
    const USERNAME_KEY = 0;
    const TOTAL_ACCOUNT_SUB_KEY = 1;
    const TOTAL_LIST_SUB_KEY = 2;
    const STORENAME_KEY = 10;
    const SYNC_LABEL_KEY = 11;
    const TOTAL_CUS_KEY = 12;
    const TOTAL_PRO_KEY = 13;
    const TOTAL_ORD_KEY = 14;
    const TOTAL_QUO_KEY = 15;
    const NO_STORE_TEXT_KEY = 20;
    const NEW_STORE_TEXT_KEY = 21;
    const STORE_MIGRATION_TEXT_KEY = 30;

    protected function _isAllowed()
    {
        $acl = 'system/config/mailchimp';

        return Mage::getSingleton('admin/session')->isAllowed($acl);
    }
    public function getStoresAction()
    {
        $apiKey = $this->getRequest()->getParam('apikey');
        $helper = Mage::helper('mailchimp');
        $data = array();
        try {
            $api = $helper->getApiByKey($apiKey);
            $stores = $api->ecommerce->stores->get(null, null, null, 100);
            $data[] = ['id'=>'', 'name'=>'--- Select a MailChimp Store ---'];
            foreach ($stores['stores'] as $store) {
                if ($store['platform'] == 'Magento') {
                    if($store['list_id']=='') {
                        continue;
                    }
                    if(isset($store['connected_site'])) {
                        $label = $store['name'];
                    } else {
                        $label = $store['name'].' (Warning: not connected)';
                    }

                    $data[] = ['id'=> $store['id'], 'name' => $label];
                }
            }
        } catch(Exception $e) {
            $data = ['error'=>1];
        }
        $jsonData = json_encode($data);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($jsonData);
    }
    public function getListAction()
    {
        $apiKey = $this->getRequest()->getParam('apikey');
        $storeId = $this->getRequest()->getParam('storeid');
        $helper = Mage::helper('mailchimp');
        $data = "";
        try {
            $api = $helper->getApiByKey($apiKey);
            $store = $api->ecommerce->stores->get($storeId);
            $listId = $store['list_id'];
            $list = $api->lists->getLists($listId);
            $data=['id'=>$list['id'], 'name' => $list['name']];
        } catch (Exception $e) {

        }
        $jsonData = json_encode($data);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($jsonData);
    }
    public function getInfoAction()
    {
        $apiKey = $this->getRequest()->getParam('apikey');
        $storeId = $this->getRequest()->getParam('storeid');
        $helper = Mage::helper('mailchimp');
        $data = array();
        try {
            $api = $helper->getApiByKey($apiKey);
            $data = $api->root->info('account_name,total_subscribers');

            $storeData = $api->ecommerce->stores->get($storeId, 'name,is_syncing,list_id');

            $listData = $api->lists->getLists($storeData['list_id'], 'stats');

            $data['list_subscribers'] = $listData['stats']['member_count'];


            $data['store_exists'] = true;
            $data['store_name'] = $storeData['name'];
            //Keep both values for backward compatibility
            $data['store_sync_flag'] = $storeData['is_syncing'];
            $data['store_sync_date'] = $this->_getDateSync($storeId);
            $totalCustomers = $api->ecommerce->customers->getAll($storeId, 'total_items');
            $data['total_customers'] = $totalCustomers['total_items'];
            $totalProducts = $api->ecommerce->products->getAll($storeId, 'total_items');
            $data['total_products'] = $totalProducts['total_items'];
            $totalOrders = $api->ecommerce->orders->getAll($storeId, 'total_items');
            $data['total_orders'] = $totalOrders['total_items'];
            $totalCarts = $api->ecommerce->carts->getAll($storeId, 'total_items');
            $data['total_carts'] = $totalCarts['total_items'];


        } catch (Exception $e) {

        }

        $jsonData = json_encode($this->_makeValues($data,$helper));
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($jsonData);

    }
    protected function _makeValues($data, $helper)
    {
        $totalAccountSubscribersText = $helper->__('Total Account Subscribers:');
        $totalAccountSubscribers = $totalAccountSubscribersText . ' ' . $data['total_subscribers'];
        $totalListSubscribers = null;
        if (isset($data['list_subscribers'])) {
            $totalListSubscribersText = $helper->__('Total List Subscribers:');
            $totalListSubscribers = $totalListSubscribersText . ' ' . $data['list_subscribers'];
        }
        $username = $helper->__('Username:') . ' ' . $data['account_name'];
        $returnArray = array(
            array('value' => self::USERNAME_KEY, 'label' => $username),
            array('value' => self::TOTAL_ACCOUNT_SUB_KEY, 'label' => $totalAccountSubscribers)
        );
        if ($totalListSubscribers) {
            $returnArray[] = array('value' => self::TOTAL_LIST_SUB_KEY, 'label' => $totalListSubscribers);
        }
        if ($data['store_exists']) {
            $totalCustomersText = $helper->__('  Total Customers:');
            $totalCustomers = $totalCustomersText . ' ' . $data['total_customers'];
            $totalProductsText = $helper->__('  Total Products:');
            $totalProducts = $totalProductsText . ' ' . $data['total_products'];
            $totalOrdersText = $helper->__('  Total Orders:');
            $totalOrders = $totalOrdersText . ' ' . $data['total_orders'];
            $totalCartsText = $helper->__('  Total Carts:');
            $totalCarts = $totalCartsText . ' ' . $data['total_carts'];
            $title = $helper->__('Ecommerce Data uploaded to MailChimp store ' . $data['store_name'] . ':');
            if ($data['store_sync_flag'] && !$data['store_sync_date']) {
                $syncValue = 'In Progress';
            } else {
                $syncData = $data['store_sync_date'];
                if ($helper->validateDate($syncData)) {
                    $syncValue = $syncData;
                } else {
                    $syncValue = 'Finished';
                }
            }
            $syncLabel = $helper->__('Initial sync: ' . $syncValue);

            $returnArray = array_merge(
                $returnArray,
                array(
                    array('value' => self::STORENAME_KEY, 'label' => $title),
                    array('value' => self::SYNC_LABEL_KEY, 'label' => $syncLabel),
                    array('value' => self::TOTAL_CUS_KEY, 'label' => $totalCustomers),
                    array('value' => self::TOTAL_PRO_KEY, 'label' => $totalProducts),
                    array('value' => self::TOTAL_ORD_KEY, 'label' => $totalOrders),
                    array('value' => self::TOTAL_QUO_KEY, 'label' => $totalCarts)
                )
            );

        }
        return $returnArray;
    }

    protected function _makeHelper()
    {
        return Mage::helper('mailchimp');
    }

    protected function _getDateSync($mailchimpStoreId)
    {
        return $this->_makeHelper()->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::ECOMMERCE_SYNC_DATE . "_$mailchimpStoreId", 0, 'default');
    }

}