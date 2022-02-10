<?php

class AdminOmnivaOrdersController extends ModuleAdminController
{
    private $_carriers = '';

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addJS('modules/' . $this->module->name . '/views/js/omniva-orders.js');
        Media::addJsDef([
            'check_orders' => $this->module->l('Check orders'),
            'carrier_cal_url' => $this->context->link->getAdminLink('AdminOmnivaOrders') . '&callCourier=1',
            'finished_trans' => $this->module->l('Finished.'),
            'message_sent_trans' => $this->module->l('Message successfully sent.'),
            'incorrect_response_trans' => $this->module->l('Incorrect response.'),
            'ajaxCall' => $this->context->link->getAdminLink('AdminOmnivaOrders') . '&ajax',
            'orderLink' => $this->context->link->getAdminLink('AdminOrders') . '&vieworder',
            'labelsLink' => $this->context->link->getAdminLink("AdminOmnivaOrders", true, [], array("action" => "bulklabels")),
            'bulkLabelsLink' => $this->context->link->getAdminLink(OmnivaltShipping::CONTROLLER_OMNIVA_AJAX, true, [], ["action" => "bulkPrintLabels"]),
            'labels_trans' => $this->module->l('Labels'),
            'not_found_trans' => $this->module->l('Nothing found'),
        ]);
    }

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();

        $this->_carriers = $this->getCarrierIds();
        if (Tools::getValue('orderSkip') != null) {
            $this->skipOrder();
            exit();
        } else if (Tools::getValue('cancelSkip') != null) {
            $this->cancelSkip();
            exit();
        } else if (Tools::getValue('callCourier')) {
            $this->callCarrier();
            exit();
        }
    }

    private function getCarrierIds()
    {
        return implode(',', OmnivaltShipping::getCarrierIds());
    }

    public function callcarrier()
    {
        $callCarrierReturn = $this->module->call_omniva();
        if ($callCarrierReturn['status'] == true)
            echo 'got_request';
        else
            echo 'got_request_false';
    }

    public function displayAjax()
    {
        $customer = Tools::getValue('customer');
        $tracking = Tools::getValue('tracking_nr');
        $date = Tools::getValue('input-date-added');
        $where = '';

        if ($tracking != '' and $tracking != null and $tracking != 'undefined')
            $where .= ' AND oc.tracking_number LIKE "%' . $tracking . '%" ';

        if ($customer != '' and $customer != null and $customer != 'undefined')
            $where .= ' AND CONCAT(oh.firstname, " ",oh.lastname) LIKE "%' . $customer . '%" ';

        if ($date != null and $date != 'undefined' and $date != '')
            $where .= ' AND oc.date_add LIKE "%' . $date . '%" ';


        if ($where == '')
            die(Tools::jsonEncode(array(array())));


        $orders = "SELECT a.id_order, oc.date_add, a.date_upd, a.total_paid_tax_incl, CONCAT(oh.firstname, '',oh.lastname) as full_name, oc.tracking_number  FROM " . _DB_PREFIX_ . "orders a
			INNER JOIN " . _DB_PREFIX_ . "customer oh ON a.id_customer = oh.id_customer
			LEFT JOIN " . _DB_PREFIX_ . "order_carrier oc ON a.id_order = oc.id_order
			 JOIN " . _DB_PREFIX_ . "cart k ON a.id_cart = k.id_cart AND k.id_carrier IN (" . $this->_carriers . ")
			 Where oc.tracking_number IS NOT NULL AND oc.tracking_number <>'' " . $where . " 
			 ORDER BY k.omnivalt_manifest DESC, a.id_order DESC
			LIMIT 20";

        $searchResponse = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($orders);
        die(Tools::jsonEncode($searchResponse));
    }

    public function initContent()
    {
        parent::initContent();

        $ordersCount = $this->ordersNumb();
        $perPage = 10;
        $pagesToShow = intval(ceil($ordersCount / $perPage));
        $page = 1;
        if (Tools::getValue('p') && Tools::getValue('p') != null)
            $page = intval(Tools::getValue('p'));
        if ($page <= 0 || $page > $pagesToShow)
            $page = 1;

        if ($pagesToShow <= 5) {
            $endGroup = $pagesToShow;
        } else {
            if ($pagesToShow - $page > 2) {
                $endGroup = $page + 2;
            } else {
                $endGroup = $pagesToShow;
            }
        }
        if ($endGroup - 4 > 0) {
            $startGroup = $endGroup - 4;
        } else {
            $startGroup = 1;
        }

        $this->context->smarty->assign(array(
            'content2' => 'dsdd',
            'orders' => $this->getOrders($page - 1, $perPage, $ordersCount),

            'sender' => Configuration::get('omnivalt_company'),
            'phone' => Configuration::get('omnivalt_phone'),
            'postcode' => Configuration::get('omnivalt_postcode'),
            'address' => Configuration::get('omnivalt_address'),

            'skippedOrders' => $this->getSkippedOrders(),
            'newOrders' => $this->getNewOrders(),
            'orderLink' => $this->context->link->getAdminLink('AdminOrders') . '&vieworder',
            'orderSkip' => $this->context->link->getAdminLink('AdminOmnivaOrders') . '&orderSkip=',
            'cancelSkip' => $this->context->link->getAdminLink('AdminOmnivaOrders') . '&cancelSkip=',
            'page' => $page,
            'manifestLink' => $this->context->link->getAdminLink("AdminOmnivaOrders", true, [], array("action" => "bulkmanifests")),
            'labelsLink' => $this->context->link->getAdminLink(OmnivaltShipping::CONTROLLER_OMNIVA_AJAX, true, [], ["action" => "printLabels"]),
            'bulkLabelsLink' => $this->context->link->getAdminLink(OmnivaltShipping::CONTROLLER_OMNIVA_AJAX, true, [], ["action" => "bulkPrintLabels"]),

            'manifestAll' => $this->context->link->getAdminLink("AdminOmnivaOrders", true, [], array("action" => "bulkmanifestsall")),
            'manifestNum' => strval(Configuration::get('omnivalt_manifest')),
            'total' => $this->_listTotal,

            'nb_products' => $ordersCount,
            'products_per_page' => $perPage,
            'pages_nb' => $pagesToShow,
            'prev_p' => (int)$page != 1 ? $page - 1 : 1,
            'next_p' => (int)$page + 1 > $pagesToShow ? $pagesToShow : $page + 1,
            'requestPage' => $this->context->link->getAdminLink('AdminOmnivaOrders') . '&tab=completed',
            'current_url' => $this->context->link->getAdminLink('AdminOmnivaOrders') . '&tab=completed',
            'requestNb' => $this->context->link->getAdminLink('AdminOmnivaOrders') . '&tab=completed',
            'p' => $page,
            'n' => $perPage,
            'start' => $startGroup,
            'stop' => $endGroup,
        ));
        $this->context->smarty->assign(
            array(
                'pagination_content' => $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'omnivaltshipping/views/templates/admin/pagination.tpl'),
                'pagination_file' => _PS_THEME_DIR_ . 'templates/_partials/pagination.tpl',
                'pagination' => array('items_shown_from' => 1, 'items_shown_to' => 1, 'total_items' => $ordersCount, 'should_be_displayed' => 1, 'pages' => 3)
        ));
        $content = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'omnivaltshipping/views/templates/admin/omnivaOrders.tpl');

        $this->context->smarty->assign(
            array(
                'content' => $this->content . $content,
            )
        );

    }

    public function getOrders($page = 1, $perPage = 10, $total = 0)
    {
        $newOrder = intval(Configuration::get('omnivalt_manifest'));
        $from = $page * $perPage;
        $orders = "SELECT * FROM " . _DB_PREFIX_ . "orders a
		INNER JOIN " . _DB_PREFIX_ . "customer oh ON a.id_customer = oh.id_customer
		LEFT JOIN " . _DB_PREFIX_ . "order_carrier oc ON a.id_order = oc.id_order
		INNER JOIN " . _DB_PREFIX_ . "omniva_order oo ON oo.id = a.id_order AND a.id_carrier IN (" . $this->_carriers . ")
		WHERE oo.manifest IS NOT NULL AND oo.manifest != " . $newOrder . "  AND oo.manifest != -1
		ORDER BY oo.manifest DESC, a.id_order DESC
		LIMIT $perPage OFFSET $from";

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($orders);
    }

    public function getSkippedOrders()
    {
        $orders = "SELECT * FROM " . _DB_PREFIX_ . "orders a
		INNER JOIN " . _DB_PREFIX_ . "customer oh ON a.id_customer = oh.id_customer
		LEFT JOIN " . _DB_PREFIX_ . "order_carrier oc ON a.id_order = oc.id_order
		INNER JOIN " . _DB_PREFIX_ . "omniva_order oo ON oo.id = a.id_order AND a.id_carrier IN (" . $this->_carriers . ")
		WHERE oo.manifest IS NOT NULL AND oo.manifest = -1
		ORDER BY oo.manifest DESC, a.id_order DESC";

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($orders);
    }

    public function getNewOrders()
    {
        $newOrderNum = intval(Configuration::get('omnivalt_manifest'));
        $newOrder = "SELECT *FROM " . _DB_PREFIX_ . "orders a
		INNER JOIN " . _DB_PREFIX_ . "customer oh ON a.id_customer = oh.id_customer
		INNER JOIN " . _DB_PREFIX_ . "order_carrier oc ON a.id_order = oc.id_order
		INNER JOIN " . _DB_PREFIX_ . "omniva_order oo ON oo.id = a.id_order AND a.id_carrier IN (" . $this->_carriers . ")
		WHERE oo.manifest IS NULL OR oo.manifest=" . $newOrderNum . "
		ORDER BY oo.manifest DESC, a.id_order DESC";

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($newOrder);
    }

    public function ordersNumb()
    {
        $newOrder = intval(Configuration::get('omnivalt_manifest'));

        $ordersCount = "SELECT COUNT(*) FROM " . _DB_PREFIX_ . "orders a
				INNER JOIN " . _DB_PREFIX_ . "customer oh ON a.id_customer = oh.id_customer
				LEFT JOIN " . _DB_PREFIX_ . "order_carrier oc ON a.id_order = oc.id_order
				INNER JOIN " . _DB_PREFIX_ . "omniva_order oo ON oo.id = a.id_order AND a.id_carrier IN (" . $this->_carriers . ")
				WHERE oo.manifest IS NOT NULL AND oo.manifest != " . $newOrder . " AND oo.manifest != -1
				ORDER BY a.id_order DESC";

        $rowCount = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($ordersCount);
        return intval($rowCount[0]["COUNT(*)"]);
    }

    public function skipOrder()
    {
        if (Tools::getValue('orderSkip')) {
            $orderIds = intval(Tools::getValue('orderSkip'));
            if ($orderIds > 0) {
                $saveManifest = "UPDATE " . _DB_PREFIX_ . "cart 
			SET omnivalt_manifest = -1
			WHERE id_cart = (SELECT id_cart FROM " . _DB_PREFIX_ . "orders WHERE id_order = " . $orderIds . ");";
                Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($saveManifest);
            }
        }
        Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminOmnivaOrders'));

    }

    public function cancelSkip()
    {
        if (Tools::getValue('cancelSkip')) {
            $orderIds = intval(Tools::getValue('cancelSkip'));
            if ($orderIds > 0) {
                $saveManifest = "UPDATE " . _DB_PREFIX_ . "cart 
			SET omnivalt_manifest = null
			WHERE id_cart = (SELECT id_cart FROM " . _DB_PREFIX_ . "orders WHERE id_order = " . $orderIds . ");";
                Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($saveManifest);
            }
        }
        Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminOmnivaOrders'));
    }
}
