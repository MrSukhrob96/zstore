<?php

namespace App\Modules\OCStore;

use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Entity\Customer;
use App\System;
use Zippy\Binding\PropertyBinding as Prop;
use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\WebApplication as App;

class Orders extends \App\Pages\Base
{

    public $_neworders = array();
    public $_eorders   = array();

    public function __construct() {
        parent::__construct();

        if (strpos(System::getUser()->modules, 'ocstore') === false && System::getUser()->rolename != 'admins') {
            System::setErrorMsg(\App\Helper::l('noaccesstopage'));

            App::RedirectError();
            return;
        }

        $modules = System::getOptions("modules");
        $statuses = System::getSession()->statuses;
        if (is_array($statuses) == false) {
            $statuses = array();
            $this->setWarn('do_connect');
        }

        $this->add(new Form('filter'))->onSubmit($this, 'filterOnSubmit');
        $this->filter->add(new DropDownChoice('status', $statuses, 0));
        $this->add(new Form('filter2'))->onSubmit($this, 'onOutcome');
        $this->filter2->add(new DropDownChoice('store', \App\Entity\Store::getList(), 0));
        $this->filter2->add(new DropDownChoice('kassa', \App\Entity\MoneyFund::getList(), \App\Helper::getDefMF()));
        $this->filter2->setVisible($modules['ocoutcome'] == 1);

        $this->add(new DataView('neworderslist', new ArrayDataSource(new Prop($this, '_neworders')), $this, 'noOnRow'));

        $this->add(new ClickLink('importbtn', $this, 'onImport'))->setVisible($modules['ocoutcome'] != 1);

        $this->add(new ClickLink('refreshbtn'))->onClick($this, 'onRefresh');
        $this->add(new Form('updateform'))->onSubmit($this, 'exportOnSubmit');
        $this->updateform->add(new DataView('orderslist', new ArrayDataSource(new Prop($this, '_eorders')), $this, 'expRow'));
        $this->updateform->add(new DropDownChoice('estatus', $statuses, 0));

        $this->add(new ClickLink('checkconn'))->onClick($this, 'onCheck');

    }

    public function filterOnSubmit($sender) {
        $modules = System::getOptions("modules");

        $status = $this->filter->status->getValue();

        $this->_neworders = array();
        $fields = array(
            'status_id' => $status,
        );
        $url = $modules['ocsite'] . '/index.php?route=api/zstore/orders&' . System::getSession()->octoken;
        $json = Helper::do_curl_request($url, $fields);
        if ($json === false) {
            return;
        }
        $data = json_decode($json, true);
        if (!isset($data)) {
            $this->setError("invalidresponse");
            \App\Helper::log($json);
            return;
        }
        if ($data['error'] == "") {


            foreach ($data['orders'] as $ocorder) {


                $isorder = Document::findCnt(" (meta_name='Order' or meta_name='TTN') and content like '%<ocorder>{$ocorder['order_id']}</ocorder>%'");
                if ($isorder > 0) { //уже импортирован
                    continue;
                }
                foreach ($ocorder['_products_'] as $product) {
                    $code = trim($product['sku']);
                    if ($code == "") {
                        $this->setWarn("noarticle_inorder", $product['name'], $ocorder['order_id']);
                    }
                }

                $order = new \App\DataItem($ocorder);

                $this->_neworders[] = $order;
            }

            $this->neworderslist->Reload();
        } else {
            $this->setError($data['error']);
        }
    }

    public function noOnRow($row) {
        $order = $row->getDataItem();

        $row->add(new Label('number', $order->order_id));
        $row->add(new Label('customer', $order->firstname . ' ' . $order->lastname));
        $row->add(new Label('amount', round($order->total)));
        $row->add(new Label('comment', $order->comment));
        $row->add(new Label('date', \App\Helper::fdt(strtotime($order->date_modified))));
    }

    public function onImport($sender) {
        $modules = System::getOptions("modules");

        $i = 0;
        foreach ($this->_neworders as $shoporder) {


            $neworder = Document::create('Order');
            $neworder->document_number = $neworder->nextNumber();
            $neworder->document_date = strtotime($shoporder->date_added);

            if (strlen($neworder->document_number) == 0) {
                $neworder->document_number = 'OC00001';
            }
            $neworder->customer_id = $modules['occustomer_id'];

            //товары
            $tlist = array();
            foreach ($shoporder->_products_ as $product) {
                //ищем по артикулу 
                if (strlen($product['sku']) == 0) {
                    continue;
                }
                $code = Item::qstr($product['sku']);

                $tovar = Item::getFirst('item_code=' . $code);
                if ($tovar == null) {

                    $this->setWarn("nofoundarticle_inorder", $product['name'], $shoporder->order_id);
                    continue;
                }
                $tovar->quantity = $product['quantity'];
                $tovar->price = str_replace(',', '.', $product['price']);
                $desc = '';
                if (array($product['_options_'])) {
                    foreach ($product['_options_'] as $k => $v) {
                        $desc = $desc . $k . ':' . $v . ';';
                    }
                }
                //$tovar->octoreoptions = serialize($product['_options_']);
                $tovar->desc = $desc;

                $tlist[] = $tovar;
            }
            if(count($tlist)==0)  {
                return;
            }
            $neworder->packDetails('detaildata', $tlist);
            $neworder->amount = round($shoporder->total);
            
            if($modules['ocsetpayamount']==1){
               $neworder->payamount = $neworder->amount;
             
            }
            
            $neworder->headerdata['outnumber'] = $shoporder->order_id;
            $neworder->headerdata['ocorder'] = $shoporder->order_id;
            $neworder->headerdata['ocorderback'] = 0;
        
            $neworder->notes = "OC номер:{$shoporder->order_id};";

            $neworder->headerdata['occlient'] = $shoporder->firstname . ' ' . $shoporder->lastname;
            $neworder->notes .= " Клиент:" . $shoporder->firstname . ' ' . $shoporder->lastname . ";";

            if ($shoporder->customer_id > 0 && $modules['ocinsertcust'] == 1) {
                $cust = Customer::getFirst("detail like '%<shopcust_id>{$shoporder->customer_id}</shopcust_id>%'");
                if ($cust == null) {
                    $cust = new Customer();
                    $cust->shopcust_id = $shoporder->customer_id;
                    $cust->customer_name = $shoporder->firstname . ' ' . $shoporder->lastname;
                    $cust->address = $shoporder->shipping_city . ' ' . $shoporder->shipping_address_1;
                    $cust->type = Customer::TYPE_BAYER;
                    $cust->phone = $shoporder->telephone;
                    $cust->email = $shoporder->email;
                    $cust->comment = "Клиент ИМ";
                    $cust->save();
                }
                $neworder->customer_id = $cust->customer_id;
            }


            if (strlen($shoporder->email) > 0) {
                $neworder->notes .= " Email:" . $shoporder->email . ";";
            }
            if (strlen($shoporder->telephone) > 0) {
                $neworder->notes .= " Тел:" . $shoporder->telephone . ";";
            }
            $neworder->notes .= " Адрес:" . $shoporder->shipping_city . ' ' . $shoporder->shipping_address_1 . ";";
            $neworder->notes .= " Оплата:" . $shoporder->payment_method . ";";
            $neworder->notes .= " Комментарий:" . $shoporder->comment . ";";
            $neworder->save();
            $neworder->updateStatus(Document::STATE_NEW);
            $neworder->updateStatus(Document::STATE_INPROCESS);

            $i++;
        }
        $this->setInfo('imported_orders', $i);

        $this->_neworders = array();
        $this->neworderslist->Reload();
    }

    //только  списание
    public function onOutcome($sender) {
        $modules = System::getOptions("modules");
        $store = $this->filter2->store->getValue();
        $kassa = $this->filter2->kassa->getValue();
        if ($store == 0) {
            $this->setError("noselstore");
            return;
        }
        $allowminus = \App\System::getOption("common", "allowminus");

        if ($allowminus != 1) {
            foreach ($this->_neworders as $shoporder) {

                foreach ($shoporder->_products_ as $product) {
                    //ищем по артикулу
                    if (strlen($product['sku']) == 0) {
                        continue;
                    }
                    $code = Item::qstr($product['sku']);

                    $tovar = Item::getFirst('item_code=' . $code);
                    if ($tovar == null) {

                        $this->setWarn("nofoundarticle_inorder", $product['name'], $shoporder['order_id']);
                        continue;
                    }
                    $tovar->quantity = $product['quantity'];

                    $qty = $tovar->getQuantity($store);
                    if ($qty < $tovar->quantity) {
                        $this->setError("nominus", \App\Helper::fqty($qty), $tovar->itemname);
                        return;
                    }
                }
            }
        }

        $i = 0;
        foreach ($this->_neworders as $shoporder) {


            $neworder = Document::create('TTN');
            $neworder->document_number = $neworder->nextNumber();
            if (strlen($neworder->document_number) == 0) {
                $neworder->document_number = 'ТТН-00001';
            }

            //товары
            $totalpr = 0;
            $tlist = array();
            foreach ($shoporder->_products_ as $product) {
                //ищем по артикулу 
                if (strlen($product['sku']) == 0) {
                    continue;
                }
                $code = Item::qstr($product['sku']);

                $tovar = Item::getFirst('item_code=' . $code);
                if ($tovar == null) {

                    $this->setWarn("nofoundarticle_inorder", $product['name'], $shoporder['order_id']);
                    continue;
                }
                $tovar->quantity = $product['quantity'];
                $tovar->price = round($product['price']);
                $totalpr += ($tovar->quantity * $tovar->price);

                $tlist[] = $tovar;
            }
            $neworder->packDetails('detaildata', $tlist);

            $neworder->headerdata['store'] = $store;
            $neworder->headerdata['store_name'] = $this->filter2->store->getValueName();
            $neworder->headerdata['ocorder'] = $shoporder->order_id;

            $neworder->customer_id = $modules['occustomer_id'];

            $neworder->amount = round($totalpr);

            if ($shoporder->total > $totalpr) {
                $neworder->headerdata['ship_amount'] = $shoporder->total - $totalpr;
                $neworder->headerdata['delivery'] = Document::DEL_SELF;
                $neworder->headerdata['delivery_name'] = \App\Helper::l('delself');
            }

            $neworder->payamount = 0;
            $neworder->payed = 0;
            $neworder->notes = "OC номер:{$shoporder->order_id};";
            $neworder->notes .= " Клиент:" . $shoporder->firstname . ' ' . $shoporder->lastname . ";";
            if (strlen($shoporder->email) > 0) {
                $neworder->notes .= " Email:" . $shoporder->email . ";";
            }
            if (strlen($shoporder->telephone) > 0) {
                $neworder->notes .= " Тел:" . $shoporder->telephone . ";";
            }
            $neworder->notes .= " Адрес:" . $shoporder->shipping_city . ' ' . $shoporder->shipping_address_1 . ";";
            $neworder->notes .= " Комментарий:" . $shoporder->comment . ";";
            $neworder->save();
            $neworder->updateStatus(Document::STATE_NEW);
            $neworder->updateStatus(Document::STATE_EXECUTED);
            $neworder->updateStatus(Document::STATE_DELIVERED);

            $i++;
        }


        $this->setInfo('imported_orders', $i);

        $this->_neworders = array();
        $this->neworderslist->Reload();
    }

    public function onCheck($sender) {

        Helper::connect();
        \App\Application::Redirect("\\App\\Modules\\OCStore\\Orders");
    }

    public function onRefresh($sender) {

        $this->_eorders = Document::find("meta_name='Order' and content like '%<ocorderback>0</ocorderback>%' and state <> " . Document::STATE_NEW);
        $this->updateform->orderslist->Reload();
    }

    public function expRow($row) {
        $order = $row->getDataItem();
        $row->add(new CheckBox('ch', new Prop($order, 'ch')));
        $row->add(new Label('number2', $order->document_number));
        $row->add(new Label('number3', $order->headerdata['ocorder']));
        $row->add(new Label('date2', \App\Helper::fd($order->document_date)));
        $row->add(new Label('amount2', $order->amount));
        $row->add(new Label('customer2', $order->headerdata['occlient']));
        $row->add(new Label('state', Document::getStateName($order->state)));
    }

    public function exportOnSubmit($sender) {
        $modules = System::getOptions("modules");

        $st = $this->updateform->estatus->getValue();
        if ($st == 0) {

            $this->setError('noselstatus');
            return;
        }
        $elist = array();
        foreach ($this->_eorders as $order) {
            if ($order->ch == false) {
                continue;
            }
            $elist[$order->headerdata['ocorder']] = $st;
        }
        if (count($elist) == 0) {

            $this->setError('noselorder');
            return;
        }
        $data = json_encode($elist);

        $fields = array(
            'data' => $data
        );
        $url = $modules['ocsite'] . '/index.php?route=api/zstore/updateorder&' . System::getSession()->octoken;
        $json = Helper::do_curl_request($url, $fields);
        if ($json === false) {
            return;
        }
        $data = json_decode($json, true);

        if ($data['error'] != "") {
            $this->setError($data['error']);
            return;
        }

        $this->setSuccess("refrehed_orders", count($elist));

        foreach ($this->_eorders as $order) {
            if ($order->ch == false) {
                continue;
            }
            $order->headerdata['ocorderback'] = 1;
            $order->save();
        }


        $this->_eorders = Document::find("meta_name='Order' and content like '%<ocorderback>0</ocorderback>%' and state <> " . Document::STATE_NEW);
        $this->updateform->orderslist->Reload();
    }

}
